// admin/08_main.js
(() => {
  const Admin = window.Admin;
  const D = Admin.dom;

  if (D.btnNuova) D.btnNuova.onclick = () => Admin.actions.nuovaSessione();

  if (D.btnSetCorrente) D.btnSetCorrente.onclick = () => Admin.actions.impostaSessioneCorrente();

  if (D.btnToggleDomandeSessione) D.btnToggleDomandeSessione.onclick = () => Admin.actions.toggleDomandeSessione();
  if (D.btnToggleDomandaEditor) D.btnToggleDomandaEditor.onclick = () => Admin.actions.toggleDomandaEditor();

  if (D.sessioneSelect) {
    D.sessioneSelect.onchange = () => {
      if (D.domandeSessioneWrapper && D.domandeSessioneWrapper.style.display !== 'none') {
        Admin.actions.caricaDomandeSessione(Number(D.sessioneSelect.value || 0));
      }

      if (D.domandaEditorWrapper && D.domandaEditorWrapper.style.display !== 'none') {
        Admin.actions.caricaDomandaEditor();
      }
    };
  }

  if (D.domandaEditorTipo) {
    D.domandaEditorTipo.onchange = () => Admin.actions.syncDomandaEditorVisibility();
  }

  if (D.domandaEditorMediaImageSelect && D.domandaEditorMediaImage) {
    D.domandaEditorMediaImageSelect.onchange = () => {
      if (D.domandaEditorMediaImageSelect.value) {
        D.domandaEditorMediaImage.value = D.domandaEditorMediaImageSelect.value;
      }
    };
  }

  if (D.domandaEditorMediaAudioSelect && D.domandaEditorMediaAudio) {
    D.domandaEditorMediaAudioSelect.onchange = () => {
      if (D.domandaEditorMediaAudioSelect.value) {
        D.domandaEditorMediaAudio.value = D.domandaEditorMediaAudioSelect.value;
      }
    };
  }

  if (D.btnRicaricaMediaCatalog) {
    D.btnRicaricaMediaCatalog.onclick = () => Admin.actions.caricaCatalogoMedia();
  }

  if (D.btnUploadDomandaMedia) {
    D.btnUploadDomandaMedia.onclick = () => Admin.actions.uploadDomandaMedia();
  }

  if (D.btnCaricaDomandaEditor) {
    D.btnCaricaDomandaEditor.onclick = () => Admin.actions.caricaDomandaEditor();
  }

  if (D.btnSalvaDomandaEditor) {
    D.btnSalvaDomandaEditor.onclick = () => Admin.actions.salvaDomandaEditor();
  }

  if (D.btnPuntata) D.btnPuntata.onclick = () => Admin.actions.callAdmin('avvia-puntata');
  if (D.btnDomanda) D.btnDomanda.onclick = () => Admin.actions.callAdmin('avvia-domanda');
  if (D.btnRisultati) D.btnRisultati.onclick = () => Admin.actions.callAdmin('risultati');
  if (D.btnProssima) D.btnProssima.onclick = () => Admin.actions.callAdmin('prossima');
  if (D.btnRiavvia) D.btnRiavvia.onclick = () => Admin.actions.callAdmin('riavvia');
  if (D.btnAudioPreview) D.btnAudioPreview.onclick = () => Admin.actions.avviaAnteprimaAudio();

  if (D.btnSchermo) D.btnSchermo.onclick = () => Admin.actions.apriSchermo();
  if (D.btnMedia) D.btnMedia.onclick = () => Admin.actions.apriMedia();
  if (D.btnSettings) D.btnSettings.onclick = () => Admin.actions.apriSettings();

  if (D.btnClearLog) D.btnClearLog.onclick = () => Admin.log.clearLog();

  setInterval(() => Admin.actions.aggiornaStato(), 1000);
  setInterval(() => Admin.actions.aggiornaJoinRichieste(), 2000);

  Admin.actions.aggiornaStato();
  Admin.actions.aggiornaJoinRichieste();
  Admin.actions.caricaSessioni();
})();
