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
    audioBufferCache: new Map(),
    statoPollMs: 1000,
    statoPollFastMs: 100,
    mediaPollMs: 30000,
    currentState: null,
    latestSessioneSnapshot: null,
    serverClockOffsetMs: 0,
    currentTimerStart: 0,
    currentTimerMax: 0,
    currentTimerQuestionId: 0,
    sarabandaPreviewStartedQuestionId: 0,
    sarabandaPreviewConsumedQuestionId: 0,
    domandaRenderizzata: false,
    currentDomandaData: null,
    mediaAttiva: null,
    currentImagePathKey: '',
    currentImageCacheBustMs: 0,
    optionRevealTimer: null,
    previewBoundaryTimer: null,
    pixelateTimer: null,
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
    audioPreviewPlayInFlight: false,
    audioContext: null,
    currentBufferSource: null,
    sarabandaAudioEnabled: false,
    sarabandaReverseEnabled: false,
    sarabandaBrokenRecordEnabled: false,
    sarabandaFastForwardEnabled: false,
    sarabandaFastForwardRate: 5,
    memeRotationTimer: null,
    memeRotationStep: -1,
    memeAlertTimer: null,
    lastMemeAlertKey: '',
    scoreboardLastRanksByParticipant: null,
    scoreboardLastQuestionNumber: 0,
    scoreboardTrendBaselineByParticipant: null,
    scoreboardTrendBaselineQuestionNumber: 0,
    debugTiming: {
      domandaId: 0,
      timerStartedAtMs: 0,
      optionsShownAtMs: 0,
      deltaMs: null,
    },
  };

  ScreenApp.copy = {
    imagePartyNotice: 'Modalita PIXELATE: l\'immagine si schiarisce nel tempo.',
    fadeNotice: 'Modalita FADE: l\'immagine emerge dal nero durante il timer.',
    memeScreenNotice: 'Modalita MEME attiva.',
    impostoreScreenNotice: 'Modalita IMPOSTORE: lo schermo non mostra la domanda.',
    impostoreMaskedCaption: 'Immagine mascherata per la modalita impostore',
  };
})();
