// admin/04_log.js
(() => {
  const Admin = window.Admin;
  const { logEl } = Admin.dom;
  const { nowTime } = Admin.utils;

  Admin.log = {
    addLog({ ok, title, message, data }) {
      if (!logEl) return;

      const time = nowTime();
      const pillClass = ok ? 'ok' : 'err';
      const icon = ok ? '✅' : '❌';

      const item = document.createElement('div');
      item.className = 'log-item';

      const safeTitle = title ?? 'Azione';
      const safeMsg = message ?? '';
      const json = (data !== undefined) ? JSON.stringify(data, null, 2) : '';

      item.innerHTML = `
        <div class="log-top">
          <div class="pill ${pillClass}">${icon} ${safeTitle}</div>
          <div class="log-time">${time}</div>
        </div>
        <div class="log-main">${safeMsg}</div>
        ${json ? `
          <details>
            <summary>Dettagli</summary>
            <pre class="json">${json}</pre>
          </details>
        ` : ''}
      `;

      logEl.prepend(item);
    },

    clearLog() {
      if (!logEl) return;
      logEl.innerHTML = '';
    }
  };
})();