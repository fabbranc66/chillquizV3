// 06a_polling_render.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const { isDomandaAttiva } = Player.utils;
  const Clock = window.ChillQuizClock;

  function persistDebugTiming() {
    try {
      if (Number(S.sessioneId || 0) <= 0) return;
      window.localStorage.setItem(
        `chillquiz_debug_timing_player_${Number(S.sessioneId || 0)}`,
        JSON.stringify(S.debugTiming || {})
      );
    } catch (err) {
      console.warn(err);
    }
  }

  function markTimerStarted(domandaId) {
    const currentDomandaId = Number(domandaId || 0);
    if (currentDomandaId <= 0) return;

    if (Number(S.debugTiming?.domandaId || 0) !== currentDomandaId) {
      S.debugTiming = {
        domandaId: currentDomandaId,
        timerStartedAtMs: 0,
        optionsShownAtMs: 0,
        deltaMs: null,
      };
    }

    if (Number(S.debugTiming.timerStartedAtMs || 0) > 0) return;

    S.debugTiming.timerStartedAtMs = Date.now();
    if (Number(S.debugTiming.optionsShownAtMs || 0) > 0) {
      S.debugTiming.deltaMs = S.debugTiming.optionsShownAtMs - S.debugTiming.timerStartedAtMs;
    }
    persistDebugTiming();
    console.info('[player] timer-start', S.debugTiming);
  }

  function resolveTimingDomandaId() {
    return Number(
      S.debugTiming?.domandaId
      || S.badgeQuestionId
      || S.questionShownDomandaId
      || 0
    );
  }

  function resetRoundInteractionFlags() {
    S.rispostaInviata = false;
    S.selectedAnswerDomandaId = 0;
    S.selectedAnswerOptionId = 0;
  }

  function resetRoundForNewQuestionCycle() {
    resetRoundInteractionFlags();
    S.puntataInviata = false;
    S.domandaTimerStart = 0;
    S.domandaTimerQuestionId = 0;
    S.sarabandaPreviewStartedQuestionId = 0;
    if (S.optionRevealTimer) {
      clearTimeout(S.optionRevealTimer);
      S.optionRevealTimer = null;
    }
  }

  function handleStateTransition(nextState) {
    const previousState = S.currentState;
    const stateChanged = nextState !== previousState;

    if (!stateChanged) {
      return false;
    }

    S.currentState = nextState;

    if (nextState === 'puntata' || nextState === 'attesa' || nextState === 'conclusa') {
      resetRoundForNewQuestionCycle();
    } else if (nextState === 'domanda') {
      S.rispostaInviata = false;
      S.selectedAnswerDomandaId = 0;
      S.selectedAnswerOptionId = 0;
      S.domandaTimerStart = 0;
      S.domandaTimerQuestionId = 0;
      S.sarabandaPreviewStartedQuestionId = 0;
    }

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

    const currentSec = Clock.nowSec(S);
    const delayMs = start > currentSec ? Math.round((start - currentSec) * 1000) : 0;

    const tick = () => {
      markTimerStarted(resolveTimingDomandaId());
      const elapsed = Math.max(0, Clock.nowSec(S) - start);
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

    if (delayMs > 0) {
      if (D.timerIndicator) {
        D.timerIndicator.style.setProperty('--progress', '0deg');
      }
      if (D.timerLabel) {
        D.timerLabel.textContent = '';
      }
      S.timerInterval = setTimeout(() => {
        tick();
        S.timerInterval = setInterval(tick, 250);
      }, delayMs);
      return;
    }

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
        resetTimerUI();
        if (D.opzioniDiv) {
          D.opzioniDiv.innerHTML = '';
          D.opzioniDiv.classList.add('hidden');
        }
        Player.screens.showOnly('screen-domanda');
        break;

      case 'risultati':
        Player.screens.showOnly('screen-risultati');
        if (D.risultatiTitle) D.risultatiTitle.textContent = 'Risultati';
        if (D.risultatoPersonale) D.risultatoPersonale.classList.remove('hidden');
        if (D.classifica) D.classifica.classList.add('hidden');
        Player.classifica.fetchClassifica();
        resetTimerUI();
        break;

      case 'conclusa':
        Player.screens.showOnly('screen-risultati');
        if (D.risultatiTitle) D.risultatiTitle.textContent = 'Classifica finale';
        if (D.risultatoPersonale) D.risultatoPersonale.classList.add('hidden');
        if (D.classifica) D.classifica.classList.remove('hidden');
        Player.classifica.fetchClassifica();
        resetTimerUI();
        break;

      case 'attesa':
        Player.screens.showOnly('screen-lobby');
        if (D.classifica) D.classifica.classList.add('hidden');
        resetTimerUI();
        break;

      case 'puntata':
        Player.screens.showOnly('screen-puntata');
        if (D.classifica) D.classifica.classList.add('hidden');
        if (stateChanged) {
          Player.puntata.prepareScreen?.();
        }
        resetTimerUI();
        break;

      default:
        Player.screens.showOnly('screen-lobby');
        if (D.classifica) D.classifica.classList.add('hidden');
        resetTimerUI();
        break;
    }
  }

  Player.pollingSupport = {
    resetRoundInteractionFlags,
    resetRoundForNewQuestionCycle,
    handleStateTransition,
    resetTimerUI,
    renderTimer,
    renderState,
  };
})();
