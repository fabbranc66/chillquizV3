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
    networkSarabandaAudioError: 'Errore rete durante toggle SARABANDA',
    networkSarabandaReverseError: 'Errore rete durante toggle REVERSE SARABANDA',
    networkSarabandaBrokenRecordError: 'Errore rete durante toggle DISCO ROTTO SARABANDA',
    networkSarabandaFastError: 'Errore rete durante toggle FAST SARABANDA',
    networkSarabandaFastRateError: 'Errore rete durante update velocita FAST SARABANDA',
    memeTextRequired: 'Inserisci prima il testo MEME',
  };

  function readTargetSessioneId() {
    return Number(D.sessioneSelect?.value || S.SESSIONE_ID || 0);
  }

  async function fetchAdminJson(action, sessioneId, body = null) {
    const res = await fetch(`${S.API_BASE}/admin/${action}/${sessioneId}`, {
      method: 'POST',
      body,
    });

    return res.json();
  }

  async function ensureCurrentSession(sessioneId) {
    const targetId = Number(sessioneId || 0);
    if (targetId <= 0) return false;
    if (Number(S.SESSIONE_ID || 0) === targetId && Number(D.sessioneSelect?.value || 0) === targetId) {
      // Still persist server-side current session to keep generic screen/player aligned.
    }

    const formData = new FormData();
    formData.append('sessione_id', String(targetId));
    const data = await fetchAdminJson('set-corrente', 0, formData);
    if (data?.success) {
      S.SESSIONE_ID = targetId;
    }
    return !!data?.success;
  }

  function logActionResult(title, data, successMessage, fallbackError = 'Operazione fallita') {
    addLog({
      ok: !!data?.success,
      title,
      message: data?.success ? successMessage : (data?.error || fallbackError),
      data,
    });
  }

  async function refreshRuntimeContext(forceState = false) {
    await Admin.actions.aggiornaStato(forceState);
    await Admin.actions.aggiornaJoinRichieste();
    await Admin.actions.aggiornaDomandaCorrenteMeta();
  }

  function setDebugPanelVisible(visible) {
    if (!D.debugSessionePanel || !D.btnDebugSessione) {
      return;
    }

    D.debugSessionePanel.style.display = visible ? 'block' : 'none';
    D.btnDebugSessione.textContent = visible ? 'DEBUG ON' : 'DEBUG OFF';
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
    ensureCurrentSession,
    logActionResult,
    refreshRuntimeContext,
    setDebugPanelVisible,
    persistMemeDraft,
  };
})();
