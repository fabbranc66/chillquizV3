(() => {
  const BOOT = window.CROP169_BOOTSTRAP || {};
  const rawBasePublicUrl = String(
    BOOT.basePublicUrl
    || String(window.location.pathname || '').replace(/index\.php.*$/i, '')
    || '/'
  );
  const BASE_PUBLIC_URL = rawBasePublicUrl.endsWith('/') ? rawBasePublicUrl : `${rawBasePublicUrl}/`;
  const API_BASE = String(BOOT.apiBase || `${BASE_PUBLIC_URL}index.php?url=api`);
  const RATIO = 16 / 9;

  const dom = {
    sourcePath: document.getElementById('source-path'),
    saveMode: document.getElementById('save-mode'),
    copySuffixWrap: document.getElementById('copy-suffix-wrap'),
    copySuffix: document.getElementById('copy-suffix'),
    btnRefresh: document.getElementById('btn-refresh'),
    btnLoad: document.getElementById('btn-load'),
    btnResetRect: document.getElementById('btn-reset-rect'),
    btnSave: document.getElementById('btn-save'),
    log: document.getElementById('log'),
    stage: document.getElementById('stage'),
    stageImage: document.getElementById('stage-image'),
    cropRect: document.getElementById('crop-rect'),
    cropHandle: document.getElementById('crop-handle'),
    previewCanvas: document.getElementById('preview-canvas'),
  };

  const state = {
    sourcePath: '',
    imageNaturalWidth: 0,
    imageNaturalHeight: 0,
    dragging: false,
    resizing: false,
    dragOffsetX: 0,
    dragOffsetY: 0,
    rect: { x: 0, y: 0, w: 0, h: 0 },
  };

  const query = new URLSearchParams(window.location.search || '');
  let sourceFromQuery = String(query.get('source') || '').trim();

  function showLog(message, ok = true) {
    if (!dom.log) return;
    dom.log.textContent = message;
    dom.log.style.color = ok ? '#8bf7a8' : '#ff8f8f';
  }

  function assetUrl(path, cacheBust = false) {
    const clean = String(path || '').trim().replace(/^\/+/, '');
    const base = `${BASE_PUBLIC_URL}${clean}`;
    if (!cacheBust) return base;
    return `${base}${base.includes('?') ? '&' : '?'}v=${Date.now()}`;
  }

  function extFromPath(path) {
    const clean = String(path || '').trim().toLowerCase();
    const dot = clean.lastIndexOf('.');
    return dot >= 0 ? clean.substring(dot + 1) : '';
  }

  function mimeForSource(path) {
    const ext = extFromPath(path);
    if (ext === 'jpg' || ext === 'jpeg') return 'image/jpeg';
    if (ext === 'png') return 'image/png';
    if (ext === 'webp') return 'image/webp';
    return 'image/png';
  }

  function clampRectWithinImage() {
    const imgW = dom.stageImage.clientWidth;
    const imgH = dom.stageImage.clientHeight;
    if (imgW <= 0 || imgH <= 0) return;

    const minW = Math.max(80, Math.min(imgW, 160));
    const minH = minW / RATIO;

    state.rect.w = Math.max(minW, Math.min(state.rect.w, imgW));
    state.rect.h = state.rect.w / RATIO;
    if (state.rect.h > imgH) {
      state.rect.h = Math.max(minH, imgH);
      state.rect.w = state.rect.h * RATIO;
    }

    state.rect.x = Math.max(0, Math.min(state.rect.x, imgW - state.rect.w));
    state.rect.y = Math.max(0, Math.min(state.rect.y, imgH - state.rect.h));
  }

  function renderRect() {
    if (!dom.cropRect) return;
    clampRectWithinImage();
    dom.cropRect.style.left = `${state.rect.x}px`;
    dom.cropRect.style.top = `${state.rect.y}px`;
    dom.cropRect.style.width = `${state.rect.w}px`;
    dom.cropRect.style.height = `${state.rect.h}px`;
    renderPreview();
  }

  function initDefaultRect() {
    const imgW = dom.stageImage.clientWidth;
    const imgH = dom.stageImage.clientHeight;
    if (imgW <= 0 || imgH <= 0) return;

    let w = Math.round(imgW * 0.72);
    let h = w / RATIO;
    if (h > imgH * 0.9) {
      h = Math.round(imgH * 0.9);
      w = h * RATIO;
    }
    state.rect.w = Math.max(80, w);
    state.rect.h = Math.max(45, h);
    state.rect.x = Math.round((imgW - state.rect.w) / 2);
    state.rect.y = Math.round((imgH - state.rect.h) / 2);
    dom.cropRect.style.display = 'block';
    renderRect();
  }

  function naturalCropRect() {
    const displayW = dom.stageImage.clientWidth;
    const displayH = dom.stageImage.clientHeight;
    if (displayW <= 0 || displayH <= 0 || state.imageNaturalWidth <= 0 || state.imageNaturalHeight <= 0) {
      return null;
    }

    const scaleX = state.imageNaturalWidth / displayW;
    const scaleY = state.imageNaturalHeight / displayH;

    const x = Math.max(0, Math.round(state.rect.x * scaleX));
    const y = Math.max(0, Math.round(state.rect.y * scaleY));
    let w = Math.max(16, Math.round(state.rect.w * scaleX));
    let h = Math.max(9, Math.round(state.rect.h * scaleY));

    const ratioH = Math.max(1, Math.round(w / RATIO));
    h = ratioH;

    if (x + w > state.imageNaturalWidth) {
      w = state.imageNaturalWidth - x;
      h = Math.max(1, Math.round(w / RATIO));
    }
    if (y + h > state.imageNaturalHeight) {
      h = state.imageNaturalHeight - y;
      w = Math.max(1, Math.round(h * RATIO));
    }

    return { x, y, w, h };
  }

  function renderPreview() {
    const crop = naturalCropRect();
    if (!crop || !dom.previewCanvas || !dom.stageImage.complete) return;

    dom.previewCanvas.width = crop.w;
    dom.previewCanvas.height = crop.h;
    const ctx = dom.previewCanvas.getContext('2d');
    ctx.clearRect(0, 0, crop.w, crop.h);
    ctx.drawImage(
      dom.stageImage,
      crop.x, crop.y, crop.w, crop.h,
      0, 0, crop.w, crop.h
    );
  }

  function startDrag(ev) {
    ev.preventDefault();
    const rectBounds = dom.cropRect.getBoundingClientRect();
    state.dragging = true;
    state.dragOffsetX = ev.clientX - rectBounds.left;
    state.dragOffsetY = ev.clientY - rectBounds.top;
  }

  function startResize(ev) {
    ev.preventDefault();
    ev.stopPropagation();
    state.resizing = true;
  }

  function moveInteraction(ev) {
    if (!state.dragging && !state.resizing) return;
    const stageBounds = dom.stage.getBoundingClientRect();
    const imgW = dom.stageImage.clientWidth;
    const imgH = dom.stageImage.clientHeight;
    if (imgW <= 0 || imgH <= 0) return;

    const px = ev.clientX - stageBounds.left;
    const py = ev.clientY - stageBounds.top;

    if (state.dragging) {
      state.rect.x = px - state.dragOffsetX;
      state.rect.y = py - state.dragOffsetY;
      renderRect();
      return;
    }

    if (state.resizing) {
      let nextW = px - state.rect.x;
      const minW = 80;
      nextW = Math.max(minW, Math.min(nextW, imgW - state.rect.x));
      let nextH = nextW / RATIO;
      if (state.rect.y + nextH > imgH) {
        nextH = imgH - state.rect.y;
        nextW = nextH * RATIO;
      }
      state.rect.w = nextW;
      state.rect.h = nextH;
      renderRect();
    }
  }

  function endInteraction() {
    state.dragging = false;
    state.resizing = false;
  }

  async function loadSourceList() {
    if (!dom.sourcePath) return;
    dom.sourcePath.innerHTML = '<option value="">Caricamento...</option>';

    const res = await fetch(`${API_BASE}/admin/crop-image-list/0`, { method: 'POST' });
    const data = await res.json();
    if (!data.success) {
      dom.sourcePath.innerHTML = '<option value="">Errore caricamento immagini</option>';
      showLog(data.error || 'Errore caricamento immagini', false);
      return;
    }

    const items = Array.isArray(data.images) ? data.images : [];
    if (items.length === 0) {
      dom.sourcePath.innerHTML = '<option value="">Nessuna immagine disponibile</option>';
      showLog('Nessuna immagine disponibile in /upload/image o /upload/domanda/image', false);
      return;
    }

    const options = ['<option value="">Seleziona immagine...</option>'];
    items.forEach((item) => {
      const path = String(item.file_path || '').trim();
      if (!path) return;
      const label = String(item.label || path);
      options.push(`<option value="${path}">${label}</option>`);
    });
    dom.sourcePath.innerHTML = options.join('');

    if (sourceFromQuery && items.some((item) => String(item.file_path || '') === sourceFromQuery)) {
      dom.sourcePath.value = sourceFromQuery;
      loadSelectedImage();
      sourceFromQuery = '';
    }

    showLog(`Caricate ${items.length} immagini disponibili`);
  }

  function loadSelectedImage() {
    const source = String(dom.sourcePath?.value || '').trim();
    if (!source) {
      showLog('Seleziona un file immagine', false);
      return;
    }
    state.sourcePath = source;
    dom.cropRect.style.display = 'none';
    dom.stageImage.onload = () => {
      state.imageNaturalWidth = Number(dom.stageImage.naturalWidth || 0);
      state.imageNaturalHeight = Number(dom.stageImage.naturalHeight || 0);
      initDefaultRect();
      showLog(`Immagine caricata: ${source}`);
    };
    dom.stageImage.onerror = () => {
      showLog('Impossibile caricare l\'immagine selezionata', false);
    };
    dom.stageImage.src = assetUrl(source, true);
  }

  async function saveCrop() {
    if (!state.sourcePath || !dom.stageImage.complete) {
      showLog('Carica prima un\'immagine valida', false);
      return;
    }

    const crop = naturalCropRect();
    if (!crop) {
      showLog('Riquadro crop non valido', false);
      return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = crop.w;
    canvas.height = crop.h;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(dom.stageImage, crop.x, crop.y, crop.w, crop.h, 0, 0, crop.w, crop.h);

    const mode = String(dom.saveMode?.value || 'overwrite');
    const suffix = String(dom.copySuffix?.value || '-169').trim() || '-169';
    const mime = mimeForSource(state.sourcePath);

    canvas.toBlob(async (blob) => {
      if (!blob) {
        showLog('Errore generazione file crop', false);
        return;
      }

      const formData = new FormData();
      formData.append('source_path', state.sourcePath);
      formData.append('save_mode', mode);
      formData.append('copy_suffix', suffix);
      formData.append('cropped_image', blob, 'crop');

      const res = await fetch(`${API_BASE}/admin/crop-image-save/0`, {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();
      if (!data.success) {
        showLog(data.error || 'Errore salvataggio crop', false);
        return;
      }

      const outputPath = String(data.output_path || '');
      showLog(`Salvato con successo: ${outputPath}`);
      await loadSourceList();
      if (outputPath) {
        dom.sourcePath.value = outputPath;
        loadSelectedImage();
      }
    }, mime, (mime === 'image/jpeg' || mime === 'image/webp') ? 0.92 : undefined);
  }

  function syncSaveMode() {
    const isCopy = String(dom.saveMode?.value || '') === 'copy';
    dom.copySuffixWrap.style.display = isCopy ? 'grid' : 'none';
  }

  dom.cropRect.addEventListener('mousedown', startDrag);
  dom.cropHandle.addEventListener('mousedown', startResize);
  window.addEventListener('mousemove', moveInteraction);
  window.addEventListener('mouseup', endInteraction);
  dom.btnRefresh.addEventListener('click', loadSourceList);
  dom.btnLoad.addEventListener('click', loadSelectedImage);
  dom.btnResetRect.addEventListener('click', initDefaultRect);
  dom.btnSave.addEventListener('click', saveCrop);
  dom.saveMode.addEventListener('change', syncSaveMode);

  syncSaveMode();
  loadSourceList();
})();
