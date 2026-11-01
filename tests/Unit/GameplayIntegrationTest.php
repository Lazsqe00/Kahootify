<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/modes/GameStateManager.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';
require_once __DIR__ . '/../../includes/modes/WagerCalculator.php';

class GameplayIntegrationTest
{
    private function assert($condition, $message = 'Assertion failed')
    {
        if (!$condition) {
            throw new Exception($message);
        }
    }

    private function assertEquals($expected, $actual, $message = '')
    {
        if ($expected !== $actual) {
            $msg = $message ?: ("Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
            throw new Exception($msg);
        }
    }

    public function testLockWagerPhaseAndAdvanceToQuestion()
    {
        $pdo = getDB();
        $pdo->beginTransaction();
        try {
            // 1. Create quiz
            $quizId = db_insert("INSERT INTO quiz (id_creator, name) VALUES (1, 'Gameplay Test Quiz')");

            // 2. Create questions: Q1 (wager), Q2 (normal)
            $q1 = db_insert("INSERT INTO questions (id_quiz, text, time_limit, points, mode, mode_config) VALUES (:q, 'Q1 Wager', 20, 1000, 'wager', :cfg)", [
                ':q' => $quizId,
                ':cfg' => json_encode(['wagerTimeSec' => 10])
            ]);
            $a1_1 = db_insert("INSERT INTO answers (id_question, text, is_correct) VALUES (:q, 'Correct A', 1)", [':q' => $q1]);
            $a1_2 = db_insert("INSERT INTO answers (id_question, text, is_correct) VALUES (:q, 'Wrong B', 0)", [':q' => $q1]);

            $q2 = db_insert("INSERT INTO questions (id_quiz, text, time_limit, points, mode) VALUES (:q, 'Q2 Normal', 20, 1000, 'normal')", [
                ':q' => $quizId
            ]);
            $a2_1 = db_insert("INSERT INTO answers (id_question, text, is_correct) VALUES (:q, 'Correct A2', 1)", [':q' => $q2]);

            // 3. Create room
            $rId = db_insert("INSERT INTO room (id_quiz, id_creator, pin, status, question_order, starting_score) VALUES (:q, 1, 999123, 'waiting', :qo, 1000)", [
                ':q' => $quizId,
                ':qo' => json_encode([$q1, $q2])
            ]);

            // 4. Create 2 players
            $p1 = db_insert("INSERT INTO players (id_room, nickname, score, status) VALUES (:r, 'Player One', 1000, 'ACTIVE')", [':r' => $rId]);
            $p2 = db_insert("INSERT INTO players (id_room, nickname, score, status) VALUES (:r, 'Player Two', 1000, 'ACTIVE')", [':r' => $rId]);

            // Start game -> initial status should be wager_<q1>
            $firstQ = db_fetch_one("SELECT * FROM questions WHERE id_question = :q", [':q' => $q1]);
            $initialStatus = GameStateManager::getInitialQuestionStatus($firstQ);
            $this->assertEquals("wager_$q1", $initialStatus);
            db_execute("UPDATE room SET status = :s WHERE id_room = :r", [':s' => $initialStatus, ':r' => $rId]);

            // Player 1 wagers tier_50 (500 pts)
            $wagerRes = GameStateManager::submitPlayerWager($rId, $p1, $q1, 'tier_50');
            $this->assertEquals(true, $wagerRes['success']);
            $this->assertEquals(500, $wagerRes['wager_amount']);
            $this->assertEquals('50%', $wagerRes['tier']);
            $this->assertEquals('50%', $wagerRes['tier_label']);

            // Test calling GameStateManager::lockWagerPhase directly (was undefined before!)
            $lockRes = GameStateManager::lockWagerPhase($rId, $q1);
            $this->assertEquals(true, $lockRes['success']);
            $this->assertEquals((string)$q1, $lockRes['status']);

            // Room status should now be "<q1>" (Question phase)
            $roomState = db_fetch_one("SELECT status FROM room WHERE id_room = :r", [':r' => $rId]);
            $this->assertEquals((string)$q1, $roomState['status']);

            // P1 votes correct answer
            db_insert("INSERT INTO votes (id_player, id_answer, points_earned, streak_bonus) VALUES (:p, :a, 500, 0)", [':p' => $p1, ':a' => $a1_1]);
            db_execute("UPDATE players SET score = score + 500, answers_correct = answers_correct + 1 WHERE id_player = :p", [':p' => $p1]);
            db_execute("UPDATE player_question_logs SET delta_score = 500, points_change = 500, final_score = 1500, is_correct = 1 WHERE id_room = :r AND id_question = :q AND id_player = :p", [
                ':r' => $rId, ':q' => $q1, ':p' => $p1
            ]);

            // Finalize question phase (res_<q1>)
            $finRes = GameStateManager::finalizeQuestionPhase($rId, $q1);
            $this->assertEquals("res_$q1", $finRes['status']);

            // Test Leaderboard query from modules/player/play.php:127
            $leaderboardSql = "
                SELECT p.*,
                       COALESCE(l.points_change, l.delta_score, COALESCE(v.points_earned + v.streak_bonus, 0)) as points_gained,
                       COALESCE(a.is_correct, 0) as is_round_correct
                FROM players p
                LEFT JOIN player_question_logs l ON l.id_player = p.id_player AND l.id_question = :qid AND l.id_room = :r
                LEFT JOIN votes v ON v.id_player = p.id_player
                     AND v.id_answer IN (SELECT id_answer FROM answers WHERE id_question = :qid2)
                LEFT JOIN answers a ON v.id_answer = a.id_answer
                WHERE p.id_room = :r2
                ORDER BY p.score DESC, p.playeradd ASC, p.id_player ASC
            ";
            $board = db_fetch_all($leaderboardSql, [':r' => $rId, ':qid' => $q1, ':qid2' => $q1, ':r2' => $rId]);
            $this->assertEquals(2, count($board), "Leaderboard should have 2 players");
            $this->assertEquals(1500, intval($board[0]['score']));
            $this->assertEquals(500, intval($board[0]['points_gained']));

            // Test host leaderboard stats queries from modules/host/leaderboard.php:78-79
            $allInCount = db_count('player_question_logs', "id_room = :r AND id_question = :q AND (wager_tier = '100%' OR wager_tier = 'tier_100')", [':r' => $rId, ':q' => $q1]);
            $wagerWinnersCount = db_count('player_question_logs', "id_room = :r AND id_question = :q AND (points_change > 0 OR delta_score > 0)", [':r' => $rId, ':q' => $q1]);
            $this->assertEquals(0, $allInCount);
            $this->assertEquals(1, $wagerWinnersCount);

            // Test advancing to next question via advanceToQuestion (was undefined before!)
            $advRes = GameStateManager::advanceToQuestion($rId, $q2);
            $this->assertEquals(true, $advRes['success']);
            $this->assertEquals((string)$q2, $advRes['status']);

            $roomStateAfterAdv = db_fetch_one("SELECT status FROM room WHERE id_room = :r", [':r' => $rId]);
            $this->assertEquals((string)$q2, $roomStateAfterAdv['status']);

            $pdo->rollBack();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
