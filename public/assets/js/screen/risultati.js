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
    const parseScore = (value) => {
      if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
      }
      const normalized = String(value ?? '').trim().replace(/[^\d-]/g, '');
      if (normalized === '' || normalized === '-') return 0;
      const parsed = Number.parseInt(normalized, 10);
      return Number.isFinite(parsed) ? parsed : 0;
    };

    const totalPlayers = Array.isArray(lista) ? lista.length : 0;
    const isDesktop = !!(window.matchMedia && window.matchMedia('(min-width: 1024px)').matches);
    const columns = isDesktop
      ? (totalPlayers <= 8 ? 1 : (totalPlayers <= 16 ? 2 : (totalPlayers <= 24 ? 3 : 4)))
      : 1;

    const ranked = [...lista]
      .sort((a, b) => parseScore(b.capitale_attuale ?? 0) - parseScore(a.capitale_attuale ?? 0))
      .map((row, index) => ({ ...row, __rank: index + 1 }));

    if (columns <= 1 || ranked.length <= 1) {
      return { items: ranked, columns };
    }

    const rows = Math.ceil(ranked.length / columns);
    const arranged = [];

    for (let row = 0; row < rows; row += 1) {
      for (let col = 0; col < columns; col += 1) {
        const sourceIndex = (col * rows) + row;
        if (sourceIndex < ranked.length) {
          arranged.push(ranked[sourceIndex]);
        }
      }
    }

    return { items: arranged, columns };
  }

  function getCurrentQuestionNumber() {
    return Number(ScreenApp.store?.latestSessioneSnapshot?.domanda_corrente || 0);
  }

  function getTrendStorageKey(questionNumber) {
    const sessioneId = Number(ScreenApp.store?.sessioneId || 0);
    const domanda = Number(questionNumber || 0);
    if (sessioneId <= 0 || domanda <= 0) {
      return '';
    }
    return `chillquiz_screen_trend_${sessioneId}_${domanda}`;
  }

  function readTrendRankMap(questionNumber) {
    const key = getTrendStorageKey(questionNumber);
    if (!key) return null;
    try {
      const raw = window.localStorage.getItem(key);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return (parsed && typeof parsed === 'object') ? parsed : null;
    } catch (err) {
      return null;
    }
  }

  function writeTrendRankMap(questionNumber, rankMap) {
    const key = getTrendStorageKey(questionNumber);
    if (!key || !rankMap || typeof rankMap !== 'object') return;
    try {
      window.localStorage.setItem(key, JSON.stringify(rankMap));
    } catch (err) {
      // ignore storage errors
    }
  }

  function buildRankMapByParticipant(lista) {
    const rankMap = {};
    if (!Array.isArray(lista)) {
      return rankMap;
    }

    lista.forEach((row, index) => {
      const partecipazioneId = Number(row?.partecipazione_id || 0);
      const rank = Number(row?.__rank || (index + 1));
      if (partecipazioneId > 0 && rank > 0) {
        rankMap[partecipazioneId] = rank;
      }
    });

    return rankMap;
  }

  function resolveTrendBaseline(currentQuestionNumber) {
    const baselineQuestionNumber = Number(ScreenApp.store?.scoreboardTrendBaselineQuestionNumber || 0);
    const baselineMap = ScreenApp.store?.scoreboardTrendBaselineByParticipant || null;
    if (currentQuestionNumber <= 1) {
      return null;
    }

    if (baselineMap && typeof baselineMap === 'object' && baselineQuestionNumber === (currentQuestionNumber - 1)) {
      return baselineMap;
    }

    const fromStorage = readTrendRankMap(currentQuestionNumber - 1);
    if (fromStorage && typeof fromStorage === 'object') {
      return fromStorage;
    }

    if (baselineMap && typeof baselineMap === 'object' && baselineQuestionNumber > 0 && baselineQuestionNumber < currentQuestionNumber) {
      return baselineMap;
    }

    const lastQuestionNumber = Number(ScreenApp.store?.scoreboardLastQuestionNumber || 0);
    const lastMap = ScreenApp.store?.scoreboardLastRanksByParticipant || null;
    if (lastMap && typeof lastMap === 'object' && lastQuestionNumber > 0 && lastQuestionNumber < currentQuestionNumber) {
      return lastMap;
    }

    return null;
  }

  function resolveTrendData(row, baselineRankMap) {
    const defaultTrend = { kind: 'same', symbol: '', label: 'Posizione stabile' };
    const partecipazioneId = Number(row?.partecipazione_id || 0);
    const currentRank = Number(row?.__rank || 0);
    if (partecipazioneId <= 0 || currentRank <= 0 || !baselineRankMap) {
      return defaultTrend;
    }

    const previousRank = Number(baselineRankMap[partecipazioneId] || 0);
    if (previousRank <= 0) {
      return defaultTrend;
    }
    if (currentRank < previousRank) {
      return { kind: 'up', symbol: '▲', label: 'Posizione migliorata' };
    }
    if (currentRank > previousRank) {
      return { kind: 'down', symbol: '▼', label: 'Posizione peggiorata' };
    }

    return defaultTrend;
  }

  function updateTrendMemory(currentQuestionNumber, currentRankMap) {
    if (currentQuestionNumber <= 0 || !currentRankMap || typeof currentRankMap !== 'object') {
      return;
    }

    const lastQuestionNumber = Number(ScreenApp.store?.scoreboardLastQuestionNumber || 0);
    if (lastQuestionNumber > 0 && lastQuestionNumber !== currentQuestionNumber) {
      ScreenApp.store.scoreboardTrendBaselineByParticipant = ScreenApp.store.scoreboardLastRanksByParticipant || null;
      ScreenApp.store.scoreboardTrendBaselineQuestionNumber = lastQuestionNumber;
    }

    ScreenApp.store.scoreboardLastQuestionNumber = currentQuestionNumber;
    ScreenApp.store.scoreboardLastRanksByParticipant = currentRankMap;
    writeTrendRankMap(currentQuestionNumber, currentRankMap);
  }

  function applyScoreboardLayout(listEl, columns, totalPlayers) {
    if (!listEl) return;

    listEl.style.setProperty('--scoreboard-cols', String(Math.max(1, Number(columns || 1))));

    if (totalPlayers <= 32 && Number(columns || 1) > 0) {
      const viewportWidth = Math.max(320, window.innerWidth || document.documentElement.clientWidth || 0);
      const fullWrapWidth = Math.min(2400, Math.max(320, viewportWidth - 20));
      const colGap = 10;
      const colWidth = Math.max(180, (fullWrapWidth - (3 * colGap)) / 4);
      const maxWidth = (columns * colWidth) + ((columns - 1) * colGap);
      listEl.style.maxWidth = `${maxWidth}px`;
      listEl.style.marginInline = 'auto';
      return;
    }

    listEl.style.maxWidth = '';
    listEl.style.marginInline = '';
  }

  function formatThousands(value) {
    const toGroupedIt = (n) => {
      const sign = n < 0 ? '-' : '';
      const abs = Math.abs(Math.trunc(n));
      return `${sign}${String(abs).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
    };

    if (typeof value === 'number') {
      return Number.isFinite(value) ? toGroupedIt(value) : '0';
    }

    const normalized = String(value ?? '').trim().replace(/[^\d-]/g, '');
    if (normalized === '' || normalized === '-') return '0';
    const parsed = Number.parseInt(normalized, 10);
    if (!Number.isFinite(parsed)) return '0';
    return toGroupedIt(parsed);
  }

  function renderScoreboardItems(lista) {
    return lista.map((p, index) => {
      const nome = p.nome || 'Giocatore';
      const punti = p.capitale_attuale ?? 0;
      const rank = Number(p.__rank || (index + 1));
      const trend = p.__trend || { kind: 'same', symbol: '', label: 'Posizione stabile' };
      return `
        <div class="scoreboard-item">
          <div class="score-rank">#${rank}</div>
          <div class="score-name" title="${nome}">
            <span class="score-trend score-trend--${trend.kind}" aria-label="${trend.label}">${trend.symbol}</span>
            <span class="score-name-text">${nome}</span>
          </div>
          <div class="score-points">${formatThousands(punti)}</div>
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

    const ordered = getOrderedScoreboard(lista);
    const currentQuestionNumber = getCurrentQuestionNumber();
    const baselineRankMap = resolveTrendBaseline(currentQuestionNumber);
    const itemsWithTrend = ordered.items.map((row) => ({
      ...row,
      __trend: resolveTrendData(row, baselineRankMap),
    }));
    const currentRankMap = buildRankMapByParticipant(ordered.items);

    applyScoreboardLayout(listEl, ordered.columns, lista.length);
    listEl.innerHTML = renderScoreboardItems(itemsWithTrend);
    updateTrendMemory(currentQuestionNumber, currentRankMap);

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
