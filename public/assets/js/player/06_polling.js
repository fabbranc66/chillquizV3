// 06_polling.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const Support = Player.pollingSupport;
  const Clock = window.ChillQuizClock;
  const { isQuestionStage } = Player.utils;

  function getNextPollDelayMs() {
    const sessione = S.latestSessioneSnapshot || null;
    const stato = String((sessione && sessione.stato) || '');
    const currentType = String((S.currentDomandaData && S.currentDomandaData.tipo_domanda) || '').toUpperCase();
    const waitingSarabandaIntro = currentType === 'SARABANDA'
      && (
        stato === 'preview'
        || (stato === 'domanda' && Number((sessione && sessione.timer_start) || 0) > Clock.nowSec(S))
      );

    if (stato === 'conclusa') {
      return 5000;
    }

    if (stato === 'risultati') {
      return 2500;
    }

    return waitingSarabandaIntro ? 100 : 1000;
  }

  function clearPreviewBoundaryPoll() {
    if (S.previewBoundaryTimer) {
      clearTimeout(S.previewBoundaryTimer);
      S.previewBoundaryTimer = null;
    }
  }

  function schedulePreviewBoundaryPoll(sessione, domandaId) {
    clearPreviewBoundaryPoll();
    const stato = String((sessione && sessione.stato) || '');
    const timerStart = Number((sessione && sessione.timer_start) || 0);
    const currentDomandaId = Number(domandaId || 0);
    if (stato !== 'preview' || currentDomandaId <= 0 || timerStart <= 0) {
      return;
    }

    const delayMs = Clock.computeDelayMsFromStart(S, timerStart);
    if (delayMs <= 0) {
      return;
    }

    S.previewBoundaryTimer = setTimeout(() => {
      S.previewBoundaryTimer = null;

      if (
        String(S.currentState || '') === 'preview'
        && Number(S.domandaTimerQuestionId || 0) === currentDomandaId
      ) {
        S.currentState = 'domanda';
        if (S.latestSessioneSnapshot && typeof S.latestSessioneSnapshot === 'object') {
          S.latestSessioneSnapshot.stato = 'domanda';
          S.latestSessioneSnapshot.timer_start = timerStart;
        }

        Support.renderState(S.latestSessioneSnapshot || sessione || null, true);
        if (S.currentDomandaData) {
          Player.domanda.renderDomanda(S.currentDomandaData, S.latestSessioneSnapshot || sessione || null);
        }
      }

      fetchStato();
    }, delayMs + 15);
  }

  function scheduleNextPoll() {
    if (S.pollingInterval) {
      clearTimeout(S.pollingInterval);
      S.pollingInterval = null;
    }
    const delayMs = getNextPollDelayMs();
    if (delayMs === null) {
      return;
    }
    S.pollingInterval = setTimeout(fetchStato, delayMs);
  }

  function startPolling() {
    scheduleNextPoll();
  }

  async function fetchStato() {
    if (!S.sessioneId || S.statoRequestInFlight) return;
    S.statoRequestInFlight = true;

    try {
      const statoUrl = new URL(`${S.API_BASE}/stato/${S.sessioneId || 0}`, window.location.origin);
      statoUrl.searchParams.set('viewer', 'player');
      if (Number(S.partecipazioneId || 0) > 0) {
        statoUrl.searchParams.set('partecipazione_id', String(Number(S.partecipazioneId || 0)));
      }
      const response = await fetch(statoUrl.toString());
      const data = await response.json();

      if (!data.success || !data.sessione) return;

      const sessione = data.sessione;
      Clock.updateOffsetFromServerNow(S, (data && data.server_now) || 0);
      S.latestSessioneSnapshot = sessione;
      const stato = sessione.stato;
      const currentDomandaId = Number((data && data.domanda && data.domanda.id) || 0);
      S.domandaTimerStart = Number((sessione && sessione.timer_start) || 0);
      S.domandaTimerMax = Number((sessione && sessione.timer_max) || 0);
      if (currentDomandaId > 0) {
        S.domandaTimerQuestionId = currentDomandaId;
      }
      if (stato === 'domanda' && S.domandaTimerStart > 0) {
        S.sarabandaPreviewStartedQuestionId = currentDomandaId;
      }
      if (stato !== 'domanda') {
        S.sarabandaPreviewStartedQuestionId = 0;
      }
      if (stato !== 'preview') {
        clearPreviewBoundaryPoll();
      }
      schedulePreviewBoundaryPoll(sessione, currentDomandaId);
      const stateChanged = Support.handleStateTransition(stato);

      if (!(stato === 'puntata' && !stateChanged)) {
        Support.renderState(sessione, stateChanged);
      }

      if (isQuestionStage(stato)) {
        if (data.domanda) {
          Player.domanda.renderDomanda(data.domanda, sessione);
        } else {
          Player.domanda.fetchDomanda();
        }
      }
    } catch (err) {
      console.error(err);
    } finally {
      S.statoRequestInFlight = false;
      scheduleNextPoll();
    }
  }

  Player.polling = { startPolling, fetchStato };
})();
