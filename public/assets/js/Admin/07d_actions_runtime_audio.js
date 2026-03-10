// admin/07d_actions_runtime_audio.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;
  const { addLog } = Admin.log;
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
})();
