// admin/05_render.js
(() => {
  const Admin = window.Admin;
  const { classificaLiveEl, joinRichiesteEl } = Admin.dom;
  const { escapeHtml } = Admin.utils;

  function formatTempoRisposta(value) {
    if (value === null || value === undefined || value === '' || value === '-') return '-';
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '-';
    return numeric.toFixed(2);
  }

  Admin.render = {
    renderClassificaLive(lista) {
      if (!classificaLiveEl) return;

      if (!Array.isArray(lista) || lista.length === 0) {
        classificaLiveEl.innerHTML = '<tr><td colspan="7">Nessun partecipante</td></tr>';
        return;
      }

      const primoVeloceCorretto = lista
        .filter((p) => p.esito === 'corretta' && p.tempo_risposta !== null && p.tempo_risposta !== undefined)
        .reduce((best, p) => {
          if (!best) return p;
          return Number(p.tempo_risposta) < Number(best.tempo_risposta) ? p : best;
        }, null);

      classificaLiveEl.innerHTML = lista.map((p, index) => {
        const capitale = Number(p.capitale_attuale ?? 0);
        const puntata = Number(p.ultima_puntata ?? 0);
        const esito = p.esito ?? '-';
        const tempo = formatTempoRisposta(p.tempo_risposta);
        const vincita = (p.vincita_domanda === null || p.vincita_domanda === undefined) ? '-' : Number(p.vincita_domanda);

        const isPrimoVincente = primoVeloceCorretto && Number(primoVeloceCorretto.partecipazione_id) === Number(p.partecipazione_id);
        const rowClass = isPrimoVincente ? 'live-row-primo' : '';

        const nomeSafe = escapeHtml(p.nome ?? '-');
        const nomeConIcona = isPrimoVincente
          ? `<span class="first-win-icon" title="Primo a rispondere correttamente">ðŸ¥‡âš¡</span>${nomeSafe}`
          : nomeSafe;

        return `
          <tr class="${rowClass}">
            <td>${index + 1}</td>
            <td>${nomeConIcona}<\/td>
            <td>${capitale}<\/td>
            <td>${puntata}<\/td>
            <td>${esito}<\/td>
            <td>${tempo}<\/td>
            <td>${vincita}<\/td>
          </tr>
        `;
      }).join('');
    },

    renderJoinRichieste(lista) {
      if (!joinRichiesteEl) return;

      if (!Array.isArray(lista) || lista.length === 0) {
        Admin.ui?.ensureJoinPanelClosed?.();
        joinRichiesteEl.innerHTML = '<div class="join-item">Nessuna richiesta pending</div>';
        return;
      }

      Admin.ui?.ensureJoinPanelOpen?.();

      joinRichiesteEl.innerHTML = lista.map((r) => `
        <div class="join-item">
          <div>
            <strong>${r.nome}</strong> Â· richiesta #${r.id}
          </div>
          <div class="join-actions">
            <button class="btn-join-ok" onclick="gestisciJoin(${r.id}, 'approva-join')">Approva</button>
            <button class="btn-join-no" onclick="gestisciJoin(${r.id}, 'rifiuta-join')">Rifiuta</button>
          </div>
        </div>
      `).join('');
    }
  };
})();
