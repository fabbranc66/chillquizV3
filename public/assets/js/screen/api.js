/* public/assets/js/screen/api.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;

  function extractSessioneIdFromUrl() {
    const raw = new URLSearchParams(window.location.search).get('url') || '';
    if (!raw.startsWith('screen/')) return 0;
    const id = parseInt(raw.split('/')[1], 10);
    return Number.isNaN(id) || id <= 0 ? 0 : id;
  }

  async function fetchJson(url, options = {}) {
    const response = await fetch(url, { cache: 'no-store', ...options });
    const text = await response.text();

    if (/^\s*</.test(text)) {
      throw new Error(`Risposta HTML (non JSON) da: ${url}`);
    }

    return JSON.parse(text);
  }

  ScreenApp.api = {
    extractSessioneIdFromUrl,
    fetchJson,
    get sessioneId() {
      return Number(S.sessioneId || 0);
    },
    get publicBaseUrl() {
      return String(S.publicBaseUrl || '/');
    },
    get apiBase() {
      return String(S.apiBase || '');
    },
    get publicHost() {
      return String(S.publicHost || window.location.host || '');
    },
  };
})();
