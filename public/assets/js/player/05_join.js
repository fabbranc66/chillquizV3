// 05_join.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;

  function completeJoin(nome, capitale) {
    if (D.displayName) D.displayName.innerText = nome;
    if (D.capitaleValue) D.capitaleValue.innerText = String(capitale);

    S.currentState = null;
    Player.polling.startPolling();

    Player.screens.hideAllScreens();
    Player.screens.show('screen-lobby');

    Player.polling.fetchStato();
  }

  async function handleJoin() {
    const nome = (D.inputNome?.value || '').trim();
    if (!nome) {
      alert('Inserisci un nome');
      return;
    }

    try {
      const formData = new FormData();
      formData.append('nome', nome);

      const response = await fetch(`${S.API_BASE}/join/${S.sessioneId || 0}`, {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (!data.success) {
        if (data.requires_approval && data.request_id) {
          alert(data.error || 'Richiesta in approvazione');
          watchJoinRequest(data.request_id, nome);
          return;
        }

        alert(data.error || 'Join non riuscito');
        return;
      }

      S.partecipazioneId = Number(data.partecipazione_id || 0);
      completeJoin(nome, Number(data.capitale || 0));
    } catch (err) {
      console.error(err);
    }
  }

  function watchJoinRequest(requestId, nome) {
    if (S.joinRequestPolling) {
      clearInterval(S.joinRequestPolling);
      S.joinRequestPolling = null;
    }

    const checkStatus = async () => {
      try {
        const formData = new FormData();
        formData.append('request_id', requestId);

        const response = await fetch(`${S.API_BASE}/joinStato/${S.sessioneId || 0}`, {
          method: 'POST',
          body: formData,
        });

        const data = await response.json();
        if (!data.success) return;

        if (data.stato === 'approvata' && data.partecipazione_id) {
          S.partecipazioneId = Number(data.partecipazione_id || 0);

          clearInterval(S.joinRequestPolling);
          S.joinRequestPolling = null;

          completeJoin(nome, Number(data.capitale || 0));
          return;
        }

        if (data.stato === 'rifiutata') {
          clearInterval(S.joinRequestPolling);
          S.joinRequestPolling = null;
          alert('Richiesta di accesso rifiutata dalla regia');
        }
      } catch (err) {
        console.error(err);
      }
    };

    checkStatus();
    S.joinRequestPolling = setInterval(checkStatus, 2000);
  }

  Player.join = { handleJoin };
})();