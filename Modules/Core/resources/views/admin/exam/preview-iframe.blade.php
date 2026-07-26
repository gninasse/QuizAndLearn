<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Exam Player - Preview</title>
  
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
      --success-dark: #047857;
      --success-light: #ecfdf5;
      --danger: #ef4444;
      --danger-dark: #b91c1c;
      --danger-light: #fef2f2;
      --warning: #f59e0b;
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
      max-width: 800px;
      width: 100%;
      margin: 0 auto;
      padding: 20px;
      box-sizing: border-box;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .exam-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
      padding: 30px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      position: relative;
      overflow: hidden;
    }

    /* Progress bar */
    .progress-wrapper {
      margin-bottom: 24px;
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

    /* Slide area */
    .question-slide-container {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }
    .question-slide {
      display: none;
      flex-grow: 1;
      flex-direction: column;
      animation: fadeIn 0.3s ease-in-out;
    }
    .question-slide.active {
      display: flex;
    }

    .question-header {
      margin-bottom: 20px;
    }
    .question-points {
      display: inline-block;
      padding: 4px 10px;
      background-color: var(--primary-light);
      color: var(--primary-dark);
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 700;
      margin-bottom: 10px;
    }
    .question-title {
      font-size: 1.25rem;
      font-weight: 700;
      line-height: 1.4;
      margin: 0 0 16px 0;
    }

    /* Option templates */
    .options-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 10px;
    }

    /* True/False Cards */
    .tf-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-top: 20px;
    }
    .tf-card {
      border: 2px solid var(--border);
      border-radius: var(--radius);
      padding: 30px 20px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s ease;
      background-color: var(--card);
    }
    .tf-card:hover {
      border-color: var(--primary);
      background-color: #f0fdf4;
    }
    .tf-card.active {
      border-color: var(--primary);
      background-color: var(--primary-light);
      color: var(--primary-dark);
      font-weight: 700;
      box-shadow: 0 4px 12px rgba(30, 111, 92, 0.15);
    }
    .tf-card i {
      font-size: 2.5rem;
      display: block;
      margin-bottom: 12px;
    }
    .tf-card.tf-true.active i {
      color: var(--success);
    }
    .tf-card.tf-false.active i {
      color: var(--danger);
    }

    /* MCQ Custom Checkboxes */
    .mcq-option {
      display: flex;
      align-items: center;
      padding: 16px 20px;
      border: 1px solid var(--border);
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.2s ease;
      background-color: var(--card);
    }
    .mcq-option:hover {
      border-color: var(--primary);
      background-color: #f8fafc;
    }
    .mcq-option.active {
      border-color: var(--primary);
      background-color: var(--primary-light);
    }
    .mcq-check-input {
      margin-right: 16px;
      transform: scale(1.2);
      accent-color: var(--primary);
    }
    .mcq-text {
      font-size: 1rem;
      font-weight: 500;
    }

    /* Fill Blank Inputs */
    .fill-blank-text {
      font-size: 1.1rem;
      line-height: 1.8;
      margin-top: 15px;
      padding: 15px;
      border-radius: 12px;
      background-color: #f1f5f9;
      border: 1px solid var(--border);
    }
    .fb-input {
      display: inline-block;
      width: 140px;
      margin: 0 6px;
      border: 2px solid var(--border);
      border-radius: 6px;
      padding: 4px 8px;
      font-size: 0.95rem;
      font-weight: 600;
      text-align: center;
      transition: border-color 0.2s;
    }
    .fb-input:focus {
      outline: none;
      border-color: var(--primary);
    }

    /* Matching lists */
    .matching-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-top: 15px;
    }
    .matching-col {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .matching-item {
      padding: 14px 16px;
      background-color: var(--bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      font-size: 0.95rem;
      font-weight: 600;
      min-height: 50px;
      box-sizing: border-box;
      display: flex;
      align-items: center;
    }
    .matching-item.draggable {
      cursor: grab;
      background-color: #ffffff;
      border: 1px solid #94a3b8;
    }
    .matching-item.draggable:active {
      cursor: grabbing;
    }
    .matching-item.sortable-ghost {
      opacity: 0.4;
      background-color: var(--primary-light);
    }

    /* Ordering list */
    .ordering-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 15px;
    }
    .ordering-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 16px;
      background-color: #ffffff;
      border: 1px solid #94a3b8;
      border-radius: 10px;
      cursor: grab;
      font-weight: 600;
    }
    .ordering-item:active {
      cursor: grabbing;
    }
    .ordering-item-badge {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      background-color: var(--primary-light);
      color: var(--primary-dark);
      border-radius: 50%;
      font-size: 0.8rem;
    }

    /* Free Text Textarea */
    .free-text-area {
      border-radius: 12px;
      border: 1px solid var(--border);
      padding: 15px;
      font-size: 1rem;
      outline: none;
      resize: none;
      transition: border-color 0.2s;
    }
    .free-text-area:focus {
      border-color: var(--primary);
    }

    /* Footer actions */
    .player-footer {
      display: flex;
      justify-content: space-between;
      margin-top: auto;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }

    /* Results Screen */
    .results-slide {
      text-align: center;
      animation: zoomIn 0.4s ease;
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      flex-grow: 1;
    }
    .results-icon {
      font-size: 5rem;
      margin-bottom: 20px;
    }
    .results-score-badge {
      font-size: 3rem;
      font-weight: 800;
      color: var(--primary-dark);
      margin-bottom: 10px;
    }
    .results-status {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 30px;
    }

    /* Result Details Table */
    .results-table-container {
      width: 100%;
      max-height: 250px;
      overflow-y: auto;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      margin-bottom: 30px;
      text-align: left;
    }
    .table-results {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }
    .table-results th, .table-results td {
      padding: 10px 15px;
      border-bottom: 1px solid var(--border);
    }
    .table-results th {
      background-color: #f1f5f9;
      font-weight: 700;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes zoomIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
  </style>
</head>
<body>

  <div class="player-container">
    <div class="exam-card">
      
      <!-- Progress, metadata and active countdown timer -->
      <div class="progress-wrapper" id="playerProgressWrapper">
        <div class="progress-info">
          <span id="playerQuestionIndex">Question 1 sur 1</span>
          <div>
            <span id="playerPointsVal" class="me-3">1 pts</span>
            <span id="timer-display" class="badge bg-secondary py-2 px-3 fw-normal" style="font-size: 0.85rem;">
              <i class="bi bi-clock me-1"></i> <span id="timer-clock">00:00</span>
            </span>
          </div>
        </div>
        <div class="progress">
          <div class="progress-bar" id="playerProgressBar"></div>
        </div>
      </div>

      <!-- Slide content zone -->
      <div class="question-slide-container" id="slidesContainer">
        <!-- Rendered in Javascript -->
      </div>

      <!-- Results Slide -->
      <div class="results-slide" id="resultsSlide">
        <div class="results-icon" id="resultsIcon">🎉</div>
        <div class="results-score-badge" id="resultsScore">80%</div>
        <h4 class="results-status" id="resultsStatus">Félicitations, vous avez réussi !</h4>
        
        <div class="results-table-container">
          <table class="table-results">
            <thead>
              <tr>
                <th>Question</th>
                <th>Points obtenus</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody id="resultsTableBody">
              <!-- Result rows inserted here -->
            </tbody>
          </table>
        </div>
        
        <button type="button" class="btn btn-primary px-5 py-3" id="btnRestartQuiz" style="border-radius: 30px; background-color: var(--primary-dark); border: none;">
          <i class="bi bi-arrow-clockwise me-1"></i> Recommencer
        </button>
      </div>

      <!-- Footer Buttons -->
      <div class="player-footer" id="playerFooter">
        <button type="button" class="btn btn-outline-secondary px-4 py-2" id="btnPrevQuestion" style="border-radius: var(--radius-sm);">
          <i class="bi bi-chevron-left me-1"></i> Précédent
        </button>
        <button type="button" class="btn btn-primary px-4 py-2" id="btnNextQuestion" style="border-radius: var(--radius-sm); background-color: var(--primary); border: none;">
          Suivant <i class="bi bi-chevron-right ms-1"></i>
        </button>
      </div>

    </div>
  </div>

  <!-- jQuery & SortableJS -->
  <script src="{{ asset('plugins/jquery/jquery-3.7.1.min.js') }}"></script>
  <script src="{{ asset('plugins/sortablejs/Sortable.min.js') }}"></script>
  
  <script>
    (function($) {
      $(function() {
        // Load questions and configurations from Blade
        var questions = @json($exam->questions);
        var examDuration = parseInt("{{ $exam->duration }}") * 60; // seconds
        var passingScore = {{ $exam->passing_score ?? 50 }};
        var noteMax = {{ $exam->note_max ?? 20 }};
        var currentSlideIndex = 0;
        var userAnswers = {}; // key: questionId, value: user response data
        var sortableInstances = [];
        var timerVal = examDuration;
        var timerInterval = null;

        // Helper to dynamically synchronize heights of matching terms and definitions
        function alignMatchingHeights(qId) {
          var $matchList = $('#matchList_' + qId);
          if (!$matchList.length) return;
          var leftItems = $matchList.parent().find('.left-col .matching-item');
          var rightItems = $matchList.find('.matching-item');
          
          // Reset inline heights first to recalculate accurately
          leftItems.css('height', 'auto');
          rightItems.css('height', 'auto');
          
          leftItems.each(function(index) {
            var $left = $(this);
            var $right = rightItems.eq(index);
            if ($right.length) {
              var h = Math.max($left.outerHeight(), $right.outerHeight());
              $left.css('height', h + 'px');
              $right.css('height', h + 'px');
            }
          });
        }

        $(window).on('resize', function() {
          var q = questions[currentSlideIndex];
          if (q && q.type === 'matching') {
            alignMatchingHeights(q.id);
          }
        });

        var $slidesContainer = $('#slidesContainer');
        var $progressWrapper = $('#playerProgressWrapper');
        var $footer = $('#playerFooter');
        var $resultsSlide = $('#resultsSlide');
        var $btnPrev = $('#btnPrevQuestion');
        var $btnNext = $('#btnNextQuestion');

        // Check if there are any questions
        if (!questions || questions.length === 0) {
          $progressWrapper.hide();
          $footer.hide();
          $slidesContainer.html('<div class="text-center py-5 my-auto text-muted">' +
            '<i class="bi bi-emoji-frown display-1 d-block mb-3"></i>' +
            '<h4>Aucune question disponible</h4>' +
            '<p class="mb-0">Cet examen ne contient aucune question pour le moment.</p>' +
            '</div>');
          return;
        }

        // Shuffle arrays for matching/ordering preview layout
        function shuffle(array) {
          var arr = array.slice();
          for (var i = arr.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var temp = arr[i];
            arr[i] = arr[j];
            arr[j] = temp;
          }
          return arr;
        }

        // Timer controller
        function startTimer() {
          clearInterval(timerInterval);
          updateTimerDisplay();
          timerInterval = setInterval(function() {
            if (timerVal <= 0) {
              clearInterval(timerInterval);
              showResults();
            } else {
              timerVal--;
              updateTimerDisplay();
            }
          }, 1000);
        }

        function updateTimerDisplay() {
          var m = Math.floor(timerVal / 60);
          var s = timerVal % 60;
          $('#timer-clock').text((m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s);
        }

        // Render questions
        function renderExam() {
          $slidesContainer.empty();
          sortableInstances = [];
          
          questions.forEach(function(q, idx) {
            var $slide = $('<div class="question-slide" data-index="' + idx + '" data-id="' + q.id + '"></div>');
            
            // Header Info
            var headerHtml = '<div class="question-header">' +
              '<span class="question-points">' + q.points + ' pts</span>' +
              '<h3 class="question-title">' + q.question_text + '</h3>' +
              '</div>';
            $slide.append(headerHtml);

            var options = q.options || {};
            var $optionsContainer = $('<div class="options-container flex-grow-1"></div>');

            // Render based on Question Type
            if (q.type === 'true_false') {
              var activeTrue = userAnswers[q.id] === 'true' ? 'active' : '';
              var activeFalse = userAnswers[q.id] === 'false' ? 'active' : '';
              
              var tfHtml = '<div class="tf-grid">' +
                '<div class="tf-card tf-true ' + activeTrue + '" data-val="true">' +
                  '<i class="bi bi-check-circle"></i>' +
                  '<span>Vrai</span>' +
                '</div>' +
                '<div class="tf-card tf-false ' + activeFalse + '" data-val="false">' +
                  '<i class="bi bi-x-circle"></i>' +
                  '<span>Faux</span>' +
                '</div>' +
                '</div>';
              $optionsContainer.append(tfHtml);
            } 
            else if (q.type === 'mcq') {
              var listHtml = '<div class="options-list">';
              var isMultiple = options.multiple || false;
              var answers = options.answers || options.choices || [];
              
              answers.forEach(function(ans, ansIdx) {
                var inputType = isMultiple ? 'checkbox' : 'radio';
                var selected = false;
                if (isMultiple) {
                  selected = (userAnswers[q.id] || []).indexOf(ans.text) !== -1;
                } else {
                  selected = userAnswers[q.id] === ans.text;
                }
                var activeClass = selected ? 'active' : '';
                var checkedAttr = selected ? 'checked' : '';

                listHtml += '<label class="mcq-option ' + activeClass + '" data-index="' + ansIdx + '">' +
                  '<input type="' + inputType + '" name="mcq_' + q.id + '" class="mcq-check-input" value="' + ans.text + '" ' + checkedAttr + '>' +
                  '<span class="mcq-text">' + ans.text + '</span>' +
                  '</label>';
              });
              listHtml += '</div>';
              $optionsContainer.append(listHtml);
            } 
            else if (q.type === 'fill_blank') {
              var format = options.format || q.question_text;
              var blankCounter = 0;
              
              // We replace [blank] or [[any_text]] placeholders with inputs
              var parsedText = format;
              if (parsedText.indexOf('[[') !== -1) {
                parsedText = parsedText.replace(/\[\[(.*?)\]\]/g, function() {
                  var val = (userAnswers[q.id] || [])[blankCounter] || '';
                  var inputHtml = '<input type="text" class="fb-input" data-blank-index="' + blankCounter + '" value="' + val + '" placeholder="...">';
                  blankCounter++;
                  return inputHtml;
                });
              } else {
                parsedText = parsedText.replace(/\[blank\]/g, function() {
                  var val = (userAnswers[q.id] || [])[blankCounter] || '';
                  var inputHtml = '<input type="text" class="fb-input" data-blank-index="' + blankCounter + '" value="' + val + '" placeholder="...">';
                  blankCounter++;
                  return inputHtml;
                });
              }

              var fbHtml = '<div class="fill-blank-text">' + parsedText + '</div>';
              $optionsContainer.append(fbHtml);
            } 
            else if (q.type === 'matching') {
              var pairs = options.pairs || [];
              var matchedAnswers = userAnswers[q.id] || null;
              
              // Shuffle definitions if we haven't stored order yet
              if (!matchedAnswers) {
                var shuffledDefinitions = shuffle(pairs.map(p => p.definition || p.right));
                matchedAnswers = {
                  terms: pairs.map(p => p.term || p.left),
                  definitions: shuffledDefinitions
                };
                userAnswers[q.id] = matchedAnswers;
              }

              var gridHtml = '<div class="matching-grid">' +
                '<div class="matching-col left-col">';
              
              matchedAnswers.terms.forEach(function(term) {
                gridHtml += '<div class="matching-item">' + term + '</div>';
              });
              
              gridHtml += '</div>' +
                '<div class="matching-col right-col sortable-matching-list" id="matchList_' + q.id + '">';
              
              matchedAnswers.definitions.forEach(function(def) {
                gridHtml += '<div class="matching-item draggable" data-def="' + def + '">' +
                  '<i class="bi bi-grid-3x2-gap-fill me-2 text-muted"></i>' + def +
                  '</div>';
              });
              
              gridHtml += '</div>' +
                '</div>';
                
              $optionsContainer.append(gridHtml);
            } 
            else if (q.type === 'ordering') {
              var items = options.items || [];
              var orderedItems = userAnswers[q.id] || null;
              
              // Shuffle items if not set yet
              if (!orderedItems) {
                orderedItems = shuffle(items);
                userAnswers[q.id] = orderedItems;
              }

              var listHtml = '<div class="ordering-list sortable-ordering-list" id="orderList_' + q.id + '">';
              
              orderedItems.forEach(function(item, itemIdx) {
                listHtml += '<div class="ordering-item" data-item="' + item + '">' +
                  '<span class="ordering-item-badge">' + (itemIdx + 1) + '</span>' +
                  '<i class="bi bi-grid-3x2-gap-fill text-muted me-2"></i>' + item +
                  '</div>';
              });
              
              listHtml += '</div>';
              $optionsContainer.append(listHtml);
            } 
            else if (q.type === 'open_text') {
              var savedText = userAnswers[q.id] || '';
              var otHtml = '<textarea class="form-control free-text-area w-100 flex-grow-1" rows="5" placeholder="Saisissez votre réponse ici..." name="ot_' + q.id + '">' + savedText + '</textarea>';
              $optionsContainer.append(otHtml);
            }

            $slide.append($optionsContainer);
            $slidesContainer.append($slide);
          });

          // Show current slide
          showSlide(currentSlideIndex);
          startTimer();
        }

        // Display Slide
        function showSlide(index) {
          $('.question-slide').removeClass('active');
          var $currSlide = $('.question-slide[data-index="' + index + '"]');
          $currSlide.addClass('active');

          // Progress indicator
          var total = questions.length;
          var percent = ((index + 1) / total) * 100;
          $('#playerProgressBar').css('width', percent + '%');
          $('#playerQuestionIndex').text('Question ' + (index + 1) + ' sur ' + total);
          $('#playerPointsVal').text(questions[index].points + ' pts');

          // Footer buttons
          if (index === 0) {
            $btnPrev.prop('disabled', true);
          } else {
            $btnPrev.prop('disabled', false);
          }

          if (index === total - 1) {
            $btnNext.html('Terminer <i class="bi bi-check-lg ms-1"></i>');
            $btnNext.removeClass('btn-primary').addClass('btn-success');
          } else {
            $btnNext.html('Suivant <i class="bi bi-chevron-right ms-1"></i>');
            $btnNext.removeClass('btn-success').addClass('btn-primary');
          }

          // Initialize sortables for current slide
          var q = questions[index];
          if (q.type === 'matching') {
            var $el = $('#matchList_' + q.id);
            if ($el.length && !$el.data('sortable-init')) {
              var s = new Sortable($el[0], {
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function() {
                  // Save matched answers state
                  var currentMatched = [];
                  $el.find('.matching-item').each(function() {
                    currentMatched.push($(this).data('def'));
                  });
                  userAnswers[q.id].definitions = currentMatched;
                  // Re-align heights because the order of definitions changed
                  alignMatchingHeights(q.id);
                }
              });
              $el.data('sortable-init', true);
            }
            // Align heights on slide show
            setTimeout(function() {
              alignMatchingHeights(q.id);
            }, 50);
          } 

          else if (q.type === 'ordering') {
            var $el = $('#orderList_' + q.id);
            if ($el.length && !$el.data('sortable-init')) {
              var s = new Sortable($el[0], {
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function() {
                  // Save items ordering state
                  var currentOrdered = [];
                  $el.find('.ordering-item').each(function() {
                    currentOrdered.push($(this).data('item'));
                  });
                  userAnswers[q.id] = currentOrdered;
                  
                  // Update badges
                  $el.find('.ordering-item').each(function(badgeIdx) {
                    $(this).find('.ordering-item-badge').text(badgeIdx + 1);
                  });
                }
              });
              $el.data('sortable-init', true);
            }
          }
        }

        // Navigate slide
        function navigate(direction) {
          saveCurrentSlideAnswer();

          if (direction === 'next') {
            if (currentSlideIndex < questions.length - 1) {
              currentSlideIndex++;
              showSlide(currentSlideIndex);
            } else {
              // Submit and calculate results
              showResults();
            }
          } else if (direction === 'prev') {
            if (currentSlideIndex > 0) {
              currentSlideIndex--;
              showSlide(currentSlideIndex);
            }
          }
        }

        // Save current inputs to local userAnswers state
        function saveCurrentSlideAnswer() {
          var q = questions[currentSlideIndex];
          var $slide = $('.question-slide[data-index="' + currentSlideIndex + '"]');

          if (q.type === 'true_false') {
            var val = $slide.find('.tf-card.active').data('val');
            userAnswers[q.id] = val || null;
          } 
          else if (q.type === 'mcq') {
            var isMultiple = q.options.multiple || false;
            if (isMultiple) {
              var checkedVals = [];
              $slide.find('.mcq-check-input:checked').each(function() {
                checkedVals.push($(this).val());
              });
              userAnswers[q.id] = checkedVals;
            } else {
              var checkedVal = $slide.find('.mcq-check-input:checked').val();
              userAnswers[q.id] = checkedVal || null;
            }
          } 
          else if (q.type === 'fill_blank') {
            var inputVals = [];
            $slide.find('.fb-input').each(function() {
              inputVals.push($(this).val().trim());
            });
            userAnswers[q.id] = inputVals;
          } 
          else if (q.type === 'open_text') {
            userAnswers[q.id] = $slide.find('.free-text-area').val();
          }
          // Matching and Ordering states are already saved dynamically on Sortable dragging
        }

        // Calculate results
        function showResults() {
          clearInterval(timerInterval);
          saveCurrentSlideAnswer();

          var totalPoints = 0;
          var scoredPoints = 0;
          var details = [];

          questions.forEach(function(q) {
            totalPoints += q.points;
            var options = q.options || {};
            var earned = 0;
            var isCorrect = false;
            var pointsNegatifs = parseFloat(q.points_negatifs || 0);

            if (q.type === 'true_false') {
              var correct = options.correct_answer === 'true';
              var userAns = userAnswers[q.id] === 'true';
              if (userAnswers[q.id] !== undefined && userAnswers[q.id] !== null) {
                if (correct === userAns) {
                  earned = q.points;
                  isCorrect = true;
                } else {
                  earned = -pointsNegatifs;
                }
              } else {
                earned = -pointsNegatifs;
              }
            } 
            else if (q.type === 'mcq') {
              var isMultiple = options.multiple || false;
              var answers = options.answers || options.choices || [];
              var correctAnswers = answers.filter(a => a.is_correct === true || a.is_correct === 'true').map(a => a.text);
              
              if (isMultiple) {
                var userAns = userAnswers[q.id] || [];
                // Compare exact sets
                var matches = userAns.filter(v => correctAnswers.indexOf(v) !== -1).length;
                var incorrect = userAns.filter(v => correctAnswers.indexOf(v) === -1).length;
                
                if (options.partial_score) {
                  if (correctAnswers.length > 0 && incorrect === 0) {
                    earned = Math.round((matches / correctAnswers.length) * q.points);
                  } else {
                    earned = -pointsNegatifs;
                  }
                  if (earned === q.points) {
                    isCorrect = true;
                  }
                } else {
                  if (matches === correctAnswers.length && incorrect === 0 && userAns.length === correctAnswers.length) {
                    earned = q.points;
                    isCorrect = true;
                  } else {
                    earned = -pointsNegatifs;
                  }
                }
              } else {
                var userAns = userAnswers[q.id] || '';
                if (correctAnswers.indexOf(userAns) !== -1) {
                  earned = q.points;
                  isCorrect = true;
                } else {
                  earned = -pointsNegatifs;
                }
              }
            } 
            else if (q.type === 'fill_blank') {
              var blanks = options.blanks || [];
              var userAns = userAnswers[q.id] || [];
              var correctCount = 0;

              blanks.forEach(function(blank, bIdx) {
                var uText = (userAns[bIdx] || '').trim();
                var answers = blank.answers || [];
                var match = false;
                
                answers.forEach(function(ans) {
                  if (blank.case_sensitive) {
                    if (ans === uText) match = true;
                  } else {
                    if (ans.toLowerCase() === uText.toLowerCase()) match = true;
                  }
                });

                if (match) correctCount++;
              });

              if (blanks.length > 0) {
                earned = Math.round((correctCount / blanks.length) * q.points);
                if (correctCount === blanks.length) {
                  isCorrect = true;
                }
              }
              if (earned === 0) {
                earned = -pointsNegatifs;
              }
            } 
            else if (q.type === 'matching') {
              var pairs = options.pairs || [];
              var userAns = userAnswers[q.id] || { terms: [], definitions: [] };
              var matchCount = 0;

              // Check if each term is aligned with its correct definition
              userAns.terms.forEach(function(term, idx) {
                var userDef = userAns.definitions[idx];
                var originalPair = pairs.find(p => (p.term || p.left) === term);
                var originalDef = originalPair ? (originalPair.definition || originalPair.right) : null;
                if (originalDef === userDef) {
                  matchCount++;
                }
              });

              if (pairs.length > 0) {
                earned = Math.round((matchCount / pairs.length) * q.points);
                if (matchCount === pairs.length) {
                  isCorrect = true;
                }
              }
              if (earned === 0) {
                earned = -pointsNegatifs;
              }
            } 
            else if (q.type === 'ordering') {
              var items = options.items || [];
              var userAns = userAnswers[q.id] || [];
              var correctCount = 0;

              // Compare indices
              userAns.forEach(function(item, idx) {
                if (items[idx] === item) {
                  correctCount++;
                }
              });

              if (items.length > 0) {
                earned = Math.round((correctCount / items.length) * q.points);
                if (correctCount === items.length) {
                  isCorrect = true;
                }
              }
              if (earned === 0) {
                earned = -pointsNegatifs;
              }
            } 
            else if (q.type === 'open_text') {
              var userAns = (userAnswers[q.id] || '').trim();
              if (userAns.length > 0) {
                earned = q.points; // Give full points in preview mock
                isCorrect = true;
              } else {
                earned = -pointsNegatifs;
              }
            }

            scoredPoints += earned;
            details.push({
              text: q.question_text,
              earned: earned,
              max: q.points,
              isCorrect: isCorrect,
              type: q.type
            });
          });

          // Capped scored points to minimum of 0
          scoredPoints = Math.max(0, scoredPoints);

          // Compute score percentages
          var scorePercent = totalPoints > 0 ? Math.round((scoredPoints / totalPoints) * 100) : 100;
          var finalNote = Math.round((scorePercent / 100) * noteMax * 10) / 10;
          var passed = scorePercent >= passingScore;

          // Display screen
          $progressWrapper.hide();
          $slidesContainer.hide();
          $footer.hide();

          $('#resultsScore').text(finalNote + ' / ' + noteMax);
          if (passed) {
            $('#resultsIcon').text('🎉');
            $('#resultsStatus').text('Félicitations, vous avez réussi ! (' + scoredPoints + '/' + totalPoints + ' pts)').css('color', 'var(--success-dark)');
          } else {
            $('#resultsIcon').text('⚠️');
            $('#resultsStatus').text('Vous n\'avez pas atteint le score de validation. (' + scoredPoints + '/' + totalPoints + ' pts)').css('color', 'var(--danger-dark)');
          }

          // Build details table rows
          var $tableBody = $('#resultsTableBody');
          $tableBody.empty();
          details.forEach(function(det) {
            var statusBadge = '';
            if (det.type === 'open_text') {
              statusBadge = '<span class="badge bg-info">Réponse libre</span>';
            } else if (det.isCorrect) {
              statusBadge = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Correct</span>';
            } else if (det.earned > 0) {
              statusBadge = '<span class="badge bg-warning"><i class="bi bi-exclamation-circle me-1"></i> Partiel</span>';
            } else if (det.earned < 0) {
              statusBadge = '<span class="badge bg-danger"><i class="bi bi-shield-exclamation me-1"></i> Pénalité (' + det.earned + ' pts)</span>';
            } else {
              statusBadge = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> Incorrect</span>';
            }

            var rowHtml = '<tr>' +
              '<td>' + det.text + '</td>' +
              '<td>' + (det.earned > 0 ? det.earned : 0) + ' / ' + det.max + '</td>' +
              '<td>' + statusBadge + '</td>' +
              '</tr>';
            $tableBody.append(rowHtml);
          });

          $resultsSlide.css('display', 'flex');
        }

        // Restart Quiz Action
        $('#btnRestartQuiz').on('click', function() {
          currentSlideIndex = 0;
          userAnswers = {};
          timerVal = examDuration;
          $resultsSlide.hide();
          $progressWrapper.show();
          $slidesContainer.show();
          $footer.show();
          renderExam();
        });

        // Event triggers for True/False Selection Cards
        $slidesContainer.on('click', '.tf-card', function() {
          $(this).closest('.tf-grid').find('.tf-card').removeClass('active');
          $(this).addClass('active');
        });

        // Event triggers for MCQ Option Cards
        $slidesContainer.on('click', '.mcq-option', function(e) {
          var $input = $(this).find('.mcq-check-input');
          if (e.target !== $input[0]) {
            $input.prop('checked', !$input.prop('checked'));
            $input.trigger('change');
          }
        });
        
        $slidesContainer.on('change', '.mcq-check-input', function() {
          var isCheckbox = $(this).attr('type') === 'checkbox';
          var $parent = $(this).closest('.mcq-option');
          if (isCheckbox) {
            $parent.toggleClass('active', $(this).is(':checked'));
          } else {
            $(this).closest('.options-list').find('.mcq-option').removeClass('active');
            $parent.addClass('active', $(this).is(':checked'));
          }
        });

        // Setup button handlers
        $btnPrev.on('click', function() { navigate('prev'); });
        $btnNext.on('click', function() { navigate('next'); });

        // Build Exam
        renderExam();

      });
    })(jQuery);
  </script>
</body>
</html>
