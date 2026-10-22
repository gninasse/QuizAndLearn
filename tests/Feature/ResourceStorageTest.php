<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Modules\Core\Http\Middleware\ResolveImpersonation;
use Modules\Core\Jobs\ConvertDocumentToPdf;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Resource;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;
use Modules\Core\Services\ResourceStorage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Ressources : stockage privé, conversion des documents en PDF, lecture
 * protégée par URL signée courte et téléchargement contrôlé.
 */
class ResourceStorageTest extends TestCase
{
    use RefreshDatabase;

    private const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private User $user;

    private Learner $learner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        Storage::fake('local');
        Storage::fake('public');

        $this->user = User::create([
            'name' => 'Ressource',
            'last_name' => 'Learner',
            'user_name' => 'reslearner',
            'email' => 'res@learnandquiz.fr',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->learner = Learner::create(['user_id' => $this->user->id, 'matricule' => 'MAT-RES']);
        $this->group = Group::create(['name' => 'Promo Ressources', 'is_active' => true]);
        $this->learner->groups()->attach($this->group->id);
    }

    private function storage(): ResourceStorage
    {
        return app(ResourceStorage::class);
    }

    private function place(Resource $resource, ?Folder $folder = null): Resource
    {
        $this->group->resources()->attach($resource->id, ['folder_id' => $folder?->id, 'position' => 0]);

        return $resource;
    }

    private function storedPdf(array $attrs = []): Resource
    {
        $file = UploadedFile::fake()->createWithContent('cours.pdf', '%PDF-1.4 contenu du cours');

        return $this->storage()->store($file, $attrs + ['title' => 'Cours PDF'], $this->user);
    }

    private function storedDocx(): Resource
    {
        Queue::fake();
        $file = UploadedFile::fake()->create('support.docx', 12, self::DOCX);

        return $this->storage()->store($file, ['title' => 'Support Word'], $this->user);
    }

    /** Process::fake qui simule LibreOffice : écrit le PDF dans --outdir. */
    private function fakeSoffice(): void
    {
        Process::fake(function (PendingProcess $process) {
            $command = (array) $process->command;
            $outdir = $command[array_search('--outdir', $command, true) + 1];
            $input = end($command);
            file_put_contents($outdir.'/'.pathinfo($input, PATHINFO_FILENAME).'.pdf', '%PDF-1.7 converti');

            return Process::result('convert ok');
        });
    }

    private function body(TestResponse $response): string
    {
        $base = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);
        ob_start();
        $base->sendContent();

        return (string) ob_get_clean();
    }

    private function mobileToken(): string
    {
        return $this->postJson('/api/mobile/v1/login', [
            'login' => 'reslearner',
            'password' => 'password123',
            'device_name' => 'Pixel Test',
        ])->json('token');
    }

    public function test_upload_kinds_detected(): void
    {
        Queue::fake();
        $storage = $this->storage();

        $image = $storage->store(UploadedFile::fake()->image('schema.png'), ['title' => 'Schéma'], $this->user);
        $audio = $storage->store(UploadedFile::fake()->create('podcast.mp3', 20, 'audio/mpeg'), ['title' => 'Podcast'], $this->user);
        $pdf = $storage->store(UploadedFile::fake()->create('cours.pdf', 20, 'application/pdf'), ['title' => 'Cours'], $this->user);
        $docx = $storage->store(UploadedFile::fake()->create('support.docx', 20, self::DOCX), ['title' => 'Support'], $this->user);
        $odp = $storage->store(UploadedFile::fake()->create('slides.odp', 20, 'application/vnd.oasis.opendocument.presentation'), ['title' => 'Slides'], $this->user);
        // MIME générique détecté pour un .pptx : l'extension tranche.
        $pptx = $storage->store(UploadedFile::fake()->create('deck.pptx', 20, 'application/zip'), ['title' => 'Deck'], $this->user);
        $video = $storage->store(UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'), ['title' => 'Clip'], $this->user);

        $this->assertSame('image', $image->kind);
        $this->assertSame('audio', $audio->kind);
        $this->assertSame('pdf', $pdf->kind);
        $this->assertSame('document', $docx->kind);
        $this->assertSame('document', $odp->kind);
        $this->assertSame('document', $pptx->kind);
        $this->assertSame('pending', $docx->conversion_status);
        $this->assertNull($pdf->conversion_status);

        foreach ([$image, $audio, $pdf, $docx] as $resource) {
            $this->assertSame('local', $resource->disk);
            $this->assertStringStartsWith("resources/{$resource->id}/", $resource->path);
            Storage::disk('local')->assertExists($resource->path);
            $this->assertSame($this->user->id, $resource->created_by);
            $this->assertFalse($resource->allow_download);
        }
        $this->assertSame('podcast.mp3', $audio->original_name);
        $this->assertSame('audio/mpeg', $audio->mime);
        $this->assertSame(20 * 1024, $audio->size);

        $this->assertSame('video', $video->kind);
        $this->assertNotNull($video->video_id);
        $this->assertNull($video->path);
        $this->assertSame('clip.mp4', Video::find($video->video_id)->original_name);

        // Taille maximale (media.documents.max_size_kb).
        try {
            $storage->store(UploadedFile::fake()->create('lourd.pdf', 51201, 'application/pdf'), ['title' => 'Lourd'], $this->user);
            $this->fail('Un fichier trop lourd doit être refusé.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }

        // Type non pris en charge.
        try {
            $storage->store(UploadedFile::fake()->create('script.sh', 1, 'application/x-sh'), ['title' => 'Script'], $this->user);
            $this->fail('Un type non pris en charge doit être refusé.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }

        $this->assertSame(7, Resource::count());
    }

    public function test_create_link_accepts_http_only(): void
    {
        $link = $this->storage()->createLink('https://example.org/cours', 'Site du cours', $this->user);
        $this->assertSame('link', $link->kind);
        $this->assertSame('https://example.org/cours', $link->url);
        $this->assertNull($link->path);

        $this->assertSame('link', $this->storage()->createLink('http://example.org', 'HTTP', $this->user)->kind);

        foreach (['javascript:alert(1)', 'ftp://example.org/f', 'file:///etc/passwd', 'pas une url'] as $bad) {
            try {
                $this->storage()->createLink($bad, 'Mauvais', $this->user);
                $this->fail("URL refusée attendue : {$bad}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('url', $e->errors());
            }
        }
        $this->assertSame(2, Resource::count());
    }

    public function test_document_dispatches_conversion_job(): void
    {
        $resource = $this->storedDocx();

        Queue::assertPushedOn('documents', ConvertDocumentToPdf::class, fn (ConvertDocumentToPdf $job) => $job->resourceId === $resource->id);

        $job = new ConvertDocumentToPdf($resource->id);
        $this->assertSame(300, $job->timeout);
        $this->assertSame(2, $job->tries);
    }

    public function test_conversion_success_sets_pdf_path(): void
    {
        $resource = $this->storedDocx();
        $this->fakeSoffice();

        app()->call([new ConvertDocumentToPdf($resource->id), 'handle']);

        $resource->refresh();
        $this->assertSame('ready', $resource->conversion_status);
        $this->assertNull($resource->conversion_error);
        $this->assertStringStartsWith("resources/{$resource->id}/", $resource->pdf_path);
        $this->assertStringEndsWith('.pdf', $resource->pdf_path);
        $this->assertSame('%PDF-1.7 converti', Storage::disk('local')->get($resource->pdf_path));
        Storage::disk('local')->assertExists($resource->path); // l'original est conservé

        Process::assertRan(function (PendingProcess $process) {
            $command = (array) $process->command;

            return $command[0] === 'soffice'
                && in_array('--headless', $command, true)
                && in_array('--outdir', $command, true)
                && array_slice($command, array_search('--convert-to', $command, true), 2) === ['--convert-to', 'pdf'];
        });

        // Répertoire temporaire nettoyé.
        $this->assertDirectoryDoesNotExist(ConvertDocumentToPdf::tmpDir($resource->id));

        // Le PDF converti est servi à l'apprenant.
        $this->place($resource);
        $view = $this->actingAs($this->user)->getJson(route('learn.v1.resources.view', $resource->id))
            ->assertOk()->assertJsonPath('type', 'pdf');
        $file = $this->get($view->json('url'))->assertOk();
        $this->assertSame('%PDF-1.7 converti', $this->body($file));
        $this->assertSame('application/pdf', $file->headers->get('Content-Type'));
    }

    public function test_conversion_missing_binary_fails(): void
    {
        $resource = $this->storedDocx();
        config(['media.documents.soffice_binary' => '/opt/absent/soffice']);
        Process::fake(['*' => Process::result('', '', 127)]);

        app()->call([new ConvertDocumentToPdf($resource->id), 'handle']);

        $resource->refresh();
        $this->assertSame('failed', $resource->conversion_status);
        $this->assertSame("LibreOffice n'est pas installé sur le serveur.", $resource->conversion_error);
        $this->assertNull($resource->pdf_path);
        Process::assertRan(fn (PendingProcess $p) => ((array) $p->command)[0] === '/opt/absent/soffice');

        // Review Focus 5 : l'apprenant reçoit 409 {status:'failed'}.
        $this->place($resource);
        $this->actingAs($this->user)->getJson(route('learn.v1.resources.view', $resource->id))
            ->assertStatus(409)
            ->assertExactJson(['status' => 'failed']);
    }

    public function test_conversion_error_is_retried_then_marked_failed(): void
    {
        $resource = $this->storedDocx();
        Process::fake(['*' => Process::result('', 'Error: source file could not be loaded', 1)]);

        try {
            app()->call([new ConvertDocumentToPdf($resource->id), 'handle']);
            $this->fail('Une conversion échouée doit lever une exception (nouvel essai).');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('source file could not be loaded', $e->getMessage());
        }
        $this->assertSame('pending', $resource->refresh()->conversion_status);

        // Dernier essai épuisé : le worker appelle failed().
        (new ConvertDocumentToPdf($resource->id))->failed($e);
        $resource->refresh();
        $this->assertSame('failed', $resource->conversion_status);
        $this->assertStringContainsString('source file could not be loaded', $resource->conversion_error);
    }

    public function test_view_returns_short_signed_url(): void
    {
        $pdf = $this->place($this->storedPdf());

        $response = $this->actingAs($this->user)->getJson(route('learn.v1.resources.view', $pdf->id))
            ->assertOk()
            ->assertJsonPath('type', 'pdf')
            ->assertJsonPath('expires_at', now()->addSeconds(900)->toIso8601String());
        $url = $response->json('url');
        $this->assertStringContainsString("/api/learner/v1/resources/{$pdf->id}/file", $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires='.now()->addSeconds(900)->getTimestamp(), $url);
        $this->assertStringNotContainsString($pdf->path, $url);

        // Le fichier signé se lit sans session.
        $this->app['auth']->forgetGuards();
        $file = $this->get($url)->assertOk();
        $this->assertSame('%PDF-1.4 contenu du cours', $this->body($file));
        $this->assertSame('inline', $file->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $file->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', $file->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $file->headers->get('Cache-Control'));

        // Requête Range (lecture partielle).
        $partial = $this->get($url, ['Range' => 'bytes=0-7'])->assertStatus(206);
        $this->assertSame('%PDF-1.4', $this->body($partial));
        $this->assertSame('bytes 0-7/25', $partial->headers->get('Content-Range'));

        // Image, audio : même forme.
        Queue::fake();
        $image = $this->place($this->storage()->store(UploadedFile::fake()->image('schema.png'), ['title' => 'Schéma'], $this->user));
        $this->actingAs($this->user)->getJson(route('learn.v1.resources.view', $image->id))
            ->assertOk()->assertJsonPath('type', 'image')->assertJsonStructure(['type', 'url', 'expires_at']);
        $audio = $this->place($this->storage()->store(UploadedFile::fake()->create('son.mp3', 4, 'audio/mpeg'), ['title' => 'Son'], $this->user));
        $this->getJson(route('learn.v1.resources.view', $audio->id))
            ->assertOk()->assertJsonPath('type', 'audio');

        // Lien et vidéo.
        $link = $this->place($this->storage()->createLink('https://example.org/cours', 'Lien', $this->user));
        $this->getJson(route('learn.v1.resources.view', $link->id))
            ->assertOk()->assertExactJson(['type' => 'link', 'url' => 'https://example.org/cours']);
        $video = $this->place($this->storage()->store(UploadedFile::fake()->create('clip.mp4', 50, 'video/mp4'), ['title' => 'Clip'], $this->user));
        $this->getJson(route('learn.v1.resources.view', $video->id))
            ->assertOk()->assertExactJson(['type' => 'video', 'video_ref' => $video->video->ref]);

        // Document en attente de conversion.
        $docx = $this->place($this->storedDocx());
        $this->getJson(route('learn.v1.resources.view', $docx->id))
            ->assertStatus(202)->assertExactJson(['status' => 'pending']);
        $this->get(URL::temporarySignedRoute('learn.v1.resources.file', now()->addMinutes(5), ['resource' => $docx->id]))
            ->assertNotFound();
    }

    public function test_signed_file_expires(): void
    {
        $pdf = $this->place($this->storedPdf());
        $url = $this->actingAs($this->user)->getJson(route('learn.v1.resources.view', $pdf->id))->json('url');
        $this->app['auth']->forgetGuards();

        $this->travel(899)->seconds();
        $this->get($url)->assertOk();

        $this->travel(2)->seconds();
        $this->get($url)->assertForbidden();

        // URL sans signature ou falsifiée : refusée.
        $this->get("/api/learner/v1/resources/{$pdf->id}/file")->assertForbidden();
        $this->get(str_replace('signature=', 'signature=0', $url))->assertForbidden();

        // Ressource désactivée : plus servie, même avec une signature valide.
        $fresh = URL::temporarySignedRoute('learn.v1.resources.file', now()->addMinutes(5), ['resource' => $pdf->id]);
        $this->get($fresh)->assertOk();
        $pdf->update(['is_active' => false]);
        $this->get($fresh)->assertNotFound();
    }

    public function test_download_forbidden_by_default(): void
    {
        $pdf = $this->place($this->storedPdf());

        $this->actingAs($this->user)->getJson(route('learn.v1.resources.download', $pdf->id))
            ->assertForbidden();

        $pdf->update(['allow_download' => true]);
        $response = $this->getJson(route('learn.v1.resources.download', $pdf->id))->assertOk();
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('cours.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('%PDF-1.4 contenu du cours', $response->streamedContent());

        // Document : l'original (Word) est téléchargé, pas le PDF.
        $docx = $this->place($this->storedDocx());
        $docx->update(['allow_download' => true]);
        $original = $this->getJson(route('learn.v1.resources.download', $docx->id))->assertOk();
        $this->assertStringContainsString('support.docx', $original->headers->get('Content-Disposition'));

        // Lien : rien à télécharger.
        $link = $this->place($this->storage()->createLink('https://example.org', 'Lien', $this->user));
        $link->update(['allow_download' => true]);
        $this->getJson(route('learn.v1.resources.download', $link->id))->assertNotFound();
    }

    public function test_view_forbidden_when_hidden(): void
    {
        $hidden = Folder::create(['group_id' => $this->group->id, 'name' => 'Masqué', 'position' => 0, 'is_visible' => false]);
        $pdf = $this->place($this->storedPdf(['allow_download' => true]), $hidden);

        $this->actingAs($this->user)->getJson(route('learn.v1.resources.view', $pdf->id))->assertForbidden();
        $this->getJson(route('learn.v1.resources.download', $pdf->id))->assertForbidden();

        // Ressource d'un autre groupe.
        $other = Group::create(['name' => 'Autre', 'is_active' => true]);
        $foreign = $this->storedPdf(['allow_download' => true]);
        $other->resources()->attach($foreign->id, ['folder_id' => null, 'position' => 0]);
        $this->getJson(route('learn.v1.resources.view', $foreign->id))->assertForbidden();

        // Ressource visible mais désactivée.
        $inactive = $this->place($this->storedPdf(['is_active' => false]));
        $this->getJson(route('learn.v1.resources.view', $inactive->id))->assertForbidden();

        // Identifiant inexistant : même réponse (aucune fuite d'existence).
        $this->getJson(route('learn.v1.resources.view', 999999))->assertForbidden();

        // Dossier rendu visible : accès ouvert.
        $hidden->update(['is_visible' => true]);
        $this->getJson(route('learn.v1.resources.view', $pdf->id))->assertOk();
    }

    public function test_view_allowed_in_impersonation_but_not_download(): void
    {
        $this->assertContains('learn.v1.resources.view', ResolveImpersonation::READ_ONLY_ALLOWED);
        $this->assertNotContains('learn.v1.resources.download', ResolveImpersonation::READ_ONLY_ALLOWED);
    }

    public function test_mobile_view_with_sanctum(): void
    {
        $pdf = $this->place($this->storedPdf());
        $headers = ['Authorization' => 'Bearer '.$this->mobileToken()];

        $url = $this->getJson("/api/mobile/v1/resources/{$pdf->id}/view", $headers)
            ->assertOk()
            ->assertJsonPath('type', 'pdf')
            ->json('url');
        $this->getJson("/api/mobile/v1/resources/{$pdf->id}/download", $headers)->assertForbidden();

        // L'URL signée fonctionne sans cookie ni jeton.
        $this->app['auth']->forgetGuards();
        $this->assertSame('%PDF-1.4 contenu du cours', $this->body($this->get($url)->assertOk()));

        // Sans jeton : refusé.
        $this->getJson("/api/mobile/v1/resources/{$pdf->id}/view")->assertUnauthorized();
    }

    public function test_release_keeps_shared_file(): void
    {
        $original = $this->storedPdf();
        $copy = Resource::create($original->only(['kind', 'title', 'disk', 'path', 'mime', 'size', 'original_name']));

        $this->storage()->release($original);
        Storage::disk('local')->assertExists($original->path);

        $original->delete();
        $this->storage()->release($copy);
        Storage::disk('local')->assertMissing($copy->path);

        // Document converti : le PDF suit la même règle.
        $docx = $this->storedDocx();
        $this->fakeSoffice();
        app()->call([new ConvertDocumentToPdf($docx->id), 'handle']);
        $docx->refresh();
        $twin = Resource::create($docx->only(['kind', 'title', 'disk', 'path', 'pdf_path', 'conversion_status', 'mime', 'size', 'original_name']));

        $this->storage()->release($docx);
        Storage::disk('local')->assertExists($docx->path);
        Storage::disk('local')->assertExists($docx->pdf_path);

        $docx->delete();
        $this->storage()->release($twin);
        Storage::disk('local')->assertMissing($twin->path);
        Storage::disk('local')->assertMissing($twin->pdf_path);

        // Lien : rien à supprimer, aucune erreur.
        $this->storage()->release($this->storage()->createLink('https://example.org', 'Lien', $this->user));
    }
}
