(function () {
    'use strict';
    const panel = document.getElementById('hostPlayers');
    if (!panel) return;
    const container = document.getElementById('playersContainer');
    const message = document.getElementById('kickPlayerStatus');
    let previousPlayers = '';
    let pending = false;
    let fetching = false;
    let revision = 0;

    async function refreshPlayers() {
        if (fetching || pending) return;
        fetching = true;
        const requestedRevision = revision;
        try {
            const response = await fetch(panel.dataset.apiUrl + 'game_state.php?action=get_players&id_room=' + panel.dataset.roomId, {cache: 'no-store'});
            const data = await response.json();
            if (!response.ok || !data.success || pending || requestedRevision !== revision) return;
            const signature = JSON.stringify(data.players.map(p => [p.id_player, p.nickname]));
            if (signature !== previousPlayers) {
                previousPlayers = signature;
                container.replaceChildren();
                data.players.forEach(function (player) {
                    const card = document.createElement('div');
                    card.className = 'badge badge-light px-3 py-2 text-dark font-weight-bold shadow-sm';
                    card.style.cssText = 'font-size:1.1rem;border-radius:8px';
                    const name = document.createElement('span');
                    name.textContent = player.nickname;
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-sm btn-outline-danger ml-2 kick-player';
                    button.dataset.playerId = player.id_player;
                    button.dataset.playerName = player.nickname;
                    button.setAttribute('aria-label', 'Kick ' + player.nickname);
                    button.textContent = 'Kick';
                    card.append(name, button);
                    container.appendChild(card);
                });
            }
            document.getElementById('waitingMsg').hidden = data.players.length > 0;
            document.dispatchEvent(new CustomEvent('roomplayerschange', {detail: data}));
        } catch (error) {
            // Retry on the next poll; retain the current player list.
        } finally {
            fetching = false;
        }
    }

    panel.addEventListener('click', async function (event) {
        const button = event.target.closest('.kick-player');
        if (!button || pending) return;
        if (!window.confirm('Remove "' + button.dataset.playerName + '" from this game?')) return;
        pending = true;
        revision++;
        button.disabled = true;
        message.textContent = 'Removing player...';
        try {
            const response = await fetch(panel.dataset.apiUrl + 'kick_player.php', {
                method: 'POST',
                body: new URLSearchParams({id_room: panel.dataset.roomId, id_player: button.dataset.playerId, csrf_token: panel.dataset.csrfToken})
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Unable to remove player.');
            message.textContent = data.message;
            if (panel.dataset.reloadAfterKick === '1') window.location.reload();
        } catch (error) {
            message.textContent = error.message || 'Unable to remove player. Please try again.';
        } finally {
            pending = false;
            button.disabled = false;
            refreshPlayers();
        }
    });
    refreshPlayers();
    window.setInterval(refreshPlayers, 1500);
})();
