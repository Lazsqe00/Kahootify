<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';
require_once __DIR__ . '/../../includes/modes/GameStateManager.php';
require_once __DIR__ . '/../../includes/modes/WagerCalculator.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$playerId = intval($data['id_player'] ?? 0);
$answerId = intval($data['id_answer'] ?? 0);
$roomId   = intval($data['id_room'] ?? 0);

if ($playerId <= 0 || $answerId <= 0 || $roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

if (!player_request_is_valid($playerId, $roomId, $data['token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid player session']);
    exit;
}
$room = db_fetch_one('SELECT * FROM room WHERE id_room = :r', [':r' => $roomId]);
if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Room does not exist']);
    exit;
}

$playerCheck = db_fetch_one(
    "SELECT id_player, current_streak, status, score, eliminated_at_question, elimination_order, is_disqualified_obstacle 
     FROM players WHERE id_player = :p AND id_room = :r",
    [':p' => $playerId, ':r' => $roomId]
);
if (!$playerCheck) {
    echo json_encode(['success' => false, 'message' => 'Player not found in this room']);
    exit;
}
$currentStreak = intval($playerCheck['current_streak'] ?? 0);

try {
    $result = with_room_access_lock($room, function ($handle) use ($room, $playerId, $answerId, $roomId, $currentStreak, $playerCheck) {
        $state = read_room_access_state($handle);
        if (!empty($state['kicked'][$playerId])) {
            return ['success' => false, 'kicked' => true, 'message' => 'You were removed from the game by the host.'];
        }

        $ans = db_fetch_one(
            "SELECT a.*, q.points, q.time_limit, q.mode, q.mode_config, q.piece_index, r.question_start_time, r.status, r.game_type
             FROM answers a
             JOIN questions q ON a.id_question = q.id_question
             JOIN room r ON r.id_room = :r
             WHERE a.id_answer = :aid",
            [':r' => $roomId, ':aid' => $answerId]
        );

        if (!$ans) {
            return ['success' => false, 'message' => 'Invalid answer or room'];
        }

        $qId = intval($ans['id_question']);
        $modeId = $ans['mode'] ?? 'normal';
        $mode = QuestionModeRegistry::get($modeId);

        // Check if player has permission to participate in this question
        $context = ['phase' => 'question'];
        $wagerLog = null;
        if ($modeId === 'wager') {
            $wagerLog = db_fetch_one(
                "SELECT * FROM player_question_logs 
                 WHERE id_room = :r AND id_question = :q AND id_player = :p",
                [':r' => $roomId, ':q' => $qId, ':p' => $playerId]
            );
            $hasWagered = ($wagerLog && !empty($wagerLog['wager_tier']) && empty($wagerLog['is_sitting_out']));
            $context['has_wagered'] = $hasWagered;
        }

        if (!$mode->canParticipate($playerCheck, $room, $ans, $context)) {
            return [
                'success' => false,
                'message' => 'Bạn không thể tham gia trả lời câu hỏi này (do bị loại, là khán giả hoặc không đặt cược).'
            ];
        }

        $alreadyVoted = db_fetch_one(
            "SELECT v.id_vote FROM votes v JOIN answers a ON v.id_answer = a.id_answer WHERE v.id_player = :p AND a.id_question = :q LIMIT 1",
            [':p' => $playerId, ':q' => $qId]
        );

        if ($alreadyVoted) {
            return ['success' => false, 'message' => 'You have already answered this question'];
        }

        $isCorrect = ($ans['is_correct'] == 1);
        $timeLimit  = intval($ans['time_limit'] ?: 20);
        $startTime  = intval($ans['question_start_time'] ?: time());
        $elapsed    = max(0, time() - $startTime);

        $pointsEarned = 0;
        $streakBonus  = 0;
        $newStreak    = 0;
        $streakLost   = false;
        $wagerAmount  = 0;
        $scoreDelta   = 0;

        if ($modeId === 'wager') {
            $tierId = (string)($wagerLog['wager_tier'] ?? 'tier_10');
            $tierPercent = WagerCalculator::getTierPercent($tierId) ?? 0.10;
            $qConfig = !empty($ans['mode_config']) ? json_decode($ans['mode_config'], true) : [];
            if (!is_array($qConfig)) $qConfig = [];

            $speedBonus = 0;
            if ($isCorrect && !empty($qConfig['speedBonusEnabled'])) {
                $ratio = min(1.0, $elapsed / max(1, $timeLimit));
                $speedBonus = intval(round(500 * (1.0 - ($ratio / 2.0))));
            }

            $scoreResult = $mode->scoreQuestion([
                'currentScore' => intval($playerCheck['score']),
                'tierPercent'  => $tierPercent,
                'isCorrect'    => $isCorrect,
                'speedBonus'   => $speedBonus,
                'config'       => $qConfig,
            ]);

            $wagerAmount = $scoreResult['wagerAmount'];
            $scoreDelta  = $scoreResult['delta'];
            $newStreak   = $isCorrect ? ($currentStreak + 1) : 0;
            $streakLost  = (!$isCorrect && $currentStreak >= 2);
            $pointsEarned = $isCorrect ? $wagerAmount : 0;
            $streakBonus  = $speedBonus;

            $after = $mode->afterScoring($playerCheck, $scoreResult, $room, $ans);

            // Record vote
            db_insert(
                "INSERT INTO votes (id_player, id_answer, points_earned, streak_bonus) VALUES (:p, :a, :pts, :bonus)",
                [
                    ':p'     => $playerId,
                    ':a'     => $answerId,
                    ':pts'   => $isCorrect ? $scoreDelta : 0,
                    ':bonus' => $speedBonus,
                ]
            );

            // Update player
            db_execute(
                "UPDATE players 
                 SET score = :ns, status = :st, eliminated_at_question = :eq, elimination_order = :eo, 
                     answers_correct = answers_correct + :ac, current_streak = :cs 
                 WHERE id_player = :p",
                [
                    ':ns' => $after['new_score'],
                    ':st' => $after['status'],
                    ':eq' => $after['eliminated_at_question'],
                    ':eo' => $after['elimination_order'],
                    ':ac' => $isCorrect ? 1 : 0,
                    ':cs' => $newStreak,
                    ':p'  => $playerId,
                ]
            );

            // Update player_question_logs
            if ($wagerLog) {
                db_execute(
                    "UPDATE player_question_logs 
                     SET delta_score = :ds, points_change = :ds, final_score = :fs, is_correct = :ic, player_status = :ps 
                     WHERE id_log = :id",
                    [
                        ':ds' => $scoreDelta,
                        ':fs' => $after['new_score'],
                        ':ic' => $isCorrect ? 1 : 0,
                        ':ps' => $after['status'],
                        ':id' => $wagerLog['id_log'],
                    ]
                );
            }

        } else {
            // Normal mode
            $scoreResult = $mode->scoreQuestion([
                'current_score'  => intval($playerCheck['score']),
                'is_correct'     => $isCorrect,
                'points'         => intval($ans['points'] ?: 1000),
                'time_limit'     => $timeLimit,
                'time_elapsed'   => $elapsed,
                'current_streak' => $currentStreak,
            ]);

            $pointsEarned = $scoreResult['points_earned'];
            $streakBonus  = $scoreResult['streak_bonus'];
            $totalPts     = $scoreResult['total_delta'];
            $newStreak    = $scoreResult['new_streak'];
            $streakLost   = (!$isCorrect && $currentStreak >= 2);

            $after = $mode->afterScoring($playerCheck, $scoreResult, $room, $ans);

            db_insert(
                "INSERT INTO votes (id_player, id_answer, points_earned, streak_bonus) VALUES (:p, :a, :pts, :bonus)",
                [
                    ':p'     => $playerId,
                    ':a'     => $answerId,
                    ':pts'   => $pointsEarned,
                    ':bonus' => $streakBonus,
                ]
            );

            db_execute(
                "UPDATE players SET score = :ns, answers_correct = answers_correct + :ac, current_streak = :newStreak WHERE id_player = :p",
                [
                    ':ns'        => $after['new_score'],
                    ':ac'        => $isCorrect ? 1 : 0,
                    ':newStreak' => $newStreak,
                    ':p'         => $playerId,
                ]
            );

            // Obstacle puzzle: unlock piece for this player
            $unlockedPiece = null;
            if ($isCorrect && (($ans['game_type'] ?? 'classic') === 'obstacle')) {
                $pieceIdx = intval($ans['piece_index'] ?? 0);
                if ($pieceIdx > 0) {
                    $pRow = db_fetch_one("SELECT unlocked_pieces FROM players WHERE id_player = :p", [':p' => $playerId]);
                    $myUnlocked = !empty($pRow['unlocked_pieces']) ? json_decode($pRow['unlocked_pieces'], true) : [];
                    if (!is_array($myUnlocked)) $myUnlocked = [];
                    if (!in_array($pieceIdx, $myUnlocked)) {
                        $myUnlocked[] = $pieceIdx;
                        sort($myUnlocked);
                        db_execute("UPDATE players SET unlocked_pieces = :up WHERE id_player = :p", [
                            ':up' => json_encode($myUnlocked),
                            ':p'  => $playerId
                        ]);
                    }
                    $unlockedPiece = $pieceIdx;
                }
            }

            // Record in player_question_logs for analytics & report
            $existingLog = db_fetch_one(
                "SELECT id_log FROM player_question_logs WHERE id_room = :r AND id_question = :q AND id_player = :p",
                [':r' => $roomId, ':q' => $qId, ':p' => $playerId]
            );
            if ($existingLog) {
                db_execute(
                    "UPDATE player_question_logs SET delta_score = :ds, points_change = :ds, final_score = :fs, is_correct = :ic WHERE id_log = :id",
                    [':ds' => $totalPts, ':fs' => $after['new_score'], ':ic' => $isCorrect ? 1 : 0, ':id' => $existingLog['id_log']]
                );
            } else {
                db_insert(
                    "INSERT INTO player_question_logs (id_room, id_question, id_player, mode, wager_amount, delta_score, points_change, final_score, is_correct, player_status, is_sitting_out) 
                     VALUES (:r, :q, :p, 'normal', 0, :ds, :ds, :fs, :ic, :st, 0)",
                    [
                        ':r'  => $roomId,
                        ':q'  => $qId,
                        ':p'  => $playerId,
                        ':ds' => $totalPts,
                        ':fs' => $after['new_score'],
                        ':ic' => $isCorrect ? 1 : 0,
                        ':st' => $playerCheck['status'] ?? 'ACTIVE',
                    ]
                );
            }
        }

        // Check if all participants have answered -> auto-finalize question phase
        $counts = room_question_counts($room, $qId, $state);
        $totalPlayers = $counts['total_players'];
        $votedCount   = $counts['voted_count'];

        if ($totalPlayers > 0 && $votedCount >= $totalPlayers) {
            GameStateManager::finalizeQuestionPhase($roomId, $qId);
        }

        return [
            'success'        => true,
            'is_correct'     => $isCorrect,
            'points_earned'  => $pointsEarned,
            'streak'         => $newStreak,
            'streak_bonus'   => $streakBonus,
            'streak_lost'    => $streakLost,
            'mode'           => $modeId,
            'wager_amount'   => $wagerAmount,
            'score_delta'    => $scoreDelta,
            'is_eliminated'  => !empty($after['eliminated']),
            'unlocked_piece' => $unlockedPiece ?? null
        ];
    });

    echo json_encode($result);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to submit your answer. Please try again.']);
}
