/* public/assets/js/common/clock_sync.js */
(function () {
  window.ChillQuizClock = window.ChillQuizClock || {};

  function getOffsetMs(store) {
    return Number(store?.serverClockOffsetMs || 0);
  }

  function nowMs(store) {
    return Date.now() + getOffsetMs(store);
  }

  function nowSec(store) {
    return nowMs(store) / 1000;
  }

  function updateOffsetFromServerNow(store, serverNowSec) {
    const serverNow = Number(serverNowSec || 0);
    if (!store || serverNow <= 0) return 0;
    const offset = Math.round((serverNow * 1000) - Date.now());
    store.serverClockOffsetMs = offset;
    return offset;
  }

  function computeDelayMsFromStart(store, startSec) {
    const start = Number(startSec || 0);
    if (start <= 0) return 0;
    const startMs = Math.round(start * 1000);
    const delayMs = startMs - nowMs(store);
    return delayMs > 0 ? delayMs : 0;
  }

  window.ChillQuizClock = {
    nowMs,
    nowSec,
    updateOffsetFromServerNow,
    computeDelayMsFromStart,
  };
})();
