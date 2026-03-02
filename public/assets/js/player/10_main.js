// 10_main.js
(() => {
  const Player = window.Player;
  const D = Player.dom;

  document.addEventListener('DOMContentLoaded', () => {
    if (D.btnEntra) D.btnEntra.addEventListener('click', Player.join.handleJoin);
    if (D.btnPunta) D.btnPunta.addEventListener('click', Player.puntata.handlePuntata);
  });
})();