// 07a_domanda_render.js
(function () {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const { isDomandaAttiva } = Player.utils;
  const Alert = Player.uiAlert;
  const Support = Player.domandaSupport;

  function renderDomandaMedia(domanda, imageOnly = false) {
    const { wrap, image, audio, caption } = Support.getMediaNodes();
    if (!wrap) return;

    const imageUrl = Support.resolveMediaUrl(domanda?.media_image_path);
    const captionText = imageOnly ? '' : String(domanda?.media_caption || '').trim();

    let hasAny = false;

    if (image && imageUrl) {
      if (S.lastMediaUrl !== imageUrl) {
        image.onerror = () => {
          image.removeAttribute('src');
          image.classList.add('hidden');
          wrap.classList.add('media-slot-empty');
          wrap.classList.remove('has-media');
        };
        image.src = imageUrl;
        S.lastMediaUrl = imageUrl;
      }
      image.classList.remove('hidden');
      hasAny = true;
    } else if (image) {
      image.onerror = null;
      image.removeAttribute('src');
      image.classList.add('hidden');
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
  }

  function renderMemeButtons(domanda, showCorrect, correctOptionId) {
    const baseOptions = Array.isArray(domanda?.opzioni) ? domanda.opzioni : [];
    const letters = ['A', 'B', 'C', 'D'];
    const applyStep = () => {
      const step = showCorrect ? 0 : Support.getMemeRotationStep(domanda);
      if (!showCorrect && step === S.memeRotationStep) {
        return;
      }

      S.memeRotationStep = step;
      const slotCount = Math.max(1, letters.length);
      D.opzioniDiv.innerHTML = '';

      const descriptors = baseOptions.map((opzione, index) => {
        const positionIndex = ((index + step) % slotCount + slotCount) % slotCount;
        return {
          opzione,
          index,
          positionIndex,
          palette: (positionIndex % 4) + 1,
          letter: letters[index] || String(index + 1),
        };
      }).sort((left, right) => left.positionIndex - right.positionIndex);

      descriptors.forEach(({ opzione, index, palette, letter }) => {
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
        } else {
          btn.onclick = () => inviaRisposta(domanda.id, opzione.id);
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
    S.renderedDomandaKey = '';
    if (D.domandaTesto) D.domandaTesto.innerText = '';
    if (D.domandaStatusMessage) {
      D.domandaStatusMessage.innerText = '';
      D.domandaStatusMessage.classList.add('hidden');
      D.domandaStatusMessage.classList.remove('is-impostore');
      D.domandaStatusMessage.classList.remove('is-meme');
    }
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
    const hasMemeDecoratedOptions = Array.isArray(domanda.opzioni)
      && domanda.opzioni.some((opzione) => String(opzione?.display_text || '') !== '');
    const isMemeMode = !!domanda.meme_mode || tipoDomanda === 'MEME' || hasMemeDecoratedOptions;

    D.domandaTesto.innerText = (isSarabandaIntro || isMemeMode || isImpostoreMasked) ? '' : (domanda.testo || '');
    if (D.domandaStatusMessage) {
      if (isMemeMode) {
        D.domandaStatusMessage.innerText = String(domanda.meme_player_notice || 'Modalita\' MEME: premi A/B/C/D mentre le associazioni ruotano.');
        D.domandaStatusMessage.classList.remove('hidden');
        D.domandaStatusMessage.classList.add('is-meme');
        D.domandaStatusMessage.classList.remove('is-impostore');
      } else if (isImpostoreMasked) {
        D.domandaStatusMessage.innerText = String(domanda.impostore_notice || 'Sei l\'impostore: osserva gli altri e deduci la risposta.');
        D.domandaStatusMessage.classList.remove('hidden');
        D.domandaStatusMessage.classList.add('is-impostore');
        D.domandaStatusMessage.classList.remove('is-meme');
      } else if (isImpostore) {
        D.domandaStatusMessage.innerText = 'Sei l\'impostore, ma in questa vista la domanda e\' mascherata.';
        D.domandaStatusMessage.classList.remove('hidden');
        D.domandaStatusMessage.classList.add('is-impostore');
        D.domandaStatusMessage.classList.remove('is-meme');
      } else {
        D.domandaStatusMessage.innerText = '';
        D.domandaStatusMessage.classList.add('hidden');
        D.domandaStatusMessage.classList.remove('is-impostore');
        D.domandaStatusMessage.classList.remove('is-meme');
      }
    }

    S.badgeQuestionId = domandaId;
    S.badgeTipoDomanda = tipoDomanda;
    Support.renderQuestionTypeBadge(tipoDomanda);

    if (isImpostoreMasked) {
      Support.resetDomandaMedia();
      const mediaNodes = Support.getMediaNodes();
      if (mediaNodes.wrap) mediaNodes.wrap.classList.add('hidden');
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
    if (
      S.questionShownDomandaId !== domandaId
      || S.questionShownTimerStart !== timerStart
      || !Number.isFinite(S.questionShownAtPerf)
      || S.questionShownAtPerf <= 0
    ) {
      S.questionShownAtPerf = (typeof performance !== 'undefined' && typeof performance.now === 'function')
        ? performance.now()
        : Date.now();
      S.questionShownDomandaId = domandaId;
      S.questionShownTimerStart = timerStart;
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
      } else {
        btn.onclick = () => inviaRisposta(domanda.id, opzione.id);
      }

      D.opzioniDiv.appendChild(btn);
    });

    S.renderedDomandaKey = renderKey;
  }

  async function inviaRisposta(domandaId, opzioneId) {
    if (S.rispostaInviata) return;
    S.rispostaInviata = true;
    Support.stopMemeRotation();
    S.renderedDomandaKey = `answered||${Number(domandaId || 0)}||${String(opzioneId || '')}`;

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
          title: 'Risposta non inviata',
          message: data.error || 'Errore invio risposta.',
          tone: 'error',
        });
        S.rispostaInviata = false;
        return;
      }

      Alert.hide();
      Support.stopMemeRotation();
      Player.classifica.renderRisultatoPersonaleImmediato(data.risultato);
    } catch (err) {
      console.error(err);
      Alert.show({
        title: 'Errore di rete',
        message: 'Impossibile inviare la risposta.',
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
