// 07_domanda.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const { isDomandaAttiva } = Player.utils;

  const PUBLIC_BASE = String(S.API_BASE || '').replace(/\?url=api.*$/i, '');
  const QUESTION_TYPE_ICON_MAP = {
    CLASSIC: 'assets/img/question-types/classic.png',
    MEDIA: 'assets/img/question-types/classic.png',
    SARABANDA: 'assets/img/question-types/sarabanda.png',
    IMPOSTORE: 'assets/img/question-types/impostore.png',
    MEME: 'assets/img/question-types/meme.png',
    MAJORITY: 'assets/img/question-types/majority.png',
    RANDOM_BONUS: 'assets/img/question-types/random_bonus.png',
    BOMB: 'assets/img/question-types/bomb.png',
    CHAOS: 'assets/img/question-types/chaos.png',
    AUDIO_PARTY: 'assets/img/question-types/audio_party.png',
    IMAGE_PARTY: 'assets/img/question-types/image_party.png',
  };

  function resolveMediaUrl(path) {
    const raw = String(path || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw) || raw.startsWith('data:')) return raw;

    const normalizedPath = raw.startsWith('/') ? raw.slice(1) : raw;
    if (!PUBLIC_BASE) return `/${normalizedPath}`;

    const base = PUBLIC_BASE.endsWith('/') ? PUBLIC_BASE : `${PUBLIC_BASE}/`;
    return `${base}${normalizedPath}`;
  }

  function getMediaNodes() {
    return {
      wrap: document.getElementById('domanda-media-player'),
      image: document.getElementById('domanda-media-image-player'),
      audio: document.getElementById('domanda-media-audio-player'),
      caption: document.getElementById('domanda-media-caption-player'),
    };
  }

  function clearQuestionTypeBadge() {
    S.badgeQuestionId = 0;
    S.badgeTipoDomanda = '';
    if (D.questionTypeBadgeImagePlayer) {
      D.questionTypeBadgeImagePlayer.removeAttribute('src');
      D.questionTypeBadgeImagePlayer.classList.add('hidden');
    }
    if (D.questionTypeBadgePlayer) {
      D.questionTypeBadgePlayer.classList.add('hidden');
    }
  }

  function renderQuestionTypeBadge(tipoDomandaRaw) {
    const tipo = String(tipoDomandaRaw || 'CLASSIC').trim().toUpperCase();
    const rel = QUESTION_TYPE_ICON_MAP[tipo] || '';
    if (!rel || !D.questionTypeBadgePlayer || !D.questionTypeBadgeImagePlayer) {
      clearQuestionTypeBadge();
      return;
    }

    const src = resolveMediaUrl(rel);
    if (D.questionTypeBadgeImagePlayer.src === src && !D.questionTypeBadgeImagePlayer.classList.contains('hidden')) {
      if (D.questionTypeBadgePlayer) D.questionTypeBadgePlayer.classList.remove('hidden');
      return;
    }

    D.questionTypeBadgeImagePlayer.onerror = () => clearQuestionTypeBadge();
    D.questionTypeBadgeImagePlayer.src = src;
    D.questionTypeBadgeImagePlayer.classList.remove('hidden');
    D.questionTypeBadgePlayer.classList.remove('hidden');
  }

  function normalizeBadgeQuestionType(domanda) {
    const tipo = String(domanda?.tipo_domanda || 'CLASSIC').trim().toUpperCase();
    const hasAudio = String(domanda?.media_audio_path || '').trim() !== '';
    if (tipo === 'SARABANDA' && !hasAudio) return 'CLASSIC';
    return tipo || 'CLASSIC';
  }

  function resetDomandaMedia() {
    const { wrap, image, audio, caption } = getMediaNodes();

    if (image) {
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

  function renderDomandaMedia(domanda, imageOnly = false) {
    const { wrap, image, audio, caption } = getMediaNodes();
    if (!wrap) return;

    const imageUrl = resolveMediaUrl(domanda?.media_image_path);
    const captionText = imageOnly ? '' : String(domanda?.media_caption || '').trim();

    let hasAny = false;

    if (image && imageUrl) {
      image.src = imageUrl;
      image.classList.remove('hidden');
      hasAny = true;
    } else if (image) {
      image.removeAttribute('src');
      image.classList.add('hidden');
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

  function resetDomandaView() {
    if (D.domandaTesto) D.domandaTesto.innerText = '';
    if (D.domandaStatusMessage) {
      D.domandaStatusMessage.innerText = '';
      D.domandaStatusMessage.classList.add('hidden');
      D.domandaStatusMessage.classList.remove('is-impostore');
    }
    if (D.opzioniDiv) D.opzioniDiv.innerHTML = '';
    resetDomandaMedia();
    clearQuestionTypeBadge();
  }

  function buildDomandaRequestUrl() {
    const url = new URL(`${S.API_BASE}/domanda/${S.sessioneId || 0}`, window.location.origin);
    url.searchParams.set('viewer', 'player');
    if (Number(S.partecipazioneId || 0) > 0) {
      url.searchParams.set('partecipazione_id', String(Number(S.partecipazioneId || 0)));
    }
    return url.toString();
  }

  async function fetchDomanda() {
    if (!isDomandaAttiva(S.currentState)) return;

    const requestNonce = ++S.domandaFetchNonce;

    try {
      const response = await fetch(buildDomandaRequestUrl());
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
      clearQuestionTypeBadge();
      return;
    }

    try {
      const response = await fetch(buildDomandaRequestUrl());
      const data = await response.json();

      if (!data.success || !data.domanda) {
        return;
      }

      const domandaId = Number(data.domanda.id || 0);
      const tipoDomanda = normalizeBadgeQuestionType(data.domanda);

      if (S.badgeQuestionId === domandaId && S.badgeTipoDomanda === tipoDomanda) {
        return;
      }

      S.badgeQuestionId = domandaId;
      S.badgeTipoDomanda = tipoDomanda;
      renderQuestionTypeBadge(tipoDomanda);
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

    const domandaId = Number(domanda.id || 0);
    const tipoDomanda = normalizeBadgeQuestionType(domanda);
    const nowSec = Math.floor(Date.now() / 1000);
    const isSarabandaIntro = tipoDomanda === 'SARABANDA' && (Number(S.domandaTimerStart || 0) <= 0 || nowSec < Number(S.domandaTimerStart || 0));
    const showCorrect = !!domanda.show_correct;
    const correctOptionId = String(domanda.correct_option_id || '');
    const isImpostoreMasked = !!domanda.impostore_masked;
    const isImpostore = !!domanda.is_impostore;

    D.domandaTesto.innerText = isSarabandaIntro ? '' : (domanda.testo || '');
    if (D.domandaStatusMessage) {
      if (isImpostoreMasked) {
        D.domandaStatusMessage.innerText = String(domanda.impostore_notice || 'Sei l\'impostore: osserva gli altri e deduci la risposta.');
        D.domandaStatusMessage.classList.remove('hidden');
        D.domandaStatusMessage.classList.add('is-impostore');
      } else if (isImpostore) {
        D.domandaStatusMessage.innerText = 'Sei l\'impostore, ma in questa vista la domanda e mascherata.';
        D.domandaStatusMessage.classList.remove('hidden');
        D.domandaStatusMessage.classList.add('is-impostore');
      } else {
        D.domandaStatusMessage.innerText = '';
        D.domandaStatusMessage.classList.add('hidden');
        D.domandaStatusMessage.classList.remove('is-impostore');
      }
    }
    S.badgeQuestionId = domandaId;
    S.badgeTipoDomanda = tipoDomanda;
    renderQuestionTypeBadge(tipoDomanda);
    if (isImpostoreMasked) {
      resetDomandaMedia();
      const mediaNodes = getMediaNodes();
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
  }

  async function inviaRisposta(domandaId, opzioneId) {
    if (S.rispostaInviata) return;
    S.rispostaInviata = true;

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
        alert(data.error || 'Errore invio risposta');
        S.rispostaInviata = false;
        return;
      }

      Player.classifica.renderRisultatoPersonaleImmediato(data.risultato);
    } catch (err) {
      console.error(err);
      S.rispostaInviata = false;
    }
  }

  Player.domanda = {
    fetchDomanda,
    fetchTipoDomandaBadge,
    renderDomanda,
    resetDomandaView,
    inviaRisposta,
    resetDomandaMedia,
    clearQuestionTypeBadge,
  };
})();

