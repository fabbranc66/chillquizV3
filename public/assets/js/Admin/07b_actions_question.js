// admin/07b_actions_question.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;
  const { escapeHtml } = Admin.utils;
  const { addLog } = Admin.log;
  const Support = Admin.actionsSupport;
  const C = Support.cache;

  Object.assign(Admin.actions, {
    syncDomandaEditorVisibility() {
      const tipo = Support.normalizeTipo(D.domandaEditorTipo?.value || 'CLASSIC');
      Support.ensureDefaultMediaPreviewValue();

      if (D.domandaEditorRowFase) {
        D.domandaEditorRowFase.style.display = (tipo === 'SARABANDA') ? 'block' : 'none';
      }

      if (D.domandaEditorMediaWrap) {
        D.domandaEditorMediaWrap.style.display = Support.TYPES_WITH_MEDIA.has(tipo) ? 'block' : 'none';
      }

      if (D.domandaEditorRowParty) {
        const partyTypes = ['AUDIO_PARTY', 'CHAOS', 'RANDOM_BONUS', 'BOMB', 'IMPOSTORE', 'MAJORITY'];
        D.domandaEditorRowParty.style.display = partyTypes.includes(tipo) ? 'block' : 'none';
      }
    },

    async caricaCatalogoMedia() {
      try {
        const res = await fetch(`${S.API_BASE}/admin/domanda-media-list/0`, {
          method: 'POST',
          cache: 'no-store',
        });

        const data = await res.json();
        if (!data.success) {
          addLog({ ok: false, title: 'media-list', message: data.error || 'Errore caricamento catalogo media', data });
          return;
        }

        C.mediaCatalog = Array.isArray(data.media) ? data.media : [];
        Support.fillMediaSelect(D.domandaEditorMediaImageSelect, C.mediaCatalog, D.domandaEditorMediaImage?.value || '', 'image');
        Support.fillMediaSelect(D.domandaEditorMediaAudioSelect, C.mediaCatalog, D.domandaEditorMediaAudio?.value || '', 'audio');
        Support.syncDomandaMediaPreview();

        const countImage = C.mediaCatalog.filter((item) => String(item.tipo_file || '').toLowerCase() === 'image').length;
        const countAudio = C.mediaCatalog.filter((item) => String(item.tipo_file || '').toLowerCase() === 'audio').length;
        addLog({
          ok: true,
          title: 'media-list',
          message: `Catalogo media caricato (img: ${countImage}, audio: ${countAudio})`,
          data: { total: C.mediaCatalog.length, image: countImage, audio: countAudio },
        });
      } catch (e) {
        addLog({
          ok: false,
          title: 'media-list',
          message: 'Errore rete/caricamento catalogo media',
          data: { error: String(e?.message || e) },
        });
      }
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

      const filePath = Support.normalizePath(data.file_path || '');
      const tipoFile = String(data.tipo_file || '').toLowerCase();
      Support.ensureDefaultMediaPreviewValue();

      if (tipoFile === 'audio') {
        Support.editorSetValue(D.domandaEditorMediaAudio, filePath);
      } else {
        Support.editorSetValue(D.domandaEditorMediaImage, filePath);
      }

      Support.syncDomandaMediaPreview();

      if (titleInput) titleInput.value = '';
      if (fileInput) fileInput.value = '';

      await Admin.actions.caricaCatalogoMedia();
    },

    toggleDomandaEditor() {
      if (!D.domandaEditorWrapper) return;

      const isHidden = D.domandaEditorWrapper.style.display === 'none' || D.domandaEditorWrapper.style.display === '';
      if (!isHidden) {
        Support.closeDomandaEditor();
        return;
      }

      D.domandaEditorWrapper.style.display = 'block';
      if (isHidden) {
        Admin.actions.caricaDomandaEditor();
      }
    },

    async caricaDomandaEditor() {
      if (!D.domandaEditorWrapper) return;

      const res = await fetch(`${S.API_BASE}/domanda/${S.SESSIONE_ID}`);
      const data = await res.json();

      if (!data.success || !data.domanda) {
        Support.editorSetValue(D.domandaEditorId, '0');
        Support.editorSetValue(D.domandaEditorTipo, 'CLASSIC');
        Support.editorSetValue(D.domandaEditorFase, 'domanda');
        Support.editorSetValue(D.domandaEditorModalitaParty, '');
        Support.editorSetValue(D.domandaEditorMediaImage, '');
        Support.editorSetValue(D.domandaEditorMediaAudio, '');
        Support.editorSetValue(D.domandaEditorMediaPreview, '');
        Support.editorSetValue(D.domandaEditorMediaCaption, '');
        Support.editorSetValue(D.domandaEditorConfigJson, '');
        if (D.domandaEditorSelectedInfo) {
          D.domandaEditorSelectedInfo.textContent = 'Nessuna domanda selezionata';
        }
        Admin.actions.syncDomandaEditorVisibility();
        await Admin.actions.caricaCatalogoMedia();
        return;
      }

      Support.fillDomandaEditorFromData(data.domanda);
      Admin.actions.syncDomandaEditorVisibility();
      await Admin.actions.caricaCatalogoMedia();
    },

    async caricaDomandaEditorDaLista(domandaIdRaw, anchorRowEl = null) {
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

      const editorVisible = !!D.domandaEditorWrapper
        && D.domandaEditorWrapper.style.display !== 'none'
        && D.domandaEditorWrapper.style.display !== '';
      const selectedEditorId = Number(D.domandaEditorId?.value || 0);
      const sameQuestionToggle = editorVisible && selectedEditorId > 0 && selectedEditorId === domandaId;

      if (sameQuestionToggle) {
        Support.closeDomandaEditor();
        return;
      }

      if (D.domandaEditorWrapper) {
        D.domandaEditorWrapper.style.display = 'block';
        if (anchorRowEl && anchorRowEl.parentNode) {
          anchorRowEl.insertAdjacentElement('afterend', D.domandaEditorWrapper);
        }
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));
      formData.append('domanda_id', String(domandaId));

      const res = await fetch(`${S.API_BASE}/admin/domanda-dettaglio/${targetSessioneId}`, {
        method: 'POST',
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

      Support.fillDomandaEditorFromData(data.domanda);
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
      const targetSessioneId = Number(D.sessioneSelect?.value || S.SESSIONE_ID || 0);
      const tipo = Support.normalizeTipo(D.domandaEditorTipo?.value || 'CLASSIC');
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
      if (targetSessioneId <= 0) {
        addLog({ ok: false, title: 'domanda-update', message: 'Sessione non valida per il salvataggio', data: {} });
        return;
      }

      if (tipo === 'SARABANDA' && !(Number.isFinite(mediaPreview) && mediaPreview > 0)) {
        addLog({
          ok: false,
          title: 'domanda-update',
          message: 'Per SARABANDA imposta "Preview audio (secondi)" maggiore di 0',
          data: { tipo_domanda: tipo, media_audio_preview_sec: mediaPreview },
        });
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

      const res = await fetch(`${S.API_BASE}/admin/domanda-update/${targetSessioneId}`, {
        method: 'POST',
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
          await Admin.actions.caricaDomandeSessione(targetSessioneId);
        }
      }
    },

    async caricaDomandeSessione(sessioneId) {
      if (!D.domandeSessioneList) return;
      if (D.domandaEditorWrapper && D.domandaEditorWrapper.parentElement === D.domandeSessioneList) {
        const panelQuestion = document.getElementById('panel-question');
        if (panelQuestion) {
          panelQuestion.appendChild(D.domandaEditorWrapper);
        }
      }

      const formData = new FormData();
      formData.append('sessione_id', String(Number(sessioneId || 0)));

      const res = await fetch(`${S.API_BASE}/admin/domande-sessione/0`, {
        method: 'POST',
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

      D.domandeSessioneList.innerHTML = domande.map((domanda) => {
        const posizione = Number(domanda.posizione || 0);
        const id = Number(domanda.domanda_id || 0);
        const isCurrent = posizione > 0 && posizione === Number(S.currentSessionState?.domanda_corrente || 0);
        const codice = escapeHtml(String(domanda.codice_domanda || '-'));
        const testo = escapeHtml(String(domanda.testo || ''));
        const tipo = escapeHtml(String(domanda.tipo_domanda || 'CLASSIC'));
        const fase = escapeHtml(String(domanda.fase_domanda || 'domanda'));
        const imagePath = Support.normalizePath(domanda.media_image_path || '');
        const imageUrl = imagePath ? Support.resolveMediaUrl(imagePath) : '';
        const hasMedia = imagePath !== '' || String(domanda.media_audio_path || '').trim() !== '';
        const thumbHtml = imageUrl
          ? `<div class="qa-item-thumb-wrap"><img class="qa-item-thumb" src="${escapeHtml(imageUrl)}" alt="Anteprima domanda ${id}" loading="lazy" onerror="this.parentNode.classList.add('qa-item-thumb-fallback'); this.remove();"></div>`
          : '';

        return `<div
          data-edit-domanda-id="${id}"
          data-domanda-posizione="${posizione}"
          role="button"
          tabindex="0"
          class="qa-item${isCurrent ? ' qa-item-current' : ''}"
        >
          ${thumbHtml}
          <div class="qa-item-content">
            <div class="qa-item-main">#${posizione} · [${id}] ${testo}</div>
            <div class="qa-item-meta">
              codice=${codice} · tipo=${tipo} · fase=${fase} · media=${hasMedia ? 'si' : 'no'}
            </div>
          </div>
        </div>`;
      }).join('');

      D.domandeSessioneList.querySelectorAll('[data-edit-domanda-id]').forEach((btn) => {
        const openEditor = () => {
          const domandaId = Number(btn.getAttribute('data-edit-domanda-id') || 0);
          Admin.actions.caricaDomandaEditorDaLista(domandaId, btn);
        };
        btn.onclick = openEditor;
        btn.onkeydown = (ev) => {
          if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            openEditor();
          }
        };
      });

      Support.syncCurrentQuestionHighlight();
    },

    async cercaImmaginiSessioneCorrente() {
      const targetSessioneId = Number(D.sessioneSelect?.value || S.SESSIONE_ID || 0);
      const isVisible = !!D.sessionImageSearchReport && D.sessionImageSearchReport.style.display !== 'none';

      if (isVisible) {
        Support.setSessionImageSearchVisibility(false);
        return;
      }

      if (Number(S.sessionImageSearchSessionId || 0) === targetSessioneId && D.sessionImageSearchList?.innerHTML) {
        Support.setSessionImageSearchVisibility(true);
        return;
      }

      if (S.sessionImageSearchInFlight) {
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetSessioneId));

      try {
        S.sessionImageSearchInFlight = true;
        const res = await fetch(`${S.API_BASE}/admin/sessione-image-search/0`, {
          method: 'POST',
          body: formData,
        });

        const data = await res.json();
        if (!data.success || !data.report) {
          addLog({
            ok: false,
            title: 'sessione-image-search',
            message: data.error || 'Errore analisi immagini sessione',
            data,
          });
          return;
        }

        Support.renderSessionImageSearchReport(data.report);
        S.sessionImageSearchSessionId = Number(data.sessione_id || targetSessioneId || 0);
        addLog({
          ok: true,
          title: 'sessione-image-search',
          message: `Analisi immagini pronta per sessione ${Number(data.sessione_id || targetSessioneId)}`,
          data: data.report.summary || {},
        });
      } catch (e) {
        addLog({
          ok: false,
          title: 'sessione-image-search',
          message: 'Errore rete durante l\'analisi immagini',
          data: { error: String(e?.message || e) },
        });
      } finally {
        S.sessionImageSearchInFlight = false;
      }
    },

    resetSessionImageSearchReport() {
      S.sessionImageSearchSessionId = 0;
      if (D.sessionImageSearchSummary) {
        D.sessionImageSearchSummary.textContent = 'Nessuna analisi eseguita';
      }
      if (D.sessionImageSearchList) {
        D.sessionImageSearchList.innerHTML = '';
      }
      Support.setSessionImageSearchVisibility(false);
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
  });
})();
