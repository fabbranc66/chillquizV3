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

  function arrangeRankedItemsByColumns(ranked, columns) {
    if (!Array.isArray(ranked) || ranked.length <= 1 || Number(columns || 1) <= 1) {
      return Array.isArray(ranked) ? ranked : [];
    }

    const total = ranked.length;
    const cols = Math.max(1, Number(columns || 1));
    const baseLen = Math.floor(total / cols);
    const extra = total % cols;
    const colLens = Array.from({ length: cols }, (_, col) => baseLen + (col < extra ? 1 : 0));

    const colChunks = [];
    let start = 0;
    for (let col = 0; col < cols; col += 1) {
      const len = colLens[col];
      colChunks.push(ranked.slice(start, start + len));
      start += len;
    }

    const maxRows = Math.max(...colLens, 0);
    const arranged = [];
    for (let row = 0; row < maxRows; row += 1) {
      for (let col = 0; col < cols; col += 1) {
        const item = colChunks[col]?.[row];
        if (item) arranged.push(item);
      }
    }

    return arranged;
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

    const prevRankMap = (ScreenApp.store && ScreenApp.store.scoreboardLastRanksByParticipant)
      ? ScreenApp.store.scoreboardLastRanksByParticipant
      : null;
    const getPrevRank = (row) => {
      const id = Number(row?.partecipazione_id || 0);
      if (!prevRankMap || id <= 0) return 0;
      return Number(prevRankMap[id] || 0);
    };

    const ranked = [...lista]
      .sort((a, b) => {
        const scoreDiff = parseScore(b.capitale_attuale ?? 0) - parseScore(a.capitale_attuale ?? 0);
        if (scoreDiff !== 0) return scoreDiff;

        const prevA = getPrevRank(a);
        const prevB = getPrevRank(b);
        if (prevA > 0 && prevB > 0 && prevA !== prevB) {
          return prevA - prevB;
        }

        const idA = Number(a?.partecipazione_id || 0);
        const idB = Number(b?.partecipazione_id || 0);
        if (idA > 0 && idB > 0 && idA !== idB) {
          return idA - idB;
        }

        const nomeA = String(a?.nome || '');
        const nomeB = String(b?.nome || '');
        return nomeA.localeCompare(nomeB, 'it', { sensitivity: 'base' });
      })
      .map((row, index) => ({ ...row, __rank: index + 1 }));

    return { ranked, items: arrangeRankedItemsByColumns(ranked, columns), columns };
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

  function isFinalScoreboardState() {
    const state = String(ScreenApp.store?.currentState || '').toLowerCase();
    return state === 'conclusa' || state === 'fine';
  }

  function renderScoreboardHeader(isFinalState) {
    const titleEl = document.getElementById('scoreboard-title-screen');
    if (!titleEl) return;
    titleEl.innerText = isFinalState ? 'CLASSIFICA FINALE' : 'Classifica';
    titleEl.classList.toggle('scoreboard-title--final', isFinalState);
  }

  function renderFinalPodium(sortedByRank) {
    const podiumEl = document.getElementById('scoreboard-podium');
    if (!podiumEl) return;

    if (!Array.isArray(sortedByRank) || sortedByRank.length === 0) {
      podiumEl.innerHTML = '';
      podiumEl.classList.add('hidden');
      return;
    }

    const topThree = sortedByRank.slice(0, 3);
    const byRank = {};
    topThree.forEach((row) => {
      const rank = Number(row?.__rank || 0);
      if (rank >= 1 && rank <= 3) byRank[rank] = row;
    });

    const visualOrder = [2, 1, 3];
    const medalByRank = { 1: '🥇', 2: '🥈', 3: '🥉' };
    const labelByRank = { 1: '1 posto', 2: '2 posto', 3: '3 posto' };
    const heightByRank = { 1: 'podium-col--first', 2: 'podium-col--second', 3: 'podium-col--third' };

    podiumEl.innerHTML = visualOrder
      .map((rank) => {
        const player = byRank[rank];
        if (!player) {
          return `<div class="podium-col ${heightByRank[rank]} is-empty"></div>`;
        }
        const nome = String(player.nome || 'Giocatore');
        const punti = formatThousands(player.capitale_attuale ?? 0);
        return `
          <div class="podium-col ${heightByRank[rank]}" aria-label="${labelByRank[rank]}">
            <div class="podium-medal">${medalByRank[rank]}</div>
            <div class="podium-player" title="${nome}">
              <span class="podium-player-name">${nome}</span>
              <span class="podium-player-points">${punti}</span>
            </div>
            <div class="podium-base">
              <span class="podium-rank">#${rank}</span>
            </div>
          </div>
        `;
      })
      .join('');
    podiumEl.classList.remove('hidden');
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
    const isFinalState = isFinalScoreboardState();
    renderScoreboardHeader(isFinalState);
    if (!listEl) return;

    if (!Array.isArray(lista) || lista.length === 0) {
      listEl.innerHTML = '<div class="scoreboard-empty">Nessun giocatore in classifica.</div>';
      renderFinalPodium([]);
      clearMemeAlert();
      return;
    }

    const ordered = getOrderedScoreboard(lista);
    const currentQuestionNumber = getCurrentQuestionNumber();
    const baselineRankMap = resolveTrendBaseline(currentQuestionNumber);
    const rankedWithTrend = ordered.ranked.map((row) => ({
      ...row,
      __trend: resolveTrendData(row, baselineRankMap),
    }));
    const currentRankMap = buildRankMapByParticipant(rankedWithTrend);
    const visibleRanked = isFinalState
      ? rankedWithTrend.filter((row) => Number(row.__rank || 0) > 3)
      : rankedWithTrend;
    const visibleItems = arrangeRankedItemsByColumns(visibleRanked, ordered.columns);

    renderFinalPodium(isFinalState ? rankedWithTrend : []);
    applyScoreboardLayout(listEl, ordered.columns, visibleItems.length);
    listEl.innerHTML = visibleItems.length > 0
      ? renderScoreboardItems(visibleItems)
      : '<div class="scoreboard-empty">Classifica completa visualizzata nel podio.</div>';
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
