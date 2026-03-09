// 03_utils.js
(() => {
  const Player = window.Player;
  const D = Player.dom;

  let alertTimer = null;

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
    betRequiredBeforeAnswerMessage: 'Devi prima confermare la puntata per poter rispondere.',
    answerFailedTitle: 'Risposta non inviata',
    answerFailedMessage: 'Errore invio risposta.',
    answerNetworkErrorMessage: 'Impossibile inviare la risposta.',
    personalResultUnavailable: 'Risultato personale non disponibile.',
    noPlayers: 'Nessun giocatore',
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
})();
