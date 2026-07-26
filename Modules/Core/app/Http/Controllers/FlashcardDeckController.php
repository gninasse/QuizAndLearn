<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\Article;
use Modules\Core\Models\Exam;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Group;
use Modules\Core\Models\Quiz;

class FlashcardDeckController extends Controller
{
    private function authorizeDeck(int $deckId): FlashcardDeck
    {
        $deck = FlashcardDeck::findOrFail($deckId);
        $user = auth()->user();
        if (! ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin'))) {
            if ($deck->created_by !== $user->id) {
                abort(403, 'Action non autorisée.');
            }
        }

        return $deck;
    }

    /**
     * Display the index view for flashcard decks.
     */
    public function index(): View
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin')) {
            $groups = Group::active()->get();
            $quizzes = Quiz::where('is_active', true)->get();
            $exams = Exam::where('is_active', true)->get();
            $articles = Article::where('is_active', true)->get();
        } elseif ($user->hasRole('trainer') || $user->trainer) {
            $groups = $user->trainer ? $user->trainer->groups()->active()->get() : collect();
            $quizzes = Quiz::where('is_active', true)->where('created_by', $user->id)->get();
            $exams = Exam::where('is_active', true)->where('created_by', $user->id)->get();
            $articles = Article::where('is_active', true)->where('created_by', $user->id)->get();
        } else {
            $groups = collect();
            $quizzes = collect();
            $exams = collect();
            $articles = collect();
        }

        return view('core::flashcards.index', compact('groups', 'quizzes', 'exams', 'articles'));
    }

    /**
     * Fetch flashcard decks for Bootstrap Table (AJAX).
     */
    public function getData(Request $request): JsonResponse
    {
        $query = FlashcardDeck::with(['creator', 'groups']);

        $user = auth()->user();
        if (! ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin'))) {
            $query->where('created_by', $user->id);
        }

        // Search
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('matiere', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort', 'id');
        $sortOrder = $request->get('order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $decks = $query->offset($offset)->limit($limit)->get();

        // Format rows
        $rows = $decks->map(function ($deck) {
            $deck->creator_name = $deck->creator ? $deck->creator->name.' '.$deck->creator->last_name : 'Système';
            $deck->cards_count = $deck->cards()->count();
            $deck->groups_list = $deck->groups->pluck('name')->toArray() ?? [];

            return $deck;
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Show a single deck details (AJAX).
     */
    public function show(int $id): JsonResponse
    {
        $deck = $this->authorizeDeck($id)->load('groups');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $deck->id,
                'titre' => $deck->titre,
                'description' => $deck->description,
                'matiere' => $deck->matiere,
                'algorithme' => $deck->algorithme,
                'is_public' => $deck->is_public,
                'group_ids' => $deck->groups->pluck('id')->toArray(),
            ],
        ]);
    }

    /**
     * Store a new flashcard deck.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'matiere' => 'nullable|string|max:100',
            'algorithme' => 'required|string|in:sm2,leitner',
            'is_public' => 'boolean',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:groups,id',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_public'] = $request->boolean('is_public', false);
        $validated['active'] = true;

        $deck = FlashcardDeck::create($validated);

        if (! empty($request->group_ids)) {
            $deck->groups()->sync($request->group_ids);
        }

        return response()->json([
            'success' => true,
            'message' => 'Paquet de cartes créé avec succès.',
            'deck' => $deck,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $deck = $this->authorizeDeck($id);

        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'matiere' => 'nullable|string|max:100',
            'algorithme' => 'required|string|in:sm2,leitner',
            'is_public' => 'boolean',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:groups,id',
        ]);

        $validated['is_public'] = $request->boolean('is_public', false);

        $deck->update($validated);

        $deck->groups()->sync($request->group_ids ?? []);

        return response()->json([
            'success' => true,
            'message' => 'Paquet de cartes mis à jour avec succès.',
            'deck' => $deck,
        ]);
    }

    /**
     * Delete a flashcard deck.
     */
    public function destroy(int $id): JsonResponse
    {
        $deck = $this->authorizeDeck($id);
        $deck->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paquet de cartes supprimé avec succès.',
        ]);
    }

    /**
     * Toggle status of a flashcard deck.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $deck = $this->authorizeDeck($id);
        $deck->update([
            'active' => ! $deck->active,
        ]);

        return response()->json([
            'success' => true,
            'active' => $deck->active,
            'message' => 'Statut mis à jour.',
        ]);
    }

    /**
     * Render editor builder view.
     */
    public function builder(int $id): View
    {
        $deck = $this->authorizeDeck($id)->load(['cards', 'groups']);
        $groups = Group::active()->get();

        return view('core::admin.flashcard.editor', compact('deck', 'groups'));
    }

    /**
     * Store a card in the deck.
     */
    public function storeCard(Request $request, int $deckId): JsonResponse
    {
        $deck = $this->authorizeDeck($deckId);

        $validated = $request->validate([
            'recto' => 'required|string',
            'verso' => 'required|string',
            'tags' => 'nullable|string',
            'note' => 'nullable|string',
            'ordre' => 'integer',
        ]);

        $validated['deck_id'] = $deck->id;
        $validated['recto_media'] = [];
        $validated['verso_media'] = [];

        $card = FlashcardItem::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Carte ajoutée avec succès.',
            'card' => $card,
        ]);
    }

    /**
     * Show card details.
     */
    public function showCard(int $deckId, int $cardId): JsonResponse
    {
        $this->authorizeDeck($deckId);
        $card = FlashcardItem::where('deck_id', $deckId)->findOrFail($cardId);

        return response()->json([
            'success' => true,
            'card' => $card,
        ]);
    }

    /**
     * Update card details.
     */
    public function updateCard(Request $request, int $deckId, int $cardId): JsonResponse
    {
        $this->authorizeDeck($deckId);
        $card = FlashcardItem::where('deck_id', $deckId)->findOrFail($cardId);

        $validated = $request->validate([
            'recto' => 'required|string',
            'verso' => 'required|string',
            'tags' => 'nullable|string',
            'note' => 'nullable|string',
            'ordre' => 'integer',
        ]);

        $card->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Carte mise à jour avec succès.',
            'card' => $card,
        ]);
    }

    /**
     * Delete card from deck.
     */
    public function destroyCard(int $deckId, int $cardId): JsonResponse
    {
        $this->authorizeDeck($deckId);
        $card = FlashcardItem::where('deck_id', $deckId)->findOrFail($cardId);
        $card->delete();

        return response()->json([
            'success' => true,
            'message' => 'Carte supprimée avec succès.',
        ]);
    }

    /**
     * Assign group to deck.
     */
    public function assignGroup(Request $request, int $deckId): JsonResponse
    {
        $request->validate(['group_id' => 'required|exists:groups,id']);
        $deck = $this->authorizeDeck($deckId);
        $deck->groups()->syncWithoutDetaching($request->group_id);

        return response()->json([
            'success' => true,
            'message' => 'Groupe assigné.',
        ]);
    }

    /**
     * Unassign group from deck.
     */
    public function unassignGroup(int $deckId, int $groupId): JsonResponse
    {
        $deck = $this->authorizeDeck($deckId);
        $deck->groups()->detach($groupId);

        return response()->json([
            'success' => true,
            'message' => 'Groupe retiré.',
        ]);
    }

    /**
     * Auto generate cards.
     */
    public function autoGenerate(Request $request, int $deckId): JsonResponse
    {
        $deck = $this->authorizeDeck($deckId);

        $request->validate([
            'source_type' => 'required|string|in:quiz,examen,article',
            'source_id' => 'required|integer',
        ]);

        $sourceType = $request->source_type;
        $sourceId = $request->source_id;

        $user = auth()->user();
        if (! ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin'))) {
            if ($sourceType === 'quiz') {
                $quiz = Quiz::findOrFail($sourceId);
                if ($quiz->created_by !== $user->id) {
                    abort(403, 'Action non autorisée sur la source.');
                }
            } elseif ($sourceType === 'examen') {
                $exam = Exam::findOrFail($sourceId);
                if ($exam->created_by !== $user->id) {
                    abort(403, 'Action non autorisée sur la source.');
                }
            } elseif ($sourceType === 'article') {
                $article = Article::findOrFail($sourceId);
                if ($article->created_by !== $user->id) {
                    abort(403, 'Action non autorisée sur la source.');
                }
            }
        }

        $cardsCreated = 0;

        if ($sourceType === 'quiz') {
            $quiz = Quiz::with('questions')->findOrFail($sourceId);
            foreach ($quiz->questions as $q) {
                $versoText = $this->formatQuestionVerso($q);
                FlashcardItem::create([
                    'deck_id' => $deck->id,
                    'recto' => $q->question_text,
                    'verso' => $versoText,
                    'tags' => 'quiz,auto-gen',
                    'note' => "Généré depuis le quiz: {$quiz->title}",
                ]);
                $cardsCreated++;
            }
        } elseif ($sourceType === 'examen') {
            $exam = Exam::with('questions')->findOrFail($sourceId);
            foreach ($exam->questions as $q) {
                $versoText = $this->formatExamQuestionVerso($q);
                FlashcardItem::create([
                    'deck_id' => $deck->id,
                    'recto' => $q->question_text,
                    'verso' => $versoText,
                    'tags' => 'exam,auto-gen',
                    'note' => "Généré depuis l'examen: {$exam->titre}",
                ]);
                $cardsCreated++;
            }
        } elseif ($sourceType === 'article') {
            $article = Article::findOrFail($sourceId);
            // Quick extraction of key definitions from markdown
            // Matches formats like "Terme : Définition" or bold terms "**Terme** : Définition"
            preg_match_all('/(?:\*\*|__)?([A-Z][a-zA-Z0-9\s\'\-]{3,40})(?:\*\*|__)?\s*:\s*(.{10,250})/u', $article->content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                FlashcardItem::create([
                    'deck_id' => $deck->id,
                    'recto' => trim($match[1]),
                    'verso' => trim($match[2]),
                    'tags' => 'article,auto-gen',
                    'note' => "Généré depuis l'article: {$article->title}",
                ]);
                $cardsCreated++;
            }

            // Fallback if no matching patterns found
            if ($cardsCreated === 0) {
                FlashcardItem::create([
                    'deck_id' => $deck->id,
                    'recto' => "Sujet de l'article : {$article->title}",
                    'verso' => 'Résumé ou notes à rédiger sur : '.substr(strip_tags($article->content), 0, 100).'...',
                    'tags' => 'article,auto-gen',
                    'note' => "Généré depuis l'article: {$article->title}",
                ]);
                $cardsCreated++;
            }
        }

        $deck->update([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$cardsCreated} cartes ont été générées automatiquement avec succès.",
        ]);
    }

    protected function formatQuestionVerso($q): string
    {
        $opts = $q->options ?? [];
        if ($q->type === 'true_false') {
            $correct = ($opts['correct_answer'] ?? 'true') === 'true' ? 'Vrai' : 'Faux';

            return "Réponse : {$correct}";
        }

        if (in_array($q->type, ['mcq', 'single_choice', 'multiple_choice'])) {
            $answers = $opts['answers'] ?? [];
            $correctAnswers = collect($answers)->filter(fn ($a) => $a['is_correct'] ?? false)->pluck('text')->toArray();

            return "Réponse(s) correcte(s) :\n- ".implode("\n- ", $correctAnswers);
        }

        if ($q->type === 'fill_blank') {
            $blanks = $opts['blanks'] ?? [];
            $answersList = [];
            foreach ($blanks as $idx => $b) {
                $ans = collect($b['answers'] ?? [])->join(' ou ');
                $answersList[] = 'Trou #'.($idx + 1).' : '.$ans;
            }

            return implode("\n", $answersList);
        }

        if ($q->type === 'matching') {
            $pairs = $opts['pairs'] ?? [];
            $answersList = [];
            foreach ($pairs as $p) {
                $answersList[] = $p['term'].' ➔ '.$p['definition'];
            }

            return "Associations correctes :\n".implode("\n", $answersList);
        }

        if ($q->type === 'ordering') {
            $items = $opts['items'] ?? [];

            return "Ordre correct :\n1. ".implode("\n2. ", $items);
        }

        return 'Veuillez consulter les détails de la question.';
    }

    protected function formatExamQuestionVerso($q): string
    {
        $opts = $q->options ?? [];
        if ($q->type === 'true_false') {
            $correct = ($opts['correct_answer'] ?? 'true') === 'true' ? 'Vrai' : 'Faux';

            return "Réponse : {$correct}";
        }

        if ($q->type === 'mcq') {
            $choices = $opts['choices'] ?? [];
            $correctChoices = collect($choices)->filter(fn ($c) => $c['is_correct'] ?? false)->pluck('text')->toArray();

            return "Réponse(s) correcte(s) :\n- ".implode("\n- ", $correctChoices);
        }

        if ($q->type === 'fill_blank') {
            $format = $opts['format'] ?? '';
            preg_match_all('/\[\[(.*?)\]\]/', $format, $matches);
            $answersList = [];
            foreach ($matches[1] ?? [] as $idx => $ans) {
                $answersList[] = 'Trou #'.($idx + 1).' : '.$ans;
            }

            return implode("\n", $answersList);
        }

        if ($q->type === 'matching') {
            $pairs = $opts['pairs'] ?? [];
            $answersList = [];
            foreach ($pairs as $p) {
                $answersList[] = ($p['term'] ?? $p['left'] ?? '').' ➔ '.($p['definition'] ?? $p['right'] ?? '');
            }

            return "Associations correctes :\n".implode("\n", $answersList);
        }

        if ($q->type === 'ordering') {
            $items = $opts['items'] ?? [];

            return "Ordre correct :\n1. ".implode("\n2. ", $items);
        }

        return 'Veuillez consulter les détails de la question.';
    }

    public function preview(int $deckId): View
    {
        $deck = $this->authorizeDeck($deckId);

        return view('core::admin.flashcard.preview', compact('deck'));
    }

    public function previewIframe(int $deckId): View
    {
        $deck = $this->authorizeDeck($deckId)->load('cards');

        return view('core::admin.flashcard.preview-iframe', compact('deck'));
    }

    public function printIframe(int $deckId): View
    {
        $deck = $this->authorizeDeck($deckId)->load('cards');

        return view('core::admin.flashcard.print-iframe', compact('deck'));
    }
}
