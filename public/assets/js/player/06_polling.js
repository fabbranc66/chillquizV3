// 06_polling.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const { isDomandaAttiva } = Player.utils;

  function startPolling() {
    if (S.pollingInterval) {
      clearInterval(S.pollingInterval);
      S.pollingInterval = null;
    }
    S.pollingInterval = setInterval(fetchStato, 1000);
  }

  async function fetchStato() {
    if (!S.sessioneId) return;

    try {
      const response = await fetch(`${S.API_BASE}/stato/${S.sessioneId || 0}`);
      const data = await response.json();

      if (!data.success || !data.sessione) return;

      const stato = data.sessione.stato;

      if (stato !== S.currentState) {
        S.currentState = stato;
        S.rispostaInviata = false;
        S.puntataInviata = false;
      }

      renderState(stato);

      if (stato === 'domanda') {
        Player.domanda.fetchDomanda();
      }
    } catch (err) {
      console.error(err);
    }
  }

  function renderState(stato) {
    Player.screens.hideAllScreens();

    if (!isDomandaAttiva(stato)) {
      S.domandaFetchNonce++;
      Player.domanda.resetDomandaView();
    }

    switch (stato) {
      case 'domanda':
        Player.screens.show('screen-domanda');
        break;

      case 'risultati':
      case 'conclusa':
        Player.screens.show('screen-risultati');
        Player.classifica.fetchClassifica();
        break;

      case 'attesa':
        Player.screens.show('screen-lobby');
        break;

      case 'puntata':
        Player.screens.show('screen-puntata');
        break;

      default:
        Player.screens.show('screen-lobby');
        break;
    }
  }

  Player.polling = { startPolling, fetchStato };
})();