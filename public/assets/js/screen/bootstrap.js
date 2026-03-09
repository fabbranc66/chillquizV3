/* public/assets/js/screen/bootstrap.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const boot = window.SCREEN_BOOTSTRAP || {};

  const basePublicUrl = String(
    boot.basePublicUrl
    || String(window.location.pathname || '').replace(/index\.php$/, '')
    || '/'
  );
  const normalizedPublicBaseUrl = basePublicUrl.endsWith('/') ? basePublicUrl : `${basePublicUrl}/`;

  ScreenApp.store = {
    sessioneId: Number(boot.sessioneId || 0),
    publicBaseUrl: normalizedPublicBaseUrl,
    apiBase: String(boot.apiBase || `${normalizedPublicBaseUrl}index.php?url=api`),
    publicHost: String(boot.publicHost || window.location.host || ''),
    audioPreviewStoragePrefix: 'chillquiz_audio_preview_',
    statoPollMs: 2500,
    mediaPollMs: 30000,
    currentState: null,
    currentTimerStart: 0,
    domandaRenderizzata: false,
    currentDomandaData: null,
    mediaAttiva: null,
    lastAudioPreviewToken: '',
    pendingAudioPreview: null,
    previewAudio: null,
    timerTick: null,
    pollStato: null,
    pollMedia: null,
    statoRequestInFlight: false,
    mediaRequestInFlight: false,
    audioPreviewRequestInFlight: false,
    audioUnlockedByUser: false,
    memeRotationTimer: null,
    memeRotationStep: -1,
    memeAlertTimer: null,
    lastMemeAlertKey: '',
  };

  ScreenApp.copy = {
    memeScreenNotice: 'Modalita MEME attiva.',
    impostoreScreenNotice: 'Modalita IMPOSTORE: lo schermo non mostra la domanda.',
    impostoreMaskedCaption: 'Immagine mascherata per la modalita impostore',
  };
})();
