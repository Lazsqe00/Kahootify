<?php
if (empty($_SESSION['kick_player_token'])) {
    $_SESSION['kick_player_token'] = bin2hex(random_bytes(32));
}
$hostPlayers = filter_room_players($roomId, db_fetch_all('SELECT id_player, nickname FROM players WHERE id_room = :r ORDER BY id_player', [':r' => $roomId]));
?>
<div id="hostPlayers" data-room-id="<?= $roomId ?>" data-api-url="<?= htmlspecialchars(BASE_URL . 'modules/api/', ENT_QUOTES, 'UTF-8') ?>" data-csrf-token="<?= htmlspecialchars($_SESSION['kick_player_token'], ENT_QUOTES, 'UTF-8') ?>" data-reload-after-kick="<?= !empty($reloadAfterKick) ? '1' : '0' ?>">
    <p id="kickPlayerStatus" class="small font-weight-bold" role="status" aria-live="polite"></p>
    <div id="playersContainer" class="d-flex flex-wrap justify-content-center" style="gap: 12px;">
        <?php foreach ($hostPlayers as $p): ?>
            <div class="badge badge-light px-3 py-2 text-dark font-weight-bold shadow-sm" style="font-size: 1.1rem; border-radius: 8px;">
                <span><?= htmlspecialchars($p['nickname'], ENT_QUOTES, 'UTF-8') ?></span>
                <button type="button" class="btn btn-sm btn-outline-danger ml-2 kick-player" data-player-id="<?= (int)$p['id_player'] ?>" data-player-name="<?= htmlspecialchars($p['nickname'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars('Kick ' . $p['nickname'], ENT_QUOTES, 'UTF-8') ?>">Kick</button>
            </div>
        <?php endforeach; ?>
    </div>
    <p id="waitingMsg" class="font-weight-bold mt-3" <?= $hostPlayers ? 'hidden' : '' ?>>Waiting for players to join...</p>
</div>
<script src="<?= BASE_URL ?>assets/js/host-players.js" defer></script>
