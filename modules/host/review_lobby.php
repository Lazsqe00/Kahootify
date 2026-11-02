<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$roomId    = intval($_GET['id_room'] ?? ($_SESSION['id_room'] ?? 0));

if ($roomId <= 0) {
    redirect('modules/quiz/list.php');
}

$room = db_fetch_one(
    "SELECT r.*, q.name as quiz_name, q.description as quiz_description, q.game_type, q.mode as quiz_mode 
     FROM room r 
     JOIN quiz q ON r.id_quiz = q.id_quiz 
     WHERE r.id_room = :r AND r.id_creator = :c",
    [':r' => $roomId, ':c' => $creatorId]
);

if (!$room || empty($room['is_review_mode'])) {
    redirect('modules/host/lobby.php?id_room=' . $roomId);
}

if ($room['status'] === 'finished') {
    set_flash_message('info', 'This review session has ended. You can view the final report.');
    redirect('modules/reports/detail.php?id_room=' . $roomId);
}

// Check expiration
if (!empty($room['review_end_time']) && strtotime($room['review_end_time']) < time()) {
    db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $roomId]);
    db_execute("UPDATE quiz SET is_review_mode = 0 WHERE id_quiz = :q", [':q' => $room['id_quiz']]);
    set_flash_message('info', 'This review session has reached its end time and ended. You can view the final report.');
    redirect('modules/reports/detail.php?id_room=' . $roomId);
}

// Handle end review action
if (isset($_GET['end_review']) || (isset($_POST['end_review']))) {
    db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r AND id_creator = :c", [':r' => $roomId, ':c' => $creatorId]);
    db_execute("UPDATE quiz SET is_review_mode = 0, review_start_time = NULL, review_end_time = NULL WHERE id_quiz = :q AND id_creator = :c", [':q' => $room['id_quiz'], ':c' => $creatorId]);
    set_flash_message('success', 'Review room has ended successfully.');
    redirect('modules/quiz/list.php');
}

$joinPageUrl = BASE_URL . 'modules/player/join.php';
$joinUrl     = BASE_URL . 'modules/player/nickname.php?pin=' . rawurlencode($room['pin']);

$joinHost = strtolower(parse_url(BASE_URL, PHP_URL_HOST) ?? '');
$isLoopbackHost = in_array($joinHost, ['localhost', '127.0.0.1', '[::1]', '::1'], true) || substr($joinHost, -10) === '.localhost';

$pageTitle = "Review Mode Lobby - Game PIN: " . htmlspecialchars($room['pin']);
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.lobby-topbar {
    background: rgba(22, 11, 40, 0.78);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    padding: 12px 32px;
}
.lobby-nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 38px;
    padding: 0 16px;
    border-radius: 20px;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.88rem;
    font-weight: 700;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    cursor: pointer;
    text-decoration: none !important;
    transition: all 0.2s cubic-bezier(0.2, 0.8, 0.4, 1);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    outline: none;
}
.lobby-nav-btn:hover {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.35);
    transform: translateY(-1px);
}
.lobby-nav-btn-danger {
    background: rgba(226, 27, 60, 0.2);
    border-color: rgba(226, 27, 60, 0.4);
    color: #ff6b81;
}
.lobby-nav-btn-danger:hover {
    background: #e21b3c !important;
    color: #ffffff !important;
    border-color: #e21b3c !important;
}

/* KAHOT LOBBY PIN CARD (Exact styling from Image 2) */
.lobby-pin-card {
    background: #ffffff;
    border-radius: 24px;
    padding: 30px 48px;
    max-width: 900px;
    width: 92%;
    margin: 20px auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.25);
    text-align: center;
    box-sizing: border-box;
}
.lobby-join-options {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 36px;
}
.lobby-pin-details {
    flex: 1;
    min-width: 0;
}
.lobby-join-address {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1368ce;
    text-decoration: none;
    overflow-wrap: anywhere;
}
.lobby-join-address:hover {
    text-decoration: underline;
}
.lobby-pin-number {
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: clamp(3.2rem, 8vw, 5rem);
    letter-spacing: 8px;
    margin-right: -8px;
    line-height: 1.1;
    color: #1e293b;
    user-select: all;
    display: inline-block;
    word-break: keep-all;
    white-space: nowrap;
}
@media (max-width: 600px) {
    .lobby-pin-number {
        font-size: clamp(2rem, 11vw, 3rem);
        letter-spacing: 6px;
        margin-right: -6px;
    }
}
.lobby-qr-panel {
    border-left: 2px solid #f1f5f9;
    padding-left: 36px;
}
.lobby-qr-button {
    display: block;
    padding: 0;
    margin: 0 auto;
    border: 0;
    border-radius: 8px;
    background: #fff;
    cursor: zoom-in;
    width: 210px;
    height: 210px;
    max-width: 100%;
}
.lobby-qr-button:focus-visible {
    outline: 3px solid #1368ce;
    outline-offset: 4px;
}
.lobby-qr-code svg {
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 8px;
}
.lobby-qr-dialog {
    width: min(90vw, 560px);
    max-height: 90vh;
    overflow: auto;
    border: 0;
    border-radius: 20px;
    padding: 24px;
    text-align: center;
    color: #212529;
}
.lobby-qr-dialog::backdrop {
    background: rgba(0, 0, 0, 0.75);
}
.lobby-qr-dialog .lobby-qr-code {
    max-width: 440px;
    margin: auto;
}
@media (max-width: 900px) {
    .lobby-pin-card {
        padding: 24px 20px;
    }
    .lobby-join-options {
        flex-direction: column;
        gap: 24px;
    }
    .lobby-qr-panel {
        border-left: 0;
        border-top: 2px solid #f1f5f9;
        padding: 20px 0 0;
        width: 100%;
    }
}
</style>

<nav class="lobby-topbar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center" style="gap: 12px;">
        <a href="<?= BASE_URL ?>modules/quiz/list.php" class="d-inline-flex align-items-center" title="Kahoot!">
            <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Kahoot!" style="max-height: 34px; filter: brightness(0) invert(1);">
        </a>
        <span class="badge badge-warning text-dark font-weight-bold px-3 py-2 shadow-sm" style="font-size: 0.85rem; border-radius: 12px;">
            <i class="fas fa-book-reader mr-1"></i> Review Mode (Self-Paced)
        </span>
    </div>
    <div class="d-flex align-items-center" style="gap: 10px;">
        <a href="<?= BASE_URL ?>modules/quiz/list.php" class="lobby-nav-btn" title="Back to My Kahoots">
            <i class="fas fa-arrow-left"></i>
            <span>My Kahoots</span>
        </a>
        <a href="review_lobby.php?id_room=<?= $roomId ?>&end_review=1" class="lobby-nav-btn lobby-nav-btn-danger" onclick="return confirm('Are you sure you want to end this review session early?');" title="End Review Session">
            <i class="fas fa-stop-circle"></i>
            <span>End Session</span>
        </a>
    </div>
</nav>

<div class="container-fluid text-center text-white py-4">

    <div class="mb-3">
        <h2 class="font-weight-bold text-white mb-1" style="font-size: 2.2rem; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">
            <?= htmlspecialchars($room['quiz_name']) ?>
        </h2>
        <p class="text-white-50 mb-0 font-weight-medium">
            Self-paced review room &bull; Players can join anytime and study at their own pace.
        </p>
    </div>

    <!-- MAIN JOIN CARD (Exact matching style from Image 2) -->
    <div class="lobby-pin-card mx-auto text-dark shadow-lg">
        <div class="lobby-join-options" id="lobbyJoin" data-join-url="<?= htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') ?>">
            <div class="lobby-pin-details">
                <p class="font-weight-bold mb-2 text-dark" style="font-size: 1.1rem;">Join at</p>
                <a class="lobby-join-address" href="<?= htmlspecialchars($joinPageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars(preg_replace('#^https?://#', '', $joinPageUrl)) ?></a>
                
                <p class="text-muted font-weight-bold text-uppercase mt-4 mb-2" style="letter-spacing: 4px; font-size: 0.95rem;">GAME PIN</p>
                <div class="lobby-pin-number"><?= htmlspecialchars($room['pin']) ?></div>

                <div class="mt-3">
                    <button type="button" id="copyJoinLink" class="btn btn-outline-dark font-weight-bold px-4 py-2" style="border-radius: 8px; border: 2px solid #2d3748;" hidden>
                        <i class="fas fa-link mr-2" aria-hidden="true"></i> Copy join link
                    </button>
                    <p id="joinLinkStatus" class="small text-muted mt-2 mb-0" role="status" aria-live="polite"></p>
                    <input type="text" id="joinLinkFallback" class="form-control mt-2" aria-label="Join link: select and copy" value="<?= htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') ?>" readonly hidden>
                </div>
            </div>

            <div class="lobby-qr-panel">
                <p class="font-weight-bold mb-2 text-dark" style="font-size: 1.1rem;">Or scan to join</p>
                <button type="button" id="enlargeJoinQr" class="lobby-qr-button" aria-label="Enlarge the QR code to join this game" aria-haspopup="dialog" aria-controls="joinQrDialog" disabled>
                    <span id="joinQrCode" class="lobby-qr-code"><span class="small text-muted">Loading QR code...</span></span>
                </button>
                <p id="joinQrHint" class="small text-muted mt-2 mb-0">Scan with your phone camera</p>
                <noscript><p class="small text-danger">Enable JavaScript to display the QR code, or join using the PIN.</p></noscript>
            </div>
        </div>

        <?php if ($isLoopbackHost): ?>
            <div class="alert alert-warning small text-left mt-4 mb-0" role="note" style="border-radius: 12px; background: #fff8e1; border: 1px solid #ffe082;">
                <strong>Joining from a phone?</strong> Connect to the same Wi-Fi and open this lobby using your computer's LAN IP instead of localhost (for example, http://192.168.1.10/Kahootify/). The QR code will then use that address.
            </div>
        <?php endif; ?>

        <div class="mt-4 pt-3 border-top text-center small text-muted">
            <i class="far fa-clock text-primary mr-1"></i> Timeframe:
            <strong class="text-dark">
                <?= !empty($room['review_start_time']) ? date('M d, H:i', strtotime($room['review_start_time'])) : 'Open now' ?>
                &rarr;
                <?= !empty($room['review_end_time']) ? date('M d, H:i', strtotime($room['review_end_time'])) : 'No expiration' ?>
            </strong>
        </div>
    </div>

    <!-- QR ENLARGE DIALOG -->
    <dialog id="joinQrDialog" class="lobby-qr-dialog" aria-labelledby="joinQrTitle">
        <form method="dialog" class="text-right"><button type="submit" class="btn btn-outline-dark btn-sm" aria-label="Close enlarged QR code">Close <span aria-hidden="true">&times;</span></button></form>
        <h2 id="joinQrTitle" class="h4 font-weight-bold mt-2">Scan to join</h2>
        <div id="joinQrCodeLarge" class="lobby-qr-code"></div>
        <p class="font-weight-bold mt-2 mb-0">Game PIN: <?= htmlspecialchars($room['pin']) ?></p>
    </dialog>

    <!-- ACTION BUTTONS: REDIRECT TO REPORT LIST & SESSION REPORT -->
    <div class="d-flex justify-content-center align-items-center flex-wrap my-4" style="gap: 20px;">
        <a href="<?= BASE_URL ?>modules/reports/list.php" class="btn btn-primary btn-lg font-weight-bold px-4 py-3 shadow rounded-pill" style="background: linear-gradient(135deg, #7c3aed, #46178f); border: none; font-size: 1.05rem;">
            <i class="fas fa-chart-pie mr-2"></i> View Reports List
        </a>
        <a href="<?= BASE_URL ?>modules/reports/detail.php?id_room=<?= $roomId ?>" class="btn btn-info btn-lg font-weight-bold px-4 py-3 shadow rounded-pill" style="font-size: 1.05rem;">
            <i class="fas fa-chart-line mr-2"></i> View Session Report
        </a>
    </div>

</div>

<script src="<?= BASE_URL ?>assets/js/qrcodegen.js"></script>
<script src="<?= BASE_URL ?>assets/js/lobby-join.js"></script>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
