// admin/07e_actions_runtime_question.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;
  const { addLog } = Admin.log;
  const Runtime = Admin.runtimeSupport;
  const Support = Admin.actionsSupport;
  const Copy = Runtime.copy;

  Object.assign(Admin.actions, {
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
          data: { error: String((e && e.message) || e) },
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
      const memeText = Support.sanitizeMemeText((D.memeTextInput && D.memeTextInput.value) || S.memeDraftText || S.memeText || '');
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
          data: { error: String((e && e.message) || e) },
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
          data: { error: String((e && e.message) || e) },
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
          data: { error: String((e && e.message) || e) },
        });
      }
    },
  });
})();
