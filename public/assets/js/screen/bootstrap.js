// public/assets/js/screen/bootstrap.js
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;

  const STATO_POLL_MS = 1000;
  const MEDIA_POLL_MS = 10000;

  let pollStato = null;
  let pollMedia = null;
  let currentState = null;

  function log(...args) {
    console.log('[screen/bootstrap]', ...args);
  }

  function ensureDomOnce() {
    const ids = [
      'screen-placeholder',
      'screen-domanda',
      'screen-risultati',
      'domanda-testo',
      'opzioni',
      'scoreboard-list',
      'placeholder-message',
      'state-image',
      'sessione-qr',
    ];
    const missing = ids.filter((id) => !document.getElementById(id));
    if (missing.length) console.warn('[screen/bootstrap] DOM missing:', missing);
  }

  async function tickStato() {
    const api = ScreenApp.api;
    if (!api) return;

    const sessioneId = api.sessioneId;
    if (!sessioneId || sessioneId <= 0) return;

    try {
      const statoData = await api.fetchJson(`${api.apiBase}/stato/${sessioneId}`);

      if (!statoData.success || !statoData.sessione) return;

      const stato = String(statoData.sessione.stato || '');

      if (stato !== currentState) {
        currentState = stato;
        log('stato =>', stato);

        // se esci da domanda: pulisci UI domanda
        if (stato !== 'domanda') {
          ScreenApp.domanda?.clear?.();
        }
      }

      ScreenApp.state?.onState?.(stato);

      // domanda
      if (stato === 'domanda') {
        if (!ScreenApp.domanda?.isRendered?.()) {
          ScreenApp.domanda?.renderLoading?.();
        }

        const domandaData = await api.fetchJson(`${api.apiBase}/domanda/${sessioneId}`);
        if (currentState !== 'domanda') return;

        if (domandaData.success) {
          ScreenApp.domanda?.render?.(domandaData.domanda);
        }
        return;
      }

      // risultati / conclusa
      if (stato === 'risultati' || stato === 'conclusa') {
        const classData = await api.fetchJson(`${api.apiBase}/classifica/${sessioneId}`);
        const lista = classData.success && Array.isArray(classData.classifica) ? classData.classifica : [];
        ScreenApp.risultati?.renderClassifica?.(lista);
        return;
      }

      // altri stati -> placeholder già gestito in state.js
    } catch (e) {
      console.error('[screen/bootstrap] tickStato error:', e);
    }
  }

  async function tickMedia() {
    const api = ScreenApp.api;
    if (!api) return;

    try {
      const mediaData = await api.fetchJson(`${api.apiBase}/mediaAttiva`);

      if (!mediaData.success) return;

      ScreenApp.state?.setMedia?.(mediaData.media || null);

      // se non siamo in domanda/risultati, aggiorna immagine placeholder
      if (currentState !== 'domanda' && currentState !== 'risultati') {
        ScreenApp.state?.renderPlaceholder?.(currentState);
      }
    } catch (e) {
      console.error('[screen/bootstrap] tickMedia error:', e);
    }
  }

  ScreenApp.bootstrap = {
    start() {
      ensureDomOnce();

      const api = ScreenApp.api;
      log('start', { sessioneId: api?.sessioneId, apiBase: api?.apiBase });

      ScreenApp.state?.setupQrJoin?.();

      if (pollStato) clearInterval(pollStato);
      if (pollMedia) clearInterval(pollMedia);

      tickMedia();
      tickStato();

      pollStato = setInterval(tickStato, STATO_POLL_MS);
      pollMedia = setInterval(tickMedia, MEDIA_POLL_MS);
    },
    stop() {
      if (pollStato) clearInterval(pollStato);
      if (pollMedia) clearInterval(pollMedia);
      pollStato = null;
      pollMedia = null;
    },
  };
})();