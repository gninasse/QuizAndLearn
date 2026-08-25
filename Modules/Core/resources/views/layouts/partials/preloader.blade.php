{{-- Preloader « rideau » : deux pans qui s'ouvrent une fois la page chargée. --}}
<div id="curtain-preloader" aria-hidden="true">
  <div class="curtain-panel curtain-left"></div>
  <div class="curtain-panel curtain-right"></div>
  <div class="curtain-brand">
    <span class="curtain-logo">🎓</span>
    <span class="curtain-title">Learn<b>&</b>Quiz</span>
    <span class="curtain-bar"><span class="curtain-bar-fill"></span></span>
  </div>
</div>

<style>
  #curtain-preloader {
    position: fixed;
    inset: 0;
    z-index: 20000;
    pointer-events: all;
  }
  #curtain-preloader .curtain-panel {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 50.5%; /* léger chevauchement : aucune fuite au centre */
    background: linear-gradient(160deg, #0f172a 0%, #1e3a5f 55%, #0c4a6e 100%);
    transition: transform 0.65s cubic-bezier(0.77, 0, 0.18, 1);
  }
  #curtain-preloader .curtain-left  { left: 0;  transform-origin: left; }
  #curtain-preloader .curtain-right { right: 0; transform-origin: right; }

  #curtain-preloader .curtain-brand {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.9rem;
    color: #fff;
    transition: opacity 0.25s ease;
  }
  #curtain-preloader .curtain-logo { font-size: 2.6rem; }
  #curtain-preloader .curtain-title {
    font-size: 1.25rem;
    font-weight: 800;
    letter-spacing: 0.02em;
  }
  #curtain-preloader .curtain-title b { color: #38bdf8; }
  #curtain-preloader .curtain-bar {
    width: 10rem;
    height: 3px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    overflow: hidden;
  }
  #curtain-preloader .curtain-bar-fill {
    display: block;
    height: 100%;
    width: 40%;
    border-radius: 999px;
    background: #38bdf8;
    animation: curtain-sweep 1s ease-in-out infinite;
  }
  @keyframes curtain-sweep {
    0%   { transform: translateX(-100%); }
    100% { transform: translateX(260%); }
  }

  /* Ouverture du rideau */
  #curtain-preloader.is-open { pointer-events: none; }
  #curtain-preloader.is-open .curtain-brand { opacity: 0; }
  #curtain-preloader.is-open .curtain-left  { transform: translateX(-101%); }
  #curtain-preloader.is-open .curtain-right { transform: translateX(101%); }

  @media (prefers-reduced-motion: reduce) {
    #curtain-preloader .curtain-panel { transition: opacity 0.2s ease; }
    #curtain-preloader.is-open .curtain-panel { opacity: 0; transform: none; }
    #curtain-preloader .curtain-bar-fill { animation: none; width: 100%; }
  }
</style>

<script>
  (function () {
    var preloader = document.getElementById('curtain-preloader');
    if (!preloader) return;

    var MIN_DISPLAY_MS = 350;  /* le rideau reste perceptible sans agacer */
    var FAILSAFE_MS = 4000;    /* ne bloque jamais la page */
    var shownAt = Date.now();
    var opened = false;

    function open() {
      if (opened) return;
      opened = true;
      var elapsed = Date.now() - shownAt;
      setTimeout(function () {
        preloader.classList.add('is-open');
        setTimeout(function () { preloader.remove(); }, 750);
      }, Math.max(0, MIN_DISPLAY_MS - elapsed));
    }

    if (document.readyState === 'complete') {
      open();
    } else {
      window.addEventListener('load', open);
    }
    setTimeout(open, FAILSAFE_MS);
  })();
</script>
