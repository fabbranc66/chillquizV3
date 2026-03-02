// admin/07_actions.js
(() => {
  const Admin = window.Admin;
  const S = Admin.state;
  const D = Admin.dom;
  const { escapeHtml, nomeSessioneFromRecord } = Admin.utils;
  const { addLog } = Admin.log;
  const { renderClassificaLive, renderJoinRichieste } = Admin.render;

  Admin.actions = {
    async aggiornaPartecipanti() {
      try {
        const res = await fetch(`${S.API_BASE}/classifica/${S.SESSIONE_ID}`);
        const data = await res.json();
        if (!data.success) return;

        const lista = data.classifica;
        const numeroAttuale = lista.length;

        if (D.partecipantiSpan) D.partecipantiSpan.textContent = String(numeroAttuale);
        renderClassificaLive(lista);

        // NUOVO PLAYER ENTRATO
        if (numeroAttuale > S.ultimoNumeroPartecipanti) {
          const nuovi = lista.slice(0, numeroAttuale - S.ultimoNumeroPartecipanti);
          nuovi.forEach(p => {
            addLog({
              ok: true,
              title: 'Nuovo giocatore',
              message: `${p.nome} si è unito alla sessione`,
              data: p
            });
          });
        }

        S.ultimoNumeroPartecipanti = numeroAttuale;
        Admin.actions.aggiornaJoinRichieste();
      } catch (e) {
        // silenzioso (come ora)
      }
    },

    async aggiornaJoinRichieste() {
      const res = await fetch(`${S.API_BASE}/admin/join-richieste/${S.SESSIONE_ID}`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN }
      });

      const data = await res.json();
      if (!data.success) return;

      renderJoinRichieste(data.richieste ?? []);
    },

    async gestisciJoin(requestId, action) {
      const formData = new FormData();
      formData.append('request_id', requestId);

      const res = await fetch(`${S.API_BASE}/admin/${action}/${S.SESSIONE_ID}`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
        body: formData
      });

      const data = await res.json();

      addLog({
        ok: !!data.success,
        title: action === 'approva-join' ? 'Join approvata' : 'Join rifiutata',
        message: data.success
          ? `Richiesta #${requestId} ${action === 'approva-join' ? 'approvata' : 'rifiutata'}`
          : (data.error || 'Operazione fallita'),
        data
      });

      if (data.success) {
        Admin.actions.aggiornaJoinRichieste();
        Admin.actions.aggiornaPartecipanti();
      }
    },

    async caricaSessioni() {
      if (!D.sessioneSelect) return;

      const res = await fetch(`${S.API_BASE}/admin/sessioni-lista/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN }
      });

      const data = await res.json();
      if (!data.success) return;

      const lista = Array.isArray(data.sessioni) ? data.sessioni : [];
      D.sessioneSelect.innerHTML = lista.map((s) => {
        const id = Number(s.id || 0);
        const nome = escapeHtml(nomeSessioneFromRecord(s));
        return `<option value="${id}">${id} · ${nome}</option>`;
      }).join('');

      const correnteId = Number(data.sessione_corrente_id || S.SESSIONE_ID || 0);
      if (correnteId > 0) {
        D.sessioneSelect.value = String(correnteId);
      }
    },

    async impostaSessioneCorrente() {
      if (!D.sessioneSelect) return;

      const targetId = Number(D.sessioneSelect.value || 0);
      if (targetId <= 0) {
        addLog({ ok: false, title: 'set-corrente', message: 'Seleziona una sessione valida', data: {} });
        return;
      }

      const formData = new FormData();
      formData.append('sessione_id', String(targetId));

      const res = await fetch(`${S.API_BASE}/admin/set-corrente/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
        body: formData
      });

      const data = await res.json();

      addLog({
        ok: !!data.success,
        title: 'set-corrente',
        message: data.success ? `Sessione corrente impostata: ${targetId}` : (data.error || 'Operazione fallita'),
        data
      });

      if (data.success) {
        S.SESSIONE_ID = targetId;
        await Admin.actions.aggiornaStato();
        await Admin.actions.aggiornaJoinRichieste();
        await Admin.actions.caricaSessioni();
        if (D.domandeSessioneWrapper && D.domandeSessioneWrapper.style.display !== 'none') {
          await Admin.actions.caricaDomandeSessione(targetId);
        }
      }
    },

    async caricaDomandeSessione(sessioneId) {
      if (!D.domandeSessioneList) return;

      const formData = new FormData();
      formData.append('sessione_id', String(Number(sessioneId || 0)));

      const res = await fetch(`${S.API_BASE}/admin/domande-sessione/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
        body: formData
      });

      const data = await res.json();
      if (!data.success) {
        D.domandeSessioneList.innerHTML = `<div>Errore caricamento domande: ${escapeHtml(data.error || 'errore sconosciuto')}</div>`;
        return;
      }

      const domande = Array.isArray(data.domande) ? data.domande : [];
      if (domande.length === 0) {
        D.domandeSessioneList.innerHTML = 'Nessuna domanda caricata';
        return;
      }

      D.domandeSessioneList.innerHTML = domande.map((d) => {
        const posizione = Number(d.posizione || 0);
        const id = Number(d.domanda_id || 0);
        const testo = escapeHtml(String(d.testo || ''));
        return `<div style="padding:4px 0; border-bottom:1px solid #222;">#${posizione} · [${id}] ${testo}</div>`;
      }).join('');
    },

    toggleDomandeSessione() {
      if (!D.domandeSessioneWrapper) return;

      const isHidden = D.domandeSessioneWrapper.style.display === 'none' || D.domandeSessioneWrapper.style.display === '';
      D.domandeSessioneWrapper.style.display = isHidden ? 'block' : 'none';

      if (isHidden) {
        const sessioneId = Number(D.sessioneSelect?.value || S.SESSIONE_ID || 0);
        Admin.actions.caricaDomandeSessione(sessioneId);
      }
    },

    async callAdmin(action) {
      const res = await fetch(`${S.API_BASE}/admin/${action}/${S.SESSIONE_ID}`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN }
      });

      const data = await res.json();

      addLog({
        ok: !!data.success,
        title: action,
        message: data.success
          ? `Azione "${action}" eseguita`
          : (data.error ? `Errore: ${data.error}` : `Errore sconosciuto`),
        data
      });

      Admin.actions.aggiornaStato();
      Admin.actions.aggiornaJoinRichieste();
    },

    async nuovaSessione() {
      const nomeSessione = String(D.inputSessioneNome?.value || '').trim();
      const formData = new FormData();

      if (nomeSessione !== '') formData.append('nome', nomeSessione);

      const res = await fetch(`${S.API_BASE}/admin/nuova-sessione/0`, {
        method: 'POST',
        headers: { 'X-ADMIN-TOKEN': S.ADMIN_TOKEN },
        body: formData
      });

      const data = await res.json();

      addLog({
        ok: !!data.success,
        title: 'nuova-sessione',
        message: data.success
          ? `Creata nuova sessione: ${data.sessione_id}`
          : (data.error ? `Errore: ${data.error}` : `Errore sconosciuto`),
        data
      });

      if (data.success) {
        S.SESSIONE_ID = data.sessione_id;

        const nomeRisposta = String(data.nome_sessione || '').trim();
        if (nomeRisposta !== '') S.NOME_SESSIONE = nomeRisposta;
        else if (nomeSessione !== '') S.NOME_SESSIONE = nomeSessione;
        else S.NOME_SESSIONE = '';

        if (D.inputSessioneNome) D.inputSessioneNome.value = '';

        Admin.actions.aggiornaStato();
        Admin.actions.aggiornaJoinRichieste();
        Admin.actions.caricaSessioni();
      }
    },

    apriMedia() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', 'admin/media');
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Media', message: 'Aperta gestione media', data: {} });
    },

    apriSchermo() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', `screen/${S.SESSIONE_ID}`);
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Screen', message: `Schermo attivato per sessione ${S.SESSIONE_ID}`, data: { sessione_id: S.SESSIONE_ID } });
    },

    apriSettings() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', 'admin/settings');
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Settings', message: 'Aperto pannello settings', data: {} });
    },

    apriQuizConfigV2() {
      const url = new URL(window.location.href);
      url.searchParams.set('url', 'admin/quizConfigV2');
      window.open(url.toString(), '_blank', 'noopener,noreferrer');
      addLog({ ok: true, title: 'Quiz Config V2', message: 'Aperto pannello SQL/API Quiz Config V2', data: {} });
    },

    async aggiornaStato() {
      const res = await fetch(`${S.API_BASE}/stato/${S.SESSIONE_ID}`);
      const data = await res.json();
      if (data.success) {
        Admin.ui.aggiornaUI(data.sessione);
      }
    }
  };

  // Compatibilità con onclick inline
  window.gestisciJoin = (requestId, action) => Admin.actions.gestisciJoin(requestId, action);
})();