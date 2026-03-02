// 02_dom.js
(() => {
  const Player = window.Player;

  Player.dom = {
    // inputs/buttons
    btnEntra: document.getElementById('btn-entra'),
    btnPunta: document.getElementById('btn-punta'),
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

    // risultati/classifica
    risultatoPersonale: document.getElementById('risultato-personale'),
    classifica: document.getElementById('classifica'),
  };
})();