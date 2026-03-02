// 08_puntata.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;

  async function handlePuntata() {
    if (S.puntataInviata) return;

    const importoRaw = D.inputPuntata?.value;
    const importo = Number(importoRaw);

    if (!importo || importo <= 0) {
      alert('Inserisci importo valido');
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
        alert(data.error || 'Errore puntata');
        S.puntataInviata = false;
      }
    } catch (err) {
      console.error(err);
      S.puntataInviata = false;
    }
  }

  Player.puntata = { handlePuntata };
})();