// admin/07c_runtime_support.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;
  const { addLog } = Admin.log;
  const Support = Admin.actionsSupport;
  const RUNTIME_COPY = {
    invalidSession: 'Sessione non valida',
    networkDebugError: 'Errore rete debug sessione',
    networkImpostoreError: 'Errore rete durante toggle IMPOSTORE',
    networkMemeError: 'Errore rete durante toggle MEME',
    networkImagePartyError: 'Errore rete durante toggle PIXELATE',
    networkFadeError: 'Errore rete durante toggle FADE',
    memeTextRequired: 'Inserisci prima il testo MEME',
  };

  function readTargetSessioneId() {
    return Number(D.sessioneSelect?.value || S.SESSIONE_ID || 0);
  }

  async function fetchAdminJson(action, sessioneId, body = null) {
    const res = await fetch(`${S.API_BASE}/admin/${action}/${sessioneId}`, {
      method: 'POST',
      headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
      body,
    });

    return res.json();
  }

  function logActionResult(title, data, successMessage, fallbackError = 'Operazione fallita') {
    addLog({
      ok: !!data?.success,
      title,
      message: data?.success ? successMessage : (data?.error || fallbackError),
      data,
    });
  }

  async function refreshRuntimeContext() {
    await Admin.actions.aggiornaStato();
    await Admin.actions.aggiornaJoinRichieste();
    await Admin.actions.aggiornaDomandaCorrenteMeta();
  }

  function setDebugPanelVisible(visible) {
    if (!D.debugSessionePanel || !D.btnDebugSessione) {
      return;
    }

    D.debugSessionePanel.style.display = visible ? 'block' : 'none';
    D.btnDebugSessione.classList.toggle('enabled', visible);
    D.btnDebugSessione.classList.toggle('disabled', !visible);
  }

  function persistMemeDraft(value) {
    const sanitized = Support.sanitizeMemeText(value);
    S.memeDraftText = sanitized;

    try {
      const key = `chillquiz_meme_draft_${Number(S.SESSIONE_ID || 0)}`;
      if (sanitized) {
        window.localStorage.setItem(key, sanitized);
      } else {
        window.localStorage.removeItem(key);
      }
    } catch (e) {
      console.warn(e);
    }

    return sanitized;
  }

  Admin.runtimeSupport = {
    copy: RUNTIME_COPY,
    readTargetSessioneId,
    fetchAdminJson,
    logActionResult,
    refreshRuntimeContext,
    setDebugPanelVisible,
    persistMemeDraft,
  };
})();
