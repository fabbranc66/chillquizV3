// 08_puntata.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const Alert = Player.uiAlert;
  const Copy = Player.copy;
  const Utils = Player.utils || {};
  const STEP = 250;

  function parseIntegerAmount(value) {
    const digitsOnly = String(value ?? '').replace(/[^\d]/g, '');
    if (digitsOnly === '') return 0;
    const parsed = Number.parseInt(digitsOnly, 10);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
  }

  function capitaleAttuale() {
    if (typeof Utils.getCapitaleRaw === 'function') {
      return Utils.getCapitaleRaw();
    }
    return parseIntegerAmount((D.capitaleValue && D.capitaleValue.innerText) || '');
  }

  function puntataCorrente() {
    return parseIntegerAmount((D.inputPuntata && D.inputPuntata.value) || '');
  }

  function setPuntata(value) {
    if (!D.inputPuntata) return;

    const capitale = capitaleAttuale();
    const normalized = parseIntegerAmount(value);
    const clamped = capitale > 0 ? Math.min(normalized, capitale) : normalized;
    const formatter = typeof Utils.formatThousands === 'function'
      ? Utils.formatThousands
      : (n) => String(n);
    D.inputPuntata.value = clamped > 0 ? formatter(clamped) : '';
  }

  async function handlePuntata() {
    if (S.puntataInviata) return;

    const importo = parseIntegerAmount(D.inputPuntata && D.inputPuntata.value);

    if (!importo || importo <= 0) {
      Alert.show({
        title: Copy.betInvalidTitle,
        message: Copy.betInvalidMessage,
        tone: 'warn',
      });
      return;
    }

    S.puntataInviata = true;

    try {
      const formData = new FormData();
      formData.append('partecipazione_id', String(S.partecipazioneId || 0));
      formData.append('puntata', String(importo));

      const response = await fetch(`${S.API_BASE}/puntata/${S.sessioneId || 0}`, {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (!data.success) {
        Alert.show({
          title: Copy.betRejectedTitle,
          message: data.error || Copy.betRejectedMessage,
          tone: 'error',
        });
        S.puntataInviata = false;
        return;
      }

      Alert.hide();
    } catch (err) {
      console.error(err);
      Alert.show({
        title: Copy.networkErrorTitle,
        message: Copy.betNetworkErrorMessage,
        tone: 'error',
      });
      S.puntataInviata = false;
    }
  }

  function increasePuntata() {
    const current = puntataCorrente();
    setPuntata(current + STEP);
  }

  function decreasePuntata() {
    const current = puntataCorrente();
    setPuntata(Math.max(0, current - STEP));
  }

  function setAllIn() {
    setPuntata(capitaleAttuale());
  }

  function prepareScreen() {
    setPuntata('');
  }

  if (D.inputPuntata) {
    D.inputPuntata.addEventListener('input', () => {
      const current = parseIntegerAmount(D.inputPuntata.value);
      setPuntata(current);
    });
  }

  Player.puntata = {
    handlePuntata,
    increasePuntata,
    decreasePuntata,
    setAllIn,
    prepareScreen,
  };
})();
