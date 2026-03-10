// 06_polling.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const Support = Player.pollingSupport;
  const Clock = window.ChillQuizClock;

  function startPolling() {
    if (S.pollingInterval) {
      clearInterval(S.pollingInterval);
      S.pollingInterval = null;
    }
    S.pollingInterval = setInterval(fetchStato, 300);
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
      S.domandaTimerStart = Number(sessione?.timer_start || 0);
      S.domandaTimerMax = Number(sessione?.timer_max || 0);
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
    }
  }

  Player.polling = { startPolling, fetchStato };
})();
