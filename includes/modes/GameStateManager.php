<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/QuestionModeRegistry.php';
require_once __DIR__ . '/WagerCalculator.php';

/**
 * Class GameStateManager
 * Engine state machine manager coordinating question modes, player statuses,
 * wager locking, scoring, and game ending.
 */
class GameStateManager
{
    /**
     * Get initial phase status string for a question.
     * Normal mode: "<qid>"
     * Wager mode: "wager_<qid>"
     */
    public static function getInitialQuestionStatus(array $question): string
    {
        $qId = intval($question['id_question'] ?? 0);
        $mode = QuestionModeRegistry::get($question['mode'] ?? 'normal');
        $phases = $mode->getPhases($question);

        // Find the first phase after 'intro'
        $firstPhase = 'question';
        $introIdx = array_search('intro', $phases, true);
        if ($introIdx !== false && isset($phases[$introIdx + 1])) {
            $firstPhase = $phases[$introIdx + 1];
        } elseif (!empty($phases[0]) && $phases[0] !== 'intro') {
            $firstPhase = $phases[0];
        }

        if ($firstPhase === 'question') {
            return (string)$qId;
        }

        return $firstPhase . '_' . $qId;
    }

    /**
     * Parse room status into component parts:
     * e.g. "wager_42" -> ['phase' => 'wager', 'qid' => 42, 'question_id' => 42]
     *      "prep_888" -> ['phase' => 'prep', 'qid' => 888, 'question_id' => 888]
     *      "42"       -> ['phase' => 'question', 'qid' => 42, 'question_id' => 42]
     *      "res_42"   -> ['phase' => 'result', 'qid' => 42, 'question_id' => 42]
     *      "waiting"  -> ['phase' => 'waiting', 'qid' => 0, 'question_id' => 0]
     *      "finished" -> ['phase' => 'finished', 'qid' => 0, 'question_id' => 0]
     */
    public static function parseRoomStatus(string $status): array
    {
        $status = trim($status);

        if ($status === 'waiting') {
            return ['phase' => 'waiting', 'qid' => 0, 'question_id' => 0];
        }

        if ($status === 'finished') {
            return ['phase' => 'finished', 'qid' => 0, 'question_id' => 0];
        }

        if (preg_match('/^([a-z0-9_-]+)_(\d+)$/i', $status, $matches)) {
            $prefix = $matches[1];
            $qId = intval($matches[2]);
            $phase = ($prefix === 'res') ? 'result' : $prefix;
            return [
                'phase' => $phase,
                'qid' => $qId,
                'question_id' => $qId,
            ];
        }

        if (is_numeric($status)) {
            $qId = intval($status);
            return ['phase' => 'question', 'qid' => $qId, 'question_id' => $qId];
        }

        return ['phase' => $status, 'qid' => 0, 'question_id' => 0];
    }

    public static function parseStatus(string $status): array
    {
        return self::parseRoomStatus($status);
    }

    /**
     * Record or update player wager for a question.
     * Client ONLY sends tierId (e.g. 'tier_10', 'tier_25', 'tier_50', 'tier_100').
     */
    public static function submitPlayerWager(
        int $roomId,
        int $playerId,
        int $questionId,
        string $tierId
    ): array {
        $room = db_fetch_one("SELECT * FROM room WHERE id_room = :r", [':r' => $roomId]);
        if (!$room) {
            return ['success' => false, 'message' => 'Phòng không tồn tại.'];
        }

        $parsed = self::parseRoomStatus($room['status'] ?? '');
        if ($parsed['phase'] !== 'wager' || $parsed['qid'] !== $questionId) {
            return ['success' => false, 'message' => 'Giai đoạn đặt cược đã kết thúc hoặc không hợp lệ.'];
        }

        $player = db_fetch_one("SELECT * FROM players WHERE id_player = :p AND id_room = :r", [
            ':p' => $playerId,
            ':r' => $roomId,
        ]);
        if (!$player) {
            return ['success' => false, 'message' => 'Người chơi không tồn tại.'];
        }

        $playerStatus = strtoupper((string)($player['status'] ?? 'ACTIVE'));
        if ($playerStatus !== 'ACTIVE') {
            return ['success' => false, 'message' => 'Người chơi đã bị loại hoặc là khán giả không thể đặt cược.'];
        }

        $tierPercent = WagerCalculator::getTierPercent($tierId);
        if ($tierPercent === null) {
            return ['success' => false, 'message' => 'Mức cược không hợp lệ.'];
        }

        $question = db_fetch_one("SELECT * FROM questions WHERE id_question = :q", [':q' => $questionId]);
        if (!$question) {
            return ['success' => false, 'message' => 'Câu hỏi không tồn tại.'];
        }

        $qConfig = !empty($question['mode_config']) ? json_decode($question['mode_config'], true) : [];
        if (!is_array($qConfig)) $qConfig = [];

        $wagerTimeSec = intval($qConfig['wagerTimeSec'] ?? 10);
        $startTime = intval($room['question_start_time'] ?? time());
        $elapsed = time() - $startTime;

        if ($elapsed > ($wagerTimeSec + 2)) { // Grace buffer 2s for network jitter
            return ['success' => false, 'message' => 'Thời gian đặt cược đã hết.'];
        }

        $currentScore = max(0, intval($player['score'] ?? 0));
        $wagerAmount = WagerCalculator::calculateWagerAmount($currentScore, $tierPercent, $qConfig);

        // Save wager to player_question_logs (idempotent upsert)
        $existingLog = db_fetch_one(
            "SELECT id_log FROM player_question_logs WHERE id_room = :r AND id_question = :q AND id_player = :p",
            [':r' => $roomId, ':q' => $questionId, ':p' => $playerId]
        );

        if ($existingLog) {
            db_execute(
                "UPDATE player_question_logs 
                 SET wager_tier = :wt, wager_amount = :wa, is_sitting_out = 0, player_status = 'ACTIVE' 
                 WHERE id_log = :id",
                [
                    ':wt' => $tierId,
                    ':wa' => $wagerAmount,
                    ':id' => $existingLog['id_log'],
                ]
            );
        } else {
            db_insert(
                "INSERT INTO player_question_logs 
                 (id_room, id_question, id_player, mode, wager_tier, wager_amount, delta_score, points_change, final_score, is_correct, player_status, is_sitting_out) 
                 VALUES (:r, :q, :p, 'wager', :wt, :wa, 0, 0, :fs, 0, 'ACTIVE', 0)",
                [
                    ':r'  => $roomId,
                    ':q'  => $questionId,
                    ':p'  => $playerId,
                    ':wt' => $tierId,
                    ':wa' => $wagerAmount,
                    ':fs' => $currentScore,
                ]
            );
        }

        $tierLabel = WagerCalculator::getTierLabel($tierId);

        return [
            'success'      => true,
            'tier_id'      => $tierId,
            'tier'         => $tierLabel,
            'tier_label'   => $tierLabel,
            'tier_percent' => $tierPercent,
            'wager_amount' => $wagerAmount,
            'message'      => 'Đặt cược thành công!',
        ];
    }

    /**
     * Get wager phase progress info for host and players.
     */
    public static function getWagerPhaseStatus(int $roomId, int $questionId): array
    {
        $room = db_fetch_one("SELECT * FROM room WHERE id_room = :r", [':r' => $roomId]);
        if (!$room) {
            return ['success' => false, 'message' => 'Phòng không tồn tại.'];
        }

        $question = db_fetch_one("SELECT * FROM questions WHERE id_question = :q", [':q' => $questionId]);
        $qConfig = (!empty($question['mode_config'])) ? json_decode($question['mode_config'], true) : [];
        if (!is_array($qConfig)) $qConfig = [];

        $wagerTimeSec = intval($qConfig['wagerTimeSec'] ?? 10);
        $startTime = intval($room['question_start_time'] ?? time());
        $elapsed = max(0, time() - $startTime);
        $remaining = max(0, $wagerTimeSec - $elapsed);

        // Active players count
        $activePlayers = db_fetch_all(
            "SELECT id_player, nickname, score FROM players WHERE id_room = :r AND status = 'ACTIVE' AND is_kicked = 0",
            [':r' => $roomId]
        );
        $totalActive = count($activePlayers);

        // Count who placed a wager
        $wageredRows = db_fetch_all(
            "SELECT id_player FROM player_question_logs 
             WHERE id_room = :r AND id_question = :q AND wager_tier IS NOT NULL AND is_sitting_out = 0",
            [':r' => $roomId, ':q' => $questionId]
        );
        $wageredCount = count($wageredRows);

        return [
            'success'           => true,
            'total_active'      => $totalActive,
            'wagered_count'     => $wageredCount,
            'remaining_seconds' => $remaining,
            'is_all_wagered'    => ($totalActive > 0 && $wageredCount >= $totalActive),
            'wager_time_sec'    => $wagerTimeSec,
        ];
    }

    /**
     * Lock wager phase and transition into Question phase (or skip question if 0 participants).
     * Alias for lockWagerPhaseAndAdvance.
     */
    public static function lockWagerPhase(int $roomId, int $questionId): array
    {
        return self::lockWagerPhaseAndAdvance($roomId, $questionId);
    }

    /**
     * Lock wager phase and transition into Question phase (or skip question if 0 participants).
     */
    public static function lockWagerPhaseAndAdvance(int $roomId, int $questionId): array
    {
        $room = db_fetch_one("SELECT * FROM room WHERE id_room = :r", [':r' => $roomId]);
        if (!$room) {
            return ['success' => false, 'message' => 'Phòng không tồn tại.'];
        }

        $activePlayers = db_fetch_all(
            "SELECT * FROM players WHERE id_room = :r AND (status = 'ACTIVE' OR status IS NULL OR status = '') AND is_kicked = 0",
            [':r' => $roomId]
        );

        $wageredPlayerIds = array_column(
            db_fetch_all(
                "SELECT id_player FROM player_question_logs 
                 WHERE id_room = :r AND id_question = :q AND wager_tier IS NOT NULL AND is_sitting_out = 0",
                [':r' => $roomId, ':q' => $questionId]
            ),
            'id_player'
        );
        $wageredMap = array_flip($wageredPlayerIds);

        $maxMissedWagers = intval($room['max_missed_wagers'] ?? 0);
        $currentEliminatedCount = db_count('players', "id_room = :r AND status = 'ELIMINATED'", [':r' => $roomId]);

        $participants = [];
        $sittingOut = [];
        $newlyEliminated = [];

        foreach ($activePlayers as $p) {
            $pId = intval($p['id_player']);
            $hasWagered = isset($wageredMap[$pId]);

            // Update consecutive missed wagers
            $missedRes = WagerCalculator::updateMissedWagers($p, 'wager', $hasWagered, $maxMissedWagers);
            $consecutive = $missedRes['consecutive_missed_wagers'];
            $isEliminated = $missedRes['eliminated'];

            if ($isEliminated) {
                $currentEliminatedCount++;
                db_execute(
                    "UPDATE players 
                     SET status = 'ELIMINATED', eliminated_at_question = :q, elimination_order = :eo, consecutive_missed_wagers = :cm 
                     WHERE id_player = :p",
                    [
                        ':q'  => $questionId,
                        ':eo' => $currentEliminatedCount,
                        ':cm' => $consecutive,
                        ':p'  => $pId,
                    ]
                );
                $newlyEliminated[] = $pId;
            } else {
                db_execute(
                    "UPDATE players SET consecutive_missed_wagers = :cm WHERE id_player = :p",
                    [':cm' => $consecutive, ':p' => $pId]
                );
            }

            if ($hasWagered && !$isEliminated) {
                $participants[] = $pId;
            } else {
                $sittingOut[] = $pId;
                // Record sitting out in log
                $existingLog = db_fetch_one(
                    "SELECT id_log FROM player_question_logs WHERE id_room = :r AND id_question = :q AND id_player = :p",
                    [':r' => $roomId, ':q' => $questionId, ':p' => $pId]
                );
                if ($existingLog) {
                    db_execute(
                        "UPDATE player_question_logs SET is_sitting_out = 1 WHERE id_log = :id",
                        [':id' => $existingLog['id_log']]
                    );
                } else {
                    db_insert(
                        "INSERT INTO player_question_logs 
                         (id_room, id_question, id_player, mode, wager_amount, delta_score, points_change, final_score, is_correct, player_status, is_sitting_out) 
                         VALUES (:r, :q, :p, 'wager', 0, 0, 0, :fs, 0, :st, 1)",
                        [
                            ':r'  => $roomId,
                            ':q'  => $questionId,
                            ':p'  => $pId,
                            ':fs' => intval($p['score']),
                            ':st' => $isEliminated ? 'ELIMINATED' : 'ACTIVE',
                        ]
                    );
                }
            }
        }

        // If NO participants placed a wager: skip this question
        if (empty($participants)) {
            return self::advanceToNextQuestion($roomId, $questionId);
        }

        // Advance room status to Question Phase: "<qid>"
        db_execute(
            "UPDATE room SET status = :s, question_start_time = UNIX_TIMESTAMP() WHERE id_room = :r",
            [
                ':s' => (string)$questionId,
                ':r' => $roomId,
            ]
        );

        return [
            'success'          => true,
            'participants'     => $participants,
            'sitting_out'      => $sittingOut,
            'newly_eliminated' => $newlyEliminated,
            'status'           => (string)$questionId,
        ];
    }

    /**
     * Finalize question phase: penalize wager participants who failed to answer,
     * check game end conditions, and update status to "res_<qid>" or "finished".
     */
    public static function finalizeQuestionPhase(int $roomId, int $questionId): array
    {
        $room = db_fetch_one("SELECT * FROM room WHERE id_room = :r", [':r' => $roomId]);
        if (!$room) {
            return ['success' => false, 'message' => 'Phòng không tồn tại.'];
        }

        $question = db_fetch_one("SELECT * FROM questions WHERE id_question = :q", [':q' => $questionId]);
        $modeId = $question['mode'] ?? 'normal';
        $mode = QuestionModeRegistry::get($modeId);

        // For wager questions: penalize participants who did NOT answer in time
        if ($modeId === 'wager') {
            $unansweredParticipants = db_fetch_all(
                "SELECT l.*, p.score, p.status, p.nickname, p.current_streak
                 FROM player_question_logs l
                 JOIN players p ON p.id_player = l.id_player
                 WHERE l.id_room = :r AND l.id_question = :q 
                   AND l.is_sitting_out = 0 
                   AND l.id_player NOT IN (
                       SELECT v.id_player FROM votes v 
                       JOIN answers a ON a.id_answer = v.id_answer 
                       WHERE a.id_question = :q2
                   )",
                [':r' => $roomId, ':q' => $questionId, ':q2' => $questionId]
            );

            $currentEliminatedCount = db_count('players', "id_room = :r AND status = 'ELIMINATED'", [':r' => $roomId]);

            foreach ($unansweredParticipants as $unans) {
                $pId = intval($unans['id_player']);
                $currScore = intval($unans['score']);
                $tierPercent = WagerCalculator::getTierPercent((string)$unans['wager_tier']) ?? 0.10;

                $qConfig = !empty($question['mode_config']) ? json_decode($question['mode_config'], true) : [];
                $res = WagerCalculator::calculateWagerResult([
                    'currentScore' => $currScore,
                    'tierPercent'  => $tierPercent,
                    'isCorrect'    => false, // Unanswered = incorrect
                    'config'       => $qConfig,
                ]);

                $newScore = $res['newScore'];
                $isEliminated = $res['eliminated'];
                $status = $isEliminated ? 'ELIMINATED' : 'ACTIVE';
                $eo = null;

                if ($isEliminated) {
                    $currentEliminatedCount++;
                    $eo = $currentEliminatedCount;
                }

                // Update player
                db_execute(
                    "UPDATE players 
                     SET score = :ns, status = :st, eliminated_at_question = :eq, elimination_order = :eo, current_streak = 0 
                     WHERE id_player = :p",
                    [
                        ':ns' => $newScore,
                        ':st' => $status,
                        ':eq' => $isEliminated ? $questionId : null,
                        ':eo' => $eo,
                        ':p'  => $pId,
                    ]
                );

                // Update log
                db_execute(
                    "UPDATE player_question_logs 
                     SET delta_score = :ds, points_change = :ds, final_score = :fs, is_correct = 0, player_status = :ps 
                     WHERE id_log = :id",
                    [
                        ':ds' => $res['delta'],
                        ':fs' => $newScore,
                        ':ps' => $status,
                        ':id' => $unans['id_log'],
                    ]
                );
            }
        }

        // Check game end conditions
        $allPlayers = db_fetch_all("SELECT * FROM players WHERE id_room = :r AND is_kicked = 0", [':r' => $roomId]);
        $selectedQIds = !empty($room['question_order']) ? json_decode($room['question_order'], true) : [];
        if (!is_array($selectedQIds)) $selectedQIds = [];

        $currentIdx = array_search((int)$questionId, array_map('intval', $selectedQIds), true);
        $questionsLeft = ($currentIdx !== false) ? (count($selectedQIds) - 1 - $currentIdx) : 0;

        $initialActiveCount = count($allPlayers);
        $endCheck = WagerCalculator::checkGameEnd($allPlayers, $questionsLeft, $modeId, $initialActiveCount);

        if ($endCheck['isFinished']) {
            db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $roomId]);
            return [
                'success'     => true,
                'status'      => 'finished',
                'is_finished' => true,
                'end_reason'  => $endCheck['reason'],
                'winner'      => $endCheck['winner'],
            ];
        }

        // Set status to res_<qid>
        $newStatus = 'res_' . $questionId;
        db_execute("UPDATE room SET status = :s WHERE id_room = :r", [
            ':s' => $newStatus,
            ':r' => $roomId,
        ]);

        return [
            'success'     => true,
            'status'      => $newStatus,
            'is_finished' => false,
        ];
    }

    /**
     * Advance directly to a specific question (starts that question).
     */
    public static function advanceToQuestion(int $roomId, int $questionId): array
    {
        $question = db_fetch_one("SELECT * FROM questions WHERE id_question = :q", [':q' => $questionId]);
        $initialStatus = self::getInitialQuestionStatus($question ?: ['id_question' => $questionId, 'mode' => 'normal']);

        db_execute(
            "UPDATE room SET status = :s, question_start_time = UNIX_TIMESTAMP() WHERE id_room = :r",
            [
                ':s' => $initialStatus,
                ':r' => $roomId,
            ]
        );

        return [
            'success'     => true,
            'status'      => $initialStatus,
            'question_id' => $questionId,
        ];
    }

    /**
     * Advance to the next question in room.question_order or finish the game.
     */
    public static function advanceToNextQuestion(int $roomId, int $currentQId): array
    {
        $room = db_fetch_one("SELECT * FROM room WHERE id_room = :r", [':r' => $roomId]);
        if (!$room) {
            return ['success' => false, 'message' => 'Phòng không tồn tại.'];
        }

        $selectedQIds = !empty($room['question_order']) ? json_decode($room['question_order'], true) : [];
        if (!is_array($selectedQIds) || empty($selectedQIds)) {
            $fallbackQuestions = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $room['id_quiz']]);
            $selectedQIds = array_column($fallbackQuestions, 'id_question');
        }
        $selectedQIds = array_values(array_filter(array_map('intval', (array)$selectedQIds)));

        $currentIdx = array_search((int)$currentQId, $selectedQIds, true);
        if ($currentIdx === false && $currentQId > 0) {
            $currentIdx = array_search((int)$currentQId, $selectedQIds);
        }

        $nextIdx = ($currentIdx !== false) ? ($currentIdx + 1) : 0;

        if (isset($selectedQIds[$nextIdx])) {
            $nextQId = intval($selectedQIds[$nextIdx]);
            $res = self::advanceToQuestion($roomId, $nextQId);

            return [
                'success'      => true,
                'status'       => $res['status'],
                'next_qid'     => $nextQId,
                'is_finished'  => false,
            ];
        }

        // No more questions -> finish game
        db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $roomId]);

        return [
            'success'     => true,
            'status'      => 'finished',
            'next_qid'    => 0,
            'is_finished' => true,
        ];
    }
}
