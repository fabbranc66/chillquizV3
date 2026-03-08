// admin/07_actions.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;
  const { escapeHtml } = Admin.utils;
  const { addLog } = Admin.log;

  const TYPES_WITH_MEDIA = new Set(['MEDIA', 'SARABANDA', 'AUDIO_PARTY', 'IMAGE_PARTY']);
  const AUDIO_PREVIEW_STORAGE_PREFIX = 'chillquiz_audio_preview_';

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

  function sanitizeMemeText(raw) {
    return String(raw || '').replace(/\s+/g, ' ').trim();
  }

  function refreshMemeToggleUi(enabled, textValue = '') {
    S.memeEnabled = !!enabled;
    if (textValue !== '') {
      S.memeText = sanitizeMemeText(textValue);
    }

    const current = S.currentSessionState || {};
    const eligible = current.meme_eligible !== undefined ? !!current.meme_eligible : true;
    const locked = current.meme_locked !== undefined ? !!current.meme_locked : false;

    if (D.btnMemeToggle) {
      D.btnMemeToggle.textContent = S.memeEnabled ? 'MEME ON' : 'MEME OFF';
      D.btnMemeToggle.disabled = !eligible || locked;
      D.btnMemeToggle.classList.toggle('enabled', S.memeEnabled);
      D.btnMemeToggle.classList.toggle('disabled', !S.memeEnabled || !eligible);
      D.btnMemeToggle.classList.toggle('is-locked', locked);
    }

    if (D.memeTextInput && document.activeElement !== D.memeTextInput) {
      D.memeTextInput.value = S.memeDraftText || S.memeText || '';
    }
  }

  function getMemeDraftStorageKey() {
    return `chillquiz_meme_draft_${Number(S.SESSIONE_ID || 0)}`;
  }

  function loadMemeDraft() {
    try {
      return sanitizeMemeText(window.localStorage.getItem(getMemeDraftStorageKey()) || '');
    } catch (e) {
      return '';
    }
  }

  function saveMemeDraft(value) {
    const normalized = sanitizeMemeText(value);
    S.memeDraftText = normalized;
    try {
      if (normalized) window.localStorage.setItem(getMemeDraftStorageKey(), normalized);
      else window.localStorage.removeItem(getMemeDraftStorageKey());
    } catch (e) {
      console.warn(e);
    }
  }

  function resolveMediaUrl(path) {
    const normalized = normalizePath(path);
    if (!normalized) return '';
    if (/^https?:\/\//i.test(normalized) || normalized.startsWith('data:')) return normalized;
    const publicBasePath = String(S.PUBLIC_BASE_URL || '/');
    const normalizedBase = publicBasePath.endsWith('/') ? publicBasePath : `${publicBasePath}/`;
    return `${window.location.origin}${normalizedBase}${normalized.replace(/^\/+/, '')}`;
  }

  function buildSearchUrl(baseUrl, query) {
    const q = String(query || '').trim();
    if (!q) return '';
    return `${baseUrl}${encodeURIComponent(q)}`;
  }

  function buildPexelsSearchUrl(query) {
    const q = String(query || '').trim();
    if (!q) return '';
    return `https://www.pexels.com/search/${encodeURIComponent(q)}/`;
  }

  async function copyTextToClipboard(value) {
    const text = String(value || '');
    if (!text) return false;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
        return true;
      }
    } catch (e) {
      console.warn(e);
    }

    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', 'readonly');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    const ok = document.execCommand('copy');
    document.body.removeChild(area);
    return !!ok;
  }

  function buildRiskLabel(risk) {
    const value = String(risk || '').trim().toLowerCase();
    if (value === 'high') return 'rischio spoiler alto';
    if (value === 'medium') return 'da rivedere';
    return 'ok';
  }

  function buildStatusLabel(status) {
    const value = String(status || '').trim().toLowerCase();
    if (value === 'missing') return 'immagine mancante';
    if (value === 'generic') return 'placeholder generico';
    if (value === 'spoiler') return 'nome file spoiler';
    if (value === 'vector') return 'immagine vettoriale';
    return 'immagine presente';
  }

  function syncSessionImageSearchButton() {
    if (!D.btnSearchSessionImages) return;
    const visible = !!D.sessionImageSearchReport && D.sessionImageSearchReport.style.display !== 'none';
    D.btnSearchSessionImages.textContent = visible ? 'Chiudi ricerca immagini' : 'Ricerca immagini sessione';
    D.btnSearchSessionImages.classList.toggle('enabled', visible);
    D.btnSearchSessionImages.classList.toggle('disabled', !visible);
  }

  function setSessionImageSearchVisibility(visible) {
    if (!D.sessionImageSearchReport) return;
    D.sessionImageSearchReport.style.display = visible ? 'block' : 'none';
    syncSessionImageSearchButton();
  }

  function renderSessionImageSearchReport(report) {
    if (!D.sessionImageSearchReport || !D.sessionImageSearchSummary || !D.sessionImageSearchList) return;

    const items = Array.isArray(report?.items) ? report.items : [];
    const summary = report?.summary || {};
    const sessionName = escapeHtml(String(report?.sessione_nome || `Sessione ${Number(report?.sessione_id || 0)}`));

    setSessionImageSearchVisibility(true);
    D.sessionImageSearchSummary.innerHTML = `
      <strong>${sessionName}</strong> · totale=${Number(summary.total || 0)} · da rivedere=${Number(summary.needs_attention || 0)} ·
      placeholder/mancanti=${Number(summary.generic_or_missing || 0)} · spoiler alto=${Number(summary.spoiler_risk_high || 0)}
    `;

    if (items.length === 0) {
      D.sessionImageSearchList.innerHTML = '<div class="qa-search-empty">Nessuna domanda trovata nella sessione.</div>';
      return;
    }

    D.sessionImageSearchList.innerHTML = items.map((item) => {
      const imagePath = normalizePath(item.media_image_path || '');
      const imageUrl = imagePath ? resolveMediaUrl(imagePath) : '';
      const targetFilename = String(item.target_filename || '').trim();
      const targetFolder = String(item.target_folder || '').trim();
      const targetAbsolute = String(item.target_absolute_path || '').trim();
      const googleUrl = buildSearchUrl('https://www.google.com/search?tbm=isch&q=', item.search_query || '');
      const pexelsUrl = buildPexelsSearchUrl(item.search_query || '');
      const wikimediaUrl = buildSearchUrl('https://commons.wikimedia.org/w/index.php?search=', item.search_query_backup || item.search_query || '');
      const thumbHtml = imageUrl
        ? `<img class="qa-search-thumb" src="${escapeHtml(imageUrl)}" alt="Anteprima domanda ${Number(item.domanda_id || 0)}" loading="lazy">`
        : `<div class="qa-search-thumb qa-search-thumb-empty">NO IMG</div>`;

      return `
        <div class="qa-search-item">
          <div class="qa-search-media">${thumbHtml}</div>
          <div class="qa-search-content">
            <div class="qa-search-head">
              <strong>#${Number(item.posizione || 0)} · [${Number(item.domanda_id || 0)}]</strong>
              <span class="qa-search-pill">${escapeHtml(buildStatusLabel(item.status))}</span>
              <span class="qa-search-pill qa-search-pill-risk">${escapeHtml(buildRiskLabel(item.spoiler_risk))}</span>
            </div>
            <div class="qa-search-question">${escapeHtml(String(item.testo || ''))}</div>
            <div class="qa-search-meta">argomento=${escapeHtml(String(item.argomento || '-'))} · risposta=${escapeHtml(String(item.risposta_corretta || '-'))}</div>
            <div class="qa-search-meta">attuale=${escapeHtml(imagePath || '(vuoto)')} · motivo=${escapeHtml(String(item.analysis_reason || ''))}</div>
            <div class="qa-search-query">query foto: <code>${escapeHtml(String(item.search_query || ''))}</code></div>
            <div class="qa-search-query">backup: <code>${escapeHtml(String(item.search_query_backup || ''))}</code></div>
            <div class="qa-search-targets">
              <div class="qa-search-target-line">salva in: <code>${escapeHtml(targetFolder)}</code><button type="button" class="qa-mini-btn" data-copy-text="${escapeHtml(targetFolder)}" data-copy-label="cartella">Copia cartella</button></div>
              <div class="qa-search-target-line">nome file: <code>${escapeHtml(targetFilename)}</code><button type="button" class="qa-mini-btn" data-copy-text="${escapeHtml(targetFilename)}" data-copy-label="nome file">Copia nome</button></div>
              <div class="qa-search-target-line">path completo: <code>${escapeHtml(targetAbsolute)}</code><button type="button" class="qa-mini-btn" data-copy-text="${escapeHtml(targetAbsolute)}" data-copy-label="path completo">Copia path</button></div>
            </div>
            <div class="qa-search-links">
              <button type="button" class="qa-mini-btn" data-open-url="${escapeHtml(googleUrl)}" data-open-label="Google Immagini">Google Immagini</button>
              <button type="button" class="qa-mini-btn" data-open-url="${escapeHtml(pexelsUrl)}" data-open-label="Pexels">Pexels</button>
              <button type="button" class="qa-mini-btn" data-open-url="${escapeHtml(wikimediaUrl)}" data-open-label="Wikimedia">Wikimedia</button>
            </div>
          </div>
        </div>
      `;
    }).join('');

    D.sessionImageSearchList.querySelectorAll('[data-copy-text]').forEach((button) => {
      button.addEventListener('click', async () => {
        const text = String(button.getAttribute('data-copy-text') || '');
        const label = String(button.getAttribute('data-copy-label') || 'testo');
        const ok = await copyTextToClipboard(text);
        addLog({ ok, title: 'clipboard', message: ok ? `${label} copiato` : `Impossibile copiare ${label}`, data: ok ? { value: text } : { attempted: text } });
      });
    });

    D.sessionImageSearchList.querySelectorAll('[data-open-url]').forEach((button) => {
      button.addEventListener('click', () => {
        const url = String(button.getAttribute('data-open-url') || '').trim();
        const label = String(button.getAttribute('data-open-label') || 'ricerca');
        if (!url) {
          addLog({ ok: false, title: 'ricerca-immagini', message: `URL non valido per ${label}`, data: {} });
          return;
        }
        window.open(url, '_blank', 'noopener,noreferrer');
        addLog({ ok: true, title: 'ricerca-immagini', message: `${label} aperto con query dedicata`, data: { url } });
      });
    });
  }

  function ensureDefaultMediaPreviewValue() {
    if (!D.domandaEditorMediaPreview) return;
    const raw = String(D.domandaEditorMediaPreview.value || '').trim();
    if (raw === '') D.domandaEditorMediaPreview.value = '5';
  }

  function syncDomandaMediaPreview() {
    const imagePath = normalizePath(D.domandaEditorMediaImage?.value || '');
    const audioPath = normalizePath(D.domandaEditorMediaAudio?.value || '');

    if (D.domandaEditorImagePreview && D.domandaEditorImagePreviewEmpty) {
      if (imagePath) {
        D.domandaEditorImagePreview.src = resolveMediaUrl(imagePath);
        D.domandaEditorImagePreview.style.display = 'block';
        D.domandaEditorImagePreviewEmpty.style.display = 'none';
      } else {
        D.domandaEditorImagePreview.removeAttribute('src');
        D.domandaEditorImagePreview.style.display = 'none';
        D.domandaEditorImagePreviewEmpty.style.display = 'block';
      }
    }

    if (D.domandaEditorAudioPreview && D.domandaEditorAudioPreviewEmpty) {
      if (audioPath) {
        D.domandaEditorAudioPreview.src = resolveMediaUrl(audioPath);
        D.domandaEditorAudioPreview.style.display = 'block';
        D.domandaEditorAudioPreviewEmpty.style.display = 'none';
      } else {
        try { D.domandaEditorAudioPreview.pause(); } catch (e) { console.warn(e); }
        D.domandaEditorAudioPreview.removeAttribute('src');
        D.domandaEditorAudioPreview.load();
        D.domandaEditorAudioPreview.style.display = 'none';
        D.domandaEditorAudioPreviewEmpty.style.display = 'block';
      }
    }
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
    const memeMode = !!domanda.meme_mode;
    const hasImage = String(domanda.media_image_path || '').trim() !== '';
    const hasAudio = String(domanda.media_audio_path || '').trim() !== '';
    const hasCaption = String(domanda.media_caption || '').trim() !== '';
    const testo = escapeHtml(String(domanda.testo || ''));

    D.domandaCorrenteMetaBody.innerHTML = `
      <div style="padding:4px 0;"><strong>ID:</strong> ${Number(domanda.id || 0)}</div>
      <div style="padding:4px 0;"><strong>Testo:</strong> ${testo}</div>
      <div style="padding:4px 0;"><strong>Tipo:</strong> ${tipo}</div>
      <div style="padding:4px 0;"><strong>MEME runtime:</strong> ${memeMode ? 'attivo' : 'no'}</div>
      <div style="padding:4px 0;"><strong>Modalita party:</strong> ${modalita}</div>
      <div style="padding:4px 0;"><strong>Fase domanda:</strong> ${fase}</div>
      <div style="padding:4px 0;"><strong>Media:</strong> immagine=${yesNo(hasImage)} · audio=${yesNo(hasAudio)} · caption=${yesNo(hasCaption)}</div>
    `;
  }

  function syncAudioPreviewButton() {
    if (!D.btnAudioPreview) return;
    const domanda = S.domandaCorrente || null;
    const hasAudio = String(domanda?.media_audio_path || '').trim() !== '';
    const domandaId = Number(domanda?.id || 0);
    const alreadyStarted = domandaId > 0 && Number(S.audioPreviewDomandaId || 0) === domandaId;
    const enabled = hasAudio && !alreadyStarted;

    D.btnAudioPreview.style.display = 'inline-block';
    D.btnAudioPreview.disabled = !enabled;
    D.btnAudioPreview.classList.toggle('disabled', !enabled);
    D.btnAudioPreview.classList.toggle('enabled', enabled);

    if (!hasAudio) {
      window.clearTimeout(S.audioPreviewResetTimer);
      S.audioPreviewResetTimer = null;
      S.audioPreviewDomandaId = 0;
    }
  }

  function scheduleAudioPreviewButtonReset(domandaId, previewSec) {
    window.clearTimeout(S.audioPreviewResetTimer);
    S.audioPreviewResetTimer = null;
    const delayMs = Math.max(1, Number(previewSec || 0)) * 1000;
    S.audioPreviewResetTimer = window.setTimeout(() => {
      if (Number(S.audioPreviewDomandaId || 0) === Number(domandaId || 0)) {
        S.audioPreviewDomandaId = 0;
        syncAudioPreviewButton();
      }
      S.audioPreviewResetTimer = null;
    }, delayMs);
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
      return String(item.tipo_file || '').toLowerCase() === String(typeFilter).toLowerCase();
    });

    const options = filtered.map((item) => {
      const filePath = normalizePath(item.file_path || '');
      const selected = filePath !== '' && filePath === normalizedCurrent ? ' selected' : '';
      return `<option value="${escapeHtml(filePath)}"${selected}>${escapeHtml(buildMediaOptionLabel(item))}</option>`;
    }).join('');

    selectEl.innerHTML = baseOption + options;
  }

  function buildSessionLabel(sessione) {
    const id = Number(sessione?.id || 0);
    const nome = String(sessione?.nome_sessione || sessione?.nome || sessione?.titolo || '').trim();
    return `${id} · ${nome}`;
  }

  function extractNomeForNewSession(rawValue) {
    const raw = String(rawValue || '').trim();
    const match = raw.match(/^\d+\s*·\s*(.+)$/);
    if (match && match[1]) return String(match[1]).trim();
    return raw;
  }

  function resolveSessioneIdFromNomeInput() {
    const raw = String(D.inputSessioneNome?.value || '').trim();
    if (raw === '') return 0;

    const cache = Admin.actionsSupport.cache;
    if (cache.sessionLabelToId.has(raw)) return Number(cache.sessionLabelToId.get(raw) || 0);
    const lower = raw.toLowerCase();
    if (cache.sessionNameToId.has(lower)) return Number(cache.sessionNameToId.get(lower) || 0);

    const matchId = raw.match(/^(\d+)\s*·/);
    if (matchId && Number(matchId[1]) > 0) return Number(matchId[1]);
    if (/^\d+$/.test(raw)) return Number(raw);
    return 0;
  }

  function applySessioneSelection(sessioneId) {
    const id = Number(sessioneId || 0);
    if (id <= 0) return;
    const row = Admin.actionsSupport.cache.sessioniCache.get(id) || null;
    if (!row) return;
    if (D.sessioneSelect) D.sessioneSelect.value = String(id);
    popolaFormSessione(row);
  }

  function popolaFormSessione(sessione) {
    if (!sessione) return;
    const id = Number(sessione.id || 0);
    const numeroDomande = Number(sessione.numero_domande || 0);
    const poolTipo = String(sessione.pool_tipo || 'tutti').trim();
    const argomentoId = sessione.argomento_id ?? '';

    if (D.inputSessioneNome) {
      D.inputSessioneNome.value = buildSessionLabel(sessione);
      D.inputSessioneNome.dataset.sessioneId = String(id);
    }
    if (D.inputSessioneNumeroDomande) {
      D.inputSessioneNumeroDomande.value = (Number.isFinite(numeroDomande) && numeroDomande > 0) ? String(Math.floor(numeroDomande)) : '10';
    }
    if (D.inputSessionePoolTipo) {
      if (poolTipo === 'sarabanda') D.inputSessionePoolTipo.value = 'sarabanda';
      else D.inputSessionePoolTipo.value = (poolTipo === 'mono' || poolTipo === 'fisso') ? 'fisso' : 'misto';
    }
    if (D.inputSessioneArgomentoId) {
      D.inputSessioneArgomentoId.value = (argomentoId === null || argomentoId === undefined) ? '' : String(argomentoId);
    }
    Admin.actions.syncArgomentoFieldState();
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
    ensureDefaultMediaPreviewValue();
    editorSetValue(D.domandaEditorMediaCaption, domanda.media_caption || '');
    syncDomandaMediaPreview();

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
      const codice = escapeHtml(String(domanda.codice_domanda || '-'));
      const testo = escapeHtml(String(domanda.testo || ''));
      const tipoLabel = escapeHtml(String(tipo || 'CLASSIC'));
      D.domandaEditorSelectedInfo.innerHTML = `<strong>Domanda selezionata:</strong> [${id}] ${testo}<br><span style="opacity:.85;">Codice: ${codice} · Tipo: ${tipoLabel}</span>`;
    }
  }

  function closeDomandaEditor() {
    if (!D.domandaEditorWrapper) return;
    D.domandaEditorWrapper.style.display = 'none';
    if (D.domandaEditorId) D.domandaEditorId.value = '0';
  }

  function syncCurrentQuestionHighlight() {
    if (!D.domandeSessioneList) return;
    const currentPosizione = Number(S.currentSessionState?.domanda_corrente || 0);
    D.domandeSessioneList.querySelectorAll('[data-domanda-posizione]').forEach((node) => {
      const posizione = Number(node.getAttribute('data-domanda-posizione') || 0);
      node.classList.toggle('qa-item-current', currentPosizione > 0 && posizione === currentPosizione);
    });
  }

  Admin.actions = Admin.actions || {};
  Admin.actionsSupport = {
    TYPES_WITH_MEDIA,
    AUDIO_PREVIEW_STORAGE_PREFIX,
    cache: {
      mediaCatalog: [],
      sessioniCache: new Map(),
      sessionLabelToId: new Map(),
      sessionNameToId: new Map(),
      argomentiCache: [],
    },
    yesNo,
    normalizeTipo,
    normalizePath,
    sanitizeMemeText,
    refreshMemeToggleUi,
    loadMemeDraft,
    saveMemeDraft,
    resolveMediaUrl,
    buildSearchUrl,
    buildPexelsSearchUrl,
    copyTextToClipboard,
    buildRiskLabel,
    buildStatusLabel,
    syncSessionImageSearchButton,
    setSessionImageSearchVisibility,
    renderSessionImageSearchReport,
    ensureDefaultMediaPreviewValue,
    syncDomandaMediaPreview,
    renderDomandaCorrenteMeta,
    syncAudioPreviewButton,
    scheduleAudioPreviewButtonReset,
    editorSetValue,
    buildMediaOptionLabel,
    fillMediaSelect,
    buildSessionLabel,
    extractNomeForNewSession,
    resolveSessioneIdFromNomeInput,
    applySessioneSelection,
    popolaFormSessione,
    fillDomandaEditorFromData,
    closeDomandaEditor,
    syncCurrentQuestionHighlight,
  };

  Admin.actions.syncDomandaMediaPreview = syncDomandaMediaPreview;
})();
