/* public/assets/js/screen/qr.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const { state } = window.ScreenApp;

  function setupSessionQr() {
    if (!state.sessioneId) return;

    const qrImg = document.getElementById('sessione-qr');
    if (!qrImg) return;

    const joinUrl = `${window.location.origin}${state.BASE_PUBLIC_URL}index.php?url=player`;
    qrImg.src =
      `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=` +
      encodeURIComponent(joinUrl);
  }

  window.ScreenApp.qr = { setupSessionQr };
})();