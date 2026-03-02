// 03_utils.js
(() => {
  const Player = window.Player;

  Player.utils = {
    safeString(v) {
      return String(v ?? '').trim();
    },

    safeNumber(v, fallback = 0) {
      const n = Number(v);
      return Number.isFinite(n) ? n : fallback;
    },

    isDomandaAttiva(stato) {
      return stato === 'domanda';
    },
  };
})();