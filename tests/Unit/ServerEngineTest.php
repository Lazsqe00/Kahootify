<?php

require_once __DIR__ . '/../../includes/modes/GameStateManager.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';
require_once __DIR__ . '/../../includes/modes/WagerCalculator.php';

class ServerEngineTest
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
            $msg = $message ?: sprintf("Expected %s, got %s", var_export($expected, true), var_export($actual, true));
            throw new Exception($msg);
        }
    }

    public function testGetInitialQuestionStatus()
    {
        $normalQ = ['id_question' => 10, 'mode' => 'normal'];
        $this->assertEquals('10', GameStateManager::getInitialQuestionStatus($normalQ));

        $wagerQ = ['id_question' => 25, 'mode' => 'wager'];
        $this->assertEquals('wager_25', GameStateManager::getInitialQuestionStatus($wagerQ));

        // Missing mode defaults to normal
        $legacyQ = ['id_question' => 30];
        $this->assertEquals('30', GameStateManager::getInitialQuestionStatus($legacyQ));
    }

    public function testParseRoomStatus()
    {
        $p1 = GameStateManager::parseRoomStatus('waiting');
        $this->assertEquals('waiting', $p1['phase']);
        $this->assertEquals(0, $p1['qid']);

        $p2 = GameStateManager::parseRoomStatus('finished');
        $this->assertEquals('finished', $p2['phase']);
        $this->assertEquals(0, $p2['qid']);

        $p3 = GameStateManager::parseRoomStatus('wager_42');
        $this->assertEquals('wager', $p3['phase']);
        $this->assertEquals(42, $p3['qid']);

        $p4 = GameStateManager::parseRoomStatus('42');
        $this->assertEquals('question', $p4['phase']);
        $this->assertEquals(42, $p4['qid']);

        $p5 = GameStateManager::parseRoomStatus('res_42');
        $this->assertEquals('result', $p5['phase']);
        $this->assertEquals(42, $p5['qid']);
    }

    public function testWagerSubmissionRejectsNonActivePlayer()
    {
        $pdo = getDB();
        $pdo->beginTransaction();

        try {
            // Setup dummy room and player
            $rId = db_insert("INSERT INTO room (id_quiz, id_creator, pin, status, question_start_time) VALUES (1, 1, '999991', 'wager_100', UNIX_TIMESTAMP())");
            $pId = db_insert("INSERT INTO players (id_room, nickname, score, status) VALUES (:r, 'ElimPlayer', 0, 'ELIMINATED')", [':r' => $rId]);
            $qId = db_insert("INSERT INTO questions (id_quiz, text, mode) VALUES (1, 'Dummy Q', 'wager')");

            // Update room to point to this qId
            db_execute("UPDATE room SET status = :s WHERE id_room = :r", [':s' => 'wager_' . $qId, ':r' => $rId]);

            $res = GameStateManager::submitPlayerWager($rId, $pId, $qId, 'tier_25');
            $this->assertEquals(false, $res['success']);
            $this->assert(str_contains($res['message'], 'loại') || str_contains($res['message'], 'khán giả'));

            $pdo->rollBack();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function testWagerSubmissionAcceptsActivePlayerAndCalculatesAmount()
    {
        $pdo = getDB();
        $pdo->beginTransaction();

        try {
            $rId = db_insert("INSERT INTO room (id_quiz, id_creator, pin, status, question_start_time) VALUES (1, 1, '999992', 'waiting', UNIX_TIMESTAMP())");
            $pId = db_insert("INSERT INTO players (id_room, nickname, score, status) VALUES (:r, 'ActivePlayer', 1000, 'ACTIVE')", [':r' => $rId]);
            $qId = db_insert("INSERT INTO questions (id_quiz, text, mode) VALUES (1, 'Dummy Q2', 'wager')");

            db_execute("UPDATE room SET status = :s WHERE id_room = :r", [':s' => 'wager_' . $qId, ':r' => $rId]);

            // Submit 50% wager
            $res = GameStateManager::submitPlayerWager($rId, $pId, $qId, 'tier_50');
            $this->assertEquals(true, $res['success']);
            $this->assertEquals(500, $res['wager_amount']);

            // Verify logged in player_question_logs
            $log = db_fetch_one("SELECT * FROM player_question_logs WHERE id_room = :r AND id_question = :q AND id_player = :p", [
                ':r' => $rId, ':q' => $qId, ':p' => $pId
            ]);
            $this->assert($log !== false, "Wager should be logged in player_question_logs");
            $this->assertEquals('tier_50', $log['wager_tier']);
            $this->assertEquals(500, intval($log['wager_amount']));
            $this->assertEquals(0, intval($log['is_sitting_out']));

            // Change mind: update to ALL-IN (100%) before lock
            $res2 = GameStateManager::submitPlayerWager($rId, $pId, $qId, 'tier_100');
            $this->assertEquals(true, $res2['success']);
            $this->assertEquals(1000, $res2['wager_amount']);

            $log2 = db_fetch_one("SELECT * FROM player_question_logs WHERE id_room = :r AND id_question = :q AND id_player = :p", [
                ':r' => $rId, ':q' => $qId, ':p' => $pId
            ]);
            $this->assertEquals('tier_100', $log2['wager_tier']);
            $this->assertEquals(1000, intval($log2['wager_amount']));

            $pdo->rollBack();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function testLockWagerPhaseMarksNonWagerersAsSittingOut()
    {
        $pdo = getDB();
        $pdo->beginTransaction();

        try {
            $rId = db_insert("INSERT INTO room (id_quiz, id_creator, pin, status, question_start_time) VALUES (1, 1, '999993', 'waiting', UNIX_TIMESTAMP())");
            $p1 = db_insert("INSERT INTO players (id_room, nickname, score, status) VALUES (:r, 'P1_Wagered', 1000, 'ACTIVE')", [':r' => $rId]);
            $p2 = db_insert("INSERT INTO players (id_room, nickname, score, status) VALUES (:r, 'P2_Ignored', 1000, 'ACTIVE')", [':r' => $rId]);
            $qId = db_insert("INSERT INTO questions (id_quiz, text, mode) VALUES (1, 'Dummy Q3', 'wager')");

            db_execute("UPDATE room SET status = :s WHERE id_room = :r", [':s' => 'wager_' . $qId, ':r' => $rId]);

            // Only P1 wagers
            GameStateManager::submitPlayerWager($rId, $p1, $qId, 'tier_25');

            // Lock wager phase
            $lockRes = GameStateManager::lockWagerPhaseAndAdvance($rId, $qId);
            $this->assertEquals(true, $lockRes['success']);
            $this->assert(in_array($p1, $lockRes['participants']));
            $this->assert(in_array($p2, $lockRes['sitting_out']));

            // Verify room advanced to Question phase: "<qId>"
            $updatedRoom = db_fetch_one("SELECT status FROM room WHERE id_room = :r", [':r' => $rId]);
            $this->assertEquals((string)$qId, $updatedRoom['status']);

            // P2 should have sitting_out = 1 in player_question_logs
            $p2Log = db_fetch_one("SELECT * FROM player_question_logs WHERE id_room = :r AND id_question = :q AND id_player = :p", [
                ':r' => $rId, ':q' => $qId, ':p' => $p2
            ]);
            $this->assert($p2Log !== false);
            $this->assertEquals(1, intval($p2Log['is_sitting_out']));

            $pdo->rollBack();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
