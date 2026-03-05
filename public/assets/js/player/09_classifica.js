// 09_classifica.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const POINTS_SYMBOL = '&#9733;';

  function getMiaRigaClassifica(lista) {
    if (!Array.isArray(lista) || lista.length === 0) return null;

    if (S.partecipazioneId) {
      const byId = lista.find((r) => Number(r.partecipazione_id || 0) === Number(S.partecipazioneId));
      if (byId) return byId;
    }

    const nomeGiocatore = (D.displayName?.innerText || '').trim().toLowerCase();
    if (!nomeGiocatore) return null;

    return lista.find((r) => (r.nome || '').trim().toLowerCase() === nomeGiocatore) || null;
  }

  function aggiornaCapitaleDaClassifica(lista) {
    const miaRiga = getMiaRigaClassifica(lista);
    if (!miaRiga) return;

    const capitale = Number(miaRiga.capitale_attuale ?? 0);
    if (D.capitaleValue) D.capitaleValue.innerText = String(capitale);
  }

  function renderRisultatoPersonaleDaClassifica(lista) {
    const container = D.risultatoPersonale;
    if (!container) return;

    const miaRiga = getMiaRigaClassifica(lista);

    if (!miaRiga) {
      container.classList.remove('esito-corretta', 'esito-errata');
      container.innerHTML = '<div>Risultato personale non disponibile.</div>';
      return;
    }

    const esito = miaRiga.esito ?? '-';
    const ultimaPuntata = Number(miaRiga.ultima_puntata ?? 0);
    const vincita = (miaRiga.vincita_domanda === null || miaRiga.vincita_domanda === undefined)
      ? '-'
      : Number(miaRiga.vincita_domanda);
    const tempo = (miaRiga.tempo_risposta === null || miaRiga.tempo_risposta === undefined)
      ? '-'
      : Number(miaRiga.tempo_risposta);
    const capitale = Number(miaRiga.capitale_attuale ?? 0);

    container.classList.remove('esito-corretta', 'esito-errata');
    if (esito === 'corretta') container.classList.add('esito-corretta');
    else if (esito === 'errata') container.classList.add('esito-errata');

    container.innerHTML = `
      <div class="risultato-row"><strong>Esito:</strong><span>${esito}</span></div>
      <div class="risultato-row"><strong>Puntata:</strong><span class="valore-numerico">${POINTS_SYMBOL} ${ultimaPuntata}</span></div>
      <div class="risultato-row"><strong>Vincita domanda:</strong><span class="valore-numerico">${vincita}</span></div>
      <div class="risultato-row"><strong>Tempo risposta:</strong><span class="valore-numerico">${tempo}</span></div>
      <div class="risultato-row"><strong>Punti attuali:</strong><span class="valore-numerico">${POINTS_SYMBOL} ${capitale}</span></div>
    `;
  }

  function renderRisultatoPersonaleImmediato(risultato) {
    const container = D.risultatoPersonale;
    if (!container || !risultato || typeof risultato !== 'object') return;

    const esito = risultato.corretta ? 'corretta' : 'errata';
    const punti = Number(risultato.punti ?? 0);
    const tempo = Number(risultato.tempo_risposta ?? 0);
    const capitale = Number(risultato.capitale ?? 0);

    container.classList.remove('esito-corretta', 'esito-errata');
    container.classList.add(risultato.corretta ? 'esito-corretta' : 'esito-errata');

    container.innerHTML = `
      <div class="risultato-row"><strong>Esito:</strong><span>${esito}</span></div>
      <div class="risultato-row"><strong>Punti:</strong><span class="valore-numerico">${punti}</span></div>
      <div class="risultato-row"><strong>Tempo risposta:</strong><span class="valore-numerico">${tempo}</span></div>
      <div class="risultato-row"><strong>Punti attuali:</strong><span class="valore-numerico">${POINTS_SYMBOL} ${capitale}</span></div>
    `;
  }

  async function fetchClassifica() {
    try {
      const response = await fetch(`${S.API_BASE}/classifica/${S.sessioneId || 0}`);
      const data = await response.json();
      if (!data.success) return;

      const classificaOrdinata = Array.isArray(data.classifica)
        ? [...data.classifica].sort((a, b) => Number(b.capitale_attuale ?? 0) - Number(a.capitale_attuale ?? 0))
        : [];

      aggiornaCapitaleDaClassifica(classificaOrdinata);
      renderRisultatoPersonaleDaClassifica(classificaOrdinata);

      const container = D.classifica;
      if (!container) return;

      container.innerHTML = '';

      if (classificaOrdinata.length === 0) {
        container.innerHTML = '<div>Nessun giocatore</div>';
        return;
      }

      classificaOrdinata.forEach((riga, index) => {
        const div = document.createElement('div');
        div.className = 'classifica-item';

        const nome = riga.nome || '-';
        const capitale = Number(riga.capitale_attuale ?? 0);

        div.innerHTML = `
          <strong>${index + 1}.</strong>
          ${nome}
          <span>${POINTS_SYMBOL} ${capitale}</span>
        `;

        container.appendChild(div);
      });
    } catch (err) {
      console.error('Errore classifica:', err);
    }
  }

  Player.classifica = {
    fetchClassifica,
    renderRisultatoPersonaleImmediato,
    renderRisultatoPersonaleDaClassifica,
    aggiornaCapitaleDaClassifica,
    getMiaRigaClassifica,
  };
})();
