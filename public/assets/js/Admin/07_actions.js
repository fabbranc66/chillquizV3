// admin/07_actions.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;
  const { escapeHtml, nomeSessioneFromRecord } = Admin.utils;
  const { addLog } = Admin.log;
  const { renderClassificaLive, renderJoinRichieste } = Admin.render;

  const TYPES_WITH_MEDIA = new Set(['MEDIA', 'SARABANDA', 'AUDIO_PARTY', 'IMAGE_PARTY']);
  let mediaCatalog = [];

  function yesNo(value) {
    return value ? 'si' : 'no';
  }

  function normalizeTipo(raw) {
    const value = String(raw || '').trim().toUpperCase();
    const allowed = [
      'CLASSIC', 'MEDIA', 'SARABANDA', 'IMPOSTORE', 'MEME', 'MAJORITY',
      'RANDOM_BONUS', 'BOMB', 'CHAOS', 'AUDIO_PARTY', 'IMAGE_PARTY',
    ];

    return allowed.includes(value) ? value : 'CLASSIC';
  }

  function normalizePath(path) {
    const raw = String(path || '').trim();
    if (raw === '') return '';
    return raw.startsWith('/') ? raw : `/${raw}`;
  }

  function renderDomandaCorrenteMeta(domanda) {
    if (!D.domandaCorrenteMetaBody) return;

    if (!domanda || !domanda.id) {
      D.domandaCorrenteMetaBody.innerHTML = 'Nessuna domanda attiva';
      return;
    }

    const tipo = escapeHtml(String(domanda.tipo_domanda || 'CLASSIC'));
    const modalita = escapeHtml(String(domanda.modalita_party || '-'));
    const fase = escapeHtml(String(domanda.fase_domanda || 'domanda'));
    const hasImage = String(domanda.media_image_path || '').trim() !== '';
    const hasAudio = String(domanda.media_audio_path || '').trim() !== '';
    const hasCaption = String(domanda.media_caption || '').trim() !== '';
    const testo = escapeHtml(String(domanda.testo || ''));

    D.domandaCorrenteMetaBody.innerHTML = `
      <div style="padding:4px 0;"><strong>ID:</strong> ${Number(domanda.id || 0)}</div>
      <div style="padding:4px 0;"><strong>Testo:</strong> ${testo}</div>
      <div style="padding:4px 0;"><strong>Tipo:</strong> ${tipo}</div>
      <div style="padding:4px 0;"><strong>Modalita party:</strong> ${modalita}</div>
      <div style="padding:4px 0;"><strong>Fase domanda:</strong> ${fase}</div>
      <div style="padding:4px 0;"><strong>Media:</strong> immagine=${yesNo(hasImage)} · audio=${yesNo(hasAudio)} · caption=${yesNo(hasCaption)}</div>
    `;
  }

  function syncAudioPreviewButton() {
    if (!D.btnAudioPreview) return;

    const domanda = S.domandaCorrente || null;
    const hasAudio = String(domanda?.media_audio_path || '').trim() !== '';
    D.btnAudioPreview.style.display = hasAudio ? 'inline-block' : 'none';
  }

  function editorSetValue(el, value) {
    if (!el) return;
    el.value = value === null || value === undefined ? '' : String(value);
  }

  function buildMediaOptionLabel(item) {
    const title = String(item.titolo || `Media #${Number(item.id || 0)}`);
    const file = String(item.file_path || '');
    return `${title} · ${file}`;
  }

  function fillMediaSelect(selectEl, items, currentPath, typeFilter) {
    if (!selectEl) return;

    const normalizedCurrent = normalizePath(currentPath);
    const baseOption = '<option value="">-- nessuno / manuale --</option>';
    const filtered = (Array.isArray(items) ? items : []).filter((item) => {
      if (!typeFilter) return true;
      const kind = String(item.tipo_file || '').toLowerCase();
      return kind === String(typeFilter).toLowerCase();
    });

    const options = filtered.map((item) => {
      const filePath = normalizePath(item.file_path || '');
      const selected = filePath !== '' && filePath === normalizedCurrent ? ' selected' : '';
      return `<option value="${escapeHtml(filePath)}"${selected}>${escapeHtml(buildMediaOptionLabel(item))}</option>`;
    }).join('');

    selectEl.innerHTML = baseOption + options;
  }

  function applyMediaSelectToInput(selectEl, inputEl) {
    if (!selectEl || !inputEl) return;

    const value = normalizePath(selectEl.value || '');
    if (value !== '') {
      inputEl.value = value;
    }
  }

  function fillDomandaEditorFromData(domanda) {
    if (!domanda) return;

    const tipo = normalizeTipo(domanda.tipo_domanda || 'CLASSIC');

    editorSetValue(D.domandaEditorId, Number(domanda.id || 0));
    editorSetValue(D.domandaEditorTipo, tipo);
    editorSetValue(D.domandaEditorFase, String(domanda.fase_domanda || 'domanda').toLowerCase() === 'intro' ? 'intro' : 'domanda');
    editorSetValue(D.domandaEditorModalitaParty, domanda.modalita_party || '');
    editorSetValue(D.domandaEditorMediaImage, normalizePath(domanda.media_image_path || ''));
    editorSetValue(D.domandaEditorMediaAudio, normalizePath(domanda.media_audio_path || ''));
    editorSetValue(D.domandaEditorMediaPreview, domanda.media_audio_preview_sec ?? '');
    editorSetValue(D.domandaEditorMediaCaption, domanda.media_caption || '');

    const cfg = domanda.config_domanda;
    if (cfg && typeof cfg === 'object' && Object.keys(cfg).length > 0) {
      editorSetValue(D.domandaEditorConfigJson, JSON.stringify(cfg, null, 2));
      return;
    }

    const configJsonRaw = String(domanda.config_json || '').trim();
    if (configJsonRaw !== '') {
      try {
        const parsed = JSON.parse(configJsonRaw);
        editorSetValue(D.domandaEditorConfigJson, JSON.stringify(parsed, null, 2));
        return;
      } catch (e) {
        editorSetValue(D.domandaEditorConfigJson, configJsonRaw);
        return;
      }
    }

    editorSetValue(D.domandaEditorConfigJson, '');

    if (D.domandaEditorSelectedInfo) {
      const id = Number(domanda.id || 0);
      const testo = escapeHtml(String(domanda.testo || ''));
      const tipoLabel = escapeHtml(String(tipo || 'CLASSIC'));
      D.domandaEditorSelectedInfo.innerHTML = `<strong>Domanda selezionata:</strong> [${id}] ${testo}<br><span style="opacity:.85;">Tipo: ${tipoLabel}</span>`;
    }
  }

  Admin.actions = {
    syncDomandaEditorVisibility() {
      const tipo = normalizeTipo(D.domandaEditorTipo?.value || 'CLASSIC');

      if (D.domandaEditorRowFase) {
        D.domandaEditorRowFase.style.display = (tipo === 'SARABANDA') ? 'block' : 'none';
      }

      if (D.domandaEditorMediaWrap) {
        D.domandaEditorMediaWrap.style.display = TYPES_WITH_MEDIA.has(tipo) ? 'block' : 'none';
      }

      if (D.domandaEditorRowParty) {
        const partyTypes = ['AUDIO_PARTY', 'IMAGE_PARTY', 'CHAOS', 'MEME', 'RANDOM_BONUS', 'BOMB', 'IMPOSTORE', 'MAJORITY'];
        D.domandaEditorRowParty.style.display = partyTypes.includes(tipo) ? 'block' : 'none';
      }
    },

    async caricaCatalogoMedia() {
      const res = await fetch(`${S.API_BASE}/admin/domanda-media-list/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
      });

      const data = await res.json();
      if (!data.success) {
        addLog({ ok: false, title: 'media-list', message: data.error || 'Errore caricamento catalogo media', data });
        return;
      }

      mediaCatalog = Array.isArray(data.media) ? data.media : [];

      fillMediaSelect(D.domandaEditorMediaImageSelect, mediaCatalog, D.domandaEditorMediaImage?.value || '', 'image');
      fillMediaSelect(D.domandaEditorMediaAudioSelect, mediaCatalog, D.domandaEditorMediaAudio?.value || '', 'audio');

      addLog({ ok: true, title: 'media-list', message: `Catalogo media caricato (${mediaCatalog.length})`, data: { count: mediaCatalog.length } });
    },

    async uploadDomandaMedia() {
      const fileInput = D.domandaEditorUploadFile;
      const titleInput = D.domandaEditorUploadTitle;

      if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        addLog({ ok: false, title: 'domanda-media-upload', message: 'Seleziona un file immagine/audio da caricare', data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('media_file', fileInput.files[0]);

      const titolo = String(titleInput?.value || '').trim();
      if (titolo !== '') {
        formData.append('titolo', titolo);
      }

      const res = await fetch(`${S.API_BASE}/admin/domanda-media-upload/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
        body: formData,
      });

      const data = await res.json();

      addLog({
        ok: !!data.success,
        title: 'domanda-media-upload',
        message: data.success ? 'Media domanda caricato correttamente' : (data.error || 'Errore upload media domanda'),
        data,
      });

      if (!data.success) {
        return;
      }

      const filePath = normalizePath(data.file_path || '');
      const tipoFile = String(data.tipo_file || '').toLowerCase();

      if (tipoFile === 'audio') {
        editorSetValue(D.domandaEditorMediaAudio, filePath);
      } else {
        editorSetValue(D.domandaEditorMediaImage, filePath);
      }

      if (titleInput) titleInput.value = '';
      if (fileInput) fileInput.value = '';

      await Admin.actions.caricaCatalogoMedia();
    },

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
          nuovi.forEach((p) => {
            addLog({
              ok: true,
              title: 'Nuovo giocatore',
              message: `${p.nome} si e unito alla sessione`,
              data: p,
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
      const res = await fetch(`${S.API_BASE}/admin/join-richieste/${S.SESSIONE_ID}`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
      });

      const data = await res.json();
      if (!data.success) return;

      renderJoinRichieste(data.richieste ?? []);
    },

    async aggiornaDomandaCorrenteMeta() {
      try {
        const res = await fetch(`${S.API_BASE}/domanda/${S.SESSIONE_ID}`);
        const data = await res.json();

        if (!data.success) {
          S.domandaCorrente = null;
          renderDomandaCorrenteMeta(null);
          syncAudioPreviewButton();
          return;
        }

        S.domandaCorrente = data.domanda || null;
        renderDomandaCorrenteMeta(data.domanda || null);
        syncAudioPreviewButton();
      } catch (e) {
        S.domandaCorrente = null;
        renderDomandaCorrenteMeta(null);
        syncAudioPreviewButton();
      }
    },

    async avviaAnteprimaAudio() {
      const domanda = S.domandaCorrente || null;
      const hasAudio = String(domanda?.media_audio_path || '').trim() !== '';

      if (!hasAudio) {
        addLog({ ok: false, title: 'audio-preview', message: 'La domanda corrente non ha audio', data: {} });
        syncAudioPreviewButton();
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
    },

    toggleDomandaEditor() {
      if (!D.domandaEditorWrapper) return;

      const isHidden = D.domandaEditorWrapper.style.display === 'none' || D.domandaEditorWrapper.style.display === '';
      D.domandaEditorWrapper.style.display = isHidden ? 'block' : 'none';

      if (isHidden) {
        Admin.actions.caricaDomandaEditor();
      }
    },

    async caricaDomandaEditor() {
      if (!D.domandaEditorWrapper) return;

      const res = await fetch(`${S.API_BASE}/domanda/${S.SESSIONE_ID}`);
      const data = await res.json();

      if (!data.success || !data.domanda) {
        editorSetValue(D.domandaEditorId, '0');
        editorSetValue(D.domandaEditorTipo, 'CLASSIC');
        editorSetValue(D.domandaEditorFase, 'domanda');
        editorSetValue(D.domandaEditorModalitaParty, '');
        editorSetValue(D.domandaEditorMediaImage, '');
        editorSetValue(D.domandaEditorMediaAudio, '');
        editorSetValue(D.domandaEditorMediaPreview, '');
        editorSetValue(D.domandaEditorMediaCaption, '');
        editorSetValue(D.domandaEditorConfigJson, '');
        if (D.domandaEditorSelectedInfo) {
          D.domandaEditorSelectedInfo.textContent = 'Nessuna domanda selezionata';
        }
        Admin.actions.syncDomandaEditorVisibility();
        await Admin.actions.caricaCatalogoMedia();
        return;
      }

      fillDomandaEditorFromData(data.domanda);

      Admin.actions.syncDomandaEditorVisibility();
      await Admin.actions.caricaCatalogoMedia();
    },

    async caricaDomandaEditorDaLista(domandaIdRaw) {
      const domandaId = Number(domandaIdRaw || 0);
      if (domandaId <= 0) return;
      const targetSessioneId = Number(D.sessioneSelect?.value || S.SESSIONE_ID || 0);
      if (targetSessioneId <= 0) {
        addLog({
          ok: false,
          title: 'domanda-dettaglio',
          message: 'Sessione non valida per caricare la domanda selezionata',
          data: { sessione_id: targetSessioneId, domanda_id: domandaId },
        });
        return;
      }

      if (D.domandaEditorWrapper) {
        D.domandaEditorWrapper.style.display = 'block';
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('domanda_id', String(domandaId));

      const res = await fetch(`${S.API_BASE}/admin/domanda-dettaglio/${targetSessioneId}`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
        body: formData,
      });

      const data = await res.json();
      if (!data.success || !data.domanda) {
        addLog({
          ok: false,
          title: 'domanda-dettaglio',
          message: data.error || `Impossibile caricare domanda ${domandaId}`,
          data,
        });
        return;
      }

      fillDomandaEditorFromData(data.domanda);
      Admin.actions.syncDomandaEditorVisibility();
      await Admin.actions.caricaCatalogoMedia();

      addLog({
        ok: true,
        title: 'domanda-dettaglio',
        message: `Domanda ${domandaId} caricata nell'editor`,
        data: { domanda_id: domandaId },
      });
    },

    async salvaDomandaEditor() {
      const domandaId = Number(D.domandaEditorId?.value || 0);
      const tipo = normalizeTipo(D.domandaEditorTipo?.value || 'CLASSIC');
      const fase = String(D.domandaEditorFase?.value || 'domanda').toLowerCase() === 'intro' ? 'intro' : 'domanda';
      const modalitaParty = String(D.domandaEditorModalitaParty?.value || '').trim();
      const mediaImage = String(D.domandaEditorMediaImage?.value || '').trim();
      const mediaAudio = String(D.domandaEditorMediaAudio?.value || '').trim();
      const mediaPreview = Number(D.domandaEditorMediaPreview?.value || 0);
      const mediaCaption = String(D.domandaEditorMediaCaption?.value || '').trim();
      const configJsonRaw = String(D.domandaEditorConfigJson?.value || '').trim();

      if (domandaId <= 0) {
        addLog({ ok: false, title: 'domanda-update', message: 'Nessuna domanda corrente da modificare', data: {} });
        return;
      }

      if (configJsonRaw !== '') {
        try {
          JSON.parse(configJsonRaw);
        } catch (e) {
          addLog({ ok: false, title: 'domanda-update', message: 'Config JSON non valido', data: { error: String(e?.message || e) } });
          return;
        }
      }

      const formData = new FormData();
      formData.append('domanda_id', String(domandaId));
      formData.append('tipo_domanda', tipo);
      formData.append('fase_domanda', fase);
      formData.append('modalita_party', modalitaParty);
      formData.append('media_image_path', mediaImage);
      formData.append('media_audio_path', mediaAudio);
      formData.append('media_audio_preview_sec', Number.isFinite(mediaPreview) && mediaPreview > 0 ? String(Math.floor(mediaPreview)) : '0');
      formData.append('media_caption', mediaCaption);
      formData.append('config_json', configJsonRaw);

      const res = await fetch(`${S.API_BASE}/admin/domanda-update/${S.SESSIONE_ID}`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
        body: formData,
      });

      const data = await res.json();

      addLog({
        ok: !!data.success,
        title: 'domanda-update',
        message: data.success ? `Domanda ${domandaId} aggiornata` : (data.error || 'Errore salvataggio domanda'),
        data,
      });

      if (data.success) {
        await Admin.actions.aggiornaDomandaCorrenteMeta();
        if (D.domandeSessioneWrapper && D.domandeSessioneWrapper.style.display !== 'none') {
          await Admin.actions.caricaDomandeSessione(S.SESSIONE_ID);
        }
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

    async caricaSessioni() {
      if (!D.sessioneSelect) return;

      const res = await fetch(`${S.API_BASE}/admin/sessioni-lista/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
      });

      const data = await res.json();
      if (!data.success) return;

      const lista = Array.isArray(data.sessioni) ? data.sessioni : [];
      D.sessioneSelect.innerHTML = lista.map((s) => {
        const id = Number(s.id || 0);
        const nome = escapeHtml(nomeSessioneFromRecord(s));
        return `<option value="${id}">${id} · ${nome}</option>`;
      }).join('');

      const correnteId = Number(data.sessione_corrente_id || S.SESSIONE_ID || 0);
      if (correnteId > 0) {
        D.sessioneSelect.value = String(correnteId);
      }
    },

    async impostaSessioneCorrente() {
      if (!D.sessioneSelect) return;

      const targetId = Number(D.sessioneSelect.value || 0);
      if (targetId <= 0) {
        addLog({ ok: false, title: 'set-corrente', message: 'Seleziona una sessione valida', data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetId));

      const res = await fetch(`${S.API_BASE}/admin/set-corrente/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
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
        await Admin.actions.aggiornaDomandaCorrenteMeta();

        if (D.domandeSessioneWrapper && D.domandeSessioneWrapper.style.display !== 'none') {
          await Admin.actions.caricaDomandeSessione(targetId);
        }

        if (D.domandaEditorWrapper && D.domandaEditorWrapper.style.display !== 'none') {
          await Admin.actions.caricaDomandaEditor();
        }
      }
    },

    async caricaDomandeSessione(sessioneId) {
      if (!D.domandeSessioneList) return;

      const formData = new FormData();
      formData.append('sessione_id', String(Number(sessioneId || 0)));

      const res = await fetch(`${S.API_BASE}/admin/domande-sessione/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
        body: formData,
      });

      const data = await res.json();
      if (!data.success) {
        D.domandeSessioneList.innerHTML = `<div>Errore caricamento domande: ${escapeHtml(data.error || 'errore sconosciuto')}</div>`;
        return;
      }

      const domande = Array.isArray(data.domande) ? data.domande : [];
      if (domande.length === 0) {
        D.domandeSessioneList.innerHTML = 'Nessuna domanda caricata';
        return;
      }

      D.domandeSessioneList.innerHTML = domande.map((d) => {
        const posizione = Number(d.posizione || 0);
        const id = Number(d.domanda_id || 0);
        const testo = escapeHtml(String(d.testo || ''));
        const tipo = escapeHtml(String(d.tipo_domanda || 'CLASSIC'));
        const fase = escapeHtml(String(d.fase_domanda || 'domanda'));
        const hasMedia = String(d.media_image_path || '').trim() !== '' || String(d.media_audio_path || '').trim() !== '';

        return `<div style="padding:8px 0; border-bottom:1px solid #222;">
          <div style="display:flex; gap:8px; align-items:center; justify-content:space-between;">
            <div style="min-width:0; flex:1 1 auto;">
              <div>#${posizione} · [${id}] ${testo}</div>
              <div style="font-size:12px; opacity:.9; margin-top:2px;">
                tipo=${tipo} · fase=${fase} · media=${hasMedia ? 'si' : 'no'}
              </div>
            </div>
            <button type="button" data-edit-domanda-id="${id}" style="padding:6px 10px; font-size:12px; border-radius:8px; margin:0;">
              Modifica
            </button>
          </div>
        </div>`;
      }).join('');

      D.domandeSessioneList.querySelectorAll('[data-edit-domanda-id]').forEach((btn) => {
        btn.onclick = () => {
          const domandaId = Number(btn.getAttribute('data-edit-domanda-id') || 0);
          Admin.actions.caricaDomandaEditorDaLista(domandaId);
        };
      });
    },

    toggleDomandeSessione() {
      if (!D.domandeSessioneWrapper) return;

      const isHidden = D.domandeSessioneWrapper.style.display === 'none' || D.domandeSessioneWrapper.style.display === '';
      D.domandeSessioneWrapper.style.display = isHidden ? 'block' : 'none';

      if (isHidden) {
        const sessioneId = Number(D.sessioneSelect?.value || S.SESSIONE_ID || 0);
        Admin.actions.caricaDomandeSessione(sessioneId);
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

      Admin.actions.aggiornaStato();
      Admin.actions.aggiornaJoinRichieste();
      Admin.actions.aggiornaDomandaCorrenteMeta();
    },

    async nuovaSessione() {
      const nomeSessione = String(D.inputSessioneNome?.value || '').trim();
      const numeroDomande = Number(D.inputSessioneNumeroDomande?.value || 0);
      const poolRaw = String(D.inputSessionePoolTipo?.value || 'misto').trim();
      const argomentoRaw = String(D.inputSessioneArgomentoId?.value || '').trim();

      const poolTipo = (poolRaw === 'fisso' || poolRaw === 'mono') ? 'mono' : 'tutti';
      const argomentoId = poolTipo === 'mono' && Number(argomentoRaw) > 0 ? String(Number(argomentoRaw)) : '';

      const formData = new FormData();

      if (nomeSessione !== '') formData.append('nome', nomeSessione);
      if (Number.isFinite(numeroDomande) && numeroDomande > 0) {
        formData.append('numero_domande', String(Math.floor(numeroDomande)));
      }
      formData.append('pool_tipo', poolTipo);
      formData.append('argomento_id', argomentoId);
      formData.append('selezione_tipo', 'random');

      const res = await fetch(`${S.API_BASE}/admin/nuova-sessione/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
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
      const res = await fetch(`${S.API_BASE}/stato/${S.SESSIONE_ID}`);
      const data = await res.json();
      if (data.success) {
        Admin.ui.aggiornaUI(data.sessione);
        Admin.actions.aggiornaDomandaCorrenteMeta();
      }
    },
  };

  window.gestisciJoin = (requestId, action) => Admin.actions.gestisciJoin(requestId, action);
})();



