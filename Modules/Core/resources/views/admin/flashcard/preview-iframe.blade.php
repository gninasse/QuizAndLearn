<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Flashcards Player - Preview</title>
  
  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="{{ asset('plugins/source-sans-3/index.css') }}">
  <link rel="stylesheet" href="{{ asset('plugins/bootstrap-icons/font/bootstrap-icons.min.css') }}">
  <link rel="stylesheet" href="{{ asset('adminlte/css/adminlte.css') }}">
  
  <style>
    :root {
      --primary: #1e6f5c;
      --primary-dark: #124e3f;
      --primary-light: #e8f5f1;
      --success: #10b981;
      --success-light: #ecfdf5;
      --danger: #ef4444;
      --danger-light: #fef2f2;
      --bg: #f8fafc;
      --card: #ffffff;
      --border: #e2e8f0;
      --text: #1e293b;
      --text-muted: #64748b;
      --radius: 16px;
      --radius-sm: 8px;
    }

    body {
      font-family: "Source Sans 3", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: var(--bg);
      color: var(--text);
      margin: 0;
      padding: 0;
      height: 100vh;
      display: flex;
      flex-direction: column;
      user-select: none;
    }

    .player-container {
      max-width: 600px;
      width: 100%;
      margin: 0 auto;
      padding: 20px;
      box-sizing: border-box;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .player-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
      padding: 30px;
      display: flex;
      flex-direction: column;
      height: 480px;
      position: relative;
    }

    /* Progress bar */
    .progress-wrapper {
      margin-bottom: 20px;
    }
    .progress-info {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.9rem;
      color: var(--text-muted);
      margin-bottom: 8px;
      font-weight: 600;
    }
    .progress {
      height: 6px;
      background-color: var(--border);
      border-radius: 3px;
      overflow: hidden;
    }
    .progress-bar {
      background-color: var(--primary);
      height: 100%;
      width: 0%;
      transition: width 0.3s ease;
    }

    /* 3D Flashcard flip container */
    .flashcard-scene {
      flex-grow: 1;
      perspective: 1000px;
      margin-bottom: 24px;
      cursor: pointer;
    }
    .flashcard-wrapper {
      width: 100%;
      height: 100%;
      position: relative;
      transform-style: preserve-3d;
      transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .flashcard-wrapper.is-flipped {
      transform: rotateY(180deg);
    }

    /* Card face layout */
    .flashcard-face {
      position: absolute;
      width: 100%;
      height: 100%;
      backface-visibility: hidden;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      background-color: #ffffff;
      overflow-y: auto;
    }

    .flashcard-face.front {
      border-top: 5px solid var(--primary);
    }

    .flashcard-face.back {
      transform: rotateY(180deg);
      border-top: 5px solid var(--warning);
      background-color: #fafdfb;
    }

    .card-label {
      position: absolute;
      top: 15px;
      left: 20px;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--text-muted);
    }

    .card-content {
      font-size: 1.25rem;
      font-weight: 600;
      line-height: 1.5;
      color: var(--text);
      word-break: break-word;
    }

    .card-content p {
      margin-bottom: 0.5rem;
    }

    .card-note {
      position: absolute;
      bottom: 15px;
      font-size: 0.8rem;
      font-style: italic;
      color: var(--text-muted);
      width: 80%;
    }

    /* Footer Navigation */
    .player-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
    }

    /* Recap Screen */
    .recap-screen {
      text-align: center;
      animation: zoomIn 0.4s ease;
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
    }
    .recap-icon {
      font-size: 5rem;
      color: var(--success);
      margin-bottom: 15px;
    }
    
    @keyframes zoomIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
  </style>
</head>
<body>

  <div class="player-container">
    <div class="player-card">
      
      <!-- Progress Bar -->
      <div class="progress-wrapper" id="progressWrapper">
        <div class="progress-info">
          <span id="cardIndexIndicator">Carte 1 sur 1</span>
          <span class="badge bg-light text-dark" id="deckSubjectIndicator">Flashcards</span>
        </div>
        <div class="progress">
          <div class="progress-bar" id="progressBar"></div>
        </div>
      </div>

      <!-- Flashcard Space -->
      <div class="flashcard-scene" id="cardScene">
        <div class="flashcard-wrapper" id="cardWrapper">
          
          <!-- Front Face -->
          <div class="flashcard-face front">
            <span class="card-label">Recto (Devant)</span>
            <div class="card-content" id="cardFrontText">Question ou terme</div>
            <div class="card-note" id="cardFrontNote">Indice</div>
          </div>
          
          <!-- Back Face -->
          <div class="flashcard-face back">
            <span class="card-label">Verso (Derrière)</span>
            <div class="card-content" id="cardBackText" style="color: var(--primary-dark);">Explication</div>
          </div>

        </div>
      </div>

      <!-- Recap Slide -->
      <div class="recap-screen" id="recapScreen">
        <div class="recap-icon"><i class="bi bi-check2-circle"></i></div>
        <h4 class="fw-bold mb-2">Terminé !</h4>
        <p class="text-muted mb-4">Vous avez prévisualisé toutes les cartes de ce paquet.</p>
        
        <button type="button" class="btn btn-primary px-5 py-3" id="btnRestart" style="border-radius: 30px; background-color: var(--primary); border: none;">
          <i class="bi bi-arrow-clockwise me-1"></i> Recommencer
        </button>
      </div>

      <!-- Footer Buttons -->
      <div class="player-footer" id="playerFooter">
        <button type="button" class="btn btn-outline-secondary px-4 py-2" id="btnPrev" style="border-radius: var(--radius-sm);">
          <i class="bi bi-chevron-left"></i> Précédent
        </button>
        <button type="button" class="btn btn-primary px-4 py-2" id="btnFlip" style="border-radius: var(--radius-sm); background-color: var(--primary); border: none;">
          <i class="bi bi-arrow-repeat me-1"></i> Retourner
        </button>
        <button type="button" class="btn btn-outline-secondary px-4 py-2" id="btnNext" style="border-radius: var(--radius-sm);">
          Suivant <i class="bi bi-chevron-right"></i>
        </button>
      </div>

    </div>
  </div>

  <script src="{{ asset('plugins/jquery/jquery-3.7.1.min.js') }}"></script>
  
  <script>
    $(function() {
      var cards = @json($deck->cards);
      var currentIdx = 0;

      // Handle empty deck
      if (!cards || cards.length === 0) {
        $('#progressWrapper').hide();
        $('#playerFooter').hide();
        $('#cardScene').html('<div class="text-center py-5 my-auto text-muted">' +
          '<i class="bi bi-emoji-frown display-1 d-block mb-3"></i>' +
          '<h4>Aucune carte disponible</h4>' +
          '<p class="mb-0">Ce paquet ne contient aucune carte pour le moment.</p>' +
          '</div>');
        return;
      }

      // Initialize subject indicator
      $('#deckSubjectIndicator').text("{{ $deck->matiere ?: 'Général' }}");

      function showCard(idx) {
        if (idx >= cards.length) {
          // Show recap screen
          $('#progressWrapper').hide();
          $('#cardScene').hide();
          $('#playerFooter').hide();
          $('#recapScreen').css('display', 'flex');
          return;
        }

        var card = cards[idx];
        
        // Reset flip state before showing next card content
        var $wrapper = $('#cardWrapper');
        $wrapper.removeClass('is-flipped');

        // Set text after brief transition time if it was flipped to avoid reading answer early
        setTimeout(function() {
          $('#cardFrontText').html(card.recto);
          $('#cardBackText').html(card.verso);
          $('#cardFrontNote').text(card.note ? '💡 ' + card.note : '');
        }, 100);

        // Progress bar
        var total = cards.length;
        var percent = ((idx + 1) / total) * 100;
        $('#progressBar').css('width', percent + '%');
        $('#cardIndexIndicator').text('Carte ' + (idx + 1) + ' sur ' + total);

        // Footer buttons state
        $('#btnPrev').prop('disabled', idx === 0);
      }

      // Flip card trigger
      function flipCard() {
        $('#cardWrapper').toggleClass('is-flipped');
      }

      $('#cardScene').click(flipCard);
      $('#btnFlip').click(flipCard);

      // Navigation buttons
      $('#btnPrev').click(function() {
        if (currentIdx > 0) {
          currentIdx--;
          showCard(currentIdx);
        }
      });

      $('#btnNext').click(function() {
        currentIdx++;
        showCard(currentIdx);
      });

      // Restart Action
      $('#btnRestart').click(function() {
        currentIdx = 0;
        $('#recapScreen').hide();
        $('#progressWrapper').show();
        $('#cardScene').show();
        $('#playerFooter').show();
        showCard(currentIdx);
      });

      // Start Player
      showCard(currentIdx);
    });
  </script>
</body>
</html>
