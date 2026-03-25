// admin/07a_actions_session.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;
  const { escapeHtml, nomeSessioneFromRecord } = Admin.utils;
  const { addLog } = Admin.log;
  const Support = Admin.actionsSupport;
  const C = Support.cache;

  Object.assign(Admin.actions, {
    async nuovaSessione() {
      const nomeSessione = Support.extractNomeForNewSession(D.inputSessioneNome?.value || '');
      const numeroDomande = Number(D.inputSessioneNumeroDomande?.value || 0);
      const poolRaw = String(D.inputSessionePoolTipo?.value || 'misto').trim();
      const selezioneTipo = String(D.inputSessioneSelezioneTipo?.value || 'random').trim() === 'manuale'
        ? 'manuale'
        : 'random';
      const argomentoRaw = String(D.inputSessioneArgomentoId?.value || '').trim();
      const maxPerArgomentoRaw = String(D.inputSessioneMaxPerArgomento?.value || '').trim();

      const poolTipo = poolRaw === 'sarabanda'
        ? 'sarabanda'
        : ((poolRaw === 'fisso' || poolRaw === 'mono') ? 'mono' : 'tutti');
      const argomentoId = poolTipo === 'mono' && Number(argomentoRaw) > 0 ? String(Number(argomentoRaw)) : '';
      const maxPerArgomento = poolTipo === 'tutti' && selezioneTipo === 'random' && Number(maxPerArgomentoRaw) > 0
        ? String(Math.floor(Number(maxPerArgomentoRaw)))
        : '';

      const formData = new FormData();
      if (nomeSessione !== '') formData.append('nome', nomeSessione);
      if (Number.isFinite(numeroDomande) && numeroDomande > 0) {
        formData.append('numero_domande', String(Math.floor(numeroDomande)));
      }
      formData.append('pool_tipo', poolTipo);
      formData.append('argomento_id', argomentoId);
      formData.append('selezione_tipo', selezioneTipo);
      formData.append('max_per_argomento', maxPerArgomento);

      const res = await fetch(`${S.API_BASE}/admin/nuova-sessione/0`, {
        method: 'POST',
        body: formData,
      });

      const data = await res.json();
      addLog({
        ok: !!data.success,
        title: 'nuova-sessione',
        message: data.success
          ? `Creata nuova sessione: ${data.sessione_id}`
          : (data.error ? `Errore: ${data.error}` : 'Errore sconosciuto'),
        data,
      });

      if (data.success) {
        S.SESSIONE_ID = data.sessione_id;
        const nomeRisposta = String(data.nome_sessione || '').trim();
        if (nomeRisposta !== '') S.NOME_SESSIONE = nomeRisposta;
        else if (nomeSessione !== '') S.NOME_SESSIONE = nomeSessione;
        else S.NOME_SESSIONE = '';

        if (D.inputSessioneNome) D.inputSessioneNome.value = '';

        Admin.actions.aggiornaStato();
        Admin.actions.aggiornaJoinRichieste();
        Admin.actions.caricaSessioni();
        Admin.actions.aggiornaDomandaCorrenteMeta();
      }
    },

    async caricaSessioni() {
      if (!D.sessioneSelect) return;

      const res = await fetch(`${S.API_BASE}/admin/sessioni-lista/0`, {
        method: 'POST',
      });

      const data = await res.json();
      if (!data.success) return;

      const lista = Array.isArray(data.sessioni) ? data.sessioni : [];
      C.sessioniCache = new Map();
      C.sessionLabelToId = new Map();
      C.sessionNameToId = new Map();

      lista.forEach((sessione) => {
        const id = Number(sessione.id || 0);
        if (id <= 0) return;
        C.sessioniCache.set(id, sessione);
        const label = Support.buildSessionLabel(sessione);
        C.sessionLabelToId.set(label, id);
        const nameLower = String(sessione.nome_sessione || sessione.nome || sessione.titolo || '').trim().toLowerCase();
        if (nameLower !== '' && !C.sessionNameToId.has(nameLower)) {
          C.sessionNameToId.set(nameLower, id);
        }
      });

      if (D.inputSessioneNomeOptions) {
        D.inputSessioneNomeOptions.innerHTML = lista.map((sessione) => {
          const label = escapeHtml(Support.buildSessionLabel(sessione));
          return `<option value="${label}"></option>`;
        }).join('');
      }

      D.sessioneSelect.innerHTML = lista.map((sessione) => {
        const id = Number(sessione.id || 0);
        const nome = escapeHtml(nomeSessioneFromRecord(sessione));
        return `<option value="${id}">${id} · ${nome}</option>`;
      }).join('');

      const correnteId = Number(data.sessione_corrente_id || S.SESSIONE_ID || 0);
      if (correnteId > 0) {
        Support.applySessioneSelection(correnteId);
      }
    },

    syncArgomentoFieldState() {
      if (!D.inputSessioneArgomentoId || !D.inputSessionePoolTipo) return;
      const poolTipo = String(D.inputSessionePoolTipo.value || 'misto');
      const isFisso = poolTipo === 'fisso';
      D.inputSessioneArgomentoId.disabled = !isFisso;
      if (!isFisso) {
        D.inputSessioneArgomentoId.value = '';
      }
    },

    syncMaxPerArgomentoFieldState() {
      if (!D.inputSessioneMaxPerArgomento || !D.inputSessionePoolTipo || !D.inputSessioneSelezioneTipo) return;
      const poolTipo = String(D.inputSessionePoolTipo.value || 'misto');
      const selezioneTipo = String(D.inputSessioneSelezioneTipo.value || 'random');
      const enabled = poolTipo === 'misto' && selezioneTipo === 'random';
      D.inputSessioneMaxPerArgomento.disabled = !enabled;
      if (!enabled) {
        D.inputSessioneMaxPerArgomento.value = '';
      }
    },

    async caricaArgomenti() {
      if (!D.inputSessioneArgomentoId) return;

      const res = await fetch(`${S.API_BASE}/admin/argomenti-lista/0`, {
        method: 'POST',
      });

      const data = await res.json();
      if (!data.success) {
        addLog({ ok: false, title: 'argomenti-lista', message: data.error || 'Errore caricamento argomenti', data });
        return;
      }

      C.argomentiCache = Array.isArray(data.argomenti) ? data.argomenti : [];
      D.inputSessioneArgomentoId.innerHTML = '<option value="">Argomento (solo se fisso)</option>' +
        C.argomentiCache.map((argomento) => `<option value="${Number(argomento.id || 0)}">${escapeHtml(String(argomento.nome || `Argomento ${Number(argomento.id || 0)}`))}</option>`).join('');

      Admin.actions.syncArgomentoFieldState();
      Admin.actions.syncMaxPerArgomentoFieldState();
    },

    popolaFormSessioneDaSelect() {
      const sessioneId = Number(D.sessioneSelect?.value || 0);
      if (sessioneId <= 0) return;
      Support.applySessioneSelection(sessioneId);
    },

    syncSessioneDaNomeInput() {
      const id = Support.resolveSessioneIdFromNomeInput();
      if (id <= 0) return 0;
      Support.applySessioneSelection(id);
      return id;
    },

    async salvaSessioneCorrente() {
      const fromNome = Admin.actions.syncSessioneDaNomeInput();
      const targetId = Number(D.sessioneSelect?.value || fromNome || 0);
      if (targetId <= 0) {
        addLog({ ok: false, title: 'sessione-update', message: 'Seleziona una sessione valida', data: {} });
        return;
      }

      const nomeSessione = String(D.inputSessioneNome?.value || '').trim();
      const numeroDomande = Number(D.inputSessioneNumeroDomande?.value || 0);
      const poolRaw = String(D.inputSessionePoolTipo?.value || 'misto').trim();
      const selezioneTipo = String(D.inputSessioneSelezioneTipo?.value || 'random').trim() === 'manuale'
        ? 'manuale'
        : 'random';
      const argomentoRaw = String(D.inputSessioneArgomentoId?.value || '').trim();
      const maxPerArgomentoRaw = String(D.inputSessioneMaxPerArgomento?.value || '').trim();

      const poolTipo = poolRaw === 'sarabanda'
        ? 'sarabanda'
        : ((poolRaw === 'fisso' || poolRaw === 'mono') ? 'mono' : 'tutti');
      const argomentoId = poolTipo === 'mono' && Number(argomentoRaw) > 0 ? String(Number(argomentoRaw)) : '';
      const maxPerArgomento = poolTipo === 'tutti' && selezioneTipo === 'random' && Number(maxPerArgomentoRaw) > 0
        ? String(Math.floor(Number(maxPerArgomentoRaw)))
        : '';

      const formData = new FormData();
      formData.append('sessione_id', String(targetId));
      formData.append('nome_sessione', nomeSessione);
      formData.append('numero_domande', Number.isFinite(numeroDomande) && numeroDomande > 0 ? String(Math.floor(numeroDomande)) : '10');
      formData.append('pool_tipo', poolTipo);
      formData.append('argomento_id', argomentoId);
      formData.append('selezione_tipo', selezioneTipo);
      formData.append('max_per_argomento', maxPerArgomento);

      const res = await fetch(`${S.API_BASE}/admin/sessione-update/0`, {
        method: 'POST',
        body: formData,
      });

      const data = await res.json();
      addLog({
        ok: !!data.success,
        title: 'sessione-update',
        message: data.success ? `Sessione ${targetId} aggiornata` : (data.error || 'Errore aggiornamento sessione'),
        data,
      });

      if (data.success) {
        await Admin.actions.caricaSessioni();
        D.sessioneSelect.value = String(targetId);
        Admin.actions.popolaFormSessioneDaSelect();
      }
    },

    async impostaSessioneCorrente() {
      if (!D.sessioneSelect) return;

      const fromNome = Admin.actions.syncSessioneDaNomeInput();
      const targetId = Number(D.sessioneSelect.value || fromNome || 0);
      if (targetId <= 0) {
        addLog({ ok: false, title: 'set-corrente', message: 'Seleziona una sessione valida', data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetId));

      const res = await fetch(`${S.API_BASE}/admin/set-corrente/0`, {
        method: 'POST',
        body: formData,
      });

      const data = await res.json();
      addLog({
        ok: !!data.success,
        title: 'set-corrente',
        message: data.success ? `Sessione corrente impostata: ${targetId}` : (data.error || 'Operazione fallita'),
        data,
      });

      if (data.success) {
        S.SESSIONE_ID = targetId;
        await Admin.actions.aggiornaStato();
        await Admin.actions.aggiornaJoinRichieste();
        await Admin.actions.caricaSessioni();
        Support.applySessioneSelection(targetId);
        await Admin.actions.aggiornaDomandaCorrenteMeta();

        if (D.domandeSessioneWrapper && D.domandeSessioneWrapper.style.display !== 'none') {
          await Admin.actions.caricaDomandeSessione(targetId);
        }

        if (D.domandaEditorWrapper && D.domandaEditorWrapper.style.display !== 'none') {
          await Admin.actions.caricaDomandaEditor();
        }
      }
    },
  });
})();
