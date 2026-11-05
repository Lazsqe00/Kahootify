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
$qIdInput = intval($data['id_question'] ?? 0);

if ($playerId <= 0 || ($answerId <= 0 && $qIdInput <= 0) || $roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

if ($answerId <= 0 && $qIdInput > 0) {
    $firstAns = db_fetch_one("SELECT id_answer FROM answers WHERE id_question = :qid ORDER BY is_correct DESC LIMIT 1", [':qid' => $qIdInput]);
    if ($firstAns) {
        $answerId = intval($firstAns['id_answer']);
    }
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
    "SELECT id_player, current_streak, status, score, eliminated_at_question, elimination_order, is_disqualified_obstacle, question_start_time 
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
            "SELECT a.*, q.points, q.time_limit, q.mode, q.mode_config, q.piece_index, q.question_type, r.question_start_time, r.status, r.game_type
             FROM answers a
             JOIN questions q ON a.id_question = q.id_question
             JOIN room r ON r.id_room = :r
             WHERE a.id_answer = :aid",
            [':r' => $roomId, ':aid' => $answerId]
        );

        if (!$ans) {
            return ['success' => false, 'message' => 'Invalid answer or room'];
        }

        if ($ans['status'] === 'finished') {
            return ['success' => false, 'message' => 'This game has ended and is closed for submissions!'];
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
                'message' => 'You cannot participate in this question (eliminated, spectator, or did not place a wager).'
            ];
        }

        $alreadyVoted = db_fetch_one(
            "SELECT v.id_vote FROM votes v JOIN answers a ON v.id_answer = a.id_answer WHERE v.id_player = :p AND a.id_question = :q LIMIT 1",
            [':p' => $playerId, ':q' => $qId]
        );

        if ($alreadyVoted) {
            return ['success' => false, 'message' => 'You have already answered this question'];
        }

        $isTextType = (($ans['question_type'] ?? '') === 'text' || isset($data['text_answer']));
        if ($isTextType) {
            $userText = trim((string)($data['text_answer'] ?? ''));
            $correctAnsRow = db_fetch_one("SELECT * FROM answers WHERE id_question = :qid AND is_correct = 1 LIMIT 1", [':qid' => $qId]);
            $correctText = trim((string)($correctAnsRow['text'] ?? ($ans['text'] ?? '')));
            $isCorrect = (mb_strtolower($userText, 'UTF-8') === mb_strtolower($correctText, 'UTF-8'));

            if ($isCorrect && $correctAnsRow) {
                $answerId = intval($correctAnsRow['id_answer']);
            } else {
                $wrongAnsRow = db_fetch_one("SELECT * FROM answers WHERE id_question = :qid AND is_correct = 0 LIMIT 1", [':qid' => $qId]);
                if (!$wrongAnsRow) {
                    $newAid = db_insert("INSERT INTO answers (id_question, is_correct, text) VALUES (:qid, 0, :txt)", [
                        ':qid' => $qId,
                        ':txt' => $userText ?: 'Incorrect'
                    ]);
                    $answerId = intval($newAid);
                } else {
                    $answerId = intval($wrongAnsRow['id_answer']);
                }
            }
        } else {
            $isCorrect = ($ans['is_correct'] == 1);
        }
        $timeLimit  = intval($ans['time_limit'] ?: 20);
        $startTime  = intval($ans['question_start_time'] ?: time());
        $elapsed    = max(0, time() - $startTime);

        $getReadyDuration = 3;
        if ($elapsed < $getReadyDuration) {
            return ['success' => false, 'message' => 'Please wait for the Get Ready countdown to finish!'];
        }
        $answerElapsed = max(0, $elapsed - $getReadyDuration);

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

            $speedBonus = 0; // Speed bonus feature removed from wager mode

            $scoreResult = $mode->scoreQuestion([
                'currentScore' => intval($playerCheck['score']),
                'tierPercent'  => $tierPercent,
                'isCorrect'    => $isCorrect,
                'speedBonus'   => $speedBonus,
                'config'       => $qConfig,
                'basePoints'   => intval($ans['points'] ?: 1000),
            ]);

            $wagerAmount = $scoreResult['wagerAmount'];
            $scoreDelta  = $scoreResult['delta'];
            $newStreak   = $isCorrect ? ($currentStreak + 1) : 0;
            $streakLost  = (!$isCorrect && $currentStreak >= 2);
            $pointsEarned = $isCorrect ? $scoreDelta : 0;
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
                'time_elapsed'   => $answerElapsed,
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
                // Priority 1: Check piece index stored in room.game_config (the exact piece host clicked)
                $roomCfg = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
                $pieceIdx = intval($roomCfg['current_piece_index'] ?? 0);

                // Priority 2: Check question's piece_index
                if ($pieceIdx <= 0) {
                    $pieceIdx = intval($ans['piece_index'] ?? 0);
                }

                // Priority 3: Fallback based on question's sort order / index in quiz
                if ($pieceIdx <= 0) {
                    $allQ = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :qz ORDER BY sort_order ASC, id_question ASC", [':qz' => $room['id_quiz']]);
                    foreach ($allQ as $qIdx => $qRow) {
                        if ($qRow['id_question'] == $qId) {
                            $pieceIdx = $qIdx + 1;
                            break;
                        }
                    }
                }

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

        // Check if all participants have answered or all players are eliminated -> auto-finalize question phase
        $counts = room_question_counts($room, $qId, $state);
        $totalPlayers = $counts['total_players'];
        $votedCount   = $counts['voted_count'];
        $activePlayersCount = (int)db_count('players', "id_room = :r AND status = 'ACTIVE' AND is_kicked = 0", [':r' => $roomId]);

        if (($totalPlayers > 0 && $votedCount >= $totalPlayers) || $activePlayersCount === 0) {
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
