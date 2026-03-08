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
