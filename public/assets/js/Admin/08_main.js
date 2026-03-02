// admin/08_main.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;

  // Bind eventi (stesso comportamento)
  if (D.btnNuova) D.btnNuova.onclick = () => Admin.actions.nuovaSessione();

  if (D.btnSetCorrente) D.btnSetCorrente.onclick = () => Admin.actions.impostaSessioneCorrente();

  if (D.btnToggleDomandeSessione) D.btnToggleDomandeSessione.onclick = () => Admin.actions.toggleDomandeSessione();

  if (D.sessioneSelect) {
    D.sessioneSelect.onchange = () => {
      if (D.domandeSessioneWrapper && D.domandeSessioneWrapper.style.display !== 'none') {
        Admin.actions.caricaDomandeSessione(Number(D.sessioneSelect.value || 0));
      }
    };
  }

  if (D.btnPuntata) D.btnPuntata.onclick = () => Admin.actions.callAdmin('avvia-puntata');
  if (D.btnDomanda) D.btnDomanda.onclick = () => Admin.actions.callAdmin('avvia-domanda');
  if (D.btnRisultati) D.btnRisultati.onclick = () => Admin.actions.callAdmin('risultati');
  if (D.btnProssima) D.btnProssima.onclick = () => Admin.actions.callAdmin('prossima');
  if (D.btnRiavvia) D.btnRiavvia.onclick = () => Admin.actions.callAdmin('riavvia');

  if (D.btnSchermo) D.btnSchermo.onclick = () => Admin.actions.apriSchermo();
  if (D.btnMedia) D.btnMedia.onclick = () => Admin.actions.apriMedia();
  if (D.btnSettings) D.btnSettings.onclick = () => Admin.actions.apriSettings();
  if (D.btnQuizConfigV2) D.btnQuizConfigV2.onclick = () => Admin.actions.apriQuizConfigV2();

  if (D.btnClearLog) D.btnClearLog.onclick = () => Admin.log.clearLog();

  // Start (stesso comportamento)
  setInterval(() => Admin.actions.aggiornaStato(), 1000);
  setInterval(() => Admin.actions.aggiornaJoinRichieste(), 2000);

  Admin.actions.aggiornaStato();
  Admin.actions.aggiornaJoinRichieste();
  Admin.actions.caricaSessioni();
})();