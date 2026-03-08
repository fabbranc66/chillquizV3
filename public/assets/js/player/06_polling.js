// 06_polling.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const { isDomandaAttiva } = Player.utils;

  function startPolling() {
    if (S.pollingInterval) {
      clearInterval(S.pollingInterval);
      S.pollingInterval = null;
    }
    S.pollingInterval = setInterval(fetchStato, 2500);
  }

  function resetTimerUI() {
    if (S.timerInterval) {
      clearInterval(S.timerInterval);
      S.timerInterval = null;
    }

    if (D.timerIndicator) {
      D.timerIndicator.style.setProperty('--progress', '0deg');
    }
    if (D.timerLabel) {
      D.timerLabel.textContent = '0s';
    }
  }

  function renderTimer(sessione) {
    const max = Number(sessione?.timer_max || 0);
    const start = Number(sessione?.timer_start || 0);

    if (!isDomandaAttiva(sessione?.stato) || max <= 0 || start <= 0) {
      resetTimerUI();
      return;
    }

    if (S.timerInterval) {
      clearInterval(S.timerInterval);
      S.timerInterval = null;
    }

    const tick = () => {
      const elapsed = Math.max(0, (Date.now() / 1000) - start);
      const remaining = Math.max(0, max - elapsed);
      const visibleRemaining = Math.max(0, Math.ceil(remaining));
      const pct = max > 0 ? (remaining / max) : 0;
      const deg = Math.max(0, Math.min(360, pct * 360));

      if (D.timerIndicator) {
        D.timerIndicator.style.setProperty('--progress', `${deg}deg`);
      }
      if (D.timerLabel) {
        D.timerLabel.textContent = `${visibleRemaining}s`;
      }

      if (remaining <= 0 && S.timerInterval) {
        clearInterval(S.timerInterval);
        S.timerInterval = null;
      }
    };

    tick();
    S.timerInterval = setInterval(tick, 250);
  }

  async function fetchStato() {
    if (!S.sessioneId || S.statoRequestInFlight) return;
    S.statoRequestInFlight = true;

    try {
      const response = await fetch(`${S.API_BASE}/stato/${S.sessioneId || 0}`);
      const data = await response.json();

      if (!data.success || !data.sessione) return;

      const sessione = data.sessione;
      const stato = sessione.stato;
      S.domandaTimerStart = Number(sessione?.timer_start || 0);
      const stateChanged = stato !== S.currentState;

      if (stateChanged) {
        S.currentState = stato;
        S.rispostaInviata = false;
        S.puntataInviata = false;
      }

      if (!(stato === 'puntata' && !stateChanged)) {
        renderState(sessione, stateChanged);
      }

      if (stato === 'domanda') {
        Player.domanda.fetchDomanda();
      }
    } catch (err) {
      console.error(err);
    } finally {
      S.statoRequestInFlight = false;
    }
  }

  function renderState(sessione, stateChanged = false) {
    const stato = sessione?.stato;

    if (!isDomandaAttiva(stato) && stato !== 'puntata') {
      S.domandaFetchNonce++;
      Player.domanda.resetDomandaView();
      Player.domanda.clearQuestionTypeBadge?.();
    }

    switch (stato) {
      case 'domanda':
        Player.screens.showOnly('screen-domanda');
        renderTimer(sessione);
        break;

      case 'risultati':
      case 'conclusa':
        Player.screens.showOnly('screen-risultati');
        Player.classifica.fetchClassifica();
        resetTimerUI();
        break;

      case 'attesa':
        Player.screens.showOnly('screen-lobby');
        resetTimerUI();
        break;

      case 'puntata':
        Player.screens.showOnly('screen-puntata');
        if (stateChanged) {
          Player.puntata.prepareScreen?.();
        }
        resetTimerUI();
        break;

      default:
        Player.screens.showOnly('screen-lobby');
        resetTimerUI();
        break;
    }
  }

  Player.polling = { startPolling, fetchStato };
})();
