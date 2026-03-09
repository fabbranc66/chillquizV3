// 07a_domanda_render.js
(function () {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const { isDomandaAttiva } = Player.utils;
  const Alert = Player.uiAlert;
  const Copy = Player.copy;
  const Support = Player.domandaSupport;

  function bindImmediateAnswer(button, domandaId, opzioneId) {
    const handleAnswer = (event) => {
      event.preventDefault();
      inviaRisposta(domandaId, opzioneId);
    };

    button.onclick = null;
    button.addEventListener('pointerdown', handleAnswer, { once: true });
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

  function renderDomandaMedia(domanda, imageOnly = false, overrides = {}) {
    const { wrap, image, canvas, audio, caption } = Support.getMediaNodes();
    if (!wrap) return;

    const mediaPath = String(overrides.media_image_path || domanda?.media_image_path || '').trim();
    const imageUrl = Support.resolveMediaUrl(mediaPath);
    const captionText = imageOnly
      ? ''
      : String(overrides.media_caption ?? domanda?.media_caption ?? '').trim();

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
      if (overrides.is_image_party || overrides.is_fade_mode) {
        image.classList.remove('hidden');
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
    wrap.classList.toggle('is-image-party', !!overrides.is_image_party || !!overrides.is_fade_mode);

    if ((overrides.is_image_party || overrides.is_fade_mode) && imageUrl) {
      if (overrides.is_fade_mode) {
        Support.startFadeRender(domanda, !!domanda?.show_correct);
      } else {
        Support.startPixelateRender(domanda, !!domanda?.show_correct);
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
    const baseOptions = Array.isArray(domanda?.opzioni) ? domanda.opzioni : [];
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
        btn.dataset.id = String(opzione?.id || '');
        btn.dataset.letter = letter;
        btn.classList.add(`opzione-kahoot-${palette}`, 'meme-letter-button');

        if (showCorrect) {
          btn.disabled = true;
          if (btn.dataset.id === correctOptionId) {
            btn.classList.add('is-correct-reveal');
          } else {
            btn.classList.add('is-reveal-dim');
          }
          if (selectedDomandaId === Number(domanda?.id || 0) && btn.dataset.id === selectedOptionId) {
            btn.classList.add('is-player-choice-reveal');
          }
        } else {
          bindImmediateAnswer(btn, domanda.id, opzione.id);
        }

        D.opzioniDiv.appendChild(btn);
      });
    };

    applyStep();
    Support.stopMemeRotation();
    if (!showCorrect) {
      const rotationMs = Math.max(100, Number(domanda?.meme_rotation_ms || 300));
      S.memeRotationTimer = window.setInterval(applyStep, rotationMs);
    }
  }

  function resetDomandaView() {
    Support.stopMemeRotation();
    Support.stopPixelateRender();
    S.renderedDomandaKey = '';
    if (D.domandaTesto) D.domandaTesto.innerText = '';
    clearStatusMessage();
    if (D.opzioniDiv) D.opzioniDiv.innerHTML = '';
    Support.resetDomandaMedia();
    Support.clearQuestionTypeBadge();
  }

  async function fetchDomanda() {
    if (!isDomandaAttiva(S.currentState)) return;

    const requestNonce = ++S.domandaFetchNonce;

    try {
      const response = await fetch(Support.buildDomandaRequestUrl());
      const data = await response.json();

      if (!data.success) return;
      if (!isDomandaAttiva(S.currentState)) return;
      if (requestNonce !== S.domandaFetchNonce) return;

      renderDomanda(data.domanda);
    } catch (err) {
      console.error(err);
    }
  }

  async function fetchTipoDomandaBadge() {
    if (!S.sessioneId) {
      Support.clearQuestionTypeBadge();
      return;
    }

    try {
      const response = await fetch(Support.buildDomandaRequestUrl());
      const data = await response.json();

      if (!data.success || !data.domanda) {
        return;
      }

      const domandaId = Number(data.domanda.id || 0);
      const tipoDomanda = Support.normalizeBadgeQuestionType(data.domanda);

      if (S.badgeQuestionId === domandaId && S.badgeTipoDomanda === tipoDomanda) {
        return;
      }

      S.badgeQuestionId = domandaId;
      S.badgeTipoDomanda = tipoDomanda;
      Support.renderQuestionTypeBadge(tipoDomanda);
    } catch (err) {
      console.error(err);
    }
  }

  function renderDomanda(domanda) {
    if (!isDomandaAttiva(S.currentState)) return;

    if (!domanda || !Array.isArray(domanda.opzioni)) {
      resetDomandaView();
      return;
    }

    if (!D.domandaTesto || !D.opzioniDiv) return;

    const renderKey = Support.buildDomandaRenderKey(domanda);
    if (renderKey !== '' && renderKey === S.renderedDomandaKey) {
      return;
    }

    const domandaId = Number(domanda.id || 0);
    const tipoDomanda = Support.normalizeBadgeQuestionType(domanda);
    const nowSec = Math.floor(Date.now() / 1000);
    const isSarabandaIntro = tipoDomanda === 'SARABANDA' && (Number(S.domandaTimerStart || 0) <= 0 || nowSec < Number(S.domandaTimerStart || 0));
    const showCorrect = !!domanda.show_correct;
    const correctOptionId = String(domanda.correct_option_id || '');
    const isImpostoreMasked = !!domanda.impostore_masked;
    const isImpostore = !!domanda.is_impostore;
    const isImageParty = tipoDomanda === 'IMAGE_PARTY' && String(domanda.media_image_path || '').trim() !== '';
    const isFadeMode = tipoDomanda === 'FADE' && String(domanda.media_image_path || '').trim() !== '';
    const hasMemeDecoratedOptions = Array.isArray(domanda.opzioni)
      && domanda.opzioni.some((opzione) => String(opzione?.display_text || '') !== '');
    const isMemeMode = !!domanda.meme_mode || tipoDomanda === 'MEME' || hasMemeDecoratedOptions;

    D.domandaTesto.innerText = (isSarabandaIntro || isMemeMode || isImpostoreMasked || isImageParty || isFadeMode) ? '' : (domanda.testo || '');
    renderStatusMessage(domanda, isMemeMode, isImpostoreMasked, isImpostore, isImageParty, isFadeMode);

    S.badgeQuestionId = domandaId;
    S.badgeTipoDomanda = tipoDomanda;
    Support.renderQuestionTypeBadge(tipoDomanda);

    if (isImpostoreMasked) {
      renderDomandaMedia(domanda, false, {
        media_image_path: '/assets/img/player/impostore-fake.svg',
        media_caption: 'Immagine mascherata per l\'impostore',
      });
    } else if (isImageParty) {
      renderDomandaMedia(domanda, false, { is_image_party: true });
    } else if (isFadeMode) {
      renderDomandaMedia(domanda, false, { is_fade_mode: true });
    } else if (isSarabandaIntro) {
      renderDomandaMedia(domanda, true);
    } else {
      renderDomandaMedia(domanda, false);
    }

    D.opzioniDiv.innerHTML = '';

    if (isSarabandaIntro) {
      S.questionShownAtPerf = 0;
      S.questionShownDomandaId = domandaId;
      S.questionShownTimerStart = Number(S.domandaTimerStart || 0);
      return;
    }

    const timerStart = Number(S.domandaTimerStart || 0);
    if (Support.shouldMarkQuestionShown(domandaId, timerStart)) {
      Support.markQuestionShown(domandaId, timerStart);
    }

    if (isMemeMode) {
      S.renderedDomandaKey = renderKey;
      renderMemeButtons(domanda, showCorrect, correctOptionId);
      return;
    }

    domanda.opzioni.forEach((opzione, index) => {
      const btn = document.createElement('button');
      btn.innerText = opzione.testo || '';
      btn.dataset.id = String(opzione.id || '');

      const paletteIndex = (index % 4) + 1;
      btn.classList.add(`opzione-kahoot-${paletteIndex}`);

      if (showCorrect) {
        btn.disabled = true;
        if (btn.dataset.id === correctOptionId) {
          btn.classList.add('is-correct-reveal');
        } else {
          btn.classList.add('is-reveal-dim');
        }
        if (
          Number(S.selectedAnswerDomandaId || 0) === domandaId
          && btn.dataset.id === String(S.selectedAnswerOptionId || '')
        ) {
          btn.classList.add('is-player-choice-reveal');
        }
      } else {
        bindImmediateAnswer(btn, domanda.id, opzione.id);
      }

      D.opzioniDiv.appendChild(btn);
    });

    S.renderedDomandaKey = renderKey;
  }

  async function inviaRisposta(domandaId, opzioneId) {
    if (S.rispostaInviata) return;
    if (!S.puntataInviata) {
      Alert.show({
        title: Copy.betRequiredBeforeAnswerTitle,
        message: Copy.betRequiredBeforeAnswerMessage,
        tone: 'warn',
      });
      return;
    }
    S.rispostaInviata = true;
    Support.stopMemeRotation();
    Support.freezeAnsweredQuestion(domandaId, opzioneId);

    const buttons = document.querySelectorAll('#opzioni button');
    buttons.forEach((btn) => {
      btn.disabled = true;
      if (String(btn.dataset.id) === String(opzioneId)) {
        btn.classList.add('selected');
      } else {
        btn.classList.add('dimmed');
      }
    });

    try {
      const formData = new FormData();
      formData.append('partecipazione_id', String(S.partecipazioneId || 0));
      formData.append('domanda_id', String(domandaId || 0));
      formData.append('opzione_id', String(opzioneId || 0));

      const perfNow = (typeof performance !== 'undefined' && typeof performance.now === 'function')
        ? performance.now()
        : Date.now();
      const shownAt = Number(S.questionShownAtPerf || 0);
      if (shownAt > 0 && perfNow >= shownAt) {
        const elapsedClient = Math.max(0, ((perfNow - shownAt) / 1000));
        formData.append('tempo_client', elapsedClient.toFixed(3));
      }

      const response = await fetch(`${S.API_BASE}/risposta/${S.sessioneId || 0}`, {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (!data.success) {
        Alert.show({
          title: Copy.answerFailedTitle,
          message: data.error || Copy.answerFailedMessage,
          tone: 'error',
        });
        S.rispostaInviata = false;
        return;
      }

      Alert.hide();
      Support.stopMemeRotation();
      Player.classifica.setImmediateResult?.(data.risultato);
      Player.classifica.renderRisultatoPersonaleImmediato(data.risultato);
    } catch (err) {
      console.error(err);
      Alert.show({
        title: Copy.networkErrorTitle,
        message: Copy.answerNetworkErrorMessage,
        tone: 'error',
      });
      S.rispostaInviata = false;
    }
  }

  Player.domanda = {
    fetchDomanda,
    fetchTipoDomandaBadge,
    renderDomanda,
    resetDomandaView,
    inviaRisposta,
    resetDomandaMedia: Support.resetDomandaMedia,
    clearQuestionTypeBadge: Support.clearQuestionTypeBadge,
  };
})();
