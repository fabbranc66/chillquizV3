// admin/08_main.js
(() => {
  const Admin = window.Admin;
  const D = Admin.dom;
  const Runtime = Admin.runtimeSupport;
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
  if (D.btnMemeToggle) D.btnMemeToggle.onclick = () => Admin.actions.toggleMemeCorrente();
  if (D.btnImpostoreToggle) D.btnImpostoreToggle.onclick = () => Admin.actions.toggleImpostoreCorrente();
  if (D.btnImagePartyToggle) D.btnImagePartyToggle.onclick = () => Admin.actions.toggleImagePartyCorrente();
  if (D.btnFadeToggle) D.btnFadeToggle.onclick = () => Admin.actions.toggleFadeCorrente();
  if (D.btnSarabandaReverseToggle) D.btnSarabandaReverseToggle.onclick = () => Admin.actions.toggleSarabandaReverse();
  if (D.btnSarabandaBrokenRecordToggle) D.btnSarabandaBrokenRecordToggle.onclick = () => Admin.actions.toggleSarabandaBrokenRecord();
  if (D.btnSarabandaFastToggle) D.btnSarabandaFastToggle.onclick = () => Admin.actions.toggleSarabandaFast();
  if (Array.isArray(D.sarabandaFastRateButtons)) {
    D.sarabandaFastRateButtons.forEach((btn) => {
      btn.onclick = () => {
        if (btn.disabled) return;
        const nextRate = Number(btn.dataset.fastRate || 5);
        Admin.state.sarabandaFastForwardRate = nextRate;
        const optimisticSessionState = Object.assign(
          {},
          Admin.state.currentSessionState || {},
          {
            sarabanda_fast_forward_enabled: true,
            sarabanda_fast_forward_rate: nextRate,
            sarabanda_reverse_enabled: false,
            sarabanda_broken_record_enabled: false,
          }
        );
        Admin.state.currentSessionState = optimisticSessionState;
        Admin.ui.aggiornaUI(optimisticSessionState);
        Admin.actions.setSarabandaFastRate();
      };
    });
  }
  if (D.btnDebugSessione) D.btnDebugSessione.onclick = () => Admin.actions.toggleDebugSessione();
  if (D.debugSessionePanel && D.btnDebugSessione) {
    Runtime.setDebugPanelVisible(D.debugSessionePanel.style.display !== 'none');
  }
  if (D.memeTextInput) {
    D.memeTextInput.oninput = () => {
      Runtime.persistMemeDraft(String(D.memeTextInput.value || ''));
    };
  }

  if (D.btnToggleDomandeSessione) D.btnToggleDomandeSessione.onclick = () => Admin.actions.toggleDomandeSessione();
  if (D.btnToggleDomandaEditor) D.btnToggleDomandaEditor.onclick = () => Admin.actions.toggleDomandaEditor();
  if (D.btnSearchSessionImages) D.btnSearchSessionImages.onclick = () => Admin.actions.cercaImmaginiSessioneCorrente();
  if (Admin.actions.resetSessionImageSearchReport) Admin.actions.resetSessionImageSearchReport();

  if (D.sessioneSelect) {
    D.sessioneSelect.onchange = () => {
      Admin.actions.popolaFormSessioneDaSelect();
      if (Admin.actions.resetSessionImageSearchReport) {
        Admin.actions.resetSessionImageSearchReport();
      }

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

  if (D.btnSchermo) D.btnSchermo.onclick = () => Admin.actions.apriSchermo();
  if (D.btnMedia) D.btnMedia.onclick = () => Admin.actions.apriMedia();
  if (D.btnHeaderMedia) D.btnHeaderMedia.onclick = () => Admin.actions.apriMedia();
  if (D.btnSettings) D.btnSettings.onclick = () => Admin.actions.apriSettings();
  if (D.btnHeaderSettings) D.btnHeaderSettings.onclick = () => Admin.actions.apriSettings();

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
  setInterval(() => {
    if (D.debugSessionePanel && D.debugSessionePanel.style.display !== 'none') {
      Admin.actions.aggiornaDebugSessione();
    }
  }, 4000);

  Admin.actions.aggiornaStato();
  Admin.actions.aggiornaJoinRichieste();
  Admin.actions.caricaArgomenti();
  Admin.actions.caricaSessioni();
})();
