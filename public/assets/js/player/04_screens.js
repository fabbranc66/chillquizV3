// 04_screens.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;

  Player.screens = {
    hideAllScreens() {
      (D.screens || []).forEach((screen) => {
        screen.classList.add('hidden');
        screen.style.display = 'none';
      });
      S.activeScreenId = null;
    },

    show(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.remove('hidden');
      el.style.removeProperty('display');
      S.activeScreenId = id;
    },

    showOnly(id) {
      const el = document.getElementById(id);
      if (!el) return false;
      if (S.activeScreenId === id && !el.classList.contains('hidden')) {
        return false;
      }

      (D.screens || []).forEach((screen) => {
        const isTarget = screen.id === id;
        screen.classList.toggle('hidden', !isTarget);
        if (isTarget) screen.style.removeProperty('display');
        else screen.style.display = 'none';
      });

      S.activeScreenId = id;
      return true;
    },
  };
})();
