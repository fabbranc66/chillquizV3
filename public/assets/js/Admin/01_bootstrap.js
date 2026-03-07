// admin/01_bootstrap.js
(() => {
  window.Admin = window.Admin || {};

  const ADMIN_BOOTSTRAP = window.ADMIN_BOOTSTRAP || {};

  window.Admin.state = {
    SESSIONE_ID: Number(ADMIN_BOOTSTRAP.sessioneId || 0),
    NOME_SESSIONE: String(ADMIN_BOOTSTRAP.nomeSessione || '').trim(),

    ADMIN_TOKEN: String(ADMIN_BOOTSTRAP.adminToken || 'SUPERSEGRETO123'),
    API_BASE: String(ADMIN_BOOTSTRAP.apiBase || 'index.php?url=api'),

    timerInterval: null,
    ultimoNumeroPartecipanti: 0,
    domandaCorrente: null,
    audioPreviewDomandaId: 0,
    audioPreviewResetTimer: null,
    statoRequestInFlight: false,
    joinRequestInFlight: false,
    domandaMetaRequestInFlight: false,
  };
})();
