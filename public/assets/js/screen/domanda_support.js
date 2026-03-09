/* public/assets/js/screen/domanda_support.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;
  const Copy = ScreenApp.copy || {};

  function resolveMediaUrl(path) {
    const raw = String(path || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw) || raw.startsWith('data:')) return raw;
    const clean = raw.startsWith('/') ? raw.substring(1) : raw;
    return `${window.location.origin}${ScreenApp.api.publicBaseUrl}${clean}`;
  }

  function getDomandaMediaNodes() {
    return {
      wrap: document.getElementById('domanda-media-screen'),
      image: document.getElementById('domanda-media-image-screen'),
      audio: document.getElementById('domanda-media-audio-screen'),
      caption: document.getElementById('domanda-media-caption-screen'),
    };
  }

  function getDomandaStatusMessageNode() {
    return document.getElementById('domanda-status-message-screen');
  }

  function getQuestionTypeBadgeNodes() {
    return {
      wrap: document.getElementById('question-type-badge-screen'),
      image: document.getElementById('question-type-badge-image-screen'),
      label: document.getElementById('question-type-badge-label-screen'),
    };
  }

  function clearStatusMessage() {
    const statusMessage = getDomandaStatusMessageNode();
    if (!statusMessage) return;

    statusMessage.innerText = '';
    statusMessage.classList.add('hidden');
    statusMessage.classList.remove('is-impostore');
    statusMessage.classList.remove('is-meme');
  }

  function renderStatusMessage(domanda, isMemeMode, isImpostoreMasked) {
    const statusMessage = getDomandaStatusMessageNode();
    if (!statusMessage) return;

    if (isMemeMode) {
      statusMessage.innerText = String(domanda.meme_screen_notice || Copy.memeScreenNotice || 'Modalita MEME attiva.');
      statusMessage.classList.remove('hidden');
      statusMessage.classList.add('is-meme');
      statusMessage.classList.remove('is-impostore');
      return;
    }

    if (isImpostoreMasked) {
      statusMessage.innerText = String(domanda.impostore_screen_notice || Copy.impostoreScreenNotice || 'Modalita IMPOSTORE attiva.');
      statusMessage.classList.remove('hidden');
      statusMessage.classList.add('is-impostore');
      statusMessage.classList.remove('is-meme');
      return;
    }

    clearStatusMessage();
  }

  function normalizeQuestionType(domanda) {
    const tipo = String(domanda?.tipo_domanda || 'CLASSIC').trim().toUpperCase();
    const hasAudio = String(domanda?.media_audio_path || '').trim() !== '';
    if (tipo === 'SARABANDA' && !hasAudio) return 'CLASSIC';
    return tipo || 'CLASSIC';
  }

  function isSarabandaQuestionType(domanda) {
    return normalizeQuestionType(domanda) === 'SARABANDA';
  }

  function clearDomandaMedia() {
    const { wrap, image, audio, caption } = getDomandaMediaNodes();
    ScreenApp.domandaAudio?.clearAudioPreviewRuntime?.();

    if (image) {
      image.removeAttribute('src');
      image.classList.add('hidden');
    }

    if (audio) {
      window.clearTimeout(audio.__previewTimer);
      audio.pause();
      audio.removeAttribute('src');
      delete audio.dataset.mediaSrc;
      audio.load();
      audio.classList.add('hidden');
    }

    if (caption) {
      caption.innerText = '';
      caption.classList.add('hidden');
    }

    if (wrap) {
      wrap.classList.remove('hidden');
      wrap.classList.add('media-slot-empty');
    }
  }

  function renderDomandaMedia(domanda, imageOnly, overrides = {}) {
    const { wrap, image, audio, caption } = getDomandaMediaNodes();
    if (!wrap) return;

    const imageUrl = resolveMediaUrl(overrides.media_image_path || domanda?.media_image_path);
    const audioUrl = resolveMediaUrl(overrides.media_audio_path || domanda?.media_audio_path);
    const captionText = imageOnly ? '' : String(overrides.media_caption ?? domanda?.media_caption ?? '').trim();
    let hasAny = false;

    if (image && imageUrl) {
      image.src = imageUrl;
      image.classList.remove('hidden');
      hasAny = true;
    } else if (image) {
      image.removeAttribute('src');
      image.classList.add('hidden');
    }

    if (!imageOnly && audio && audioUrl) {
      const currentMediaSrc = String(audio.dataset.mediaSrc || '');
      if (currentMediaSrc !== audioUrl) {
        audio.src = audioUrl;
        audio.dataset.mediaSrc = audioUrl;
      }
      audio.classList.remove('hidden');
    } else if (!imageOnly && audio) {
      window.clearTimeout(audio.__previewTimer);
      audio.pause();
      audio.removeAttribute('src');
      audio.load();
      audio.classList.add('hidden');
    }

    if (caption && captionText) {
      caption.innerText = captionText;
      caption.classList.remove('hidden');
      hasAny = true;
    } else if (caption) {
      caption.innerText = '';
      caption.classList.add('hidden');
    }

    wrap.classList.remove('hidden');
    wrap.classList.toggle('has-media', hasAny);
    wrap.classList.toggle('media-slot-empty', !hasAny);
  }

  function renderQuestionMediaForState(domanda, isImpostoreMasked, isSarabandaIntro) {
    if (isImpostoreMasked) {
      renderDomandaMedia(domanda, false, {
        media_image_path: '/assets/img/player/impostore-fake.svg',
        media_caption: Copy.impostoreMaskedCaption || 'Immagine mascherata per la modalita impostore',
      });
      return;
    }

    if (isSarabandaIntro) {
      renderDomandaMedia(domanda, true);
      return;
    }

    renderDomandaMedia(domanda, false);
  }

  function renderLoadingOptions(opzioni) {
    if (opzioni.children.length > 0) return;

    opzioni.innerHTML = '';
    for (let i = 0; i < 4; i += 1) {
      const el = document.createElement('div');
      el.className = 'opzione';
      el.innerText = '...';
      opzioni.appendChild(el);
    }
  }

  function getQuestionRenderState(domanda) {
    const tipoDomanda = normalizeQuestionType(domanda);
    const nowSec = Math.floor(Date.now() / 1000);
    const hasMemeDecoratedOptions = Array.isArray(domanda?.opzioni)
      && domanda.opzioni.some((opzione) => String(opzione?.display_text || '') !== '');

    return {
      tipoDomanda,
      isSarabandaIntro: tipoDomanda === 'SARABANDA'
        && (S.currentTimerStart <= 0 || nowSec < S.currentTimerStart),
      isImpostoreMasked: !!domanda?.impostore_masked,
      isMemeMode: !!domanda?.meme_mode || tipoDomanda === 'MEME' || hasMemeDecoratedOptions,
      showCorrect: !!domanda?.show_correct,
      correctOptionId: String(domanda?.correct_option_id || ''),
    };
  }

  ScreenApp.domandaSupport = {
    clearDomandaMedia,
    clearStatusMessage,
    getQuestionRenderState,
    getQuestionTypeBadgeNodes,
    isSarabandaQuestionType,
    normalizeQuestionType,
    renderDomandaMedia,
    renderLoadingOptions,
    renderQuestionMediaForState,
    renderStatusMessage,
    resolveMediaUrl,
  };
})();
