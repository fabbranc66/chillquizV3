// 07b_domanda_render_support.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const Copy = Player.copy;
  const Support = Player.domandaSupport;

  function persistDebugTiming() {
    try {
      if (Number(S.sessioneId || 0) <= 0) return;
      window.localStorage.setItem(
        `chillquiz_debug_timing_player_${Number(S.sessioneId || 0)}`,
        JSON.stringify(S.debugTiming || {})
      );
    } catch (err) {
      console.warn(err);
    }
  }

  function markOptionsShown(domandaId) {
    const currentDomandaId = Number(domandaId || 0);
    if (currentDomandaId <= 0) return;

    if (Number((S.debugTiming && S.debugTiming.domandaId) || 0) !== currentDomandaId) {
      S.debugTiming = {
        domandaId: currentDomandaId,
        timerStartedAtMs: 0,
        optionsShownAtMs: 0,
        deltaMs: null,
      };
    }

    if (Number(S.debugTiming.optionsShownAtMs || 0) > 0) return;

    S.debugTiming.optionsShownAtMs = Date.now();
    if (Number(S.debugTiming.timerStartedAtMs || 0) > 0) {
      S.debugTiming.deltaMs = S.debugTiming.optionsShownAtMs - S.debugTiming.timerStartedAtMs;
    }
    persistDebugTiming();
    console.info('[player] options-shown', S.debugTiming);
  }

  function bindImmediateAnswer(button, domandaId, opzioneId) {
    const handleAnswer = (event) => {
      event.preventDefault();
      if (Player.domanda && typeof Player.domanda.inviaRisposta === 'function') {
        Player.domanda.inviaRisposta(domandaId, opzioneId);
      }
    };

    button.onclick = null;
    button.addEventListener('pointerdown', handleAnswer, { once: true });
  }

  function clearOptionRevealTimer() {
    if (S.optionRevealTimer) {
      clearTimeout(S.optionRevealTimer);
      S.optionRevealTimer = null;
    }
  }

  function clearAndHideOptions() {
    if (!D.opzioniDiv) return;
    D.opzioniDiv.innerHTML = '';
    D.opzioniDiv.classList.add('hidden');
    D.opzioniDiv.classList.remove('is-pending-reveal');
    D.opzioniDiv.style.opacity = '';
    D.opzioniDiv.style.visibility = '';
    D.opzioniDiv.style.pointerEvents = '';
  }

  function clearStatusMessage() {
    if (!D.domandaStatusMessage) {
      return;
    }

    D.domandaStatusMessage.innerText = '';
    D.domandaStatusMessage.classList.add('hidden');
    D.domandaStatusMessage.classList.remove('is-impostore');
    D.domandaStatusMessage.classList.remove('is-meme');
    D.domandaStatusMessage.classList.remove('is-image-party');
  }

  function renderStatusMessage(domanda, isMemeMode, isImpostoreMasked, isImpostore, isImageParty, isFadeMode) {
    if (!D.domandaStatusMessage) {
      return;
    }

    if (isMemeMode) {
      D.domandaStatusMessage.innerText = String(domanda.meme_player_notice || Copy.memePlayerNotice);
      D.domandaStatusMessage.classList.remove('hidden');
      D.domandaStatusMessage.classList.add('is-meme');
      D.domandaStatusMessage.classList.remove('is-impostore');
      return;
    }

    if (isImageParty || isFadeMode) {
      D.domandaStatusMessage.innerText = String(
        isFadeMode
          ? (domanda.fade_notice || Copy.fadeNotice)
          : (domanda.image_party_notice || Copy.imagePartyNotice)
      );
      D.domandaStatusMessage.classList.remove('hidden');
      D.domandaStatusMessage.classList.add('is-image-party');
      D.domandaStatusMessage.classList.remove('is-impostore');
      D.domandaStatusMessage.classList.remove('is-meme');
      return;
    }

    if (isImpostoreMasked) {
      D.domandaStatusMessage.innerText = String(domanda.impostore_notice || Copy.impostoreMaskedNotice);
      D.domandaStatusMessage.classList.remove('hidden');
      D.domandaStatusMessage.classList.add('is-impostore');
      D.domandaStatusMessage.classList.remove('is-meme');
      return;
    }

    if (isImpostore) {
      D.domandaStatusMessage.innerText = Copy.impostoreMaskedFallback;
      D.domandaStatusMessage.classList.remove('hidden');
      D.domandaStatusMessage.classList.add('is-impostore');
      D.domandaStatusMessage.classList.remove('is-meme');
      return;
    }

    clearStatusMessage();
  }

  function renderDomandaMedia(domanda, imageOnly, overrides) {
    const { wrap, image, canvas, audio, caption } = Support.getMediaNodes();
    if (!wrap) return;

    const safeOverrides = overrides || {};
    const mediaPath = String(safeOverrides.media_image_path || (domanda && domanda.media_image_path) || '').trim();
    const imageUrl = Support.resolveMediaUrl(mediaPath);
    const captionText = imageOnly
      ? ''
      : String(
        safeOverrides.media_caption !== undefined
          ? safeOverrides.media_caption
          : ((domanda && domanda.media_caption) !== undefined ? domanda.media_caption : '')
      ).trim();

    let hasAny = false;

    if (image && imageUrl) {
      if (String(image.getAttribute('src') || '') !== imageUrl) {
        image.src = imageUrl;
      }
      image.onerror = () => {
        image.removeAttribute('src');
        image.classList.add('hidden');
        if (canvas) canvas.classList.add('hidden');
        wrap.classList.add('media-slot-empty');
        wrap.classList.remove('has-media');
      };
      image.style.opacity = '1';
      if (canvas) {
        canvas.style.opacity = '1';
      }
      if (safeOverrides.is_image_party || safeOverrides.is_fade_mode) {
        image.classList.add('hidden');
        image.style.opacity = '0';
      } else {
        image.style.opacity = '1';
        image.classList.remove('hidden');
      }
      S.lastMediaUrl = imageUrl;
      hasAny = true;
    } else if (image) {
      image.onerror = null;
      image.removeAttribute('src');
      image.style.opacity = '1';
      image.classList.add('hidden');
      if (canvas) {
        canvas.style.opacity = '1';
        canvas.classList.add('hidden');
      }
      S.lastMediaUrl = '';
    }

    if (audio) {
      audio.pause();
      audio.removeAttribute('src');
      audio.load();
      audio.classList.add('hidden');
      audio.onplay = null;
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
    wrap.classList.toggle('is-image-party', !!safeOverrides.is_image_party || !!safeOverrides.is_fade_mode);

    if ((safeOverrides.is_image_party || safeOverrides.is_fade_mode) && imageUrl) {
      if (safeOverrides.is_fade_mode) {
        Support.startFadeRender(domanda, !!(domanda && domanda.show_correct));
      } else {
        Support.startPixelateRender(domanda, !!(domanda && domanda.show_correct));
      }
    } else {
      Support.stopPixelateRender();
      if (canvas) {
        canvas.style.opacity = '1';
        canvas.classList.add('hidden');
      }
      if (image && imageUrl) {
        image.style.opacity = '1';
        image.classList.remove('hidden');
      }
    }
  }

  function renderMemeButtons(domanda, showCorrect, correctOptionId) {
    const baseOptions = Array.isArray(domanda && domanda.opzioni) ? domanda.opzioni : [];
    const letters = ['A', 'B', 'C', 'D'];
    const selectedDomandaId = Number(S.selectedAnswerDomandaId || 0);
    const selectedOptionId = String(S.selectedAnswerOptionId || '');

    const applyStep = () => {
      const step = showCorrect ? 0 : Support.getMemeRotationStep(domanda);
      if (!showCorrect && step === S.memeRotationStep) {
        return;
      }

      S.memeRotationStep = step;
      D.opzioniDiv.innerHTML = '';

      const descriptors = Support.buildMemeButtonDescriptors(baseOptions, step, letters);

      descriptors.forEach(({ opzione, palette, letter }) => {
        const btn = document.createElement('button');
        btn.innerText = letter;
        btn.dataset.id = String((opzione && opzione.id) || '');
        btn.dataset.letter = letter;
        btn.classList.add(`opzione-kahoot-${palette}`, 'meme-letter-button');

        if (showCorrect) {
          btn.disabled = true;
          if (btn.dataset.id === correctOptionId) {
            btn.classList.add('is-correct-reveal');
          } else {
            btn.classList.add('is-reveal-dim');
          }
          if (selectedDomandaId === Number((domanda && domanda.id) || 0) && btn.dataset.id === selectedOptionId) {
            btn.classList.add('is-player-choice-reveal');
          }
        } else {
          bindImmediateAnswer(btn, domanda.id, opzione.id);
        }

        D.opzioniDiv.appendChild(btn);
      });
    };

    applyStep();
    if (!showCorrect) {
      markOptionsShown(domanda && domanda.id);
    }
    Support.stopMemeRotation();
    if (!showCorrect) {
      const rotationMs = Math.max(100, Number((domanda && domanda.meme_rotation_ms) || 300));
      S.memeRotationTimer = window.setInterval(applyStep, rotationMs);
    }
  }

  Player.domandaRenderSupport = {
    bindImmediateAnswer,
    clearAndHideOptions,
    clearOptionRevealTimer,
    clearStatusMessage,
    markOptionsShown,
    persistDebugTiming,
    renderDomandaMedia,
    renderMemeButtons,
    renderStatusMessage,
  };
})();
