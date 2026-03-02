// admin/03_utils.js
(() => {
  const Admin = window.Admin;

  Admin.utils = {
    nowTime() {
      const d = new Date();
      return d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    },

    escapeHtml(value) {
      const div = document.createElement('div');
      div.innerText = value ?? '';
      return div.innerHTML;
    },

    nomeSessioneFromRecord(sessione) {
      const raw = String(sessione?.nome_sessione || sessione?.nome || sessione?.titolo || '').trim();
      if (raw !== '') return raw;

      const id = Number(sessione?.id || 0);
      const creata = Number(sessione?.creata_il || 0);
      if (creata > 0) {
        return `Sessione ${id || ''} ${new Date(creata * 1000).toLocaleString('it-IT')}`.trim();
      }

      return id > 0 ? `Sessione ${id}` : 'Sessione';
    }
  };
})();