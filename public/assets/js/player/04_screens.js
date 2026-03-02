// 04_screens.js
(() => {
  const Player = window.Player;
  const D = Player.dom;

  Player.screens = {
    hideAllScreens() {
      (D.screens || []).forEach((screen) => {
        screen.classList.add('hidden');
        screen.style.display = 'none';
      });
    },

    show(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.remove('hidden');
      el.style.display = 'block';
    },
  };
})();