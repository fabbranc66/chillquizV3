// 07_domanda.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const PUBLIC_BASE = String(S.PUBLIC_BASE_URL || '/');

  function resolveMediaUrl(path) {
    const raw = String(path || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw) || raw.startsWith('data:')) return raw;

    const normalizedPath = raw.startsWith('/') ? raw.slice(1) : raw;
    const basePath = PUBLIC_BASE.endsWith('/') ? PUBLIC_BASE : `${PUBLIC_BASE}/`;
    return `${window.location.origin}${basePath}${normalizedPath}`;
  }

  function getMediaNodes() {
    return {
      wrap: document.getElementById('domanda-media-player'),
      image: document.getElementById('domanda-media-image-player'),
      canvas: document.getElementById('domanda-media-canvas-player'),
      audio: document.getElementById('domanda-media-audio-player'),
      caption: document.getElementById('domanda-media-caption-player'),
    };
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

    const { canvas } = getMediaNodes();
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
    const displayWidth = Math.max(1, Math.round(canvas.clientWidth || image.clientWidth || image.width || 1));
    const displayHeight = Math.max(1, Math.round(canvas.clientHeight || image.clientHeight || image.height || 1));
    const sourceWidth = Math.max(1, image.naturalWidth || image.width || displayWidth);
    const sourceHeight = Math.max(1, image.naturalHeight || image.height || displayHeight);
    const containScale = Math.min(displayWidth / sourceWidth, displayHeight / sourceHeight);
    const drawWidth = Math.max(1, Math.round(sourceWidth * containScale));
    const drawHeight = Math.max(1, Math.round(sourceHeight * containScale));
    const offsetX = Math.round((displayWidth - drawWidth) / 2);
    const offsetY = Math.round((displayHeight - drawHeight) / 2);

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
    const { image, canvas } = getMediaNodes();
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

      const totalSec = Math.max(1, Number(S.domandaTimerMax || 10));
      const startSec = Number(S.domandaTimerStart || 0);
      const elapsedSec = startSec > 0 ? Math.max(0, (Date.now() / 1000) - startSec) : 0;
      const shouldShowRealImage = !!(domanda && domanda.show_correct) || elapsedSec >= totalSec;

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

  function buildDomandaRenderKey(domanda) {
    if (!domanda || !Array.isArray(domanda.opzioni)) return '';
    const optionKey = domanda.opzioni.map((opzione) => [
      Number((opzione && opzione.id) || 0),
      String((opzione && opzione.testo) || ''),
      String((opzione && opzione.display_text) || ''),
      (opzione && opzione.is_meme_display) ? '1' : '0',
    ].join(':')).join('|');

    return [
      Number(domanda.id || 0),
      String(domanda.tipo_domanda || ''),
      domanda.show_correct ? '1' : '0',
      String(domanda.correct_option_id || ''),
      domanda.impostore_masked ? '1' : '0',
      domanda.is_impostore ? '1' : '0',
      domanda.meme_mode ? '1' : '0',
      String(domanda.media_image_path || ''),
      String(domanda.media_caption || ''),
      String(S.domandaTimerStart || 0),
      optionKey,
    ].join('||');
  }

  function clearQuestionTypeBadge() {
    S.badgeQuestionId = 0;
    S.badgeTipoDomanda = '';
  }

  function renderQuestionTypeBadge() {
    clearQuestionTypeBadge();
  }

  function normalizeBadgeQuestionType(domanda) {
    const tipo = String((domanda && domanda.tipo_domanda) || 'CLASSIC').trim().toUpperCase();
    const hasAudio = String((domanda && domanda.media_audio_path) || '').trim() !== '';
    if (tipo === 'SARABANDA' && !hasAudio) return 'CLASSIC';
    return tipo || 'CLASSIC';
  }

  function resetDomandaMedia() {
    const { wrap, image, canvas, audio, caption } = getMediaNodes();
    S.lastMediaUrl = '';

    if (image) {
      image.onerror = null;
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
      audio.pause();
      audio.removeAttribute('src');
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

  function stopMemeRotation() {
    if (S.memeRotationTimer) {
      window.clearInterval(S.memeRotationTimer);
      S.memeRotationTimer = null;
    }
    S.memeRotationStep = -1;
  }

  function getMemeRotationStep(domanda) {
    const rotationMs = Math.max(100, Number((domanda && domanda.meme_rotation_ms) || 300));
    const start = Number(S.domandaTimerStart || 0);
    const elapsedMs = start > 0
      ? Math.max(0, Math.floor(((Date.now() / 1000) - start) * 1000))
      : 0;
    return Math.floor(elapsedMs / rotationMs);
  }

  function getMemeSlots(step) {
    const base = [
      { letter: 'A', palette: 1 },
      { letter: 'B', palette: 2 },
      { letter: 'C', palette: 3 },
      { letter: 'D', palette: 4 },
    ];
    const normalized = ((Number(step || 0) % base.length) + base.length) % base.length;
    if (normalized === 0) return base;
    return base.slice(normalized).concat(base.slice(0, normalized));
  }

  function buildDomandaRequestUrl() {
    const url = new URL(`${S.API_BASE}/domanda/${S.sessioneId || 0}`, window.location.origin);
    url.searchParams.set('viewer', 'player');
    if (Number(S.partecipazioneId || 0) > 0) {
      url.searchParams.set('partecipazione_id', String(Number(S.partecipazioneId || 0)));
    }
    return url.toString();
  }

  function shouldMarkQuestionShown(domandaId, timerStart) {
    return (
      S.questionShownDomandaId !== domandaId
      || S.questionShownTimerStart !== timerStart
      || !Number.isFinite(S.questionShownAtPerf)
      || S.questionShownAtPerf <= 0
    );
  }

  function markQuestionShown(domandaId, timerStart) {
    S.questionShownAtPerf = (typeof performance !== 'undefined' && typeof performance.now === 'function')
      ? performance.now()
      : Date.now();
    S.questionShownDomandaId = domandaId;
    S.questionShownTimerStart = timerStart;
  }

  function freezeAnsweredQuestion(domandaId, opzioneId) {
    S.selectedAnswerDomandaId = Number(domandaId || 0);
    S.selectedAnswerOptionId = Number(opzioneId || 0);
    S.renderedDomandaKey = `answered||${Number(domandaId || 0)}||${String(opzioneId || '')}`;
  }

  function buildMemeButtonDescriptors(baseOptions, step, letters = ['A', 'B', 'C', 'D']) {
    const slotCount = Math.max(1, letters.length);
    return baseOptions.map((opzione, index) => {
      const positionIndex = ((index + step) % slotCount + slotCount) % slotCount;
      return {
        opzione,
        positionIndex,
        palette: (index % 4) + 1,
        letter: letters[index] || String(index + 1),
      };
    }).sort((left, right) => left.positionIndex - right.positionIndex);
  }

  Player.domandaSupport = {
    resolveMediaUrl,
    getMediaNodes,
    buildDomandaRenderKey,
    clearQuestionTypeBadge,
    renderQuestionTypeBadge,
    normalizeBadgeQuestionType,
    resetDomandaMedia,
    stopPixelateRender,
    startPixelateRender,
    startFadeRender,
    stopMemeRotation,
    getMemeRotationStep,
    getMemeSlots,
    buildDomandaRequestUrl,
    shouldMarkQuestionShown,
    markQuestionShown,
    freezeAnsweredQuestion,
    buildMemeButtonDescriptors,
  };
})();
