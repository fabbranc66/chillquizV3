/* public/assets/js/screen/dom.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};

  function el(id) {
    return document.getElementById(id);
  }

  function hide(id) {
    const node = el(id);
    if (!node) return;
    node.classList.add('hidden');
  }

  function show(id) {
    const node = el(id);
    if (!node) return;
    node.classList.remove('hidden');
  }

  window.ScreenApp.dom = { el, hide, show };
})();