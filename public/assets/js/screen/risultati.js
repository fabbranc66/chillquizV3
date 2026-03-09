/* public/assets/js/screen/risultati.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;

  function clearMemeAlertTimer() {
    if (ScreenApp.store.memeAlertTimer) {
      window.clearTimeout(ScreenApp.store.memeAlertTimer);
      ScreenApp.store.memeAlertTimer = null;
    }
  }

  function getMemeAlertNodes() {
    return {
      wrap: document.getElementById('meme-alert-screen'),
      message: document.getElementById('meme-alert-message'),
    };
  }

  function clearMemeAlert() {
    clearMemeAlertTimer();
    const { wrap, message } = getMemeAlertNodes();
    if (message) {
      message.innerText = '';
    }
    if (wrap) {
      wrap.classList.add('hidden');
    }
  }

  function buildMemeAlertMessage(choosers) {
    const names = choosers.map((row) => String(row.nome || 'Giocatore')).filter(Boolean);
    const memeText = String(choosers[0]?.meme_text || choosers[0]?.risposta_data_testo || '').trim();
    const who = names.length === 1
      ? names[0]
      : `${names.slice(0, -1).join(', ')} e ${names[names.length - 1]}`;

    const templates = [
      `${who} hanno puntato tutto su "${memeText}". La scienza non approva, ma il pubblico si diverte.`,
      `${who} hanno scelto "${memeText}". Decisione tecnicamente discutibile, spiritualmente impeccabile.`,
      `${who} hanno visto "${memeText}" e hanno detto: si, questa e' la mia verita'.`,
      `${who} hanno creduto in "${memeText}". Nessun rimorso, solo caos controllato.`,
    ];

    const index = (names.join('|').length + memeText.length) % templates.length;
    return templates[index];
  }

  function buildMemeAlertKey(choosers) {
    return choosers
      .map((row) => `${row.nome || ''}:${row.risposta_data_testo || ''}:${row.tempo_risposta_display || ''}`)
      .join('|');
  }

  function getMemeChoosers(lista) {
    return Array.isArray(lista) ? lista.filter((row) => !!row?.is_meme_choice) : [];
  }

  function renderMemeAlert(lista) {
    const { wrap, message } = getMemeAlertNodes();
    if (!wrap || !message || !Array.isArray(lista)) {
      return;
    }

    const choosers = getMemeChoosers(lista);
    if (choosers.length === 0) {
      ScreenApp.store.lastMemeAlertKey = '';
      clearMemeAlert();
      return;
    }

    const alertKey = buildMemeAlertKey(choosers);

    if (ScreenApp.store.lastMemeAlertKey === alertKey) {
      return;
    }

    ScreenApp.store.lastMemeAlertKey = alertKey;
    message.innerText = buildMemeAlertMessage(choosers);
    wrap.classList.remove('hidden');
    clearMemeAlertTimer();
    ScreenApp.store.memeAlertTimer = window.setTimeout(() => {
      clearMemeAlert();
    }, 15000);
  }

  function getOrderedScoreboard(lista) {
    return [...lista]
      .sort((a, b) => Number(b.capitale_attuale ?? 0) - Number(a.capitale_attuale ?? 0))
      .slice(0, 10);
  }

  function renderScoreboardItems(lista) {
    return lista.map((p, index) => {
      const nome = p.nome || 'Giocatore';
      const punti = Number(p.capitale_attuale ?? 0);
      return `
        <div class="scoreboard-item">
          <div class="score-rank">#${index + 1}</div>
          <div>${nome}</div>
          <div class="score-points">${punti}</div>
        </div>
      `;
    }).join('');
  }

  function renderClassifica(lista) {
    const listEl = document.getElementById('scoreboard-list');
    if (!listEl) return;

    if (!Array.isArray(lista) || lista.length === 0) {
      listEl.innerHTML = '<div class="scoreboard-empty">Nessun giocatore in classifica.</div>';
      clearMemeAlert();
      return;
    }

    listEl.innerHTML = renderScoreboardItems(getOrderedScoreboard(lista));

    renderMemeAlert(lista);
  }

  async function fetchClassifica() {
    if (!ScreenApp.api.sessioneId) return;

    try {
      const data = await ScreenApp.api.fetchJson(`${ScreenApp.api.apiBase}/classifica/${ScreenApp.api.sessioneId || 0}`);
      renderClassifica(data.success ? (data.classifica || []) : []);
    } catch (error) {
      console.error(error);
      renderClassifica([]);
    }
  }

  ScreenApp.risultati = { renderClassifica, fetchClassifica, clearMemeAlert };
})();
