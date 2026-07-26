@extends('core::layouts.admin-editor')

@section('title', 'Aperçu du Quiz - ' . $quiz->title)

@section('editor-content')
<div class="preview-container">
  
  <!-- Simulator Header Toolbar -->
  <div class="simulator-toolbar d-flex align-items-center justify-content-between p-3 bg-white border-bottom shadow-sm">
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('cores.editor.quizzes.edit', $quiz->id) }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;">
        <i class="bi bi-arrow-left me-1"></i> Retour à l'éditeur
      </a>
      <button class="btn btn-sm btn-outline-dark" id="btnPrintQuiz" style="border-radius: 8px;">
        <i class="bi bi-print me-1"></i> Imprimer (A4)
      </button>
      <h5 class="mb-0 ms-2 fw-bold text-truncate" style="max-width: 250px; color: var(--green-dark);">Aperçu : {{ $quiz->title }}</h5>
    </div>
    
    <!-- Device Selectors -->
    <div class="device-selector-group p-1 bg-light d-flex gap-1" style="border-radius: 10px;">
      <button type="button" class="btn btn-sm btn-light device-btn active" data-device="laptop" style="border-radius: 8px; font-weight: 600; padding: 6px 12px;">
        <i class="bi bi-laptop me-1"></i> Laptop
      </button>
      <button type="button" class="btn btn-sm btn-light device-btn" data-device="tablet" style="border-radius: 8px; font-weight: 600; padding: 6px 12px;">
        <i class="bi bi-tablet-landscape me-1"></i> Tablette
      </button>
      <button type="button" class="btn btn-sm btn-light device-btn" data-device="mobile" style="border-radius: 8px; font-weight: 600; padding: 6px 12px;">
        <i class="bi bi-phone me-1"></i> Mobile
      </button>
    </div>
    
    <!-- Orientation and Screen Size Indicator -->
    <div class="d-flex align-items-center gap-3">
      <button type="button" class="btn btn-sm btn-outline-dark d-none d-md-inline-flex align-items-center gap-1" id="btnRotateOrientation" style="border-radius: 8px;" disabled>
        <i class="bi bi-arrow-repeat"></i> Rotation
      </button>
      <span class="badge bg-secondary py-2 px-3 fw-normal" id="screenSizeIndicator" style="border-radius: 6px; font-size: 0.85rem;">100% x 100%</span>
    </div>
  </div>
  
  <!-- Simulated Screen / Frame Area -->
  <div class="simulator-screen-area d-flex justify-content-center align-items-center p-4">
    <div class="device-wrapper laptop" id="deviceWrapper">
      <div class="device-bezel">
        <div class="device-camera"></div>
        <div class="device-content">
          <iframe src="{{ route('cores.editor.quizzes.preview-iframe', $quiz->id) }}" id="previewIframe" title="Quiz Player Preview"></iframe>
        </div>
        <div class="device-home-button"></div>
      </div>
    </div>
  </div>

</div>

<!-- Modal Print Quiz -->
<div class="modal fade" id="modal-print-quiz" tabindex="-1" aria-labelledby="modal-print-quiz-label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
      <div class="modal-header bg-light py-3">
        <h5 class="modal-title fw-bold" id="modal-print-quiz-label" style="color: #1e6f5c;">
          <i class="bi bi-printer-fill me-2"></i> Aperçu Avant Impression - Format A4
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 bg-light" style="height: 70vh;">
        <iframe id="print-iframe-loader" src="" style="width: 100%; height: 100%; border: none;"></iframe>
      </div>
      <div class="modal-footer bg-white border-top p-3 d-flex justify-content-between">
        <span class="text-muted small"><i class="bi bi-info-circle me-1"></i> La grille de correction est automatiquement générée et insérée sur une feuille séparée à la fin du document.</span>
        <div>
          <button type="button" class="btn btn-secondary px-4 py-2 me-2" data-bs-dismiss="modal" style="border-radius: 8px;">Annuler</button>
          <button type="button" class="btn btn-primary px-4 py-2" id="btn-print-confirm" style="background-color: #1e6f5c; border: none; border-radius: 8px; font-weight: 600; color: white;">
            <i class="bi bi-printer me-1"></i> Imprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* Modern Simulator Layout */
.preview-container {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 120px);
  background-color: #f4f6f9;
}

.simulator-toolbar {
  z-index: 10;
}

.simulator-screen-area {
  flex-grow: 1;
  overflow: auto;
  background-color: #e9ecef;
  background-image: radial-gradient(#ced4da 1px, transparent 1px);
  background-size: 20px 20px;
  position: relative;
  min-height: 550px;
}

/* Device Wrapper Transition & Styles */
.device-wrapper {
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
  max-width: 100%;
}

.device-bezel {
  width: 100%;
  height: 100%;
  position: relative;
  background-color: #ffffff;
  transition: all 0.4s ease;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.device-content {
  width: 100%;
  height: 100%;
  position: relative;
  overflow: hidden;
}

#previewIframe {
  width: 100%;
  height: 100%;
  border: 0;
  display: block;
}

/* Laptop State (Standard Full Container) */
.device-wrapper.laptop {
  width: 100%;
  height: 100%;
}
.device-wrapper.laptop .device-bezel {
  box-shadow: none;
  background: transparent;
}

/* Tablet State */
.device-wrapper.tablet {
  width: 768px;
  height: 1024px;
  max-height: 85%;
}
.device-wrapper.tablet.landscape {
  width: 1024px;
  height: 768px;
  max-height: 85%;
}
.device-wrapper.tablet .device-bezel {
  border: 24px solid #1a1a1a;
  border-radius: 36px;
  padding: 0;
}
/* Camera notch */
.device-wrapper.tablet .device-camera {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #333;
  z-index: 5;
}
.device-wrapper.tablet.landscape .device-camera {
  top: 50%;
  left: -12px;
  transform: translateY(-50%);
}

/* Mobile State */
.device-wrapper.mobile {
  width: 375px;
  height: 750px;
  max-height: 90%;
}
.device-wrapper.mobile.landscape {
  width: 750px;
  height: 375px;
  max-height: 90%;
}
.device-wrapper.mobile .device-bezel {
  border: 14px solid #18181b;
  border-radius: 40px;
  padding: 0;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}
/* Mobile Camera Speaker Notch */
.device-wrapper.mobile .device-camera {
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 110px;
  height: 25px;
  background-color: #18181b;
  border-bottom-left-radius: 16px;
  border-bottom-right-radius: 16px;
  z-index: 5;
}
.device-wrapper.mobile.landscape .device-camera {
  top: 50%;
  left: 0;
  transform: translateY(-50%) rotate(90deg);
  transform-origin: top left;
  margin-top: -55px;
}

/* Hover buttons on active device selector */
.device-btn.active {
  background-color: var(--green-dark) !important;
  color: white !important;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}
</style>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
  var $wrapper = $('#deviceWrapper');
  var $indicator = $('#screenSizeIndicator');
  var $rotateBtn = $('#btnRotateOrientation');
  
  // Update Size Badge
  function updateSizeIndicator() {
    var w = $wrapper.width();
    var h = $wrapper.height();
    $indicator.text(w + 'px × ' + h + 'px');
  }

  // Handle device switches
  $('.device-btn').on('click', function() {
    var device = $(this).data('device');
    
    // Toggle active state
    $('.device-btn').removeClass('active');
    $(this).addClass('active');
    
    // Apply layout wrapper class
    $wrapper.removeClass('laptop tablet mobile landscape');
    $wrapper.addClass(device);
    
    if (device === 'laptop') {
      $rotateBtn.prop('disabled', true);
    } else {
      $rotateBtn.prop('disabled', false);
    }
    
    // Give time for CSS transition
    setTimeout(updateSizeIndicator, 450);
  });

  // Handle rotation orientation
  $rotateBtn.on('click', function() {
    if (!$wrapper.hasClass('laptop')) {
      $wrapper.toggleClass('landscape');
      
      // Update icons inside rotation for tablet/mobile landscape modes
      if ($wrapper.hasClass('tablet')) {
        var $tabIcon = $('.device-btn[data-device="tablet"] i');
        if ($wrapper.hasClass('landscape')) {
          $tabIcon.removeClass('bi-tablet').addClass('bi-tablet-landscape');
        } else {
          $tabIcon.removeClass('bi-tablet-landscape').addClass('bi-tablet');
        }
      }
      if ($wrapper.hasClass('mobile')) {
        var $mobIcon = $('.device-btn[data-device="mobile"] i');
        if ($wrapper.hasClass('landscape')) {
          $mobIcon.removeClass('bi-phone').addClass('bi-phone-landscape');
        } else {
          $mobIcon.removeClass('bi-phone-landscape').addClass('bi-phone');
        }
      }
      
      setTimeout(updateSizeIndicator, 450);
    }
  });

  // Print
  $('#btnPrintQuiz').click(function () {
    const printUrl = "{{ route('cores.editor.quizzes.print-iframe', $quiz->id) }}";
    $('#print-iframe-loader').attr('src', printUrl);
    $('#modal-print-quiz').modal('show');
  });

  // Print Confirmation
  $('#btn-print-confirm').click(function () {
    document.getElementById('print-iframe-loader').contentWindow.print();
  });

  // Init size
  updateSizeIndicator();
  $(window).on('resize', function() {
    updateSizeIndicator();
  });
});
</script>
@endpush
