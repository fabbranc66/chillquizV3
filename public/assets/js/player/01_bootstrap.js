// 01_bootstrap.js
(() => {
  window.Player = window.Player || {};

  const PLAYER_BOOTSTRAP = window.PLAYER_BOOTSTRAP || {};
  const API_BASE = String(PLAYER_BOOTSTRAP.apiBase || '/chillquizV3/public/?url=api');

  let sessioneId = Number(PLAYER_BOOTSTRAP.sessioneId || 0);
  if (Number.isNaN(sessioneId)) sessioneId = 0;

  window.Player.state = {
    PLAYER_BOOTSTRAP,
    API_BASE,

    sessioneId,

    partecipazioneId: null,
    currentState: null,

    pollingInterval: null,
    joinRequestPolling: null,
    timerInterval: null,

    rispostaInviata: false,
    puntataInviata: false,

    domandaFetchNonce: 0,
    badgeQuestionId: 0,
    badgeTipoDomanda: '',
    domandaTimerStart: 0,
  };
})();
