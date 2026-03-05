// public/assets/js/screen/api.js
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;

  function getBoot() {
    return window.SCREEN_BOOTSTRAP || {};
  }

  function getSessioneId() {
    const id = Number(getBoot().sessioneId || 0);
    return Number.isFinite(id) ? id : 0;
  }

  function getBasePublicUrl() {
    const boot = getBoot();
    const fromBoot = String(boot.basePublicUrl || '').trim();
    if (fromBoot) return fromBoot; // es: "/chillquizV3/public/"

    // fallback: deriva da pathname
    // /chillquizV3/public/index.php  -> /chillquizV3/public/
    const path = window.location.pathname;
    return path.replace(/index\.php$/, '');
  }

  function getApiBase() {
    const boot = getBoot();
    const fromBoot = String(boot.apiBase || '').trim();
    if (fromBoot) return fromBoot; // es: "/chillquizV3/public/index.php?url=api"

    return getBasePublicUrl() + 'index.php?url=api';
  }

  async function fetchJson(url, options = {}) {
    const r = await fetch(url, { cache: 'no-store', ...options });
    const txt = await r.text();

    // guard HTML
    if (/^\s*</.test(txt)) {
      throw new Error('Risposta HTML (non JSON) da: ' + url);
    }

    return JSON.parse(txt);
  }

  ScreenApp.api = {
    get sessioneId() {
      return getSessioneId();
    },
    get basePublicUrl() {
      return getBasePublicUrl();
    },
    get apiBase() {
      return getApiBase();
    },
    fetchJson,
  };
})();