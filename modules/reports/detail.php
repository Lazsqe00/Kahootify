<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/TeamManager.php';
require_once __DIR__ . '/../../includes/player_characters.php';

$creatorId = intval($_SESSION['id_creator']);
$roomId    = intval($_GET['id_room'] ?? $_GET['id'] ?? 0);

if ($roomId <= 0) {
    set_flash_message('danger', 'Invalid game room ID!');
    redirect('modules/reports/list.php');
}

// 1. Verify session ownership
$room = db_fetch_one("
    SELECT r.*, q.name as quiz_name, q.description as quiz_description
    FROM room r
    JOIN quiz q ON r.id_quiz = q.id_quiz
    WHERE r.id_room = :r AND r.id_creator = :c
", [':r' => $roomId, ':c' => $creatorId]);

if (!$room) {
    set_flash_message('danger', 'Game report not found or you do not have permission to view it!');
    redirect('modules/quiz/list.php');
}

$isTeamMode = TeamManager::isTeamMode($room);
$teamCount = TeamManager::getTeamCount($room);
$teamLeaderboard = $isTeamMode ? TeamManager::getTeamLeaderboard($roomId) : [];

// 2. Fetch questions played in this session (respecting randomized order if saved)
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

// 3. Fetch player scoreboard
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
$rawPlayers = db_fetch_all($sqlPlayers, [':r' => $roomId]);
$players = function_exists('filter_room_players') ? filter_room_players($roomId, $rawPlayers) : $rawPlayers;
$totalPlayers = count($players);

// 4. Compute KPI summaries
$totalScore = array_sum(array_column($players, 'score'));
$avgScore   = $totalPlayers > 0 ? round($totalScore / $totalPlayers) : 0;

$totalCorrectCount = array_sum(array_column($players, 'correct_count'));
$totalPossibleAnswers = $totalPlayers * $totalQuestions;
$classAccuracy = $totalPossibleAnswers > 0 ? round(($totalCorrectCount / $totalPossibleAnswers) * 100, 1) : 0;

// 5. Compute question breakdown & identify most difficult question
$questionAnalytics = [];
$mostDifficultQuestion = null;
$maxIncorrectRate = -1;

$kahootSymbols = [
    0 => ['symbol' => '▲', 'name' => 'A', 'color' => '#e21b3c'],
    1 => ['symbol' => '◆', 'name' => 'B', 'color' => '#1368ce'],
    2 => ['symbol' => '●', 'name' => 'C', 'color' => '#d89e00'],
    3 => ['symbol' => '■', 'name' => 'D', 'color' => '#26890c']
];

foreach ($questions as $qIndex => $q) {
    $qId = intval($q['id_question']);

    // Fetch all answers for this question
    $answers = db_fetch_all("
        SELECT * FROM answers 
        WHERE id_question = :qid 
        ORDER BY id_answer ASC
    ", [':qid' => $qId]);

    // Fetch student votes for this question in this session
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
        
        $denominator = ($totalPlayers > 0) ? $totalPlayers : max(1, $qTotalVotes);
        $ans['vote_percent'] = round(($voteCnt / $denominator) * 100, 1);

        if ($ans['is_correct'] == 1) {
            $qCorrectVotes += $voteCnt;
        }

        $styleMeta = $kahootSymbols[$ansIdx % 4] ?? ['symbol' => '●', 'name' => chr(65 + $ansIdx), 'color' => '#64748b'];
        $ans['symbol'] = $styleMeta['symbol'];
        $ans['label'] = $styleMeta['name'];
        $ans['color'] = $styleMeta['color'];
    }
    unset($ans);

    // Accuracy and incorrect rate
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

    // Detect most difficult question
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

$pageTitle = "Report PIN: " . htmlspecialchars($room['pin']) . " | Reports";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/reports-analytics.css">

<style>
/* Team Standings in Report */
.report-team-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.report-team-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 18px 22px;
    border: 1px solid var(--saas-slate-200);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    position: relative;
    overflow: hidden;
    transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}
.report-team-card:hover {
    transform: translateY(-2px);
    border-color: var(--team-color, #7c3aed);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
}
.report-team-strip {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: var(--team-color, #7c3aed);
}
.report-team-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.report-team-rank-badge {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.15rem;
    color: #fff;
    background: #64748b;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.report-team-rank-badge.team-rank-1 {
    background: linear-gradient(135deg, #ffd700, #ffb300);
    color: #333;
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.45);
}
.report-team-rank-badge.team-rank-2 {
    background: linear-gradient(135deg, #e0e0e0, #bdbdbd);
    color: #333;
    box-shadow: 0 2px 8px rgba(189, 189, 189, 0.45);
}
.report-team-rank-badge.team-rank-3 {
    background: linear-gradient(135deg, #cd7f32, #b87333);
    color: #fff;
    box-shadow: 0 2px 8px rgba(205, 127, 50, 0.45);
}
.report-team-top3-chip {
    display: inline-flex;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 3px 10px 3px 6px;
    gap: 8px;
    font-size: 0.82rem;
    color: #1e293b;
}
.report-team-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    overflow: hidden;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #e2e8f0;
}
.report-team-avatar svg {
    width: 100%;
    height: 100%;
}

/* Back Button (Clean Human SaaS Style) */
.report-back-btn {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    color: #334155;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 0.85rem;
    font-weight: 700;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    transition: all 0.15s ease-in-out;
}
.report-back-btn:hover {
    background: #f8fafc;
    color: #46178f;
    border-color: #c4b5fd;
    box-shadow: 0 2px 5px rgba(70, 23, 143, 0.12);
    transform: translateX(-2px);
    text-decoration: none;
}

/* Report Tab Navigation - Kahoot Purple Theme */
.tab-btn-clean {
    border: 1.5px solid #ddd6fe !important;
    background-color: #ffffff !important;
    color: #46178f !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    padding: 8px 18px !important;
    font-size: 0.92rem !important;
    transition: all 0.15s ease-in-out !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    outline: none !important;
}

.tab-btn-clean:hover {
    background-color: #f5f3ff !important;
    border-color: #c4b5fd !important;
    color: #46178f !important;
    transform: translateY(-1px);
}

.tab-btn-clean.active {
    background: #46178f !important;
    border-color: #46178f !important;
    color: #ffffff !important;
    box-shadow: 0 3px 8px rgba(70, 23, 143, 0.28) !important;
}

.tab-btn-clean.active .badge {
    background-color: rgba(255, 255, 255, 0.22) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
    font-weight: 700 !important;
}

.tab-btn-clean:not(.active) .badge {
    background-color: #f5f3ff !important;
    color: #46178f !important;
    border: 1px solid #ddd6fe !important;
    font-weight: 700 !important;
}
</style>

<div class="container my-4" style="max-width: 1140px;">

    <!-- 1. Header & Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center flex-wrap mb-2" style="gap: 10px;">
                <a href="<?= BASE_URL ?>modules/reports/list.php" class="report-back-btn text-decoration-none">
                    <i class="fas fa-arrow-left mr-2"></i> Quay lại danh sách
                </a>
                <span class="badge badge-light border font-monospace text-dark font-weight-bold px-2 py-1" style="font-size: 0.85rem; letter-spacing: 1px; border-radius: 6px;">
                    PIN: <?= htmlspecialchars($room['pin']) ?>
                </span>
                <?php if ($isTeamMode): ?>
                    <span class="badge text-white font-weight-bold px-2 py-1 shadow-sm" style="font-size: 0.82rem; background: #46178f; border-radius: 6px;">
                        <i class="fas fa-users mr-1"></i> Chế độ Đội (<?= $teamCount ?> Đội)
                    </span>
                <?php endif; ?>
            </div>

            <h3 class="font-weight-bold text-dark mb-1">
                <?= htmlspecialchars($room['quiz_name']) ?>
            </h3>

            <div class="text-muted small">
                <i class="far fa-calendar-alt mr-1"></i> Ngày chơi: <?= date('d/m/Y', strtotime($room['roomadd'])) ?> lúc <?= date('H:i', strtotime($room['roomadd'])) ?> &bull; <i class="far fa-question-circle mr-1"></i> <?= $totalQuestions ?> câu hỏi
            </div>
        </div>

        <!-- Quick actions -->
        <div class="mt-3 mt-md-0 d-flex align-items-center" style="gap: 8px;">
            <a href="<?= BASE_URL ?>modules/reports/export_csv.php?id_room=<?= $roomId ?>" class="btn btn-success font-weight-bold px-3 py-2" style="border-radius: 8px; font-size: 0.88rem;">
                <i class="fas fa-file-excel mr-1"></i> Xuất file CSV
            </a>
            <button type="button" class="btn btn-outline-secondary font-weight-bold px-3 py-2" onclick="window.print();" style="border-radius: 8px; font-size: 0.88rem;">
                <i class="fas fa-print mr-1"></i> In báo cáo
            </button>
        </div>
    </div>

    <!-- 2. Clean Summary Bar -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; background: #ffffff;">
        <div class="card-body py-3 px-4">
            <div class="row text-center text-md-left">
                <div class="col-6 col-md-3 border-right py-1">
                    <div class="text-muted small font-weight-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">Số người chơi</div>
                    <div class="h4 font-weight-bold text-dark mb-0 mt-1"><?= $totalPlayers ?> <small class="text-muted font-weight-normal" style="font-size: 0.82rem;">người</small></div>
                    <div class="small text-muted" style="font-size: 0.75rem;"><?= $isTeamMode ? (count($teamLeaderboard) . ' đội tham gia') : 'Đã tham gia phòng' ?></div>
                </div>
                <div class="col-6 col-md-3 border-right py-1">
                    <div class="text-muted small font-weight-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">Tỉ lệ trả lời đúng</div>
                    <div class="h4 font-weight-bold text-success mb-0 mt-1"><?= $classAccuracy ?>%</div>
                    <div class="small text-muted" style="font-size: 0.75rem;"><?= $totalCorrectCount ?>/<?= max(1, $totalPossibleAnswers) ?> câu đúng</div>
                </div>
                <div class="col-6 col-md-3 border-right py-1 mt-3 mt-md-0">
                    <?php if ($isTeamMode && !empty($teamLeaderboard)): 
                        $champ = $teamLeaderboard[0];
                    ?>
                        <div class="text-muted small font-weight-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">Đội Vô Địch</div>
                        <div class="h5 font-weight-bold mb-0 mt-1 text-truncate" style="color: <?= htmlspecialchars($champ['color']) ?>;">
                            <i class="<?= htmlspecialchars($champ['icon']) ?> mr-1"></i> <?= htmlspecialchars($champ['name']) ?>
                        </div>
                        <div class="small text-muted" style="font-size: 0.75rem;"><?= number_format($champ['total_score']) ?> điểm</div>
                    <?php else: ?>
                        <div class="text-muted small font-weight-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">Điểm trung bình</div>
                        <div class="h4 font-weight-bold text-dark mb-0 mt-1"><?= number_format($avgScore) ?> <small class="text-muted font-weight-normal" style="font-size: 0.82rem;">điểm</small></div>
                        <div class="small text-muted" style="font-size: 0.75rem;">Trung bình mỗi học sinh</div>
                    <?php endif; ?>
                </div>
                <div class="col-6 col-md-3 py-1 mt-3 mt-md-0">
                    <div class="text-muted small font-weight-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">Câu hỏi khó nhất</div>
                    <?php if ($mostDifficultQuestion && $totalPlayers > 0): ?>
                        <div class="h5 font-weight-bold text-danger mb-0 mt-1">Câu #<?= $mostDifficultQuestion['number'] ?> (sai <?= $mostDifficultQuestion['incorrect_rate'] ?>%)</div>
                        <div class="small text-muted text-truncate" style="font-size: 0.75rem; max-width: 180px;" title="<?= htmlspecialchars($mostDifficultQuestion['text']) ?>">
                            <?= htmlspecialchars($mostDifficultQuestion['text']) ?>
                        </div>
                    <?php else: ?>
                        <div class="h5 font-weight-bold text-muted mb-0 mt-1">Không có</div>
                        <div class="small text-muted" style="font-size: 0.75rem;">Tất cả câu trả lời đều tốt</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Clean Tab Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex flex-wrap" style="gap: 8px;">
            <?php if ($isTeamMode): ?>
            <button class="btn btn-sm tab-btn-clean active" id="tabBtnTeams" type="button" onclick="switchReportTab('teams')">
                <i class="fas fa-users mr-1"></i> Bảng xếp hạng Đội <span class="badge ml-1"><?= count($teamLeaderboard) ?></span>
            </button>
            <?php endif; ?>
            <button class="btn btn-sm tab-btn-clean <?= $isTeamMode ? '' : 'active' ?>" id="tabBtnPlayers" type="button" onclick="switchReportTab('players')">
                <i class="fas fa-trophy mr-1"></i> Bảng xếp hạng cá nhân <span class="badge ml-1"><?= $totalPlayers ?></span>
            </button>
            <button class="btn btn-sm tab-btn-clean" id="tabBtnQuestions" type="button" onclick="switchReportTab('questions')">
                <i class="fas fa-list mr-1"></i> Phân tích câu hỏi <span class="badge ml-1"><?= $totalQuestions ?></span>
            </button>
        </div>
    </div>

    <?php if ($isTeamMode): ?>
    <!-- 4. TAB 0: TEAM LEADERBOARD (WHEN TEAM MODE IS ACTIVE) -->
    <div id="tabContentTeams" class="tab-pane-content" style="display: block;">
        <div class="saas-table-card mb-4">
            <div class="saas-table-header d-flex justify-content-between align-items-center">
                <h5 class="saas-table-title mb-0">
                    <i class="fas fa-users" style="color: #7c3aed; font-size: 14px;"></i>
                    <span>Team Standings & Leaderboard</span>
                </h5>
                <span class="saas-badge saas-badge-purple font-weight-bold">
                    <?= count($teamLeaderboard) ?> Teams
                </span>
            </div>

            <?php if (empty($teamLeaderboard)): ?>
                <div class="text-center py-5 px-3">
                    <div class="saas-kpi-icon mx-auto mb-3" style="width: 44px; height: 44px; font-size: 18px; background: #eff6ff; color: #3b82f6; border: 1px dashed #bfdbfe;">
                        <i class="fas fa-users-slash"></i>
                    </div>
                    <h6 class="font-weight-bold" style="color: #0f172a; margin-bottom: 4px;">No team data recorded</h6>
                    <p class="text-muted mx-auto" style="max-width: 380px; font-size: 13px;">
                        No team points or answers were recorded in this game session.
                    </p>
                </div>
            <?php else: ?>
                <div class="p-3 p-md-4">
                    <div class="report-team-grid">
                        <?php foreach ($teamLeaderboard as $tRankIdx => $teamItem):
                            $tRank = $teamItem['rank'] ?? ($tRankIdx + 1);
                            $rankClass = ($tRank === 1) ? 'team-rank-1' : (($tRank === 2) ? 'team-rank-2' : (($tRank === 3) ? 'team-rank-3' : 'team-rank-other'));
                        ?>
                            <div class="report-team-card" style="--team-color: <?= htmlspecialchars($teamItem['color']) ?>;">
                                <div class="report-team-strip"></div>
                                <div class="report-team-header">
                                    <div class="d-flex align-items-center" style="gap: 12px;">
                                        <div class="report-team-rank-badge <?= $rankClass ?>">
                                            <?= $tRank ?>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                                <h4 class="font-weight-bold mb-0" style="font-size: 1.15rem; color: #0f172a;">
                                                    <i class="<?= htmlspecialchars($teamItem['icon']) ?> mr-1" style="color: <?= htmlspecialchars($teamItem['color']) ?>;"></i>
                                                    <?= htmlspecialchars($teamItem['name']) ?>
                                                </h4>
                                                <?php if ($tRank === 1): ?>
                                                    <span class="badge badge-warning text-dark font-weight-bold px-2 py-1" style="font-size: 0.72rem;">
                                                        <i class="fas fa-trophy mr-1"></i> Champion
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted font-weight-medium mt-1">
                                                <span class="mr-2"><i class="fas fa-user-friends mr-1 text-primary"></i> <?= $teamItem['member_count'] ?> members</span>
                                                <span><i class="fas fa-check-circle mr-1 text-success"></i> <?= (int)$teamItem['total_correct'] ?> correct</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center" style="gap: 14px;">
                                        <div class="text-right">
                                            <div class="font-weight-bold tabular-nums" style="font-size: 1.45rem; color: #1e293b; line-height: 1.1;">
                                                <?= number_format($teamItem['total_score']) ?>
                                            </div>
                                            <small class="text-muted font-weight-bold text-uppercase" style="font-size: 0.72rem;">pts</small>
                                        </div>
                                        <button type="button" class="saas-btn saas-btn-outline" style="font-size: 12px; padding: 6px 12px;" onclick="openTeamReportModal(<?= (int)$teamItem['id'] ?>)">
                                            <span>Details</span>
                                            <i class="fas fa-chevron-right ml-1" style="font-size: 10px;"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- TOP 3 PLAYERS IN TEAM -->
                                <div class="report-team-top3 pt-3 mt-2 border-top">
                                    <div class="small font-weight-bold text-muted mb-2 text-uppercase d-flex align-items-center" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                        <i class="fas fa-medal text-warning mr-1"></i> Top 3 team members:
                                    </div>
                                    <?php if (!empty($teamItem['top_3_players'])): ?>
                                        <div class="d-flex flex-wrap" style="gap: 8px;">
                                            <?php foreach ($teamItem['top_3_players'] as $t3Idx => $topP): 
                                                $pApp = get_player_appearance($room, (int)$topP['id_player']);
                                            ?>
                                                <div class="report-team-top3-chip shadow-sm">
                                                    <span class="badge badge-warning text-dark font-weight-bold" style="font-size: 0.68rem; border-radius: 50%; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                        <?= $t3Idx + 1 ?>
                                                    </span>
                                                    <span class="report-team-avatar">
                                                        <?= player_appearance_image($pApp, 'report-mini-avatar') ?>
                                                    </span>
                                                    <span class="font-weight-bold text-dark text-truncate" style="max-width: 120px;" title="<?= htmlspecialchars($topP['nickname']) ?>">
                                                        <?= htmlspecialchars($topP['nickname']) ?>
                                                    </span>
                                                    <span class="badge badge-light border text-primary font-weight-bold tabular-nums">
                                                        <?= number_format($topP['score']) ?> pts
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small font-italic">No players in this team yet.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 5. TAB 1: PLAYERS LEADERBOARD & PAGINATION -->
    <div id="tabContentPlayers" class="tab-pane-content" style="<?= $isTeamMode ? 'display: none;' : 'display: block;' ?>">
        <div class="saas-table-card">
            <div class="saas-table-header">
                <h5 class="saas-table-title">
                    <i class="fas fa-medal" style="color: #f59e0b; font-size: 14px;"></i>
                    <span>Student Final Standings</span>
                </h5>
                <a href="<?= BASE_URL ?>modules/reports/export_csv.php?id_room=<?= $roomId ?>" class="saas-btn saas-btn-outline" style="font-size: 12px; padding: 4px 10px;">
                    <i class="fas fa-file-excel" style="color: #10b981;"></i>
                    <span>Export CSV</span>
                </a>
            </div>

            <?php if (empty($players)): ?>
                <div class="text-center py-5 px-3">
                    <div class="saas-kpi-icon mx-auto mb-3" style="width: 44px; height: 44px; font-size: 18px; background: #eff6ff; color: #3b82f6; border: 1px dashed #bfdbfe;">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <h6 class="font-weight-bold" style="color: #0f172a; margin-bottom: 4px;">No players recorded</h6>
                    <p class="text-muted mx-auto" style="max-width: 380px; font-size: 13px;">
                        No student participants joined or answered questions in this game session.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="saas-table table-hover" id="playersTable">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 70px;">Rank</th>
                                <th class="text-left">Student Nickname</th>
                                <?php if ($isTeamMode): ?>
                                    <th class="text-left" style="width: 170px;">Team</th>
                                <?php endif; ?>
                                <th class="text-right" style="width: 150px;">Total Score</th>
                                <th class="text-right" style="width: 150px;">Correct Answers</th>
                                <th class="text-left" style="width: 260px;">Accuracy Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $idx => $p): 
                                $rank = $idx + 1;
                                $correct = intval($p['correct_count']);
                                $accuracy = $totalQuestions > 0 ? round(($correct / $totalQuestions) * 100, 1) : 0;
                                
                                $rankClass = 'saas-rank-other';
                                $rankPrefix = '';
                                if ($rank === 1) {
                                    $rankClass = 'saas-rank-1';
                                    $rankPrefix = '<i class="fas fa-crown mr-1" style="color: #ca8a04; font-size: 10px;"></i>';
                                } elseif ($rank === 2) {
                                    $rankClass = 'saas-rank-2';
                                    $rankPrefix = '<i class="fas fa-medal mr-1" style="color: #64748b; font-size: 10px;"></i>';
                                } elseif ($rank === 3) {
                                    $rankClass = 'saas-rank-3';
                                    $rankPrefix = '<i class="fas fa-award mr-1" style="color: #ea580c; font-size: 10px;"></i>';
                                }

                                $progressClass = 'saas-progress-emerald';
                                if ($accuracy < 40) {
                                    $progressClass = 'saas-progress-rose';
                                } elseif ($accuracy < 70) {
                                    $progressClass = 'saas-progress-amber';
                                }
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="saas-rank-chip <?= $rankClass ?>">
                                            <?= $rankPrefix ?><?= $rank ?>
                                        </span>
                                    </td>
                                    <td class="text-left">
                                        <div class="font-weight-medium" style="color: #0f172a; font-size: 14px;">
                                            <?= htmlspecialchars($p['nickname']) ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: #94a3b8; margin-top: 1px;">
                                            Submitted <?= intval($p['total_answered']) ?> / <?= $totalQuestions ?> questions
                                        </div>
                                    </td>
                                    <?php if ($isTeamMode): ?>
                                    <td class="text-left">
                                        <?= TeamManager::renderTeamBadge(intval($p['team_id'] ?? 0), $p['team_name'] ?? null) ?>
                                    </td>
                                    <?php endif; ?>
                                    <td class="text-right">
                                        <span class="saas-numeric font-weight-bold" style="color: #0f172a; font-size: 14px;">
                                            <?= number_format($p['score']) ?>
                                        </span>
                                        <span style="font-size: 11.5px; color: #94a3b8;">pts</span>
                                    </td>
                                    <td class="text-right">
                                        <span class="saas-numeric font-weight-medium" style="color: #1e293b; font-size: 13.5px;">
                                            <?= $correct ?>
                                        </span>
                                        <span style="font-size: 11.5px; color: #64748b;">/ <?= $totalQuestions ?> correct</span>
                                    </td>
                                    <td class="text-left">
                                        <div class="d-flex align-items-center" style="gap: 12px;">
                                            <div class="saas-progress-track flex-grow-1">
                                                <div class="saas-progress-bar <?= $progressClass ?>" style="width: <?= $accuracy ?>%;"></div>
                                            </div>
                                            <span class="saas-numeric font-weight-bold" style="font-size: 12.5px; color: #334155; width: 44px; text-align: right;">
                                                <?= $accuracy ?>%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Integrated Pagination Footer -->
                <div id="playersPagination" class="saas-pagination-bar"></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 6. TAB 2: QUESTION ANALYSIS -->
    <div id="tabContentQuestions" class="tab-pane-content" style="display: none;">
        <?php if (empty($questionAnalytics)): ?>
            <div class="saas-table-card text-center py-5 px-3">
                <div class="saas-kpi-icon mx-auto mb-3" style="width: 44px; height: 44px; font-size: 18px; background: #f5f3ff; color: #7c3aed;">
                    <i class="far fa-question-circle"></i>
                </div>
                <h6 class="font-weight-bold" style="color: #0f172a;">No questions found</h6>
                <p class="text-muted" style="font-size: 13px;">This quiz contains no questions.</p>
            </div>
        <?php else: ?>
            <?php foreach ($questionAnalytics as $qa): 
                $q = $qa['question'];
                $qAcc = $qa['accuracy'];
                
                $accBadge = 'saas-badge-emerald';
                $accProgress = 'saas-progress-emerald';
                if ($qAcc < 40) {
                    $accBadge = 'saas-badge-rose';
                    $accProgress = 'saas-progress-rose';
                } elseif ($qAcc < 70) {
                    $accBadge = 'saas-badge-amber';
                    $accProgress = 'saas-progress-amber';
                }
            ?>
                <div class="saas-question-card">
                    <div class="saas-question-header">
                        <div class="d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
                            <div class="d-flex align-items-center" style="gap: 10px; flex: 1 1 500px;">
                                <span class="saas-badge saas-badge-slate font-mono font-weight-bold" style="font-size: 11px;">
                                    Question #<?= $qa['index'] ?>
                                </span>
                                <h6 class="saas-question-title mb-0">
                                    <?= htmlspecialchars($q['text']) ?>
                                </h6>
                            </div>

                            <div class="d-flex align-items-center" style="gap: 6px;">
                                <span class="saas-badge saas-badge-slate" style="font-size: 11.5px;">
                                    <i class="far fa-clock mr-1" style="color: #3b82f6;"></i><?= $q['time_limit'] ?>s
                                </span>
                                <span class="saas-badge saas-badge-slate" style="font-size: 11.5px;">
                                    <i class="fas fa-award mr-1" style="color: #7c3aed;"></i><?= $q['points'] ?> pts
                                </span>
                                <span class="saas-badge <?= $accBadge ?> tabular-nums font-weight-bold" style="font-size: 12px;">
                                    Accuracy: <?= $qAcc ?>% (<?= $qa['correct_votes'] ?>/<?= max(1, $totalPlayers) ?>)
                                </span>
                            </div>
                        </div>

                        <!-- 6px Sleek Accuracy Bar -->
                        <div class="saas-progress-track mt-3">
                            <div class="saas-progress-bar <?= $accProgress ?>" style="width: <?= $qAcc ?>%;"></div>
                        </div>
                    </div>

                    <div class="p-3" style="background-color: #fafbfd;">
                        <div class="row">
                            <?php foreach ($qa['answers'] as $ans): 
                                $isCorrect = ($ans['is_correct'] == 1);
                            ?>
                                <div class="col-md-6 mb-3">
                                    <div class="saas-answer-item <?= $isCorrect ? 'correct' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2" style="gap: 8px;">
                                            <div class="d-flex align-items-center" style="gap: 8px;">
                                                <span class="saas-answer-symbol" style="background-color: <?= $ans['color'] ?>;">
                                                    <?= $ans['symbol'] ?>
                                                </span>
                                                <span class="font-weight-medium" style="color: #0f172a; font-size: 13.5px;">
                                                    <?= htmlspecialchars($ans['text']) ?>
                                                </span>
                                            </div>
                                            <?php if ($isCorrect): ?>
                                                <span class="saas-badge saas-badge-emerald" style="font-size: 11px; padding: 2px 6px;">
                                                    <i class="fas fa-check mr-1" style="font-size: 10px;"></i> Correct Answer
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top" style="border-color: #f1f5f9 !important;">
                                            <span style="font-size: 12px; color: #64748b;">
                                                <b class="tabular-nums font-weight-semibold" style="color: #1e293b;"><?= $ans['vote_count'] ?></b> <?= $ans['vote_count'] === 1 ? 'student selected' : 'students selected' ?>
                                            </span>
                                            <span class="saas-numeric font-weight-bold <?= $isCorrect ? 'text-success' : '' ?>" style="font-size: 13px; color: <?= $isCorrect ? '#047857' : '#0f172a' ?>;">
                                                <?= $ans['vote_percent'] ?>%
                                            </span>
                                        </div>

                                        <div class="saas-progress-track mt-2">
                                            <div class="saas-progress-bar" style="width: <?= $ans['vote_percent'] ?>%; background-color: <?= $ans['color'] ?>;"></div>
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

<!-- MODAL: TEAM DETAIL ROSTER -->
<div class="modal fade" id="teamReportModal" tabindex="-1" role="dialog" aria-labelledby="teamReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header text-white" id="teamReportModalHeader" style="background: linear-gradient(135deg, #1368ce, #46178f);">
                <div class="d-flex align-items-center" style="gap: 12px;">
                    <div id="teamReportModalIcon" style="font-size: 1.8rem;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" id="teamReportModalLabel">Team Detail</h5>
                        <small id="teamReportModalSubtitle" class="text-white-50">Team standings and member roster</small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="d-flex justify-content-around py-3 px-4 bg-light border-bottom text-center">
                    <div>
                        <div class="small text-muted font-weight-bold text-uppercase">Members</div>
                        <div class="font-weight-bold h5 mb-0" id="teamReportMemberCount">0</div>
                    </div>
                    <div class="border-left"></div>
                    <div>
                        <div class="small text-muted font-weight-bold text-uppercase">Total Score</div>
                        <div class="font-weight-bold h5 mb-0 text-primary tabular-nums" id="teamReportTotalScore">0 pts</div>
                    </div>
                    <div class="border-left"></div>
                    <div>
                        <div class="small text-muted font-weight-bold text-uppercase">Total Correct</div>
                        <div class="font-weight-bold h5 mb-0 text-success tabular-nums" id="teamReportTotalCorrect">0</div>
                    </div>
                </div>
                <div class="table-responsive p-3" style="max-height: 400px; overflow-y: auto;">
                    <table class="saas-table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 70px;">Rank</th>
                                <th class="text-left">Student</th>
                                <th class="text-right" style="width: 130px;">Score</th>
                                <th class="text-right" style="width: 140px;">Correct</th>
                                <th class="text-right" style="width: 120px;">Accuracy</th>
                            </tr>
                        </thead>
                        <tbody id="teamReportTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="saas-btn saas-btn-outline" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/reports-pagination.js"></script>
<script>
const REPORT_TEAMS = <?= json_encode($teamLeaderboard) ?>;
const TOTAL_QUESTIONS = <?= intval($totalQuestions) ?>;

function switchReportTab(tabName) {
    const tabs = ['teams', 'players', 'questions'];
    tabs.forEach(t => {
        const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
        const pane = document.getElementById('tabContent' + t.charAt(0).toUpperCase() + t.slice(1));
        if (btn) {
            if (t === tabName) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        }
        if (pane) {
            pane.style.display = (t === tabName) ? 'block' : 'none';
        }
    });
}

function openTeamReportModal(teamId) {
    const team = REPORT_TEAMS.find(t => Number(t.id) === Number(teamId));
    if (!team) return;

    const modalHeader = document.getElementById('teamReportModalHeader');
    if (modalHeader) {
        modalHeader.style.background = team.bg_gradient || team.color || '#1368ce';
    }

    const title = document.getElementById('teamReportModalLabel');
    if (title) title.textContent = team.name;

    const icon = document.getElementById('teamReportModalIcon');
    if (icon) icon.innerHTML = '<i class="' + (team.icon || 'fas fa-users') + '"></i>';

    const sub = document.getElementById('teamReportModalSubtitle');
    if (sub) sub.textContent = 'Rank ' + (team.rank || '-') + ' overall • ' + (team.member_count || 0) + ' members';

    const countEl = document.getElementById('teamReportMemberCount');
    if (countEl) countEl.textContent = team.member_count + ' members';

    const scoreEl = document.getElementById('teamReportTotalScore');
    if (scoreEl) scoreEl.textContent = Number(team.total_score).toLocaleString() + ' pts';

    const corrEl = document.getElementById('teamReportTotalCorrect');
    if (corrEl) corrEl.textContent = Number(team.total_correct || 0) + ' correct';

    const tbody = document.getElementById('teamReportTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!team.members || team.members.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted font-weight-bold">No players in this team yet.</td></tr>';
    } else {
        team.members.forEach((m, idx) => {
            const tr = document.createElement('tr');
            const rankNum = idx + 1;
            let rankBadge = '<span class="saas-rank-chip saas-rank-other">' + rankNum + '</span>';
            if (rankNum === 1) rankBadge = '<span class="saas-rank-chip saas-rank-1"><i class="fas fa-crown mr-1" style="color: #ca8a04; font-size: 10px;"></i>1</span>';
            else if (rankNum === 2) rankBadge = '<span class="saas-rank-chip saas-rank-2"><i class="fas fa-medal mr-1" style="color: #64748b; font-size: 10px;"></i>2</span>';
            else if (rankNum === 3) rankBadge = '<span class="saas-rank-chip saas-rank-3"><i class="fas fa-award mr-1" style="color: #ea580c; font-size: 10px;"></i>3</span>';

            const correct = Number(m.correct_count || m.answers_correct || 0);
            const accuracy = TOTAL_QUESTIONS > 0 ? Math.round((correct / TOTAL_QUESTIONS) * 1000) / 10 : 0;

            tr.innerHTML = `
                <td class="text-center">${rankBadge}</td>
                <td class="text-left font-weight-medium" style="color: #0f172a;">${escapeHtml(m.nickname || 'Unknown')}</td>
                <td class="text-right font-weight-bold tabular-nums" style="color: #0f172a;">${Number(m.score || 0).toLocaleString()} <span style="font-size: 11px; color: #94a3b8;">pts</span></td>
                <td class="text-right tabular-nums">${correct} / ${TOTAL_QUESTIONS}</td>
                <td class="text-right font-weight-bold tabular-nums" style="color: #334155;">${accuracy}%</td>
            `;
            tbody.appendChild(tr);
        });
    }

    if (window.jQuery && jQuery('#teamReportModal').modal) {
        jQuery('#teamReportModal').modal('show');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('playersTable') && document.getElementById('playersPagination')) {
        new SaasTablePagination({
            table: '#playersTable',
            container: '#playersPagination',
            itemNoun: 'players',
            pageSize: 10,
            allowedPageSizes: [10, 25, 50]
        });
    }
});
</script>

<style>
@media print {
    .navbar-kahoot,
    .saas-btn,
    .saas-tabs-container,
    .saas-pagination-bar,
    .saas-action-dropdown,
    .alert {
        display: none !important;
    }
    .tab-pane-content {
        display: block !important;
    }
    .saas-kpi-card,
    .saas-table-card,
    .saas-question-card {
        box-shadow: none !important;
        border: 1px solid #cbd5e1 !important;
        page-break-inside: avoid;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
