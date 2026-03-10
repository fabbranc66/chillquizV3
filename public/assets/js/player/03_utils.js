// 03_utils.js
(() => {
  const Player = window.Player;
  const D = Player.dom;

  let alertTimer = null;

  function formatThousands(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '0';
    return new Intl.NumberFormat('it-IT').format(numeric);
  }

  function normalizeCapitaleDisplay() {
    if (!D.capitaleValue) return;

    const raw = String(D.capitaleValue.textContent || '').trim();
    if (raw === '') return;

    const normalized = raw.replace(/[^\d-]/g, '');
    if (normalized === '' || normalized === '-') return;

    const formatted = formatThousands(Number(normalized));
    if (D.capitaleValue.textContent !== formatted) {
      D.capitaleValue.textContent = formatted;
    }
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
