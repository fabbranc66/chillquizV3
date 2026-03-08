// 02_dom.js
(() => {
  const Player = window.Player;

  Player.dom = {
    // inputs/buttons
    btnEntra: document.getElementById('btn-entra'),
    btnPunta: document.getElementById('btn-punta'),
    btnPuntataDec: document.getElementById('btn-puntata-dec'),
    btnPuntataAllIn: document.getElementById('btn-puntata-allin'),
    btnPuntataInc: document.getElementById('btn-puntata-inc'),
    inputNome: document.getElementById('player-name'),
    inputPuntata: document.getElementById('puntata'),

    // header/player info
    displayName: document.getElementById('player-display-name'),
    capitaleValue: document.getElementById('capitale-value'),

    // screens
    screens: Array.from(document.querySelectorAll('.screen')),

    // domanda
    domandaTesto: document.getElementById('domanda-testo'),
    domandaStatusMessage: document.getElementById('domanda-status-message-player'),
    opzioniDiv: document.getElementById('opzioni'),
    timerIndicator: document.getElementById('player-header-timer-indicator') || document.getElementById('player-timer-indicator'),
    timerLabel: document.getElementById('player-header-timer-label') || document.getElementById('player-timer-label'),

    // risultati/classifica
    risultatoPersonale: document.getElementById('risultato-personale'),
    classifica: document.getElementById('classifica'),

    // ui alert
    uiAlert: document.getElementById('player-ui-alert'),
    uiAlertCard: document.getElementById('player-ui-alert-card'),
    uiAlertTitle: document.getElementById('player-ui-alert-title'),
    uiAlertMessage: document.getElementById('player-ui-alert-message'),
    uiAlertClose: document.getElementById('player-ui-alert-close'),
  };
})();
