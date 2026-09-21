(function () {
  'use strict';

  var cfg = window.LATINFO_MOBILEAPP || {};
  var btn = document.getElementById('ma-install-btn');
  var statusEl = document.getElementById('ma-install-status');
  var deferredPrompt = null;

  function setStatus(text) {
    if (!statusEl) {
      return;
    }
    statusEl.hidden = !text;
    statusEl.textContent = text || '';
  }

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.navigator.standalone === true;
  }

  if (isStandalone()) {
    setStatus('Az app már telepítve van ezen a készüléken.');
  }

  if (btn) {
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredPrompt = e;
      btn.hidden = false;
    });

    btn.addEventListener('click', function () {
      if (!deferredPrompt) {
        setStatus('A telepítés ehhez a böngészőhöz a fenti lépésekkel érhető el.');
        return;
      }
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function (choice) {
        if (choice && choice.outcome === 'accepted') {
          setStatus('Telepítés elindítva – keresd az ikont a kezdőképernyőn.');
        } else {
          setStatus('A telepítés megszakadt. Bármikor újrapróbálhatod.');
        }
        deferredPrompt = null;
        btn.hidden = true;
      });
    });

    window.addEventListener('appinstalled', function () {
      setStatus('Kész! Az app telepítve van.');
      btn.hidden = true;
      deferredPrompt = null;
    });
  }

  if ('serviceWorker' in navigator && typeof cfg.swUrl === 'string' && cfg.swUrl !== '') {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(cfg.swUrl).catch(function () {
        /* SW opcionális – telepítés nélkül is használható az oldal */
      });
    });
  }
})();
