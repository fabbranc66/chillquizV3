// admin/07c_actions_runtime.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;
  const { addLog } = Admin.log;
  const { renderClassificaLive, renderJoinRichieste } = Admin.render;
  const Support = Admin.actionsSupport;

  Object.assign(Admin.actions, {
    async aggiornaPartecipanti() {
      try {
        const res = await fetch(`${S.API_BASE}/classifica/${S.SESSIONE_ID}`);
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
      if (S.joinRequestInFlight || !S.SESSIONE_ID) return;
      S.joinRequestInFlight = true;
      try {
        const res = await fetch(`${S.API_BASE}/admin/join-richieste/${S.SESSIONE_ID}`, {
          method: 'POST',
          headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
        });
        const data = await res.json();
        if (!data.success) return;

        renderJoinRichieste(data.richieste ?? []);
      } finally {
        S.joinRequestInFlight = false;
      }
    },

    async aggiornaDomandaCorrenteMeta() {
      if (S.domandaMetaRequestInFlight || !S.SESSIONE_ID) return;
      S.domandaMetaRequestInFlight = true;
      try {
        const res = await fetch(`${S.API_BASE}/domanda/${S.SESSIONE_ID}`);
        const data = await res.json();

        if (!data.success) {
          S.domandaCorrente = null;
          Support.renderDomandaCorrenteMeta(null);
          Support.syncAudioPreviewButton();
          return;
        }

        S.domandaCorrente = data.domanda || null;
        Support.renderDomandaCorrenteMeta(data.domanda || null);
        Support.syncAudioPreviewButton();
      } catch (e) {
        S.domandaCorrente = null;
        Support.renderDomandaCorrenteMeta(null);
        Support.syncAudioPreviewButton();
      } finally {
        S.domandaMetaRequestInFlight = false;
      }
    },

    async avviaAnteprimaAudio() {
      const domanda = S.domandaCorrente || null;
      const hasAudio = String(domanda?.media_audio_path || '').trim() !== '';

      if (!hasAudio) {
        addLog({ ok: false, title: 'audio-preview', message: 'La domanda corrente non ha audio', data: {} });
        Support.syncAudioPreviewButton();
        return;
      }

      const res = await fetch(`${S.API_BASE}/admin/audio-preview/${S.SESSIONE_ID}`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
      });

      const data = await res.json();
      addLog({
        ok: !!data.success,
        title: 'audio-preview',
        message: data.success ? 'Anteprima audio inviata allo schermo' : (data.error || 'Errore avvio anteprima audio'),
        data,
      });

      if (data.success) {
        try {
          window.localStorage.setItem(`${Support.AUDIO_PREVIEW_STORAGE_PREFIX}${S.SESSIONE_ID}`, JSON.stringify(data.preview || {}));
        } catch (e) {
          console.warn(e);
        }
        S.audioPreviewDomandaId = Number(domanda?.id || 0);
        Support.scheduleAudioPreviewButtonReset(domanda?.id || 0, data?.preview?.preview_sec || domanda?.media_audio_preview_sec || 0);
        Support.syncAudioPreviewButton();
      }
    },

    async gestisciJoin(requestId, action) {
      const formData = new FormData();
      formData.append('request_id', requestId);

      const res = await fetch(`${S.API_BASE}/admin/${action}/${S.SESSIONE_ID}`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
        body: formData,
      });

      const data = await res.json();
      addLog({
        ok: !!data.success,
        title: action === 'approva-join' ? 'Join approvata' : 'Join rifiutata',
        message: data.success
          ? `Richiesta #${requestId} ${action === 'approva-join' ? 'approvata' : 'rifiutata'}`
          : (data.error || 'Operazione fallita'),
        data,
      });

      if (data.success) {
        Admin.actions.aggiornaJoinRichieste();
        Admin.actions.aggiornaPartecipanti();
      }
    },

    async callAdmin(action) {
      const res = await fetch(`${S.API_BASE}/admin/${action}/${S.SESSIONE_ID}`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
      });

      const data = await res.json();
      addLog({
        ok: !!data.success,
        title: action,
        message: data.success
          ? `Azione "${action}" eseguita`
          : (data.error ? `Errore: ${data.error}` : 'Errore sconosciuto'),
        data,
      });

      await Admin.actions.aggiornaStato();
      await Admin.actions.aggiornaJoinRichieste();
      await Admin.actions.aggiornaDomandaCorrenteMeta();
    },

    apriMedia() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', 'admin/media');
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Media', message: 'Aperta gestione media', data: {} });
    },

    apriSchermo() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', `screen/${S.SESSIONE_ID}`);
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Screen', message: `Schermo attivato per sessione ${S.SESSIONE_ID}`, data: { sessione_id: S.SESSIONE_ID } });
    },

    apriSettings() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', 'admin/settings');
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Settings', message: 'Aperto pannello settings', data: {} });
    },

    async aggiornaStato() {
      if (S.statoRequestInFlight || !S.SESSIONE_ID) return;
      S.statoRequestInFlight = true;
      try {
        if (!S.memeDraftText) {
          S.memeDraftText = Support.loadMemeDraft();
        }
        const res = await fetch(`${S.API_BASE}/stato/${S.SESSIONE_ID}`);
        const data = await res.json();
        if (data.success) {
          Admin.ui.aggiornaUI(data.sessione);
        }
      } finally {
        S.statoRequestInFlight = false;
      }
    },

    async toggleImpostoreCorrente() {
      const targetSessioneId = Number(D.sessioneSelect?.value || S.SESSIONE_ID || 0);
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'impostore-toggle', message: 'Sessione non valida', data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('enabled', S.impostoreEnabled ? '0' : '1');

      try {
        const res = await fetch(`${S.API_BASE}/admin/impostore-toggle/0`, {
          method: 'POST',
          headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
          body: formData,
        });

        const data = await res.json();
        addLog({
          ok: !!data.success,
          title: 'impostore-toggle',
          message: data.success
            ? `IMPOSTORE ${data.enabled ? 'attivato' : 'disattivato'} per la domanda corrente`
            : (data.error || 'Operazione fallita'),
          data,
        });

        if (data.success) {
          S.impostoreEnabled = !!data.enabled;
          await Admin.actions.aggiornaStato();
          await Admin.actions.aggiornaDomandaCorrenteMeta();
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'impostore-toggle',
          message: 'Errore rete durante toggle IMPOSTORE',
          data: { error: String(e?.message || e) },
        });
      }
    },

    async toggleMemeCorrente() {
      const targetSessioneId = Number(D.sessioneSelect?.value || S.SESSIONE_ID || 0);
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'meme-toggle', message: 'Sessione non valida', data: {} });
        return;
      }

      const nextEnabled = !S.memeEnabled;
      const memeText = Support.sanitizeMemeText(D.memeTextInput?.value || S.memeDraftText || S.memeText || '');
      if (nextEnabled && memeText === '') {
        addLog({ ok: false, title: 'meme-toggle', message: 'Inserisci prima il testo MEME', data: {} });
        if (D.memeTextInput) D.memeTextInput.focus();
        return;
      }

      if (memeText !== '') {
        Support.saveMemeDraft(memeText);
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('enabled', nextEnabled ? '1' : '0');
      formData.append('meme_text', memeText);

      try {
        const res = await fetch(`${S.API_BASE}/admin/meme-toggle/0`, {
          method: 'POST',
          headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
          body: formData,
        });

        const data = await res.json();
        addLog({
          ok: !!data.success,
          title: 'meme-toggle',
          message: data.success
            ? `MEME ${data.enabled ? 'attivato' : 'disattivato'} per la domanda corrente`
            : (data.error || 'Operazione fallita'),
          data,
        });

        if (data.success) {
          S.memeText = Support.sanitizeMemeText(data.meme_text || '');
          if (memeText !== '') {
            Support.saveMemeDraft(memeText);
          }
          Support.refreshMemeToggleUi(!!data.enabled, S.memeText);
          await Admin.actions.aggiornaStato();
          await Admin.actions.aggiornaDomandaCorrenteMeta();
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'meme-toggle',
          message: 'Errore rete durante toggle MEME',
          data: { error: String(e?.message || e) },
        });
      }
    },
  });

  window.gestisciJoin = (requestId, action) => Admin.actions.gestisciJoin(requestId, action);
})();
