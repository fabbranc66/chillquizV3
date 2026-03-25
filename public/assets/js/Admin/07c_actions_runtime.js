// admin/07c_actions_runtime.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;
  const { addLog } = Admin.log;
  const { renderClassificaLive, renderJoinRichieste } = Admin.render;
  const Support = Admin.actionsSupport;
  const Runtime = Admin.runtimeSupport;
  const Copy = Runtime.copy;

  async function syncCurrentSessionForGlobalViews(sessioneId) {
    const targetId = Number(sessioneId || 0);
    if (targetId <= 0) return true;
    try {
      return await Runtime.ensureCurrentSession(targetId);
    } catch (e) {
      addLog({
        ok: false,
        title: 'set-corrente',
        message: 'Impossibile sincronizzare la sessione corrente',
        data: { sessione_id: targetId, error: String((e && e.message) || e) },
      });
      return false;
    }
  }

  function resolveActiveSessioneId() {
    const targetId = Number(Runtime.readTargetSessioneId() || 0);
    if (targetId > 0 && targetId !== Number(S.SESSIONE_ID || 0)) {
      S.SESSIONE_ID = targetId;
    }
    return Number(S.SESSIONE_ID || 0);
  }

  Object.assign(Admin.actions, {
    async aggiornaPartecipanti() {
      try {
        const sessioneId = resolveActiveSessioneId();
        if (!sessioneId) return;
        const res = await fetch(`${S.API_BASE}/classifica/${sessioneId}`);
        const data = await res.json();
        if (!data.success) return;

        const lista = data.classifica;
        const numeroAttuale = lista.length;

        if (D.partecipantiSpan) D.partecipantiSpan.textContent = String(numeroAttuale);
        renderClassificaLive(lista);

        if (numeroAttuale > S.ultimoNumeroPartecipanti) {
          const nuovi = lista.slice(0, numeroAttuale - S.ultimoNumeroPartecipanti);
          nuovi.forEach((partecipante) => {
            addLog({
              ok: true,
              title: 'Nuovo giocatore',
              message: `${partecipante.nome} si e unito alla sessione`,
              data: partecipante,
            });
          });
        }

        S.ultimoNumeroPartecipanti = numeroAttuale;
        Admin.actions.aggiornaJoinRichieste();
      } catch (e) {
        // silenzioso
      }
    },

    async aggiornaJoinRichieste() {
      const sessioneId = resolveActiveSessioneId();
      if (S.joinRequestInFlight || !sessioneId) return;
      S.joinRequestInFlight = true;
      try {
        const data = await Runtime.fetchAdminJson('join-richieste', sessioneId);
        if (!data.success) return;

        renderJoinRichieste(data.richieste ?? []);
      } finally {
        S.joinRequestInFlight = false;
      }
    },

    async aggiornaDomandaCorrenteMeta() {
      const sessioneId = resolveActiveSessioneId();
      if (S.domandaMetaRequestInFlight || !sessioneId) return;
      S.domandaMetaRequestInFlight = true;
      try {
        const res = await fetch(`${S.API_BASE}/domanda/${sessioneId}`);
        const data = await res.json();

        if (!data.success) {
          S.domandaCorrente = null;
          Support.renderDomandaCorrenteMeta(null);
          Support.syncSessioneDomandaInfo();
          Support.syncSarabandaAudioLed();
          Support.syncAudioPreviewButton();
          return;
        }

        S.domandaCorrente = data.domanda || null;
        Support.renderDomandaCorrenteMeta(data.domanda || null);
        Support.syncSessioneDomandaInfo();
        Support.syncSarabandaAudioLed();
        Support.syncAudioPreviewButton();
      } catch (e) {
        S.domandaCorrente = null;
        Support.renderDomandaCorrenteMeta(null);
        Support.syncSessioneDomandaInfo();
        Support.syncSarabandaAudioLed();
        Support.syncAudioPreviewButton();
      } finally {
        S.domandaMetaRequestInFlight = false;
      }
    },

    async gestisciJoin(requestId, action) {
      const formData = new FormData();
      formData.append('request_id', requestId);

      const sessioneId = resolveActiveSessioneId();
      const data = await Runtime.fetchAdminJson(action, sessioneId, formData);
      Runtime.logActionResult(
        action === 'approva-join' ? 'Join approvata' : 'Join rifiutata',
        data,
        `Richiesta #${requestId} ${action === 'approva-join' ? 'approvata' : 'rifiutata'}`
      );

      if (data.success) {
        Admin.actions.aggiornaJoinRichieste();
        Admin.actions.aggiornaPartecipanti();
      }
    },

    async eliminaPartecipante(partecipazioneId, nome = '') {
      const id = Number(partecipazioneId || 0);
      if (id <= 0) return;

      const playerName = String(nome || `#${id}`).trim();
      const confirmed = window.confirm(`Eliminare ${playerName} dalla sessione corrente?`);
      if (!confirmed) return;

      const formData = new FormData();
      formData.append('partecipazione_id', String(id));

      const sessioneId = resolveActiveSessioneId();
      const data = await Runtime.fetchAdminJson('elimina-partecipante', sessioneId, formData);

      Runtime.logActionResult(
        'Partecipante eliminato',
        data,
        data.success
          ? `${playerName} rimosso dalla sessione`
          : `Errore rimozione ${playerName}`
      );

      if (data.success) {
        Admin.actions.aggiornaJoinRichieste();
        Admin.actions.aggiornaPartecipanti();
      }
    },

    async callAdmin(action) {
      const sessioneId = resolveActiveSessioneId();
      if (!await syncCurrentSessionForGlobalViews(sessioneId)) {
        return;
      }
      const data = await Runtime.fetchAdminJson(action, sessioneId);
      addLog({
        ok: !!data.success,
        title: action,
        message: data.success
          ? `Azione "${action}" eseguita`
          : (data.error ? `Errore: ${data.error}` : 'Errore sconosciuto'),
        data,
      });

      await Runtime.refreshRuntimeContext(true);
    },

    apriMedia() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', 'admin/media');
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Media', message: 'Aperta gestione media', data: {} });
    },

    async apriSchermo() {
      const sessioneId = resolveActiveSessioneId();
      if (!await syncCurrentSessionForGlobalViews(sessioneId)) {
        return;
      }
      const url = new URL(window.location.href);
      url.searchParams.set('url', 'screen');
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Screen', message: 'Schermo aperto sulla sessione corrente', data: { sessione_id: sessioneId } });
    },

    apriSettings() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', 'admin/settings');
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Settings', message: 'Aperto pannello settings', data: {} });
    },

    async aggiornaStato(force = false) {
      const sessioneId = resolveActiveSessioneId();
      if (!sessioneId) return;
      if (S.statoRequestInFlight && !force) return;
      const requestSeq = Number(S.statoRequestSeq || 0) + 1;
      S.statoRequestSeq = requestSeq;
      S.statoRequestInFlight = true;
      try {
        if (!S.memeDraftText) {
          S.memeDraftText = Support.loadMemeDraft();
        }
        const res = await fetch(`${S.API_BASE}/stato/${sessioneId}`);
        const data = await res.json();
        if (data.success && requestSeq >= Number(S.statoAppliedSeq || 0)) {
          S.statoAppliedSeq = requestSeq;
          Admin.ui.aggiornaUI(data.sessione);
        }
      } finally {
        if (requestSeq >= Number(S.statoAppliedSeq || 0)) {
          S.statoRequestInFlight = false;
        } else if (Number(S.statoRequestSeq || 0) === requestSeq) {
          S.statoRequestInFlight = false;
        }
      }
    },

    async aggiornaDebugSessione(forceOpen = false) {
      const sessioneId = resolveActiveSessioneId();
      if (S.debugSessioneInFlight || !sessioneId) return;
      S.debugSessioneInFlight = true;
      try {
        const formData = new FormData();
        formData.append('sessione_id', String(sessioneId));

        const data = await Runtime.fetchAdminJson('debug-sessione', sessioneId, formData);

        if (!data.success) {
          addLog({ ok: false, title: 'debug-sessione', message: data.error || 'Errore debug sessione', data });
          return;
        }

        const debugPayload = Object.assign({}, data.debug || {});
        try {
          const playerTimingRaw = window.localStorage.getItem(`chillquiz_debug_timing_player_${sessioneId}`);
          const screenTimingRaw = window.localStorage.getItem(`chillquiz_debug_timing_screen_${sessioneId}`);
          debugPayload.client_timing = {
            player: playerTimingRaw ? JSON.parse(playerTimingRaw) : null,
            screen: screenTimingRaw ? JSON.parse(screenTimingRaw) : null,
          };
        } catch (e) {
          debugPayload.client_timing = {
            player: null,
            screen: null,
            error: String(e?.message || e),
          };
        }

        if (D.debugSessioneOutput) {
          D.debugSessioneOutput.textContent = JSON.stringify(debugPayload, null, 2);
        }

        if (forceOpen) {
          Runtime.setDebugPanelVisible(true);
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'debug-sessione',
          message: Copy.networkDebugError,
          data: { error: String(e?.message || e) },
        });
      } finally {
        S.debugSessioneInFlight = false;
      }
    },

    toggleDebugSessione() {
      if (!D.debugSessionePanel || !D.btnDebugSessione) return;
      const nextVisible = D.debugSessionePanel.style.display === 'none';
      Runtime.setDebugPanelVisible(nextVisible);
      if (nextVisible) {
        Admin.actions.aggiornaDebugSessione(true);
      }
    },

  });

  window.gestisciJoin = (requestId, action) => Admin.actions.gestisciJoin(requestId, action);
  window.eliminaPartecipante = (partecipazioneId, nome) => Admin.actions.eliminaPartecipante(partecipazioneId, nome);
})();
