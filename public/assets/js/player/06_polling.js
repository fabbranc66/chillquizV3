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
    S.pollingInterval = setInterval(fetchStato, 2500);
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
        if (stato !== 'risultati') {
          S.lastImmediateResult = null;
        }
      }

      if (!(stato === 'puntata' && !stateChanged)) {
        Support.renderState(sessione, stateChanged);
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

  Player.polling = { startPolling, fetchStato };
})();
