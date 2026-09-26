(function () {
  'use strict';

  var cfg = window.LATINFO_MOBILEAPP || {};
  var btn = document.getElementById('ma-install-btn');
  var statusEl = document.getElementById('ma-install-status');
  var deferredPrompt = null;
  var trackUrl = typeof cfg.trackUrl === 'string' ? cfg.trackUrl : '';

  function setStatus(text) {
    if (!statusEl) {
      return;
    }
    statusEl.hidden = !text;
    statusEl.textContent = text || '';
  }

  function trackEvent(eventName) {
    if (!trackUrl || !eventName) {
      return;
    }
    try {
      var body = new FormData();
      body.append('event', eventName);
      if (navigator.sendBeacon) {
        navigator.sendBeacon(trackUrl, body);
        return;
      }
      fetch(trackUrl, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' })
        .catch(function () {});
    } catch (e) {
      /* tracking opcionális */
    }
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
      trackEvent('install_prompt');
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
          trackEvent('install_accepted');
        } else {
          setStatus('A telepítés megszakadt. Bármikor újrapróbálhatod.');
          trackEvent('install_dismissed');
        }
        deferredPrompt = null;
        btn.hidden = true;
      });
    });

    window.addEventListener('appinstalled', function () {
      setStatus('Kész! Az app telepítve van.');
      btn.hidden = true;
      deferredPrompt = null;
      trackEvent('app_installed');
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
