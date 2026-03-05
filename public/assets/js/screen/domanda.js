// public/assets/js/screen/domanda.js
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;

  let rendered = false;

  function clear() {
    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');
    if (titolo) titolo.innerText = '';
    if (opzioni) opzioni.innerHTML = '';
    rendered = false;
  }

  function renderLoading() {
    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');

    if (!titolo || !opzioni) {
      console.warn('[screen/domanda] DOM missing: domanda-testo/opzioni');
      return;
    }

    if (rendered) return;

    titolo.innerText = 'Caricamento domanda...';

    if (opzioni.children.length === 0) {
      opzioni.innerHTML = '';
      for (let i = 0; i < 4; i += 1) {
        const el = document.createElement('div');
        el.className = 'opzione';
        el.innerText = '...';
        opzioni.appendChild(el);
      }
    }
  }

  function render(domanda) {
    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');

    if (!titolo || !opzioni) {
      console.warn('[screen/domanda] DOM missing: domanda-testo/opzioni');
      return;
    }

    if (!domanda || !Array.isArray(domanda.opzioni)) {
      renderLoading();
      return;
    }

    titolo.innerText = domanda.testo || '';
    opzioni.innerHTML = '';

    domanda.opzioni.forEach((o) => {
      const el = document.createElement('div');
      el.className = 'opzione';
      el.innerText = o.testo || '';
      opzioni.appendChild(el);
    });

    rendered = true;
  }

  function isRendered() {
    return rendered;
  }

  ScreenApp.domanda = { render, renderLoading, clear, isRendered };
})();