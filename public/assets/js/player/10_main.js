// 10_main.js
(() => {
  const Player = window.Player;
  const D = Player.dom;

  document.addEventListener('DOMContentLoaded', () => {
    if (D.btnEntra) D.btnEntra.addEventListener('click', Player.join.handleJoin);
    if (D.btnPunta) D.btnPunta.addEventListener('click', Player.puntata.handlePuntata);
    if (D.btnPuntataInc) D.btnPuntataInc.addEventListener('click', Player.puntata.increasePuntata);
    if (D.btnPuntataDec) D.btnPuntataDec.addEventListener('click', Player.puntata.decreasePuntata);
    if (D.btnPuntataAllIn) D.btnPuntataAllIn.addEventListener('click', Player.puntata.setAllIn);
    if (D.uiAlertClose) D.uiAlertClose.addEventListener('click', Player.uiAlert.hide);
  });
})();
