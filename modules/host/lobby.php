<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$roomId = intval($_SESSION['id_room'] ?? 0);
$room = db_fetch_one("SELECT r.*, q.name as quiz_name FROM room r JOIN quiz q ON r.id_quiz = q.id_quiz WHERE r.id_room = :r", [':r' => $roomId]);

if (!$room || $room['status'] === 'finished') {
    set_flash_message('danger', 'Game room does not exist or has already finished!');
    redirect('modules/auth/dashboard.php');
}

$questions = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $room['id_quiz']]);
$totalQuizQuestions = count($questions);

if (isset($_GET['start_game'])) {
    if ($totalQuizQuestions > 0) {
        $allQIds = array_column($questions, 'id_question');

        if (isset($_GET['show_questions'])) {
            $showQ = intval($_GET['show_questions']) ? 1 : 0;
            db_execute("UPDATE room SET show_questions_on_players = :sq WHERE id_room = :r", [
                ':sq' => $showQ,
                ':r'  => $roomId
            ]);
        }

        $mode = $_GET['question_mode'] ?? 'all';
        $n = isset($_GET['question_limit']) ? intval($_GET['question_limit']) : $totalQuizQuestions;
        $selectedQIds = $allQIds;
        $savedLimit = null;

        // Nếu Host chọn bốc N câu hợp lệ (1 <= N < Tổng số câu)
        if ($mode === 'random' && $n > 0 && $n < $totalQuizQuestions) {
            shuffle($allQIds);
            $selectedQIds = array_slice($allQIds, 0, $n);
            $savedLimit = $n;
        } elseif ($mode === 'random' && $n >= $totalQuizQuestions) {
            // N >= tổng số câu: bốc tất cả các câu xáo trộn
            shuffle($allQIds);
            $selectedQIds = $allQIds;
            $savedLimit = $totalQuizQuestions;
        } else {
            // Chế độ 'all' hoặc N <= 0: Chơi toàn bộ câu hỏi (mặc định)
            $selectedQIds = $allQIds;
            $savedLimit = null;
        }

        $firstQId = $selectedQIds[0];
        db_execute(
            "UPDATE room SET status = :s, question_start_time = UNIX_TIMESTAMP(), question_limit = :ql, question_order = :qo WHERE id_room = :r",
            [
                ':s'  => (string)$firstQId,
                ':ql' => $savedLimit,
                ':qo' => json_encode(array_values($selectedQIds)),
                ':r'  => $roomId
            ]
        );
        $_SESSION['question_index'] = 0;
        redirect('modules/host/play.php');
    } else {
        set_flash_message('danger', 'Bài trắc nghiệm này chưa có câu hỏi nào!');
        redirect('modules/auth/dashboard.php');
    }
}

if (isset($_GET['cancel_game'])) {
    db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $roomId]);
    unset($_SESSION['id_room'], $_SESSION['pin'], $_SESSION['id_quiz']);
    set_flash_message('info', 'Game room has been cancelled!');
    redirect('modules/auth/dashboard.php');
}

$players = db_fetch_all("SELECT * FROM players WHERE id_room = :r ORDER BY id_player ASC", [':r' => $roomId]);

$showOnPlayers = !empty($room['show_questions_on_players']);

$pageTitle = "Lobby - Game PIN: " . htmlspecialchars($room['pin']);
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<audio id="lobbyAudio" loop preload="auto">
    <source src="<?= BASE_URL ?>assets/audio/lobby.mp3" type="audio/mpeg">
    <source src="<?= BASE_URL ?>assets/audio/lobby.wav" type="audio/wav">
    <source src="<?= BASE_URL ?>assets/audio/lobby.ogg" type="audio/ogg">
</audio>

<nav class="navbar navbar-dark bg-dark px-4 py-2 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
        <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 32px; filter: brightness(100);" class="mr-3">
        <span class="text-white font-weight-bold" style="font-size: 1.1rem;"><?= htmlspecialchars($room['quiz_name']) ?></span>
    </div>
    <div class="d-flex align-items-center" style="gap: 12px;">

        <button type="button" class="btn btn-sm btn-outline-light font-weight-bold shadow-sm rounded-pill px-3" data-toggle="modal" data-target="#gameSettingsModal" title="Cài đặt phòng chơi">
            <i class="fas fa-cog mr-1"></i> Cài đặt
        </button>

        <button type="button" id="audioToggleBtn" class="btn btn-sm btn-light font-weight-bold shadow-sm rounded-pill px-3" onclick="toggleAudio()" title="Toggle lobby music">
            <i class="fas fa-volume-up text-success mr-1" id="audioIcon"></i>
            <span id="audioLabel">Music: ON</span>
        </button>

        <a href="lobby.php?cancel_game=true" class="btn btn-sm btn-outline-danger font-weight-bold" onclick="return confirm('Are you sure you want to cancel this game room?');">
            <i class="fas fa-times mr-1"></i> Cancel Game
        </a>
    </div>
</nav>

<style>

.lobby-pin-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 25px 40px;
    max-width: 540px;
    width: 90%;
    margin: 20px auto;
    box-shadow: 0 15px 35px rgba(0,0,0,0.3);
    text-align: center;
    box-sizing: border-box;
}
.lobby-pin-number {
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: clamp(3rem, 7.5vw, 4.8rem);
    letter-spacing: 10px;
    margin-right: -10px;
    line-height: 1.1;
    color: #212529;
    user-select: all;
    display: inline-block;
    word-break: keep-all;
    white-space: nowrap;
}
</style>

<div class="container-fluid text-center text-white py-4">

    <div class="lobby-pin-card mx-auto text-dark">
        <p class="text-muted font-weight-bold text-uppercase mb-2" style="letter-spacing: 3px; font-size: 0.95rem;">GAME PIN</p>
        <div class="lobby-pin-number">
            <?= htmlspecialchars($room['pin']) ?>
        </div>
    </div>

    <!-- Cài đặt: Hiển thị câu hỏi & đáp án trên máy học sinh (Căn phải, in đậm, rõ ràng, không icon) -->
    <div class="lobby-settings-bar mx-auto mb-3" style="max-width: 540px; width: 90%;">
        <div class="d-flex justify-content-end align-items-center">
            <div class="lobby-device-toggle-pill shadow d-inline-flex align-items-center" style="background: #ffffff; border-radius: 50px; padding: 10px 22px; border: 2px solid #ffffff;">
                <label for="toggleShowQuestions" class="mb-0 mr-3 text-dark font-weight-bold" style="font-family: 'Montserrat', sans-serif; font-size: 0.98rem; font-weight: 800; color: #111111; cursor: pointer; user-select: none;">
                    Hiển thị câu hỏi &amp; đáp án trên máy học sinh:
                </label>
                <span id="settingStatusLabel" class="badge <?= $showOnPlayers ? 'badge-success' : 'badge-secondary' ?> font-weight-bold mr-3 px-2 py-1" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                    <?= $showOnPlayers ? 'BẬT' : 'TẮT' ?>
                </span>
                <div class="custom-control custom-switch" style="transform: scale(1.3); transform-origin: center center;">
                    <input type="checkbox" class="custom-control-input" id="toggleShowQuestions" <?= $showOnPlayers ? 'checked' : '' ?> onchange="updateShowQuestions(this.checked)">
                    <label class="custom-control-label" for="toggleShowQuestions" style="cursor: pointer;"></label>
                </div>
            </div>
        </div>
    </div>

    <!-- Cài đặt số lượng câu hỏi ván chơi (Ngân hàng đề) -->
    <div class="lobby-settings-bar mx-auto mb-4" style="max-width: 540px; width: 90%;">
        <div class="lobby-device-toggle-pill shadow d-flex flex-column text-dark" style="background: #ffffff; border-radius: 18px; padding: 14px 22px; border: 2px solid #ffffff;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="font-weight-bold" style="font-family: 'Montserrat', sans-serif; font-size: 0.98rem; font-weight: 800; color: #111111;">
                    <i class="fas fa-random text-primary mr-1"></i> Số lượng câu hỏi ván chơi:
                </span>
                <span id="lobbyModeStatusBadge" class="badge badge-primary font-weight-bold px-2 py-1" style="font-size: 0.85rem;">
                    Tất cả (<?= $totalQuizQuestions ?> câu)
                </span>
            </div>

            <div class="d-flex flex-column text-left" style="gap: 8px;">
                <div class="custom-control custom-radio">
                    <input type="radio" id="lobbyModeAll" name="lobby_question_mode" class="custom-control-input" value="all" checked onchange="syncQuestionSettings('lobby')">
                    <label class="custom-control-label font-weight-bold text-dark" for="lobbyModeAll" style="cursor: pointer; font-size: 0.92rem;">
                        Chơi tất cả câu hỏi (<span class="text-primary font-weight-bold"><?= $totalQuizQuestions ?> câu</span>)
                    </label>
                </div>
                <div class="custom-control custom-radio d-flex align-items-center flex-wrap">
                    <input type="radio" id="lobbyModeRandom" name="lobby_question_mode" class="custom-control-input" value="random" onchange="syncQuestionSettings('lobby')">
                    <label class="custom-control-label font-weight-bold text-dark mr-2 mb-0" for="lobbyModeRandom" style="cursor: pointer; font-size: 0.92rem;">
                        Bốc ngẫu nhiên
                    </label>
                    <div class="d-inline-flex align-items-center" style="gap: 6px;">
                        <input type="number" id="lobbyQuestionLimit" class="form-control form-control-sm text-center font-weight-bold text-dark" 
                               style="width: 75px; height: 32px; border-radius: 8px; border: 2px solid #ced4da;" 
                               min="1" max="<?= $totalQuizQuestions ?>" 
                               value="<?= min(5, $totalQuizQuestions) ?>" 
                               onchange="onLobbyLimitInput()" oninput="onLobbyLimitInput()" onclick="$('#lobbyModeRandom').prop('checked', true); syncQuestionSettings('lobby');">
                        <span class="font-weight-bold text-muted small">/ <?= $totalQuizQuestions ?> câu trong ngân hàng</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center align-items-center my-4" style="gap: 20px;">
        <div class="bg-dark px-4 py-2 rounded-pill shadow">
            <i class="fas fa-users text-success mr-2"></i>
            <span class="font-weight-bold" style="font-size: 1.2rem;">
                <span id="playerCount"><?= count($players) ?></span> players
            </span>
        </div>

        <a href="lobby.php?start_game=true&show_questions=<?= $showOnPlayers ? '1' : '0' ?>" id="btnStartGame" class="btn btn-kahoot btn-kahoot-green btn-lg px-5 py-3 shadow" style="<?= count($players) === 0 ? 'pointer-events: none; opacity: 0.6;' : '' ?>">
            <i class="fas fa-play mr-2"></i> START GAME
        </a>
    </div>

    <div class="container mt-4" style="max-width: 900px;">
        <h5 class="font-weight-bold mb-3" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.5);">
            Waiting for players to join:
        </h5>
        <div id="playersContainer" class="d-flex flex-wrap justify-content-center" style="gap: 12px;">
            <?php if (empty($players)): ?>
                <div id="waitingMsg" class="py-4">
                    <div class="spinner-border text-light mb-3" role="status"></div>
                    <p class="font-weight-bold" style="font-size: 1.2rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                        Waiting for players to enter PIN...
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($players as $p): ?>
                    <div class="badge badge-light px-3 py-2 text-dark font-weight-bold shadow-sm" style="font-size: 1.1rem; border-radius: 8px;">
                        <i class="fas fa-user-circle text-primary mr-1"></i> <?= htmlspecialchars($p['nickname']) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Cài đặt phòng -->
<div class="modal fade text-dark" id="gameSettingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header text-white" style="background-color: var(--kahoot-purple-dark);">
                <h5 class="modal-title font-weight-bold" id="settingsModalTitle">
                    <i class="fas fa-sliders-h mr-2"></i>Cài đặt phòng trò chơi
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-left">
                <!-- 1. Bốc ngẫu nhiên câu hỏi từ ngân hàng đề -->
                <div class="p-3 mb-3 rounded" style="background-color: #f8f9fa; border: 1px solid #e9ecef;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="font-weight-bold mb-0" style="font-family: 'Montserrat', sans-serif; font-weight: 800; color: #111111;">
                            <i class="fas fa-random text-primary mr-2"></i>Số lượng câu hỏi (Ngân hàng đề)
                        </h6>
                        <span id="modalModeStatusBadge" class="badge badge-primary font-weight-bold px-2 py-1" style="font-size: 0.85rem;">
                            Tất cả (<?= $totalQuizQuestions ?> câu)
                        </span>
                    </div>
                    <p class="text-muted small mb-3">
                        Ngân hàng Quiz hiện có tổng cộng <b><?= $totalQuizQuestions ?> câu</b>. Bạn có thể cho lớp chơi toàn bộ câu hỏi hoặc chỉ bốc ngẫu nhiên N câu hỏi cho trận đấu này.
                    </p>

                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" id="modalModeAll" name="modal_question_mode" class="custom-control-input" value="all" checked onchange="syncQuestionSettings('modal')">
                        <label class="custom-control-label font-weight-bold text-dark" for="modalModeAll" style="cursor: pointer;">
                            Chơi tất cả câu hỏi (<span class="text-primary font-weight-bold"><?= $totalQuizQuestions ?> câu</span>)
                        </label>
                    </div>

                    <div class="custom-control custom-radio d-flex align-items-center flex-wrap">
                        <input type="radio" id="modalModeRandom" name="modal_question_mode" class="custom-control-input" value="random" onchange="syncQuestionSettings('modal')">
                        <label class="custom-control-label font-weight-bold text-dark mr-2 mb-0" for="modalModeRandom" style="cursor: pointer;">
                            Bốc ngẫu nhiên
                        </label>
                        <div class="d-inline-flex align-items-center" style="gap: 6px;">
                            <input type="number" id="modalQuestionLimit" class="form-control form-control-sm text-center font-weight-bold text-dark" 
                                   style="width: 80px; height: 32px; border-radius: 8px; border: 2px solid #ced4da;" 
                                   min="1" max="<?= $totalQuizQuestions ?>" 
                                   value="<?= min(5, $totalQuizQuestions) ?>" 
                                   onchange="onModalLimitChange()" oninput="onModalLimitChange()" onclick="$('#modalModeRandom').prop('checked', true); syncQuestionSettings('modal');">
                            <span class="font-weight-bold text-muted small">/ <?= $totalQuizQuestions ?> câu</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Hiển thị câu hỏi & đáp án trên máy học sinh -->
                <div class="p-3 mb-3 rounded" style="background-color: #f8f9fa; border: 1px solid #e9ecef;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="pr-3">
                            <h6 class="font-weight-bold mb-1" style="font-family: 'Montserrat', sans-serif; font-weight: 800; color: #111111;">
                                Hiển thị câu hỏi &amp; đáp án trên máy học sinh
                            </h6>
                            <p class="text-muted small mb-0">
                                Dành cho học Online / Ngồi xa máy chiếu. Khi bật, thiết bị học sinh sẽ thấy đề bài, hình ảnh và chữ trên các nút đáp án.
                            </p>
                        </div>
                        <div class="custom-control custom-switch pl-4" style="transform: scale(1.3); transform-origin: right center;">
                            <input type="checkbox" class="custom-control-input" id="modalToggleShowQuestions" <?= $showOnPlayers ? 'checked' : '' ?> onchange="updateShowQuestions(this.checked)">
                            <label class="custom-control-label" for="modalToggleShowQuestions" style="cursor: pointer;"></label>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info py-2 px-3 small mb-0">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Chế độ mặc định (TẮT):</strong> Màn hình học sinh chỉ có 4 ô màu trơn lớn để nhìn lên máy chiếu.
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-primary font-weight-bold rounded-pill px-4" data-dismiss="modal">Xong</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>

const audio = document.getElementById('lobbyAudio');

function tryPlayAudio() {
    if (!audio) return;
    audio.play().then(function() {
        updateAudioUI(true);
    }).catch(function() {

        updateAudioUI(false);

        $(document).one('click keydown', function() {
            audio.play().then(function() {
                updateAudioUI(true);
            }).catch(function() {});
        });
    });
}

function toggleAudio() {
    if (!audio) return;
    if (audio.paused) {
        audio.play().then(function() {
            updateAudioUI(true);
        }).catch(function(e) {
            console.log('Không thể phát nhạc:', e);
        });
    } else {
        audio.pause();
        updateAudioUI(false);
    }
}

function updateAudioUI(isPlaying) {
    const icon = document.getElementById('audioIcon');
    const label = document.getElementById('audioLabel');
    const btn = document.getElementById('audioToggleBtn');
    if (!icon || !label || !btn) return;
    if (isPlaying) {
        icon.className = 'fas fa-volume-up text-success mr-1';
        label.textContent = 'Music: ON';
        btn.className = 'btn btn-sm btn-light font-weight-bold shadow-sm rounded-pill px-3';
    } else {
        icon.className = 'fas fa-volume-mute text-danger mr-1';
        label.textContent = 'Music: OFF';
        btn.className = 'btn btn-sm btn-outline-light font-weight-bold shadow-sm rounded-pill px-3';
    }
}

let currentShowQuestions = <?= $showOnPlayers ? 1 : 0 ?>;
const totalQuizQuestions = <?= $totalQuizQuestions ?>;

function getSelectedQuestionConfig() {
    const isRandom = $('#lobbyModeRandom').is(':checked') || $('#modalModeRandom').is(':checked');
    let limit = parseInt($('#lobbyQuestionLimit').val()) || totalQuizQuestions;
    if (limit <= 0) limit = 1;
    if (limit > totalQuizQuestions) limit = totalQuizQuestions;
    return {
        mode: isRandom ? 'random' : 'all',
        limit: limit
    };
}

function syncQuestionSettings(source) {
    let mode, limit;
    if (source === 'modal') {
        mode = $('input[name="modal_question_mode"]:checked').val() || 'all';
        limit = parseInt($('#modalQuestionLimit').val()) || totalQuizQuestions;
        if (limit <= 0) limit = 1;
        if (limit > totalQuizQuestions) limit = totalQuizQuestions;

        $('#lobbyModeAll').prop('checked', mode === 'all');
        $('#lobbyModeRandom').prop('checked', mode === 'random');
        $('#lobbyQuestionLimit').val(limit);
    } else {
        mode = $('input[name="lobby_question_mode"]:checked').val() || 'all';
        limit = parseInt($('#lobbyQuestionLimit').val()) || totalQuizQuestions;
        if (limit <= 0) limit = 1;
        if (limit > totalQuizQuestions) limit = totalQuizQuestions;

        $('#modalModeAll').prop('checked', mode === 'all');
        $('#modalModeRandom').prop('checked', mode === 'random');
        $('#modalQuestionLimit').val(limit);
    }

    if (mode === 'random') {
        $('#lobbyModeStatusBadge').text('Bốc ' + limit + ' / ' + totalQuizQuestions + ' câu')
                                  .removeClass('badge-primary').addClass('badge-warning text-dark');
        $('#modalModeStatusBadge').text('Bốc ' + limit + ' / ' + totalQuizQuestions + ' câu')
                                  .removeClass('badge-primary').addClass('badge-warning text-dark');
    } else {
        $('#lobbyModeStatusBadge').text('Tất cả (' + totalQuizQuestions + ' câu)')
                                  .removeClass('badge-warning text-dark').addClass('badge-primary');
        $('#modalModeStatusBadge').text('Tất cả (' + totalQuizQuestions + ' câu)')
                                  .removeClass('badge-warning text-dark').addClass('badge-primary');
    }

    updateStartGameHref();
}

function onLobbyLimitInput() {
    $('#lobbyModeRandom').prop('checked', true);
    syncQuestionSettings('lobby');
}

function onModalLimitChange() {
    $('#modalModeRandom').prop('checked', true);
    syncQuestionSettings('modal');
}

function updateStartGameHref() {
    const config = getSelectedQuestionConfig();
    let url = 'lobby.php?start_game=true&show_questions=' + currentShowQuestions + '&question_mode=' + config.mode;
    if (config.mode === 'random') {
        url += '&question_limit=' + config.limit;
    }
    $('#btnStartGame').attr('href', url);
}

$(document).ready(function() {
    tryPlayAudio();
    updateStartGameHref();
    $('#btnStartGame').on('click', function() {
        updateStartGameHref();
    });
});

function updateShowQuestions(checked) {
    currentShowQuestions = checked ? 1 : 0;

    $('#toggleShowQuestions').prop('checked', checked);
    $('#modalToggleShowQuestions').prop('checked', checked);

    updateStartGameHref();

    const label = $('#settingStatusLabel');
    if (label.length) {
        label.text(checked ? 'BẬT' : 'TẮT')
             .removeClass('badge-success badge-secondary')
             .addClass(checked ? 'badge-success' : 'badge-secondary');
    }

    $.ajax({
        url: '<?= BASE_URL ?>modules/api/game_state.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'update_settings',
            id_room: roomId,
            show_questions_on_players: currentShowQuestions
        }),
        error: function(err) {
            console.error('Failed to update room settings:', err);
        }
    });
}

const roomId = <?= $roomId ?>;
let previousCount = <?= count($players) ?>;

function checkLobbyPlayers() {
    $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_players&id_room=' + roomId, function(res) {
        if (res && res.success) {
            const players = res.players;
            $('#playerCount').text(players.length);

            if (players.length > 0) {
                $('#btnStartGame').css({'pointer-events': 'auto', 'opacity': '1'});
                $('#waitingMsg').remove();
            } else {
                $('#btnStartGame').css({'pointer-events': 'none', 'opacity': '0.6'});
            }

            if (players.length !== previousCount) {
                previousCount = players.length;
                let html = '';
                players.forEach(function(p) {
                    html += `<div class="badge badge-light px-3 py-2 text-dark font-weight-bold shadow-sm" style="font-size: 1.1rem; border-radius: 8px;">
                                <i class="fas fa-user-circle text-primary mr-1"></i> ${escapeHtml(p.nickname)}
                             </div>`;
                });
                $('#playersContainer').html(html);
            }
        }
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

setInterval(checkLobbyPlayers, 1500);
</script>
</body>
</html>
