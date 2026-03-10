// 01_bootstrap.js
(() => {
  window.Player = window.Player || {};

  const PLAYER_BOOTSTRAP = window.PLAYER_BOOTSTRAP || {};
  const publicBaseUrl = String(
    PLAYER_BOOTSTRAP.publicBaseUrl
    || String(window.location.pathname || '').replace(/index\.php.*$/i, '')
    || '/'
  );
  const normalizedPublicBaseUrl = publicBaseUrl.endsWith('/') ? publicBaseUrl : `${publicBaseUrl}/`;
  const API_BASE = String(PLAYER_BOOTSTRAP.apiBase || `${normalizedPublicBaseUrl}index.php?url=api`);

  let sessioneId = Number(PLAYER_BOOTSTRAP.sessioneId || 0);
  if (Number.isNaN(sessioneId)) sessioneId = 0;

  window.Player.state = {
    PLAYER_BOOTSTRAP,
    PUBLIC_BASE_URL: normalizedPublicBaseUrl,
    API_BASE,

    sessioneId,

    partecipazioneId: null,
    currentState: null,
    activeScreenId: null,
    latestSessioneSnapshot: null,
    serverClockOffsetMs: 0,

    pollingInterval: null,
    joinRequestPolling: null,
    timerInterval: null,
    statoRequestInFlight: false,

    rispostaInviata: false,
    puntataInviata: false,
    lastImmediateResult: null,

    domandaFetchNonce: 0,
    badgeQuestionId: 0,
    badgeTipoDomanda: '',
    domandaTimerStart: 0,
    domandaTimerMax: 0,
    questionShownAtPerf: 0,
    questionShownDomandaId: 0,
    questionShownTimerStart: 0,
    selectedAnswerDomandaId: 0,
    selectedAnswerOptionId: 0,
    renderedDomandaKey: '',
    lastMediaUrl: '',
    optionRevealTimer: null,
    pixelateTimer: null,
    memeRotationTimer: null,
    memeRotationStep: -1,
    debugTiming: {
      domandaId: 0,
      timerStartedAtMs: 0,
      optionsShownAtMs: 0,
      deltaMs: null,
    },
  };
})();
