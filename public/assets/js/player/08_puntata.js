// 08_puntata.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const Alert = Player.uiAlert;
  const Copy = Player.copy;
  const STEP = 250;

  function capitaleAttuale() {
    const raw = String((D.capitaleValue && D.capitaleValue.innerText) || '').replace(/[^\d.-]/g, '');
    const value = Number(raw);
    return Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
  }

  function puntataCorrente() {
    const value = Number((D.inputPuntata && D.inputPuntata.value) || 0);
    return Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
  }

  function setPuntata(value) {
    if (!D.inputPuntata) return;

    const capitale = capitaleAttuale();
    const normalized = Math.max(0, Math.floor(Number(value) || 0));
    const clamped = capitale > 0 ? Math.min(normalized, capitale) : normalized;
    D.inputPuntata.value = clamped > 0 ? String(clamped) : '';
  }

  async function handlePuntata() {
    if (S.puntataInviata) return;

    const importoRaw = D.inputPuntata && D.inputPuntata.value;
    const importo = Number(importoRaw);

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
      formData.append('puntata', String(parseInt(String(importo), 10)));

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

  Player.puntata = {
    handlePuntata,
    increasePuntata,
    decreasePuntata,
    setAllIn,
    prepareScreen,
  };
})();
