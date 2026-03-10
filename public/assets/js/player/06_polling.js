// 06_polling.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const Support = Player.pollingSupport;
  const Clock = window.ChillQuizClock;

  function getNextPollDelayMs() {
    const sessione = S.latestSessioneSnapshot || null;
    const stato = String(sessione?.stato || '');
    const currentType = String(S.currentDomandaData?.tipo_domanda || '').toUpperCase();
    const waitingSarabandaIntro = stato === 'domanda'
      && currentType === 'SARABANDA'
      && Number(sessione?.timer_start || 0) > Clock.nowSec(S);

    return waitingSarabandaIntro ? 100 : 1000;
  }

  function scheduleNextPoll() {
    if (S.pollingInterval) {
      clearTimeout(S.pollingInterval);
      S.pollingInterval = null;
    }
    S.pollingInterval = setTimeout(fetchStato, getNextPollDelayMs());
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
      Clock.updateOffsetFromServerNow(S, data?.server_now || 0);
      S.latestSessioneSnapshot = sessione;
      const stato = sessione.stato;
      const currentDomandaId = Number(data?.domanda?.id || 0);
      S.domandaTimerStart = Number(sessione?.timer_start || 0);
      S.domandaTimerMax = Number(sessione?.timer_max || 0);
      if (currentDomandaId > 0) {
        S.domandaTimerQuestionId = currentDomandaId;
        if (stato === 'domanda' && S.domandaTimerStart > 0) {
          S.sarabandaPreviewStartedQuestionId = currentDomandaId;
          if (Player.domanda && typeof Player.domanda.renderDomanda === 'function' && data.domanda) {
            Player.domanda.renderDomanda(data.domanda, sessione);
          }
          if (Support && typeof Support.renderTimer === 'function') {
            Support.renderTimer(sessione);
          }
        }
      }
      if (stato !== 'domanda') {
        S.sarabandaPreviewStartedQuestionId = 0;
      }
      const stateChanged = Support.handleStateTransition(stato);

      if (stato === 'domanda') {
        if (data.domanda) {
          Player.domanda.renderDomanda(data.domanda, sessione);
        } else {
          Player.domanda.fetchDomanda();
        }
      }

      if (!(stato === 'puntata' && !stateChanged)) {
        Support.renderState(sessione, stateChanged);
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
