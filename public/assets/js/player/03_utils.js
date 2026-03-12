// 03_utils.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;

  let alertTimer = null;

  function formatThousands(value) {
    const toGroupedIt = (n) => {
      const sign = n < 0 ? '-' : '';
      const abs = Math.abs(Math.trunc(n));
      return `${sign}${String(abs).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
    };

    if (typeof value === 'number') {
      return Number.isFinite(value) ? toGroupedIt(value) : '0';
    }

    const raw = String(value ?? '').trim();
    if (raw === '' || raw === '-') return '0';
    const normalized = raw.replace(/[^\d-]/g, '');
    if (normalized === '' || normalized === '-') return '0';
    const parsed = Number.parseInt(normalized, 10);
    if (!Number.isFinite(parsed)) return '0';
    return toGroupedIt(parsed);
  }

  function normalizeCapitaleDisplay() {
    if (!D.capitaleValue) return;

    const raw = String(D.capitaleValue.textContent || '').trim();
    if (raw === '') return;

    const normalized = raw.replace(/[^\d-]/g, '');
    if (normalized === '' || normalized === '-') return;

    const numeric = Number(normalized);
    if (Number.isFinite(numeric)) {
      S.capitaleRaw = numeric;
    }

    const formatted = formatThousands(numeric);
    if (D.capitaleValue.textContent !== formatted) {
      D.capitaleValue.textContent = formatted;
    }
  }

  function setCapitaleRaw(value) {
    const numeric = Number(value);
    S.capitaleRaw = Number.isFinite(numeric) ? Math.floor(numeric) : 0;

    if (D.capitaleValue) {
      D.capitaleValue.textContent = formatThousands(S.capitaleRaw);
    }
  }

  function getCapitaleRaw() {
    const numeric = Number(S.capitaleRaw);
    return Number.isFinite(numeric) ? Math.floor(numeric) : 0;
  }

  Player.utils = {
    safeString(v) {
      return String(v ?? '').trim();
    },

    safeNumber(v, fallback = 0) {
      const n = Number(v);
      return Number.isFinite(n) ? n : fallback;
    },

    isDomandaAttiva(stato) {
      return stato === 'domanda';
    },

    isQuestionStage(stato) {
      return stato === 'preview' || stato === 'domanda';
    },

    formatThousands,
    normalizeCapitaleDisplay,
    setCapitaleRaw,
    getCapitaleRaw,
  };

  Player.copy = {
    networkErrorTitle: 'Errore di rete',
    joinNameRequiredTitle: 'Nome richiesto',
    joinNameRequiredMessage: 'Inserisci un nome prima di entrare.',
    joinApprovalTitle: 'Accesso in approvazione',
    joinApprovalMessage: 'Nome gia\' presente: richiesta inviata alla regia.',
    joinRejectedTitle: 'Accesso rifiutato',
    joinRejectedMessage: 'Richiesta di accesso rifiutata dalla regia.',
    joinFailedTitle: 'Accesso non riuscito',
    joinFailedMessage: 'Accesso non riuscito.',
    joinNetworkErrorMessage: 'Impossibile contattare il server per l\'accesso.',
    betInvalidTitle: 'Puntata non valida',
    betInvalidMessage: 'Inserisci un importo valido.',
    betRejectedTitle: 'Puntata rifiutata',
    betRejectedMessage: 'Errore invio puntata.',
    betNetworkErrorMessage: 'Impossibile inviare la puntata.',
    betRequiredBeforeAnswerTitle: 'Puntata richiesta',
    betRequiredBeforeAnswerMessage: 'Devi prima confermare la puntata.',
    answerFailedTitle: 'Risposta non inviata',
    answerFailedMessage: 'Errore invio risposta.',
    answerNetworkErrorMessage: 'Impossibile inviare la risposta.',
    personalResultUnavailable: 'Risultato personale non disponibile.',
    noPlayers: 'Nessun giocatore',
    imagePartyNotice: 'Modalita\' PIXELATE: osserva l\'immagine che si schiarisce e scegli la risposta.',
    fadeNotice: 'Modalita\' FADE: l\'immagine emerge dal nero durante il timer.',
    memePlayerNotice: 'Modalita\' MEME: premi A/B/C/D mentre le associazioni ruotano.',
    impostoreMaskedNotice: 'Sei l\'impostore: osserva gli altri e deduci la risposta.',
    impostoreMaskedFallback: 'Sei l\'impostore, ma in questa vista la domanda e\' mascherata.',
  };

  Player.uiAlert = {
    show({ title = 'Messaggio', message = '', tone = 'info', autoHideMs = 0 } = {}) {
      if (!D.uiAlert || !D.uiAlertCard || !D.uiAlertTitle || !D.uiAlertMessage) return;

      if (alertTimer) {
        clearTimeout(alertTimer);
        alertTimer = null;
      }

      D.uiAlertTitle.textContent = String(title || 'Messaggio');
      D.uiAlertMessage.textContent = String(message || '');

      D.uiAlertCard.classList.remove(
        'player-ui-alert-info',
        'player-ui-alert-success',
        'player-ui-alert-warn',
        'player-ui-alert-error'
      );
      D.uiAlertCard.classList.add(`player-ui-alert-${String(tone || 'info')}`);
      D.uiAlert.classList.remove('hidden');

      if (autoHideMs > 0) {
        alertTimer = setTimeout(() => {
          Player.uiAlert.hide();
        }, autoHideMs);
      }
    },

    hide() {
      if (!D.uiAlert) return;
      if (alertTimer) {
        clearTimeout(alertTimer);
        alertTimer = null;
      }
      D.uiAlert.classList.add('hidden');
    },
  };

  if (D.capitaleValue) {
    normalizeCapitaleDisplay();

    const observer = new MutationObserver(() => {
      normalizeCapitaleDisplay();
    });

    observer.observe(D.capitaleValue, {
      childList: true,
      characterData: true,
      subtree: true,
    });
  }
})();
