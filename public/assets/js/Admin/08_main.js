// admin/08_main.js
(() => {
  const Admin = window.Admin;
  const D = Admin.dom;
  const PANEL_KEY = 'admin_panels_v1';

  function loadPanelsState() {
    try {
      const raw = localStorage.getItem(PANEL_KEY);
      if (!raw) return {};
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (e) {
      return {};
    }
  }

  function savePanelsState(state) {
    try {
      localStorage.setItem(PANEL_KEY, JSON.stringify(state || {}));
    } catch (e) {
      // ignore
    }
  }

  function applyPanelState(panelEl, buttonEl, visible) {
    if (!panelEl || !buttonEl) return;
    panelEl.style.display = visible ? 'block' : 'none';
    buttonEl.classList.toggle('enabled', visible);
    buttonEl.classList.toggle('disabled', !visible);
  }

  function setupPanelToggle(panelEl, buttonEl, key, defaults, onVisible) {
    if (!panelEl || !buttonEl) return;
    const state = loadPanelsState();
    const initial = Object.prototype.hasOwnProperty.call(state, key) ? !!state[key] : defaults;
    applyPanelState(panelEl, buttonEl, initial);
    if (initial && typeof onVisible === 'function') {
      onVisible();
    }

    buttonEl.onclick = () => {
      const currentlyVisible = panelEl.style.display !== 'none';
      const next = !currentlyVisible;
      applyPanelState(panelEl, buttonEl, next);
      if (next && typeof onVisible === 'function') {
        onVisible();
      }
      const nextState = loadPanelsState();
      nextState[key] = next;
      savePanelsState(nextState);
    };
  }

  if (D.btnNuova) D.btnNuova.onclick = () => Admin.actions.nuovaSessione();

  if (D.btnSetCorrente) D.btnSetCorrente.onclick = () => Admin.actions.impostaSessioneCorrente();
  if (D.btnSalvaSessione) D.btnSalvaSessione.onclick = () => Admin.actions.salvaSessioneCorrente();

  if (D.btnToggleDomandeSessione) D.btnToggleDomandeSessione.onclick = () => Admin.actions.toggleDomandeSessione();
  if (D.btnToggleDomandaEditor) D.btnToggleDomandaEditor.onclick = () => Admin.actions.toggleDomandaEditor();

  if (D.sessioneSelect) {
    D.sessioneSelect.onchange = () => {
      Admin.actions.popolaFormSessioneDaSelect();

      if (D.domandeSessioneWrapper && D.domandeSessioneWrapper.style.display !== 'none') {
        Admin.actions.caricaDomandeSessione(Number(D.sessioneSelect.value || 0));
      }

      if (D.domandaEditorWrapper && D.domandaEditorWrapper.style.display !== 'none') {
        Admin.actions.caricaDomandaEditor();
      }
    };
  }

  if (D.inputSessionePoolTipo) {
    D.inputSessionePoolTipo.onchange = () => {
      Admin.actions.syncArgomentoFieldState();
    };
  }

  if (D.inputSessioneNome) {
    D.inputSessioneNome.onchange = () => {
      Admin.actions.syncSessioneDaNomeInput();
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
      Admin.actions.syncDomandaMediaPreview();
    };
  }

  if (D.domandaEditorMediaAudioSelect && D.domandaEditorMediaAudio) {
    D.domandaEditorMediaAudioSelect.onchange = () => {
      if (D.domandaEditorMediaAudioSelect.value) {
        D.domandaEditorMediaAudio.value = D.domandaEditorMediaAudioSelect.value;
      }
      Admin.actions.syncDomandaMediaPreview();
    };
  }

  if (D.domandaEditorMediaImage) {
    D.domandaEditorMediaImage.oninput = () => Admin.actions.syncDomandaMediaPreview();
    D.domandaEditorMediaImage.onchange = () => Admin.actions.syncDomandaMediaPreview();
  }

  if (D.domandaEditorMediaAudio) {
    D.domandaEditorMediaAudio.oninput = () => Admin.actions.syncDomandaMediaPreview();
    D.domandaEditorMediaAudio.onchange = () => Admin.actions.syncDomandaMediaPreview();
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

  setupPanelToggle(D.panelQuestion, D.btnToggleDomandePanel, 'domande', true, () => {
    const sessioneId = Number(D.sessioneSelect?.value || Admin.state.SESSIONE_ID || 0);
    if (sessioneId > 0) {
      Admin.actions.caricaDomandeSessione(sessioneId);
    }
  });
  setupPanelToggle(D.panelClassifica, D.btnToggleClassificaPanel, 'classifica', true);
  setupPanelToggle(D.panelJoin, D.btnToggleJoinPanel, 'join', true);
  setupPanelToggle(D.panelLog, D.btnToggleLogPanel, 'log', true);

  Admin.ui = Admin.ui || {};
  Admin.ui.ensureJoinPanelOpen = () => {
    const isVisible = D.panelJoin && D.panelJoin.style.display !== 'none';
    if (isVisible) return;
    applyPanelState(D.panelJoin, D.btnToggleJoinPanel, true);
  };
  Admin.ui.ensureJoinPanelClosed = () => {
    const isVisible = D.panelJoin && D.panelJoin.style.display !== 'none';
    if (!isVisible) return;
    applyPanelState(D.panelJoin, D.btnToggleJoinPanel, false);
  };

  setInterval(() => Admin.actions.aggiornaStato(), 2500);
  setInterval(() => Admin.actions.aggiornaJoinRichieste(), 4000);
  setInterval(() => Admin.actions.aggiornaDomandaCorrenteMeta(), 5000);

  Admin.actions.aggiornaStato();
  Admin.actions.aggiornaJoinRichieste();
  Admin.actions.caricaArgomenti();
  Admin.actions.caricaSessioni();
})();
