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

  function formatTempoRisposta(value) {
    if (value === null || value === undefined || value === '' || value === '-') return '-';
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '-';
    return numeric.toFixed(2).replace('.', ',');
  }

  function rowLine(label, value, right = false) {
    return `<div class="risultato-row${right ? ' row-right' : ''}"><span class="riga-testo">${label}: ${value}</span></div>`;
  }

  function forceDisplayString(value) {
    if (value === null || value === undefined || value === '' || value === '-') return '-';
    return String(value);
  }

  function formatCoeff(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '0.00';
    return numeric.toFixed(2).replace('.', ',');
  }

  function formatSignedPoints(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '0';
    return numeric >= 0 ? `+${numeric}` : `${numeric}`;
  }

  function formatCapitaleBreakdown(capitaleAttuale, vincitaTotale) {
    const capitale = Number(capitaleAttuale);
    const vincita = Number(vincitaTotale);

    if (!Number.isFinite(capitale) || !Number.isFinite(vincita)) {
      return `${POINTS_SYMBOL} ${Number.isFinite(capitale) ? capitale : 0}`;
    }

    const capitalePrecedente = capitale - vincita;
    return `${POINTS_SYMBOL} ${capitalePrecedente} ${formatSignedPoints(vincita)} = ${POINTS_SYMBOL} ${capitale}`;
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
    const puntata = Number(miaRiga.ultima_puntata ?? 0);
    const difficolta = Number(miaRiga.difficolta_domanda ?? 0);
    const fattoreVelocita = Number(miaRiga.fattore_velocita ?? 0);
    const vincitaDifficolta = Number(miaRiga.vincita_difficolta ?? 0);
    const vincitaVelocita = Number(miaRiga.vincita_velocita ?? 0);
    const bonusPrimo = Number(miaRiga.bonus_primo ?? 0);
    const bonusImpostore = Number(miaRiga.bonus_impostore ?? 0);
    const vincitaTotale = (miaRiga.vincita_domanda === null || miaRiga.vincita_domanda === undefined)
      ? '-'
      : Number(miaRiga.vincita_domanda);
    const tempo = forceDisplayString(miaRiga.tempo_risposta_display || formatTempoRisposta(miaRiga.tempo_risposta));
    const capitale = Number(miaRiga.capitale_attuale ?? 0);

    container.classList.remove('esito-corretta', 'esito-errata');
    if (esito === 'corretta') container.classList.add('esito-corretta');
    else if (esito === 'errata') container.classList.add('esito-errata');

    container.innerHTML = [
      rowLine('Esito', esito),
      rowLine('Tempo risposta', tempo),
      rowLine('Puntata', `${POINTS_SYMBOL} ${puntata}`),
      rowLine('Coeff. difficolta', `x${formatCoeff(difficolta)}`),
      rowLine('Vincita difficolta', `${vincitaDifficolta}`, true),
      rowLine('Coeff. velocita', `x${formatCoeff(fattoreVelocita)}`),
      rowLine('Vincita velocita', `${vincitaVelocita}`, true),
      rowLine('Bonus primo', `${bonusPrimo}`, true),
      rowLine('Bonus impostore', `${bonusImpostore}`, true),
      rowLine('Vincita totale', `${vincitaTotale}`, true),
      rowLine('Punti attuali', formatCapitaleBreakdown(capitale, vincitaTotale)),
    ].join('');
  }

  function renderRisultatoPersonaleImmediato(risultato) {
    const container = D.risultatoPersonale;
    if (!container || !risultato || typeof risultato !== 'object') return;

    const esito = risultato.corretta ? 'corretta' : 'errata';
    const puntata = Number(risultato.puntata ?? 0);
    const difficolta = Number(risultato.difficolta_domanda ?? 0);
    const fattoreVelocita = Number(risultato.fattore_velocita ?? 0);
    const vincitaDifficolta = Number(risultato.vincita_difficolta ?? 0);
    const vincitaVelocita = Number(risultato.vincita_velocita ?? 0);
    const bonusPrimo = Number(risultato.bonus_primo ?? 0);
    const bonusImpostore = Number(risultato.bonus_impostore ?? 0);
    const punti = Number(risultato.punti ?? 0);
    const tempo = forceDisplayString(risultato.tempo_risposta_display || formatTempoRisposta(risultato.tempo_risposta ?? 0));
    const capitale = Number(risultato.capitale ?? 0);

    container.classList.remove('esito-corretta', 'esito-errata');
    container.classList.add(risultato.corretta ? 'esito-corretta' : 'esito-errata');

    container.innerHTML = [
      rowLine('Esito', esito),
      rowLine('Tempo risposta', tempo),
      rowLine('Puntata', `${POINTS_SYMBOL} ${puntata}`),
      rowLine('Coeff. difficolta', `x${formatCoeff(difficolta)}`),
      rowLine('Vincita difficolta', `${vincitaDifficolta}`, true),
      rowLine('Coeff. velocita', `x${formatCoeff(fattoreVelocita)}`),
      rowLine('Vincita velocita', `${vincitaVelocita}`, true),
      rowLine('Bonus primo', `${bonusPrimo}`, true),
      rowLine('Bonus impostore', `${bonusImpostore}`, true),
      rowLine('Vincita totale', `${punti}`, true),
      rowLine('Punti attuali', formatCapitaleBreakdown(capitale, punti)),
    ].join('');
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

