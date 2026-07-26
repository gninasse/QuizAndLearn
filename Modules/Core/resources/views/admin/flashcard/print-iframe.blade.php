<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Flashcards Print Preview (A4)</title>
  <link rel="stylesheet" href="{{ asset('plugins/source-sans-3/index.css') }}">
  <link rel="stylesheet" href="{{ asset('plugins/bootstrap-icons/font/bootstrap-icons.min.css') }}">
  
  <style>
    body {
      font-family: "Source Sans 3", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: #f1f5f9;
      color: #334155;
      margin: 0;
      padding: 20px;
      box-sizing: border-box;
    }

    /* Print styling rules */
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
      }
    }

    @page {
      size: A4 portrait;
      margin: 10mm;
    }

    /* A4 page preview wrapper */
    .print-page {
      background-color: #ffffff;
      width: 210mm;
      height: 297mm;
      margin: 0 auto 30px auto;
      padding: 15mm 15mm;
      box-sizing: border-box;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
      border: 1px solid #cbd5e1;
      display: flex;
      flex-direction: column;
      position: relative;
    }

    .print-page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 2px solid #e2e8f0;
      padding-bottom: 10px;
      margin-bottom: 20px;
      font-size: 0.9rem;
      color: #94a3b8;
    }

    .print-page-header strong {
      color: #1e293b;
    }

    .print-grid {
      display: flex;
      flex-direction: column;
      gap: 15mm;
      flex-grow: 1;
    }

    /* Printable Card layout (Recto/Verso side by side) */
    .printable-card {
      display: grid;
      grid-template-columns: 1fr 1fr;
      height: 52mm;
      border: 2px dotted #94a3b8;
      border-radius: 8px;
      overflow: hidden;
      background-color: #ffffff;
      box-sizing: border-box;
      position: relative;
    }

    .card-half {
      padding: 20px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      box-sizing: border-box;
      position: relative;
      overflow: hidden;
    }

    .card-half.front {
      border-right: 1px dashed #cbd5e1;
    }

    .card-half.back {
      background-color: #fafdfc;
    }

    .card-badge {
      position: absolute;
      top: 8px;
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      padding: 2px 6px;
      border-radius: 4px;
    }
    
    .card-badge.recto {
      left: 10px;
      background-color: #e2f0ec;
      color: #124e3f;
    }

    .card-badge.verso {
      right: 10px;
      background-color: #fef3c7;
      color: #b45309;
    }

    .card-num {
      position: absolute;
      top: 8px;
      right: 10px;
      font-size: 0.75rem;
      color: #94a3b8;
      font-weight: bold;
    }

    .card-num.verso-num {
      left: 10px;
      right: auto;
    }

    .card-content {
      font-size: 1.05rem;
      font-weight: 600;
      color: #334155;
      line-height: 1.4;
      width: 100%;
    }

    .card-content p {
      margin: 0;
    }

    /* Dashed folding helper line indicators */
    .fold-indicator {
      position: absolute;
      top: 0;
      bottom: 0;
      left: 50%;
      width: 0;
      border-left: 2px dashed #94a3b8;
      transform: translateX(-50%);
      pointer-events: none;
      z-index: 10;
    }
    
    .fold-help-icon {
      position: absolute;
      bottom: 8px;
      left: 50%;
      transform: translateX(-50%);
      font-size: 0.8rem;
      color: #94a3b8;
      background: #ffffff;
      padding: 0 6px;
      border-radius: 20px;
      border: 1px solid #cbd5e1;
      font-weight: bold;
    }

    .card-tags-note {
      position: absolute;
      bottom: 8px;
      font-size: 0.7rem;
      color: #94a3b8;
    }

    .card-tags-note.left-note {
      left: 10px;
    }
    
    .card-tags-note.right-note {
      right: 10px;
      font-style: italic;
    }

    /* Print toolbar at top */
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

  <!-- Hidden toolbar inside iframe so we can still launch print from inside if needed -->
  <div class="no-print-toolbar no-print">
    <div>
      <h6 class="fw-bold mb-1" style="color: #1e6f5c;"><i class="bi bi-printer-fill me-1"></i> Impression de Flashcards (A4)</h6>
      <p class="text-muted small mb-0">Imprimez au format A4 puis découpez et pliez les cartes pour obtenir vos flashcards Recto-Verso.</p>
    </div>
    <button class="btn btn-primary btn-sm py-2 px-3 fw-bold" onclick="window.print()" style="background-color: #1e6f5c; border: none; border-radius: 6px;">
      <i class="bi bi-printer me-1"></i> Imprimer
    </button>
  </div>

  @php
    $chunks = $deck->cards->chunk(4); // 4 cards per page
  @endphp

  @forelse($chunks as $pageIndex => $pageCards)
    <div class="print-page">
      <div class="print-page-header">
        <span>Paquet : <strong>{{ $deck->titre }}</strong></span>
        <span>Page {{ $pageIndex + 1 }} sur {{ $chunks->count() }}</span>
      </div>

      <div class="print-grid">
        @foreach($pageCards as $cardIndex => $card)
          @php
            $absoluteIndex = ($pageIndex * 4) + $cardIndex + 1;
          @endphp
          <div class="printable-card">
            <!-- Recto (Front) -->
            <div class="card-half front">
              <span class="card-badge recto">Recto</span>
              <span class="card-num">#{{ $absoluteIndex }}</span>
              <div class="card-content">{!! $card->recto !!}</div>
              @if($card->tags)
                <span class="card-tags-note left-note">#{{ str_replace(',', ' #', $card->tags) }}</span>
              @endif
            </div>

            <!-- Folding guideline helper -->
            <div class="fold-indicator"></div>
            <div class="fold-help-icon"><i class="bi bi-arrow-left-right"></i> Plier</div>

            <!-- Verso (Back) -->
            <div class="card-half back">
              <span class="card-badge verso">Verso</span>
              <span class="card-num verso-num">#{{ $absoluteIndex }}</span>
              <div class="card-content" style="color: #124e3f;">{!! $card->verso !!}</div>
              @if($card->note)
                <span class="card-tags-note right-note"><i class="bi bi-info-circle"></i> {{ $card->note }}</span>
              @endif
            </div>
          </div>

        @endforeach
      </div>
    </div>
  @empty
    <div class="print-page" style="justify-content: center; align-items: center;">
      <p class="text-muted">Aucune carte à imprimer dans ce paquet.</p>
    </div>
  @endforelse

</body>
</html>
