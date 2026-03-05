/* public/assets/js/screen/placeholder.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const { state } = window.ScreenApp;

  function getStateMeta(st) {
    if (st === 'classifica') return { message: 'Classifica in aggiornamento...' };
    if (st === 'risultati') return { message: 'Risultati del round' };
    if (st === 'conclusa' || st === 'fine') return { message: 'Quiz terminato' };
    return { message: 'In attesa della prossima domanda...' };
  }

  function renderStateImage(st) {
    if (st === 'risultati') return;

    const img = document.getElementById('state-image');
    const message = document.getElementById('placeholder-message');
    if (!img || !message) return;

    const meta = getStateMeta(st);
    message.innerText = meta.message;

    if (state.mediaAttiva && state.mediaAttiva.file_path) {
      const mediaPath = state.mediaAttiva.file_path.startsWith('/')
        ? state.mediaAttiva.file_path.substring(1)
        : state.mediaAttiva.file_path;

      img.src = `${window.location.origin}${state.BASE_PUBLIC_URL}${mediaPath}`;
      img.alt = state.mediaAttiva.titolo || `Immagine stato: ${meta.message}`;
      return;
    }

    img.removeAttribute('src');
    img.alt = `Immagine stato: ${meta.message}`;
  }

  window.ScreenApp.placeholder = { getStateMeta, renderStateImage };
})();