// 07_domanda.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const { isDomandaAttiva } = Player.utils;

  function resetDomandaView() {
    if (D.domandaTesto) D.domandaTesto.innerText = '';
    if (D.opzioniDiv) D.opzioniDiv.innerHTML = '';
  }

  async function fetchDomanda() {
    if (!isDomandaAttiva(S.currentState)) return;

    const requestNonce = ++S.domandaFetchNonce;

    try {
      const response = await fetch(`${S.API_BASE}/domanda/${S.sessioneId || 0}`);
      const data = await response.json();

      if (!data.success) return;
      if (!isDomandaAttiva(S.currentState)) return;
      if (requestNonce !== S.domandaFetchNonce) return;

      renderDomanda(data.domanda);
    } catch (err) {
      console.error(err);
    }
  }

  function renderDomanda(domanda) {
    if (!isDomandaAttiva(S.currentState)) return;

    if (!domanda || !Array.isArray(domanda.opzioni)) {
      resetDomandaView();
      return;
    }

    if (!D.domandaTesto || !D.opzioniDiv) return;

    D.domandaTesto.innerText = domanda.testo || '';
    D.opzioniDiv.innerHTML = '';

    domanda.opzioni.forEach((opzione, index) => {
      const btn = document.createElement('button');
      btn.innerText = opzione.testo || '';
      btn.dataset.id = String(opzione.id || '');

      const paletteIndex = (index % 4) + 1;
      btn.classList.add(`opzione-kahoot-${paletteIndex}`);

      btn.onclick = () => inviaRisposta(domanda.id, opzione.id);
      D.opzioniDiv.appendChild(btn);
    });
  }

  async function inviaRisposta(domandaId, opzioneId) {
    if (S.rispostaInviata) return;
    S.rispostaInviata = true;

    const buttons = document.querySelectorAll('#opzioni button');
    buttons.forEach((btn) => {
      btn.disabled = true;
      if (String(btn.dataset.id) === String(opzioneId)) {
        btn.classList.add('selected');
      } else {
        btn.classList.add('dimmed');
      }
    });

    try {
      const formData = new FormData();
      formData.append('partecipazione_id', String(S.partecipazioneId || 0));
      formData.append('domanda_id', String(domandaId || 0));
      formData.append('opzione_id', String(opzioneId || 0));

      const response = await fetch(`${S.API_BASE}/risposta/${S.sessioneId || 0}`, {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (!data.success) {
        alert(data.error || 'Errore invio risposta');
        S.rispostaInviata = false;
        return;
      }

      Player.classifica.renderRisultatoPersonaleImmediato(data.risultato);
    } catch (err) {
      console.error(err);
      S.rispostaInviata = false;
    }
  }

  Player.domanda = { fetchDomanda, renderDomanda, resetDomandaView, inviaRisposta };
})();