<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Impression Examen - {{ $exam->title }}</title>
  <link rel="stylesheet" href="{{ asset('plugins/source-sans-3/index.css') }}">
  <link rel="stylesheet" href="{{ asset('plugins/bootstrap-icons/font/bootstrap-icons.min.css') }}">
  
  <style>
    body {
      font-family: "Source Sans 3", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: #f1f5f9;
      color: #1e293b;
      margin: 0;
      padding: 20px;
      box-sizing: border-box;
    }

    /* Print settings */
    @media print {
      body {
        background-color: #ffffff;
        padding: 0;
        margin: 0;
      }
      .no-print {
        display: none !important;
      }
      .print-page {
        margin: 0 !important;
        box-shadow: none !important;
        page-break-after: always;
        border: none !important;
        padding: 0 !important;
      }
    }

    @page {
      size: A4 portrait;
      margin: 20mm 15mm 20mm 15mm;
    }

    .print-page {
      background-color: #ffffff;
      width: 210mm;
      min-height: 297mm;
      margin: 0 auto 30px auto;
      padding: 20mm 20mm;
      box-sizing: border-box;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
      border: 1px solid #cbd5e1;
      position: relative;
    }

    /* Header styling */
    .test-header {
      display: grid;
      grid-template-columns: 1.2fr 0.8fr;
      gap: 20px;
      border-bottom: 3px double #cbd5e1;
      padding-bottom: 15px;
      margin-bottom: 25px;
    }

    .test-title-section h2 {
      margin: 0 0 5px 0;
      font-size: 1.6rem;
      font-weight: 800;
      color: #1a365d;
    }

    .test-title-section p {
      margin: 0;
      font-size: 0.95rem;
      color: #64748b;
    }

    .student-fields {
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      padding: 10px 15px;
      font-size: 0.85rem;
      line-height: 1.8;
      background-color: #f8fafc;
    }

    .student-field-line {
      border-bottom: 1px dotted #94a3b8;
      margin-bottom: 4px;
    }

    .exam-meta-details {
      margin-top: 10px;
      display: flex;
      gap: 15px;
      font-size: 0.85rem;
      font-weight: bold;
      color: #1a365d;
    }

    /* Question list styling */
    .question-block {
      margin-bottom: 25px;
      page-break-inside: avoid;
    }

    .question-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 12px;
      display: flex;
      gap: 8px;
    }

    .question-num {
      background-color: #e2e8f0;
      color: #1a365d;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.85rem;
      font-weight: bold;
      flex-shrink: 0;
    }

    /* Option renderers */
    .options-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 8px;
      padding-left: 32px;
    }

    .option-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.95rem;
    }

    .option-checkbox {
      width: 16px;
      height: 16px;
      border: 1.5px solid #64748b;
      border-radius: 3px;
      flex-shrink: 0;
    }

    .option-radio {
      width: 16px;
      height: 16px;
      border: 1.5px solid #64748b;
      border-radius: 50%;
      flex-shrink: 0;
    }

    /* Open text writing space */
    .writing-lines {
      margin-top: 10px;
      padding-left: 32px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .writing-line {
      border-bottom: 1px solid #cbd5e1;
      height: 10px;
      width: 100%;
    }

    /* Match layout */
    .match-table {
      width: 90%;
      margin: 10px auto 0 auto;
      border-collapse: collapse;
    }

    .match-table td {
      padding: 8px 15px;
      width: 45%;
      font-size: 0.95rem;
    }

    .match-left-item {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 4px;
      text-align: right;
    }

    .match-right-item {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 4px;
      text-align: left;
    }

    .match-connector {
      width: 10%;
      text-align: center;
      color: #94a3b8;
    }

    /* Correction sheet styling */
    .correction-header {
      border-bottom: 3px double #ef4444;
      padding-bottom: 10px;
      margin-bottom: 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .correction-header h3 {
      margin: 0;
      color: #ef4444;
      font-weight: 800;
    }

    .answer-key-list {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    .answer-key-item {
      border-bottom: 1px dashed #cbd5e1;
      padding-bottom: 8px;
    }

    .answer-key-q {
      font-weight: 700;
      margin-bottom: 4px;
    }

    .answer-key-val {
      color: #10b981;
      font-weight: 600;
      padding-left: 20px;
    }

    /* Toolbar styling */
    .no-print-toolbar {
      max-width: 210mm;
      margin: 0 auto 15px auto;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
  </style>
</head>
<body>

  <!-- Hidden toolbar in printable view -->
  <div class="no-print-toolbar no-print">
    <div>
      <h6 class="fw-bold mb-1" style="color: #1a365d;"><i class="bi bi-printer-fill me-1"></i> Impression d'Examen</h6>
      <p class="text-muted small mb-0">Imprimez ce document pour obtenir une version papier de l'examen avec son barème et sa grille de correction à la fin.</p>
    </div>
    <button class="btn btn-primary btn-sm py-2 px-3 fw-bold" onclick="window.print()" style="background-color: #1a365d; border: none; border-radius: 6px; color: white; cursor: pointer;">
      <i class="bi bi-printer me-1"></i> Imprimer l'Examen
    </button>
  </div>

  <!-- Test Sheets -->
  <div class="print-page">
    <div class="test-header">
      <div class="test-title-section">
        <h2>{{ $exam->title }}</h2>
        <p>{{ $exam->description ?: 'Aucune description disponible.' }}</p>
        
        <div class="exam-meta-details">
          <span><i class="bi bi-clock"></i> Durée : {{ $exam->duration }} minutes</span>
          <span><i class="bi bi-check-all"></i> Note maximale : {{ $exam->note_max }}</span>
        </div>
      </div>
      <div class="student-fields">
        <div class="student-field-line">Nom :</div>
        <div class="student-field-line">Prénom :</div>
        <div class="student-field-line">Classe :</div>
        <div>Date :</div>
      </div>
    </div>

    <!-- Questions list -->
    <div>
      @forelse($exam->questions as $index => $q)
        <div class="question-block">
          <div class="question-title">
            <span class="question-num">{{ $index + 1 }}</span>
            <div>
              {!! $q->question_text !!}
              <span class="text-muted" style="font-size: 0.85rem; font-weight: normal;">({{ $q->points }} pts)</span>
            </div>
          </div>

          <!-- MCQ / True False / Open text renderers -->
          @php
            $opts = is_string($q->options) ? json_decode($q->options, true) : $q->options;
          @endphp

          @if($q->type === 'mcq' || $q->type === 'true_false')
            <div class="options-grid">
              @php
                $choices = $opts['options'] ?? ($q->type === 'true_false' ? ['Vrai', 'Faux'] : []);
              @endphp
              @foreach($choices as $choice)
                <div class="option-item">
                  <div class="{{ $q->type === 'mcq' && ($opts['multiple'] ?? false) ? 'option-checkbox' : 'option-radio' }}"></div>
                  <span>{{ is_array($choice) ? ($choice['text'] ?? '') : $choice }}</span>
                </div>
              @endforeach
            </div>

          @elseif($q->type === 'fill_blank')
            <div class="options-grid">
              <span class="text-muted small italic"><i class="bi bi-pencil"></i> Remplissez les espaces vides dans le texte ci-dessus.</span>
            </div>

          @elseif($q->type === 'open_text')
            <div class="writing-lines">
              <div class="writing-line"></div>
              <div class="writing-line"></div>
              <div class="writing-line"></div>
            </div>

          @elseif($q->type === 'matching')
            @php
              $pairs = $opts['pairs'] ?? [];
              $leftItems = collect($pairs)->pluck('term')->shuffle();
              $rightItems = collect($pairs)->pluck('definition')->shuffle();
            @endphp
            <span class="text-muted small italic" style="padding-left: 32px; display: block; margin-bottom: 8px;"><i class="bi bi-arrow-left-right"></i> Reliez les éléments correspondants de gauche à droite par une ligne.</span>
            <table class="match-table">
              @for($i = 0; $i < count($pairs); $i++)
                <tr>
                  <td class="match-left-item">{{ $leftItems[$i] }}</td>
                  <td class="match-connector">• &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; •</td>
                  <td class="match-right-item">{{ $rightItems[$i] ?? '' }}</td>
                </tr>
              @endfor
            </table>

          @elseif($q->type === 'ordering')
            @php
              $items = collect($opts['items'] ?? [])->shuffle();
            @endphp
            <span class="text-muted small italic" style="padding-left: 32px; display: block; margin-bottom: 8px;"><i class="bi bi-list-ol"></i> Classez les éléments ci-dessous dans l'ordre en écrivant un numéro de 1 à {{ count($items) }} dans la case.</span>
            <div class="options-grid">
              @foreach($items as $item)
                <div class="option-item">
                  <div class="option-checkbox d-flex align-items-center justify-content-center" style="width: 22px; height: 22px; font-weight: bold; color: #94a3b8;"></div>
                  <span>{{ $item }}</span>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      @empty
        <p class="text-muted text-center py-5">Cet examen ne contient aucune question pour le moment.</p>
      @endforelse
    </div>
  </div>

  <!-- Page Break for Correction Sheet -->
  <div class="print-page">
    <div class="correction-header">
      <h3>GRILLE DE CORRECTION (Réservé au correcteur)</h3>
      <span class="text-muted small">Examen : {{ $exam->title }}</span>
    </div>

    <div class="answer-key-list">
      @foreach($exam->questions as $index => $q)
        @php
          $opts = is_string($q->options) ? json_decode($q->options, true) : $q->options;
        @endphp
        <div class="answer-key-item">
          <div class="answer-key-q">Question {{ $index + 1 }} ({{ $q->points }} pts) :</div>
          <div class="answer-key-val">
            @if($q->type === 'mcq')
              @php
                $choices = $opts['options'] ?? [];
                $corrects = [];
                foreach($choices as $cIdx => $choice) {
                  $isCorrect = is_array($choice) ? ($choice['correct'] ?? false) : false;
                  if ($isCorrect) {
                    $corrects[] = is_array($choice) ? ($choice['text'] ?? '') : $choice;
                  }
                }
              @endphp
              Correct : {{ implode(' / ', $corrects) }}
            @elseif($q->type === 'true_false')
              Correct : {{ ($opts['correct_answer'] ?? '') === 'true' ? 'Vrai' : 'Faux' }}
            @elseif($q->type === 'fill_blank')
              @php
                $blanks = $opts['blanks'] ?? [];
                $blankAnswers = [];
                foreach($blanks as $bIdx => $blank) {
                  $blankAnswers[] = 'Espace '.($bIdx+1).' : '.implode(', ', $blank['answers'] ?? []);
                }
              @endphp
              {{ implode(' | ', $blankAnswers) }}
            @elseif($q->type === 'open_text')
              Critères de correction : {{ $opts['correct_answer'] ?? 'Réponse ouverte à évaluer manuellement.' }}
            @elseif($q->type === 'matching')
              @php
                $pairs = $opts['pairs'] ?? [];
                $pairList = [];
                foreach($pairs as $p) {
                  $pairList[] = $p['term'] . ' ➔ ' . $p['definition'];
                }
              @endphp
              {{ implode(' | ', $pairList) }}
            @elseif($q->type === 'ordering')
              @php
                $items = $opts['items'] ?? [];
                $orderedList = [];
                foreach($items as $iIdx => $item) {
                  $orderedList[] = ($iIdx+1).'. '.$item;
                }
              @endphp
              Ordre correct : {{ implode(' -> ', $orderedList) }}
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>

</body>
</html>
