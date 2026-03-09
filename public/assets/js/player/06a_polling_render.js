// 06a_polling_render.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const { isDomandaAttiva } = Player.utils;

  function resetRoundInteractionFlags() {
    S.rispostaInviata = false;
    S.puntataInviata = false;
  }

  function handleStateTransition(nextState) {
    const previousState = S.currentState;
    const stateChanged = nextState !== previousState;

    if (!stateChanged) {
      return false;
    }

    S.currentState = nextState;
    resetRoundInteractionFlags();

    if (nextState !== 'risultati') {
      Player.classifica.clearImmediateResult?.();
    }

    return true;
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

    if (S.timerInterval && Number(S.domandaTimerStart || 0) === start) {
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
        Player.screens.showOnly('screen-risultati');
        if (D.risultatiTitle) D.risultatiTitle.textContent = 'Risultati';
        if (D.risultatoPersonale) D.risultatoPersonale.classList.remove('hidden');
        Player.classifica.fetchClassifica();
        resetTimerUI();
        break;

      case 'conclusa':
        Player.screens.showOnly('screen-risultati');
        if (D.risultatiTitle) D.risultatiTitle.textContent = 'Classifica finale';
        if (D.risultatoPersonale) D.risultatoPersonale.classList.add('hidden');
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

  Player.pollingSupport = {
    resetRoundInteractionFlags,
    handleStateTransition,
    resetTimerUI,
    renderTimer,
    renderState,
  };
})();
