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

  function appendCacheBust(url, token) {
    const rawUrl = String(url || '').trim();
    if (!rawUrl) return '';
    const safeToken = String(token || '').trim();
    if (!safeToken) return rawUrl;
    return `${rawUrl}${rawUrl.includes('?') ? '&' : '?'}v=${encodeURIComponent(safeToken)}`;
  }

  function resolveScreenImageUrlWithRefresh(path) {
    const baseUrl = resolveMediaUrl(path);
    if (!baseUrl) return '';

    const pathKey = String(path || '').trim();
    const nowMs = Date.now();
    const shouldRefresh = pathKey !== String(S.currentImagePathKey || '')
      || (nowMs - Number(S.currentImageCacheBustMs || 0)) >= 4000;

    if (shouldRefresh) {
      S.currentImagePathKey = pathKey;
      S.currentImageCacheBustMs = nowMs;
    }

    const token = shouldRefresh ? String(S.currentImageCacheBustMs) : '';
    return appendCacheBust(baseUrl, token);
  }

  function getDomandaMediaNodes() {
    return {
      wrap: document.getElementById('domanda-media-screen'),
      image: document.getElementById('domanda-media-image-screen'),
      canvas: document.getElementById('domanda-media-canvas-screen'),
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
    statusMessage.classList.remove('is-image-party');
  }

  function renderStatusMessage(domanda, isMemeMode, isImpostoreMasked, isImageParty, isFadeMode) {
    const statusMessage = getDomandaStatusMessageNode();
    if (!statusMessage) return;

    if (isMemeMode) {
      statusMessage.innerText = String(domanda.meme_screen_notice || Copy.memeScreenNotice || 'Modalita MEME attiva.');
      statusMessage.classList.remove('hidden');
      statusMessage.classList.add('is-meme');
      statusMessage.classList.remove('is-impostore');
      return;
    }

    if (isImageParty || isFadeMode) {
      statusMessage.innerText = String(
        isFadeMode
          ? (domanda.fade_notice || Copy.fadeNotice || 'Modalita FADE attiva.')
          : (domanda.image_party_notice || Copy.imagePartyNotice || 'Modalita PIXELATE attiva.')
      );
      statusMessage.classList.remove('hidden');
      statusMessage.classList.add('is-image-party');
      statusMessage.classList.remove('is-impostore');
      statusMessage.classList.remove('is-meme');
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
    return tipo || 'CLASSIC';
  }

  function isSarabandaQuestionType(domanda) {
    return normalizeQuestionType(domanda) === 'SARABANDA';
  }

  function clearDomandaMedia() {
    const { wrap, image, canvas, audio, caption } = getDomandaMediaNodes();
    ScreenApp.domandaAudio?.clearAudioPreviewRuntime?.();
    stopPixelateRender();

    if (image) {
      image.removeAttribute('src');
      image.style.opacity = '1';
      image.classList.add('hidden');
    }
    if (canvas) {
      if (canvas.__hideTimer) {
        window.clearTimeout(canvas.__hideTimer);
        canvas.__hideTimer = null;
      }
      canvas.style.opacity = '1';
      canvas.classList.add('hidden');
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
      wrap.classList.remove('is-image-party');
    }

    S.currentImagePathKey = '';
    S.currentImageCacheBustMs = 0;
  }

  function renderDomandaMedia(domanda, imageOnly, overrides = {}) {
    const { wrap, image, canvas, audio, caption } = getDomandaMediaNodes();
    if (!wrap) return;

    const imageUrl = resolveScreenImageUrlWithRefresh(overrides.media_image_path || domanda?.media_image_path);
    const audioUrl = resolveMediaUrl(overrides.media_audio_path || domanda?.media_audio_path);
    const captionText = imageOnly ? '' : String(overrides.media_caption ?? domanda?.media_caption ?? '').trim();
    let hasAny = false;

    if (image && imageUrl) {
      image.src = imageUrl;
      if (overrides.is_image_party || overrides.is_fade_mode) {
        image.classList.remove('hidden');
        image.style.opacity = '0';
      } else {
        image.style.opacity = '1';
        image.classList.remove('hidden');
      }
      hasAny = true;
    } else if (image) {
      image.removeAttribute('src');
      image.classList.add('hidden');
      if (canvas) canvas.classList.add('hidden');
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
    wrap.classList.toggle('is-image-party', !!overrides.is_image_party || !!overrides.is_fade_mode);

    if ((overrides.is_image_party || overrides.is_fade_mode) && imageUrl) {
      if (overrides.is_fade_mode) {
        startFadeRender(domanda, !!domanda?.show_correct);
      } else {
        startPixelateRender(domanda, !!domanda?.show_correct);
      }
    } else {
      stopPixelateRender();
      if (canvas) canvas.classList.add('hidden');
      if (image && imageUrl) {
        image.style.opacity = '1';
        image.classList.remove('hidden');
      }
    }
  }

  function stopPixelateRender() {
    if (S.pixelateTimer) {
      if (typeof window.cancelAnimationFrame === 'function') {
        window.cancelAnimationFrame(S.pixelateTimer);
      } else {
        window.clearInterval(S.pixelateTimer);
      }
      S.pixelateTimer = null;
    }

    const { canvas } = getDomandaMediaNodes();
    if (canvas && canvas.__hideTimer) {
      window.clearTimeout(canvas.__hideTimer);
      canvas.__hideTimer = null;
    }
  }

  function setPixelateBlend(image, canvas, imageOpacity, canvasOpacity) {
    if (image) {
      image.style.opacity = String(Math.max(0, Math.min(1, imageOpacity)));
    }
    if (canvas) {
      canvas.style.opacity = String(Math.max(0, Math.min(1, canvasOpacity)));
    }
  }

  function finishPixelateReveal(image, canvas) {
    if (!image || !canvas) return;
    if (canvas.__hideTimer) {
      window.clearTimeout(canvas.__hideTimer);
      canvas.__hideTimer = null;
    }
    drawPixelatedImage(image, canvas, 1);
    canvas.classList.remove('hidden');
    image.classList.add('hidden');
    setPixelateBlend(image, canvas, 0, 1);
  }

  function resolvePixelBlockSize(elapsedSec, totalSec, showClear = false) {
    if (showClear) return 1;

    const startSize = 60;
    const endSize = 3;
    const duration = Math.max(1, Number(totalSec || 10));
    const clampedElapsed = Math.max(0, Math.min(duration, Number(elapsedSec || 0)));

    const secondFloor = Math.floor(clampedElapsed);
    const secondCeil = Math.min(duration, secondFloor + 1);
    const secondProgress = Math.max(0, Math.min(1, clampedElapsed - secondFloor));

    const easeOutEarly = (progress) => {
      const clamped = Math.max(0, Math.min(1, progress));
      const split = 0.5;

      if (clamped <= split) {
        const local = clamped / split;
        return 0.9 * (1 - Math.pow(1 - local, 2));
      }

      const local = (clamped - split) / split;
      return 0.9 + (0.1 * local);
    };

    const sizeAtSecond = (second) => {
      const progress = Math.max(0, Math.min(1, second / duration));
      const eased = easeOutEarly(progress);
      return startSize + ((endSize - startSize) * eased);
    };

    const fromSize = sizeAtSecond(secondFloor);
    const toSize = sizeAtSecond(secondCeil);
    const interpolated = fromSize + ((toSize - fromSize) * secondProgress);

    return Math.max(endSize, Math.round(interpolated));
  }

  function resolveEffectProgress(elapsedSec, totalSec, revealFull = false) {
    if (revealFull) return 1;

    const duration = Math.max(1, Number(totalSec || 10));
    const clampedElapsed = Math.max(0, Math.min(duration, Number(elapsedSec || 0)));
    const secondFloor = Math.floor(clampedElapsed);
    const secondCeil = Math.min(duration, secondFloor + 1);
    const secondProgress = Math.max(0, Math.min(1, clampedElapsed - secondFloor));

    const easeOutEarly = (progress) => {
      const clamped = Math.max(0, Math.min(1, progress));
      const split = 0.5;

      if (clamped <= split) {
        const local = clamped / split;
        return 0.9 * (1 - Math.pow(1 - local, 2));
      }

      const local = (clamped - split) / split;
      return 0.9 + (0.1 * local);
    };

    const progressAtSecond = (second) => {
      const progress = Math.max(0, Math.min(1, second / duration));
      return easeOutEarly(progress);
    };

    const fromProgress = progressAtSecond(secondFloor);
    const toProgress = progressAtSecond(secondCeil);
    return Math.max(0, Math.min(1, fromProgress + ((toProgress - fromProgress) * secondProgress)));
  }

  function resolveFadeProgress(elapsedSec, totalSec, revealFull = false) {
    if (revealFull) return 1;

    const duration = Math.max(1, Number(totalSec || 10));
    const clampedElapsed = Math.max(0, Math.min(duration, Number(elapsedSec || 0)));
    const secondFloor = Math.floor(clampedElapsed);
    const secondCeil = Math.min(duration, secondFloor + 1);
    const secondProgress = Math.max(0, Math.min(1, clampedElapsed - secondFloor));

    const progressAtSecond = (second) => {
      const progress = Math.max(0, Math.min(1, second / duration));
      const split = 0.5;

      if (progress <= split) {
        const local = progress / split;
        return 0.36 * Math.pow(local, 1.05);
      }

      const local = (progress - split) / split;
      return 0.36 + (0.64 * Math.pow(local, 0.72));
    };

    const fromProgress = progressAtSecond(secondFloor);
    const toProgress = progressAtSecond(secondCeil);
    return Math.max(0, Math.min(1, fromProgress + ((toProgress - fromProgress) * secondProgress)));
  }

  function drawPixelatedImage(image, canvas, blockSize) {
    if (!image || !canvas) return;
    const drawRect = resolveEffectDrawRect(image, canvas);
    if (!drawRect) return;
    const {
      displayWidth,
      displayHeight,
      sourceWidth,
      sourceHeight,
      drawWidth,
      drawHeight,
      offsetX,
      offsetY,
    } = drawRect;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    canvas.width = displayWidth;
    canvas.height = displayHeight;
    ctx.imageSmoothingEnabled = false;
    ctx.clearRect(0, 0, displayWidth, displayHeight);

    const scaledWidth = Math.max(1, Math.round(drawWidth / Math.max(1, blockSize)));
    const scaledHeight = Math.max(1, Math.round(drawHeight / Math.max(1, blockSize)));
    const offscreen = document.createElement('canvas');
    offscreen.width = scaledWidth;
    offscreen.height = scaledHeight;
    const offscreenCtx = offscreen.getContext('2d');
    if (!offscreenCtx) return;

    offscreenCtx.imageSmoothingEnabled = false;
    offscreenCtx.clearRect(0, 0, scaledWidth, scaledHeight);
    offscreenCtx.drawImage(image, 0, 0, sourceWidth, sourceHeight, 0, 0, scaledWidth, scaledHeight);
    ctx.drawImage(offscreen, 0, 0, scaledWidth, scaledHeight, offsetX, offsetY, drawWidth, drawHeight);
  }

  function drawFadeImage(image, canvas, progress) {
    if (!image || !canvas) return;
    const drawRect = resolveEffectDrawRect(image, canvas);
    if (!drawRect) return;
    const {
      displayWidth,
      displayHeight,
      sourceWidth,
      sourceHeight,
      drawWidth,
      drawHeight,
      offsetX,
      offsetY,
    } = drawRect;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    canvas.width = displayWidth;
    canvas.height = displayHeight;
    ctx.clearRect(0, 0, displayWidth, displayHeight);

    const clampedProgress = Math.max(0, Math.min(1, progress));
    const darkness = 1 - clampedProgress;
    const brightness = 0.1 + (0.9 * clampedProgress);
    const saturation = 0.08 + (0.92 * clampedProgress);
    const contrast = 0.7 + (0.3 * clampedProgress);

    ctx.filter = `brightness(${brightness}) saturate(${saturation}) contrast(${contrast})`;
    ctx.drawImage(image, 0, 0, sourceWidth, sourceHeight, offsetX, offsetY, drawWidth, drawHeight);
    ctx.filter = 'none';

    if (darkness > 0) {
      ctx.fillStyle = `rgba(0, 0, 0, ${Math.max(0, Math.min(0.84, darkness * 0.84))})`;
      ctx.fillRect(offsetX, offsetY, drawWidth, drawHeight);
    }
  }

  function resolveEffectDrawRect(image, canvas) {
    const imageStyle = window.getComputedStyle(image);
    const padLeft = Math.max(0, parseFloat(imageStyle.paddingLeft || '0') || 0);
    const padRight = Math.max(0, parseFloat(imageStyle.paddingRight || '0') || 0);
    const padTop = Math.max(0, parseFloat(imageStyle.paddingTop || '0') || 0);
    const padBottom = Math.max(0, parseFloat(imageStyle.paddingBottom || '0') || 0);
    const displayWidth = Math.max(1, Math.round(canvas.clientWidth || image.clientWidth || image.width || 1));
    const displayHeight = Math.max(1, Math.round(canvas.clientHeight || image.clientHeight || image.height || 1));
    const contentWidth = Math.max(1, Math.round(displayWidth - padLeft - padRight));
    const contentHeight = Math.max(1, Math.round(displayHeight - padTop - padBottom));
    const sourceWidth = Math.max(1, image.naturalWidth || image.width || displayWidth);
    const sourceHeight = Math.max(1, image.naturalHeight || image.height || displayHeight);
    const containScale = Math.min(contentWidth / sourceWidth, contentHeight / sourceHeight);
    const drawWidth = Math.max(1, Math.round(sourceWidth * containScale));
    const drawHeight = Math.max(1, Math.round(sourceHeight * containScale));
    const offsetX = Math.round(padLeft + ((contentWidth - drawWidth) / 2));
    const offsetY = Math.round(padTop + ((contentHeight - drawHeight) / 2));

    return {
      displayWidth,
      displayHeight,
      sourceWidth,
      sourceHeight,
      drawWidth,
      drawHeight,
      offsetX,
      offsetY,
    };
  }

  function startPixelateRender(domanda, forceClear = false) {
    return startVisualImageEffect(domanda, 'pixelate', forceClear);
  }

  function startFadeRender(domanda, forceClear = false) {
    return startVisualImageEffect(domanda, 'fade', forceClear);
  }

  function startVisualImageEffect(domanda, effect, forceClear = false) {
    const { image, canvas } = getDomandaMediaNodes();
    if (!image || !canvas) return;

    stopPixelateRender();

    if (forceClear) {
      finishPixelateReveal(image, canvas);
      return;
    }

    const tick = () => {
      if (!(image.complete && image.naturalWidth > 0)) {
        S.pixelateTimer = window.requestAnimationFrame(tick);
        return;
      }

      const totalSec = Math.max(1, Number(ScreenApp.store.currentTimerMax || 10));
      const startSec = Number(S.currentTimerStart || 0);
      const elapsedSec = startSec > 0 ? Math.max(0, (Date.now() / 1000) - startSec) : 0;
      const shouldShowRealImage = !!domanda?.show_correct || elapsedSec >= totalSec;

      if (shouldShowRealImage) {
        stopPixelateRender();
        finishPixelateReveal(image, canvas);
        return;
      }

      if (effect === 'fade') {
        const progress = resolveFadeProgress(elapsedSec, totalSec, false);
        drawFadeImage(image, canvas, progress);
      } else {
        const blockSize = resolvePixelBlockSize(elapsedSec, totalSec, false);
        drawPixelatedImage(image, canvas, blockSize);
      }
      canvas.classList.remove('hidden');
      image.classList.add('hidden');
      setPixelateBlend(image, canvas, 0, 1);
      S.pixelateTimer = window.requestAnimationFrame(tick);
    };

    tick();
  }

  function renderQuestionMediaForState(domanda, isImpostoreMasked, isSarabandaIntro, isImageParty, isFadeMode) {
    if (isImpostoreMasked) {
      renderDomandaMedia(domanda, false, {
        media_image_path: '/assets/img/player/impostore-fake.svg',
        media_caption: Copy.impostoreMaskedCaption || 'Immagine mascherata per la modalita impostore',
      });
      return;
    }

    if (isImageParty) {
      renderDomandaMedia(domanda, false, { is_image_party: true });
      return;
    }

    if (isFadeMode) {
      renderDomandaMedia(domanda, false, { is_fade_mode: true });
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
    const hasMemeDecoratedOptions = Array.isArray(domanda?.opzioni)
      && domanda.opzioni.some((opzione) => String(opzione?.display_text || '') !== '');

    return {
      tipoDomanda,
      isSarabandaIntro: tipoDomanda === 'SARABANDA' && String(S.currentState || '') === 'preview',
      isImpostoreMasked: !!domanda?.impostore_masked,
      isImageParty: tipoDomanda === 'IMAGE_PARTY' && String(domanda?.media_image_path || '').trim() !== '',
      isFadeMode: tipoDomanda === 'FADE' && String(domanda?.media_image_path || '').trim() !== '',
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
