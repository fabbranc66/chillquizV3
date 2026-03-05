// public/assets/js/screen/state.js
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;

  let mediaAttiva = null;

  function warnMissing(ids) {
    const missing = ids.filter((id) => !document.getElementById(id));
    if (missing.length) console.warn('[screen/state] DOM missing:', missing);
  }

  function getStateMeta(state) {
    if (state === 'risultati') return { message: 'Risultati del round' };
    if (state === 'conclusa' || state === 'fine') return { message: 'Quiz terminato' };
    if (state === 'domanda') return { message: 'Domanda in corso...' };
    return { message: 'In attesa della prossima domanda...' };
  }

  function showOnly(which) {
    warnMissing(['screen-placeholder', 'screen-domanda', 'screen-risultati']);

    const ph = document.getElementById('screen-placeholder');
    const dm = document.getElementById('screen-domanda');
    const rs = document.getElementById('screen-risultati');

    if (!ph || !dm || !rs) return;

    ph.classList.toggle('hidden', which !== 'placeholder');
    dm.classList.toggle('hidden', which !== 'domanda');
    rs.classList.toggle('hidden', which !== 'risultati');
  }

  function renderPlaceholder(state) {
    const img = document.getElementById('state-image');
    const msg = document.getElementById('placeholder-message');

    if (!msg) {
      console.warn('[screen/state] DOM missing: placeholder-message');
      return;
    }

    const meta = getStateMeta(state);
    msg.innerText = meta.message;

    // se abbiamo media attiva, mettiamo l'immagine
    if (img) {
      if (mediaAttiva && mediaAttiva.file_path) {
        const basePublic = ScreenApp.api?.basePublicUrl || '/';
        const filePath = String(mediaAttiva.file_path || '');
        const clean = filePath.startsWith('/') ? filePath.substring(1) : filePath;
        img.src = window.location.origin + basePublic + clean;
        img.alt = mediaAttiva.titolo || meta.message;
      } else {
        img.removeAttribute('src');
        img.alt = meta.message;
      }
    }
  }

  function setupQrJoin() {
    const sessioneId = ScreenApp.api?.sessioneId || 0;
    if (!sessioneId) return;

    const qrImg = document.getElementById('sessione-qr');
    if (!qrImg) return;

    // join URL player (come avevi prima)
    const basePublic = ScreenApp.api?.basePublicUrl || '/';
    const joinUrl = `${window.location.origin}${basePublic}index.php?url=player`;

    qrImg.src =
      `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=` +
      encodeURIComponent(joinUrl);
  }

  function setMedia(media) {
    mediaAttiva = media || null;
  }

  function onState(state) {
    if (state === 'domanda') {
      showOnly('domanda');
      return;
    }

    if (state === 'risultati' || state === 'conclusa') {
      showOnly('risultati');
      return;
    }

    // attesa / puntata / ecc
    showOnly('placeholder');
    renderPlaceholder(state);
  }

  ScreenApp.state = {
    onState,
    setupQrJoin,
    setMedia,
    renderPlaceholder,
  };
})();