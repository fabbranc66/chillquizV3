// admin/01_bootstrap.js
(() => {
  window.Admin = window.Admin || {};

  const ADMIN_BOOTSTRAP = window.ADMIN_BOOTSTRAP || {};
  const publicBaseUrl = String(
    ADMIN_BOOTSTRAP.publicBaseUrl
    || String(window.location.pathname || '').replace(/index\.php.*$/i, '')
    || '/'
  );
  const normalizedPublicBaseUrl = publicBaseUrl.endsWith('/') ? publicBaseUrl : `${publicBaseUrl}/`;

  window.Admin.state = {
    SESSIONE_ID: Number(ADMIN_BOOTSTRAP.sessioneId || 0),
    NOME_SESSIONE: String(ADMIN_BOOTSTRAP.nomeSessione || '').trim(),

    ADMIN_TOKEN: String(ADMIN_BOOTSTRAP.adminToken || 'SUPERSEGRETO123'),
    PUBLIC_BASE_URL: normalizedPublicBaseUrl,
    API_BASE: String(ADMIN_BOOTSTRAP.apiBase || `${normalizedPublicBaseUrl}index.php?url=api`),

    timerInterval: null,
    ultimoNumeroPartecipanti: 0,
    domandaCorrente: null,
    audioPreviewDomandaId: 0,
    audioPreviewResetTimer: null,
    sessionImageSearchSessionId: 0,
    impostoreEnabled: false,
    memeEnabled: false,
    memeText: '',
    memeDraftText: '',
    currentSessionState: null,
    statoRequestInFlight: false,
    joinRequestInFlight: false,
    domandaMetaRequestInFlight: false,
    sessionImageSearchInFlight: false,
  };
})();
