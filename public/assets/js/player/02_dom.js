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
    opzioniDiv: document.getElementById('opzioni'),
    questionTypeBadgePlayer: document.getElementById('question-type-badge-player'),
    questionTypeBadgeImagePlayer: document.getElementById('question-type-badge-image-player'),
    timerIndicator: document.getElementById('player-header-timer-indicator') || document.getElementById('player-timer-indicator'),
    timerLabel: document.getElementById('player-header-timer-label') || document.getElementById('player-timer-label'),

    // risultati/classifica
    risultatoPersonale: document.getElementById('risultato-personale'),
    classifica: document.getElementById('classifica'),
  };
})();
