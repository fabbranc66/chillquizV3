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
      audio: document.getElementById('domanda-media-audio-player'),
      caption: document.getElementById('domanda-media-caption-player'),
    };
  }

  function buildDomandaRenderKey(domanda) {
    if (!domanda || !Array.isArray(domanda.opzioni)) return '';
    const optionKey = domanda.opzioni.map((opzione) => [
      Number(opzione?.id || 0),
      String(opzione?.testo || ''),
      String(opzione?.display_text || ''),
      opzione?.is_meme_display ? '1' : '0',
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
    const tipo = String(domanda?.tipo_domanda || 'CLASSIC').trim().toUpperCase();
    const hasAudio = String(domanda?.media_audio_path || '').trim() !== '';
    if (tipo === 'SARABANDA' && !hasAudio) return 'CLASSIC';
    return tipo || 'CLASSIC';
  }

  function resetDomandaMedia() {
    const { wrap, image, audio, caption } = getMediaNodes();
    S.lastMediaUrl = '';

    if (image) {
      image.onerror = null;
      image.removeAttribute('src');
      image.classList.add('hidden');
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
    const rotationMs = Math.max(100, Number(domanda?.meme_rotation_ms || 300));
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
