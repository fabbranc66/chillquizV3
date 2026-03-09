// 06_polling.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const Support = Player.pollingSupport;

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
      const response = await fetch(`${S.API_BASE}/stato/${S.sessioneId || 0}`);
      const data = await response.json();

      if (!data.success || !data.sessione) return;

      const sessione = data.sessione;
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
