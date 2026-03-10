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

  function readSarabandaFastRate() {
    const activeButton = Array.isArray(D.sarabandaFastRateButtons)
      ? D.sarabandaFastRateButtons.find((btn) => btn.classList.contains('is-active-rate'))
      : null;
    return Number(activeButton?.dataset.fastRate || S.sarabandaFastForwardRate || 5);
  }

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
        const data = await Runtime.fetchAdminJson('join-richieste', S.SESSIONE_ID);
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

    async avviaAnteprimaAudio() {
      const domanda = S.domandaCorrente || null;
      const hasAudio = String(domanda?.media_audio_path || '').trim() !== '';

      if (!hasAudio) {
        addLog({ ok: false, title: 'audio-preview', message: 'La domanda corrente non ha audio', data: {} });
        Support.syncAudioPreviewButton();
        return;
      }

      const data = await Runtime.fetchAdminJson('audio-preview', S.SESSIONE_ID);
      Runtime.logActionResult('audio-preview', data, 'Anteprima audio inviata allo schermo', 'Errore avvio anteprima audio');

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

      const data = await Runtime.fetchAdminJson(action, S.SESSIONE_ID, formData);
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

    async callAdmin(action) {
      const data = await Runtime.fetchAdminJson(action, S.SESSIONE_ID);
      addLog({
        ok: !!data.success,
        title: action,
        message: data.success
          ? `Azione "${action}" eseguita`
          : (data.error ? `Errore: ${data.error}` : 'Errore sconosciuto'),
        data,
      });

      await Runtime.refreshRuntimeContext();
    },

    apriMedia() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', 'admin/media');
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Media', message: 'Aperta gestione media', data: {} });
    },

    apriSchermo() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', 'screen');
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Screen', message: 'Schermo aperto sulla sessione corrente', data: { sessione_id: S.SESSIONE_ID } });
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

    async aggiornaDebugSessione(forceOpen = false) {
      if (S.debugSessioneInFlight || !S.SESSIONE_ID) return;
      S.debugSessioneInFlight = true;
      try {
        const formData = new FormData();
        formData.append('sessione_id', String(S.SESSIONE_ID));

        const data = await Runtime.fetchAdminJson('debug-sessione', S.SESSIONE_ID, formData);

        if (!data.success) {
          addLog({ ok: false, title: 'debug-sessione', message: data.error || 'Errore debug sessione', data });
          return;
        }

        const debugPayload = Object.assign({}, data.debug || {});
        try {
          const playerTimingRaw = window.localStorage.getItem(`chillquiz_debug_timing_player_${S.SESSIONE_ID}`);
          const screenTimingRaw = window.localStorage.getItem(`chillquiz_debug_timing_screen_${S.SESSIONE_ID}`);
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

    async toggleImpostoreCorrente() {
      const targetSessioneId = Runtime.readTargetSessioneId();
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'impostore-toggle', message: Copy.invalidSession, data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('enabled', S.impostoreEnabled ? '0' : '1');

      try {
        const data = await Runtime.fetchAdminJson('impostore-toggle', 0, formData);
        Runtime.logActionResult(
          'impostore-toggle',
          data,
          `IMPOSTORE ${data.enabled ? 'attivato' : 'disattivato'} per la domanda corrente`
        );

        if (data.success) {
          S.impostoreEnabled = !!data.enabled;
          await Runtime.refreshRuntimeContext();
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'impostore-toggle',
          message: Copy.networkImpostoreError,
          data: { error: String(e?.message || e) },
        });
      }
    },

    async toggleMemeCorrente() {
      const targetSessioneId = Runtime.readTargetSessioneId();
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'meme-toggle', message: Copy.invalidSession, data: {} });
        return;
      }

      const nextEnabled = !S.memeEnabled;
      const memeText = Support.sanitizeMemeText(D.memeTextInput?.value || S.memeDraftText || S.memeText || '');
      if (nextEnabled && memeText === '') {
        addLog({ ok: false, title: 'meme-toggle', message: Copy.memeTextRequired, data: {} });
        if (D.memeTextInput) D.memeTextInput.focus();
        return;
      }

      if (memeText !== '') {
        Runtime.persistMemeDraft(memeText);
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('enabled', nextEnabled ? '1' : '0');
      formData.append('meme_text', memeText);

      try {
        const data = await Runtime.fetchAdminJson('meme-toggle', 0, formData);
        Runtime.logActionResult(
          'meme-toggle',
          data,
          `MEME ${data.enabled ? 'attivato' : 'disattivato'} per la domanda corrente`
        );

        if (data.success) {
          S.memeText = Support.sanitizeMemeText(data.meme_text || '');
          if (memeText !== '') {
            Runtime.persistMemeDraft(memeText);
          }
          Support.refreshMemeToggleUi(!!data.enabled, S.memeText);
          await Runtime.refreshRuntimeContext();
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'meme-toggle',
          message: Copy.networkMemeError,
          data: { error: String(e?.message || e) },
        });
      }
    },

    async toggleImagePartyCorrente() {
      const targetSessioneId = Runtime.readTargetSessioneId();
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'image-party-toggle', message: Copy.invalidSession, data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('enabled', S.imagePartyEnabled ? '0' : '1');

      try {
        const data = await Runtime.fetchAdminJson('image-party-toggle', 0, formData);
        Runtime.logActionResult(
          'image-party-toggle',
          data,
          `PIXELATE ${data.enabled ? 'attivato' : 'disattivato'} per la domanda corrente`
        );

        if (data.success) {
          S.imagePartyEnabled = !!data.enabled;
          await Runtime.refreshRuntimeContext();
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'image-party-toggle',
          message: Copy.networkImagePartyError,
          data: { error: String(e?.message || e) },
        });
      }
    },

    async toggleFadeCorrente() {
      const targetSessioneId = Runtime.readTargetSessioneId();
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'fade-toggle', message: Copy.invalidSession, data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('enabled', S.fadeEnabled ? '0' : '1');

      try {
        const data = await Runtime.fetchAdminJson('fade-toggle', 0, formData);
        Runtime.logActionResult(
          'fade-toggle',
          data,
          `FADE ${data.enabled ? 'attivato' : 'disattivato'} per la domanda corrente`
        );

        if (data.success) {
          S.fadeEnabled = !!data.enabled;
          await Runtime.refreshRuntimeContext();
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'fade-toggle',
          message: Copy.networkFadeError,
          data: { error: String(e?.message || e) },
        });
      }
    },

    async toggleSarabandaReverse() {
      const targetSessioneId = Runtime.readTargetSessioneId();
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'sarabanda-reverse-toggle', message: Copy.invalidSession, data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('enabled', S.sarabandaReverseEnabled ? '0' : '1');

      try {
        const data = await Runtime.fetchAdminJson('sarabanda-reverse-toggle', 0, formData);
        Runtime.logActionResult(
          'sarabanda-reverse-toggle',
          data,
          `REVERSE SARABANDA ${data.enabled ? 'attivato' : 'disattivato'}`
        );

        if (data.success) {
          S.sarabandaReverseEnabled = !!data.enabled;
          if (data.enabled) S.sarabandaFastForwardEnabled = false;
          await Runtime.refreshRuntimeContext();
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'sarabanda-reverse-toggle',
          message: Copy.networkSarabandaReverseError,
          data: { error: String(e?.message || e) },
        });
      }
    },

    async toggleSarabandaAudio() {
      const targetSessioneId = Runtime.readTargetSessioneId();
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'sarabanda-audio-toggle', message: Copy.invalidSession, data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('enabled', S.sarabandaAudioEnabled ? '0' : '1');

      try {
        const data = await Runtime.fetchAdminJson('sarabanda-audio-toggle', 0, formData);
        Runtime.logActionResult(
          'sarabanda-audio-toggle',
          data,
          `SARABANDA ${data.enabled ? 'attivata' : 'disattivata'}`
        );

        if (data.success) {
          S.sarabandaAudioEnabled = !!data.enabled;
          await Runtime.refreshRuntimeContext();
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'sarabanda-audio-toggle',
          message: Copy.networkSarabandaAudioError,
          data: { error: String(e?.message || e) },
        });
      }
    },

    async toggleSarabandaFast() {
      const targetSessioneId = Runtime.readTargetSessioneId();
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'sarabanda-fast-toggle', message: Copy.invalidSession, data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('enabled', S.sarabandaFastForwardEnabled ? '0' : '1');
      formData.append('rate', String(readSarabandaFastRate()));

      try {
        const data = await Runtime.fetchAdminJson('sarabanda-fast-toggle', 0, formData);
        Runtime.logActionResult(
          'sarabanda-fast-toggle',
          data,
          `FAST SARABANDA ${data.enabled ? 'attivato' : 'disattivato'}`
        );

        if (data.success) {
          S.sarabandaFastForwardEnabled = !!data.enabled;
          S.sarabandaFastForwardRate = Number(data.rate || S.sarabandaFastForwardRate || 5);
          if (data.enabled) S.sarabandaReverseEnabled = false;
          await Runtime.refreshRuntimeContext();
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'sarabanda-fast-toggle',
          message: Copy.networkSarabandaFastError,
          data: { error: String(e?.message || e) },
        });
      }
    },

    async setSarabandaFastRate() {
      const targetSessioneId = Runtime.readTargetSessioneId();
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'sarabanda-fast-rate-set', message: Copy.invalidSession, data: {} });
        return;
      }

      const rate = readSarabandaFastRate();
      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('rate', String(rate));

      try {
        const data = await Runtime.fetchAdminJson('sarabanda-fast-rate-set', 0, formData);
        Runtime.logActionResult(
          'sarabanda-fast-rate-set',
          data,
          `Velocita FAST impostata a x${data.rate}`
        );

        if (data.success) {
          S.sarabandaFastForwardRate = Number(data.rate || rate);
          await Runtime.refreshRuntimeContext();
        }
      } catch (e) {
        addLog({
          ok: false,
          title: 'sarabanda-fast-rate-set',
          message: Copy.networkSarabandaFastRateError,
          data: { error: String(e?.message || e) },
        });
      }
    },
  });

  window.gestisciJoin = (requestId, action) => Admin.actions.gestisciJoin(requestId, action);
})();
