// public/assets/js/screen/risultati.js
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;

  function renderClassifica(lista) {
    const listEl = document.getElementById('scoreboard-list');
    if (!listEl) {
      console.warn('[screen/risultati] DOM missing: scoreboard-list');
      return;
    }

    if (!Array.isArray(lista) || lista.length === 0) {
      listEl.innerHTML = '<div class="scoreboard-empty">Nessun giocatore in classifica.</div>';
      return;
    }

    const ordinata = [...lista].sort(
      (a, b) => Number(b.capitale_attuale ?? 0) - Number(a.capitale_attuale ?? 0)
    );

    listEl.innerHTML = ordinata
      .map((p, index) => {
        const nome = p.nome || 'Giocatore';
        const punti = Number(p.capitale_attuale ?? 0);
        return `
          <div class="scoreboard-item">
            <div class="score-rank">#${index + 1}</div>
            <div>${nome}</div>
            <div class="score-points">${punti}</div>
          </div>
        `;
      })
      .join('');
  }

  ScreenApp.risultati = { renderClassifica };
})();