// 05_join.js
(() => {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const Alert = Player.uiAlert;
  const Copy = Player.copy;

  function formatNumber(value) {
    const support = Player.classificaSupport;
    if (support && typeof support.formatNumber === 'function') {
      return support.formatNumber(value);
    }

    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '0';
    return new Intl.NumberFormat('it-IT').format(numeric);
  }

  function completeJoin(nome, capitale) {
    if (D.displayName) D.displayName.innerText = nome;
    if (D.capitaleValue) {
      D.capitaleValue.innerText = formatNumber(capitale);
    }

    S.currentState = null;
    Player.polling.startPolling();

    Player.screens.hideAllScreens();
    Player.screens.show('screen-lobby');

    Player.polling.fetchStato();
  }

  async function handleJoin() {
    const nome = ((D.inputNome && D.inputNome.value) || '').trim();
    if (!nome) {
      Alert.show({
        title: Copy.joinNameRequiredTitle,
        message: Copy.joinNameRequiredMessage,
        tone: 'warn',
      });
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
          Alert.show({
            title: Copy.joinApprovalTitle,
            message: data.error || Copy.joinApprovalMessage,
            tone: 'info',
          });
          watchJoinRequest(data.request_id, nome);
          return;
        }

        Alert.show({
          title: Copy.joinFailedTitle,
          message: data.error || Copy.joinFailedMessage,
          tone: 'error',
        });
        return;
      }

      S.partecipazioneId = Number(data.partecipazione_id || 0);
      completeJoin(nome, Number(data.capitale || 0));
    } catch (err) {
      console.error(err);
      Alert.show({
        title: Copy.networkErrorTitle,
        message: Copy.joinNetworkErrorMessage,
        tone: 'error',
      });
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
          Alert.show({
            title: Copy.joinRejectedTitle,
            message: Copy.joinRejectedMessage,
            tone: 'error',
          });
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
