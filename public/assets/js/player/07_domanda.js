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
    if (D.opzioniDiv) D.opzioniDiv.innerHTML = '';
    resetDomandaMedia();
    clearQuestionTypeBadge();
  }

  async function fetchDomanda() {
    if (!isDomandaAttiva(S.currentState)) return;

    const requestNonce = ++S.domandaFetchNonce;

    try {
      const response = await fetch(`${S.API_BASE}/domanda/${S.sessioneId || 0}`);
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
      const response = await fetch(`${S.API_BASE}/domanda/${S.sessioneId || 0}`);
      const data = await response.json();

      if (!data.success || !data.domanda) {
        return;
      }

      const domandaId = Number(data.domanda.id || 0);
      const tipoDomanda = String(data.domanda.tipo_domanda || 'CLASSIC').trim().toUpperCase();

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
    const tipoDomanda = String(domanda.tipo_domanda || 'CLASSIC').trim().toUpperCase();
    const nowSec = Math.floor(Date.now() / 1000);
    const isSarabandaIntro = tipoDomanda === 'SARABANDA' && (Number(S.domandaTimerStart || 0) <= 0 || nowSec < Number(S.domandaTimerStart || 0));

    D.domandaTesto.innerText = isSarabandaIntro ? '' : (domanda.testo || '');
    S.badgeQuestionId = domandaId;
    S.badgeTipoDomanda = tipoDomanda;
    if (isSarabandaIntro) {
      clearQuestionTypeBadge();
      renderDomandaMedia(domanda, true);
    } else {
      renderQuestionTypeBadge(tipoDomanda);
      renderDomandaMedia(domanda, false);
    }
    D.opzioniDiv.innerHTML = '';

    if (isSarabandaIntro) {
      return;
    }

    domanda.opzioni.forEach((opzione, index) => {
      const btn = document.createElement('button');
      btn.innerText = opzione.testo || '';
      btn.dataset.id = String(opzione.id || '');

      const paletteIndex = (index % 4) + 1;
      btn.classList.add(`opzione-kahoot-${paletteIndex}`);

      btn.onclick = () => inviaRisposta(domanda.id, opzione.id);
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
