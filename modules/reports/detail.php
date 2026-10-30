<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$roomId    = intval($_GET['id_room'] ?? $_GET['id'] ?? 0);

if ($roomId <= 0) {
    set_flash_message('danger', 'Mã phòng không hợp lệ!');
    redirect('modules/reports/list.php');
}

// 1. Kiểm tra quyền sở hữu phòng ván đấu
$room = db_fetch_one("
    SELECT r.*, q.name as quiz_name, q.description as quiz_description
    FROM room r
    JOIN quiz q ON r.id_quiz = q.id_quiz
    WHERE r.id_room = :r AND r.id_creator = :c
", [':r' => $roomId, ':c' => $creatorId]);

if (!$room) {
    set_flash_message('danger', 'Không tìm thấy ván đấu hoặc bạn không có quyền xem báo cáo này!');
    redirect('modules/auth/dashboard.php');
}

// 2. Lấy danh sách câu hỏi của ván đấu (nếu bốc ngẫu nhiên N câu thì lấy đúng N câu đã chơi theo thứ tự)
$selectedQIds = !empty($room['question_order']) ? json_decode($room['question_order'], true) : null;
if (is_array($selectedQIds) && !empty($selectedQIds)) {
    $inClause = implode(',', array_map('intval', $selectedQIds));
    $questions = db_fetch_all("
        SELECT * FROM questions 
        WHERE id_question IN ($inClause)
        ORDER BY FIELD(id_question, $inClause)
    ");
} else {
    $questions = db_fetch_all("
        SELECT * FROM questions 
        WHERE id_quiz = :q 
        ORDER BY id_question ASC
    ", [':q' => $room['id_quiz']]);
}
$totalQuestions = count($questions);

// 3. Lấy danh sách người chơi kèm số câu đúng
$sqlPlayers = "
    SELECT p.*,
           (SELECT COUNT(*) FROM votes v 
            JOIN answers a ON v.id_answer = a.id_answer 
            WHERE v.id_player = p.id_player AND a.is_correct = 1) as correct_count,
           (SELECT COUNT(*) FROM votes v 
            WHERE v.id_player = p.id_player) as total_answered
    FROM players p
    WHERE p.id_room = :r
    ORDER BY p.score DESC, p.playeradd ASC
";
$players = filter_room_players($roomId, db_fetch_all($sqlPlayers, [':r' => $roomId]));
$totalPlayers = count($players);

// 4. Tính toán các chỉ số KPI Cards
$totalScore = array_sum(array_column($players, 'score'));
$avgScore   = $totalPlayers > 0 ? round($totalScore / $totalPlayers) : 0;

$totalCorrectCount = array_sum(array_column($players, 'correct_count'));
$totalPossibleAnswers = $totalPlayers * $totalQuestions;
$classAccuracy = $totalPossibleAnswers > 0 ? round(($totalCorrectCount / $totalPossibleAnswers) * 100, 1) : 0;

// 5. Thống kê chi tiết từng câu hỏi & tìm câu hỏi khó nhất (Difficult Question)
$questionAnalytics = [];
$mostDifficultQuestion = null;
$maxIncorrectRate = -1;

$kahootSymbols = [
    0 => ['symbol' => '▲', 'name' => 'A', 'color' => '#e21b3c', 'class' => 'badge-danger'],
    1 => ['symbol' => '◆', 'name' => 'B', 'color' => '#1368ce', 'class' => 'badge-primary'],
    2 => ['symbol' => '●', 'name' => 'C', 'color' => '#d89e00', 'class' => 'badge-warning'],
    3 => ['symbol' => '■', 'name' => 'D', 'color' => '#26890c', 'class' => 'badge-success']
];

foreach ($questions as $qIndex => $q) {
    $qId = intval($q['id_question']);

    // Lấy tất cả đáp án của câu hỏi
    $answers = db_fetch_all("
        SELECT * FROM answers 
        WHERE id_question = :qid 
        ORDER BY id_answer ASC
    ", [':qid' => $qId]);

    // Lấy số lượt bình chọn từng đáp án của học sinh phòng này
    $votesRows = db_fetch_all("
        SELECT v.id_answer, COUNT(*) as vote_count
        FROM votes v
        WHERE v.id_player IN (SELECT id_player FROM players WHERE id_room = :r)
          AND v.id_answer IN (SELECT id_answer FROM answers WHERE id_question = :qid)
        GROUP BY v.id_answer
    ", [':r' => $roomId, ':qid' => $qId]);

    $votesMap = [];
    foreach ($votesRows as $vr) {
        $votesMap[intval($vr['id_answer'])] = intval($vr['vote_count']);
    }

    $qTotalVotes = array_sum($votesMap);
    $qCorrectVotes = 0;

    foreach ($answers as $ansIdx => &$ans) {
        $aId = intval($ans['id_answer']);
        $voteCnt = $votesMap[$aId] ?? 0;
        $ans['vote_count'] = $voteCnt;
        
        // Tỷ lệ chọn tính trên số người chơi trong phòng (hoặc số người đã trả lời)
        $denominator = ($totalPlayers > 0) ? $totalPlayers : max(1, $qTotalVotes);
        $ans['vote_percent'] = round(($voteCnt / $denominator) * 100, 1);

        if ($ans['is_correct'] == 1) {
            $qCorrectVotes += $voteCnt;
        }

        $styleMeta = $kahootSymbols[$ansIdx % 4] ?? ['symbol' => '●', 'name' => chr(65 + $ansIdx), 'color' => '#6c757d', 'class' => 'badge-secondary'];
        $ans['symbol'] = $styleMeta['symbol'];
        $ans['label'] = $styleMeta['name'];
        $ans['color'] = $styleMeta['color'];
    }
    unset($ans);

    // Tỷ lệ đúng và sai của cả lớp cho câu hỏi này
    $denominatorQ = ($totalPlayers > 0) ? $totalPlayers : max(1, $qTotalVotes);
    $qAccuracy = round(($qCorrectVotes / $denominatorQ) * 100, 1);
    $qIncorrectRate = 100 - $qAccuracy;

    $itemData = [
        'question'       => $q,
        'index'          => $qIndex + 1,
        'answers'        => $answers,
        'total_voted'    => $qTotalVotes,
        'correct_votes'  => $qCorrectVotes,
        'accuracy'       => $qAccuracy,
        'incorrect_rate' => $qIncorrectRate
    ];
    $questionAnalytics[] = $itemData;

    // Xác định câu hỏi khó nhất (tỷ lệ trả lời sai cao nhất)
    if ($totalPlayers > 0 && $qIncorrectRate > $maxIncorrectRate) {
        $maxIncorrectRate = $qIncorrectRate;
        $mostDifficultQuestion = [
            'number'         => $qIndex + 1,
            'text'           => $q['text'],
            'incorrect_rate' => $qIncorrectRate,
            'accuracy'       => $qAccuracy,
            'correct_votes'  => $qCorrectVotes,
            'total_players'  => $totalPlayers
        ];
    }
}

$pageTitle = "Báo cáo ván đấu PIN: " . htmlspecialchars($room['pin']) . " | Reports";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container my-4">

    <!-- Header & Nút Điều Hướng -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <div class="mb-1">
                <a href="<?= BASE_URL ?>modules/reports/list.php" class="text-muted font-weight-bold">
                    <i class="fas fa-arrow-left mr-1"></i> Quay lại danh sách báo cáo
                </a>
            </div>
            <h2 class="font-weight-bold mb-1" style="color: var(--kahoot-purple);">
                <i class="fas fa-poll-h mr-2"></i>Báo cáo chi tiết: <?= htmlspecialchars($room['quiz_name']) ?>
            </h2>
            <div class="d-flex flex-wrap align-items-center text-muted small mt-1">
                <span class="badge badge-warning text-dark font-weight-bold mr-2 px-2 py-1" style="font-size: 0.95rem;">
                    PIN: <?= htmlspecialchars($room['pin']) ?>
                </span>
                <span class="mr-3">
                    <i class="far fa-calendar-alt mr-1"></i> Ngày: <?= date('d/m/Y', strtotime($room['roomadd'])) ?>
                </span>
                <span class="mr-3">
                    <i class="far fa-clock mr-1"></i> Giờ: <?= date('H:i', strtotime($room['roomadd'])) ?>
                </span>
                <span>
                    <i class="fas fa-question-circle mr-1"></i> <?= $totalQuestions ?> câu hỏi
                </span>
            </div>
        </div>

        <div class="mt-3 mt-md-0 d-flex">
            <a href="<?= BASE_URL ?>modules/reports/export_csv.php?id_room=<?= $roomId ?>" class="btn btn-kahoot btn-kahoot-green mr-2 shadow-sm font-weight-bold">
                <i class="fas fa-file-excel mr-1"></i> Tải bảng điểm (CSV)
            </a>
            <button type="button" class="btn btn-outline-secondary shadow-sm font-weight-bold" onclick="window.print();">
                <i class="fas fa-print mr-1"></i> In báo cáo
            </button>
        </div>
    </div>

    <!-- PHẦN 1: THẺ TÓM TẮT TỔNG QUAN (KPI CARDS) -->
    <div class="row mb-4">
        <!-- 1. Tổng số người chơi -->
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm text-white h-100" style="background: linear-gradient(135deg, #1368ce, #2980b9); border-radius: 12px;">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-uppercase small font-weight-bold" style="opacity: 0.9;">Tổng số người chơi</span>
                        <i class="fas fa-users fa-2x" style="opacity: 0.4;"></i>
                    </div>
                    <div class="mt-3">
                        <h2 class="display-4 font-weight-bold mb-0" style="font-size: 2.8rem;"><?= $totalPlayers ?></h2>
                        <small style="opacity: 0.9;">học sinh tham gia ván đấu</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Tỷ lệ trả lời đúng trung bình cả lớp -->
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm text-white h-100" style="background: linear-gradient(135deg, #26890c, #2ecc71); border-radius: 12px;">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-uppercase small font-weight-bold" style="opacity: 0.9;">Tỷ lệ đúng của lớp</span>
                        <i class="fas fa-bullseye fa-2x" style="opacity: 0.4;"></i>
                    </div>
                    <div class="mt-3">
                        <h2 class="display-4 font-weight-bold mb-0" style="font-size: 2.8rem;"><?= $classAccuracy ?>%</h2>
                        <small style="opacity: 0.9;"><?= $totalCorrectCount ?>/<?= max(1, $totalPossibleAnswers) ?> câu trả lời đúng</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Điểm số trung bình toàn phòng -->
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm text-white h-100" style="background: linear-gradient(135deg, #46178F, #6a2cc9); border-radius: 12px;">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-uppercase small font-weight-bold" style="opacity: 0.9;">Điểm số trung bình</span>
                        <i class="fas fa-star fa-2x" style="opacity: 0.4;"></i>
                    </div>
                    <div class="mt-3">
                        <h2 class="display-4 font-weight-bold mb-0" style="font-size: 2.8rem;"><?= number_format($avgScore) ?></h2>
                        <small style="opacity: 0.9;">điểm / người chơi</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Câu hỏi khó nhất (Difficult Question) -->
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm text-white h-100" style="background: linear-gradient(135deg, #e21b3c, #ff4757); border-radius: 12px;">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-uppercase small font-weight-bold" style="opacity: 0.9;">Câu hỏi khó nhất</span>
                        <i class="fas fa-exclamation-triangle fa-2x" style="opacity: 0.4;"></i>
                    </div>
                    <div class="mt-2">
                        <?php if ($mostDifficultQuestion && $totalPlayers > 0): ?>
                            <div class="font-weight-bold" style="font-size: 1.1rem;">
                                Câu #<?= $mostDifficultQuestion['number'] ?>
                            </div>
                            <div class="small mb-1 text-truncate" title="<?= htmlspecialchars($mostDifficultQuestion['text']) ?>">
                                <?= htmlspecialchars(mb_strimwidth($mostDifficultQuestion['text'], 0, 45, '...')) ?>
                            </div>
                            <span class="badge badge-light text-danger font-weight-bold px-2 py-1">
                                <?= $mostDifficultQuestion['incorrect_rate'] ?>% học sinh làm sai
                            </span>
                        <?php else: ?>
                            <div class="font-weight-bold mt-2" style="font-size: 1.1rem;">Không có câu khó</div>
                            <small style="opacity: 0.9;">Cả lớp đều làm tốt hoặc chưa có lượt trả lời</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABS ĐIỀU HƯỚNG BÁO CÁO -->
    <ul class="nav nav-tabs nav-fill mb-4 font-weight-bold" id="reportTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active py-3" id="players-tab" data-toggle="tab" href="#players" role="tab" aria-controls="players" aria-selected="true" style="font-size: 1.05rem;">
                <i class="fas fa-trophy mr-2 text-warning"></i> Bảng xếp hạng người chơi (Leaderboard)
                <span class="badge badge-primary badge-pill ml-2"><?= $totalPlayers ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-3" id="questions-tab" data-toggle="tab" href="#questions" role="tab" aria-controls="questions" aria-selected="false" style="font-size: 1.05rem;">
                <i class="fas fa-chart-bar mr-2 text-info"></i> Phân tích từng câu hỏi (Question Analysis)
                <span class="badge badge-secondary badge-pill ml-2"><?= $totalQuestions ?></span>
            </a>
        </li>
    </ul>

    <div class="tab-content" id="reportTabsContent">

        <!-- PHẦN 2: TAB BẢNG XẾP HẠNG NGƯỜI CHƠI (PLAYERS LEADERBOARD) -->
        <div class="tab-pane fade show active" id="players" role="tabpanel" aria-labelledby="players-tab">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="font-weight-bold mb-0 text-dark">
                        <i class="fas fa-medal mr-2 text-warning"></i>Kết quả xếp hạng học sinh
                    </h5>
                    <a href="<?= BASE_URL ?>modules/reports/export_csv.php?id_room=<?= $roomId ?>" class="btn btn-sm btn-outline-success font-weight-bold">
                        <i class="fas fa-file-csv mr-1"></i> Xuất file CSV
                    </a>
                </div>

                <div class="card-body p-0">
                    <?php if (empty($players)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-slash fa-3x text-muted mb-3" style="opacity: 0.5;"></i>
                            <h5 class="text-muted font-weight-bold">Ván đấu này không có người chơi nào tham gia!</h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 70px;" class="text-center">Hạng</th>
                                        <th>Nickname học sinh</th>
                                        <th class="text-right" style="width: 170px;">Tổng điểm</th>
                                        <th class="text-center" style="width: 180px;">Số câu đúng</th>
                                        <th style="width: 240px;">Tỷ lệ chính xác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($players as $idx => $p): 
                                        $rank = $idx + 1;
                                        $correct = intval($p['correct_count']);
                                        $accuracy = $totalQuestions > 0 ? round(($correct / $totalQuestions) * 100, 1) : 0;
                                        
                                        $rankBadgeClass = 'badge-light text-dark';
                                        $rankIcon = '';
                                        if ($rank === 1) {
                                            $rankBadgeClass = 'badge-warning text-dark';
                                            $rankIcon = '👑 ';
                                        } elseif ($rank === 2) {
                                            $rankBadgeClass = 'badge-secondary text-white';
                                            $rankIcon = '🥈 ';
                                        } elseif ($rank === 3) {
                                            $rankBadgeClass = 'badge-danger text-white';
                                            $rankIcon = '🥉 ';
                                        }

                                        $progressColor = 'bg-success';
                                        if ($accuracy < 40) {
                                            $progressColor = 'bg-danger';
                                        } elseif ($accuracy < 70) {
                                            $progressColor = 'bg-warning';
                                        }
                                    ?>
                                        <tr>
                                            <td class="text-center">
                                                <span class="badge <?= $rankBadgeClass ?> font-weight-bold" style="font-size: 1.05rem; width: 34px; height: 34px; line-height: 24px; border-radius: 50%;">
                                                    <?= $rank ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="font-weight-bold text-dark" style="font-size: 1.1rem;">
                                                    <?= $rankIcon ?><?= htmlspecialchars($p['nickname']) ?>
                                                </div>
                                                <small class="text-muted">Đã trả lời <?= intval($p['total_answered']) ?> / <?= $totalQuestions ?> câu</small>
                                            </td>
                                            <td class="text-right">
                                                <span class="font-weight-bold text-success" style="font-size: 1.25rem;">
                                                    <?= number_format($p['score']) ?>
                                                </span>
                                                <small class="text-muted font-weight-bold">pts</small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-pill badge-primary px-3 py-2 font-weight-bold" style="font-size: 0.95rem;">
                                                    <?= $correct ?> / <?= $totalQuestions ?> câu
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="font-weight-bold mr-2 text-dark" style="width: 45px;"><?= $accuracy ?>%</span>
                                                    <div class="progress flex-grow-1" style="height: 10px; border-radius: 5px;">
                                                        <div class="progress-bar <?= $progressColor ?>" role="progressbar" style="width: <?= $accuracy ?>%;" aria-valuenow="<?= $accuracy ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PHẦN 3: TAB PHÂN TÍCH TỪNG CÂU HỎI (QUESTION ANALYSIS) -->
        <div class="tab-pane fade" id="questions" role="tabpanel" aria-labelledby="questions-tab">
            <?php if (empty($questionAnalytics)): ?>
                <div class="card border-0 shadow-sm p-5 text-center" style="border-radius: 12px;">
                    <i class="fas fa-question-circle fa-3x text-muted mb-3" style="opacity: 0.5;"></i>
                    <h5 class="text-muted font-weight-bold">Không có câu hỏi nào trong bài trắc nghiệm này!</h5>
                </div>
            <?php else: ?>
                <?php foreach ($questionAnalytics as $qa): 
                    $q = $qa['question'];
                    $qAcc = $qa['accuracy'];
                    
                    $accBadgeColor = 'badge-success';
                    $progressColor = 'bg-success';
                    if ($qAcc < 40) {
                        $accBadgeColor = 'badge-danger';
                        $progressColor = 'bg-danger';
                    } elseif ($qAcc < 70) {
                        $accBadgeColor = 'badge-warning text-dark';
                        $progressColor = 'bg-warning';
                    }
                ?>
                    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
                        <!-- Tiêu đề câu hỏi -->
                        <div class="card-header bg-white py-3 border-bottom">
                            <div class="d-flex flex-wrap justify-content-between align-items-center">
                                <div class="pr-2" style="flex: 1 1 auto;">
                                    <span class="badge badge-dark mr-2 px-2 py-1 font-weight-bold" style="font-size: 0.95rem;">
                                        Câu #<?= $qa['index'] ?>
                                    </span>
                                    <span class="font-weight-bold text-dark" style="font-size: 1.15rem;">
                                        <?= htmlspecialchars($q['text']) ?>
                                    </span>
                                </div>
                                <div class="mt-2 mt-md-0 d-flex align-items-center">
                                    <span class="badge badge-light text-muted border mr-2">
                                        <i class="far fa-clock mr-1"></i><?= $q['time_limit'] ?>s
                                    </span>
                                    <span class="badge badge-light text-muted border mr-2">
                                        <i class="fas fa-award mr-1"></i><?= $q['points'] ?> pts
                                    </span>
                                    <span class="badge <?= $accBadgeColor ?> px-3 py-2 font-weight-bold" style="font-size: 0.95rem;">
                                        Đúng: <?= $qAcc ?>% (<?= $qa['correct_votes'] ?>/<?= max(1, $totalPlayers) ?>)
                                    </span>
                                </div>
                            </div>

                            <!-- Thanh tỷ lệ đúng của lớp -->
                            <div class="progress mt-3" style="height: 6px; border-radius: 3px;">
                                <div class="progress-bar <?= $progressColor ?>" role="progressbar" style="width: <?= $qAcc ?>%;" aria-valuenow="<?= $qAcc ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <!-- Thống kê lựa chọn từng đáp án -->
                        <div class="card-body p-3">
                            <div class="row">
                                <?php foreach ($qa['answers'] as $ans): 
                                    $isCorrect = ($ans['is_correct'] == 1);
                                    $cardBorder = $isCorrect ? 'border: 2px solid #26890c; background-color: #f6fff4;' : 'border: 1px solid #e9ecef; background-color: #fafafa;';
                                ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="p-3 h-100 rounded position-relative shadow-2xs" style="<?= $cardBorder ?> border-radius: 8px;">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="d-flex align-items-center">
                                                    <span class="badge text-white font-weight-bold mr-2" style="background-color: <?= $ans['color'] ?>; font-size: 1rem; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;">
                                                        <?= $ans['symbol'] ?>
                                                    </span>
                                                    <span class="font-weight-bold text-dark" style="font-size: 1.05rem;">
                                                        <?= htmlspecialchars($ans['text']) ?>
                                                    </span>
                                                </div>
                                                <?php if ($isCorrect): ?>
                                                    <span class="badge badge-success px-2 py-1 font-weight-bold text-uppercase" style="font-size: 0.75rem;">
                                                        <i class="fas fa-check mr-1"></i>Đáp án đúng
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                                <span class="text-muted small">
                                                    <b><?= $ans['vote_count'] ?></b> học sinh chọn
                                                </span>
                                                <span class="font-weight-bold <?= $isCorrect ? 'text-success' : 'text-dark' ?>" style="font-size: 1.1rem;">
                                                    <?= $ans['vote_percent'] ?>%
                                                </span>
                                            </div>

                                            <div class="progress mt-1" style="height: 6px; border-radius: 3px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?= $ans['vote_percent'] ?>%; background-color: <?= $ans['color'] ?>;" aria-valuenow="<?= $ans['vote_percent'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
@media print {
    .navbar-kahoot,
    .btn,
    .nav-tabs,
    .alert {
        display: none !important;
    }
    .tab-content > .tab-pane {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        page-break-inside: avoid;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
