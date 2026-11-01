<?php

require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';
require_once __DIR__ . '/../../includes/modes/NormalMode.php';

class NormalModeRegressionTest
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

    public function testPhasesOrder()
    {
        $mode = new NormalMode();
        $this->assertEquals(['intro', 'question', 'result', 'leaderboard'], $mode->getPhases([]));
    }

    public function testScoringInstantCorrectAnswer()
    {
        $mode = new NormalMode();
        // Base: 1000 pts, time limit 20s, elapsed 0s, streak 0 -> gets 1000 pts, streak becomes 1
        $res = $mode->scoreQuestion([
            'current_score' => 500,
            'is_correct' => true,
            'points' => 1000,
            'time_limit' => 20,
            'time_elapsed' => 0,
            'current_streak' => 0,
        ]);

        $this->assertEquals(1000, $res['points_earned']);
        $this->assertEquals(0, $res['streak_bonus']);
        $this->assertEquals(1000, $res['total_delta']);
        $this->assertEquals(1500, $res['new_score']);
        $this->assertEquals(1, $res['new_streak']);
        $this->assertEquals(false, $res['eliminated']);
    }

    public function testScoringHalfTimeElapsed()
    {
        $mode = new NormalMode();
        // 10s elapsed of 20s -> ratio = 0.5. formula: 1000 * (1 - 0.5/2) = 1000 * 0.75 = 750
        $res = $mode->scoreQuestion([
            'current_score' => 1000,
            'is_correct' => true,
            'points' => 1000,
            'time_limit' => 20,
            'time_elapsed' => 10,
            'current_streak' => 1,
        ]);

        $this->assertEquals(750, $res['points_earned']);
        $this->assertEquals(100, $res['streak_bonus'], "Streak 2 gets +100 bonus");
        $this->assertEquals(850, $res['total_delta']);
        $this->assertEquals(1850, $res['new_score']);
        $this->assertEquals(2, $res['new_streak']);
    }

    public function testScoringStreakBonuses()
    {
        $mode = new NormalMode();

        // Streak 3 -> +200 bonus
        $res3 = $mode->scoreQuestion(['is_correct' => true, 'points' => 1000, 'time_limit' => 20, 'time_elapsed' => 0, 'current_streak' => 2]);
        $this->assertEquals(200, $res3['streak_bonus']);

        // Streak 4 -> +300 bonus
        $res4 = $mode->scoreQuestion(['is_correct' => true, 'points' => 1000, 'time_limit' => 20, 'time_elapsed' => 0, 'current_streak' => 3]);
        $this->assertEquals(300, $res4['streak_bonus']);

        // Streak 5+ -> +500 bonus
        $res5 = $mode->scoreQuestion(['is_correct' => true, 'points' => 1000, 'time_limit' => 20, 'time_elapsed' => 0, 'current_streak' => 4]);
        $this->assertEquals(500, $res5['streak_bonus']);

        $res6 = $mode->scoreQuestion(['is_correct' => true, 'points' => 1000, 'time_limit' => 20, 'time_elapsed' => 0, 'current_streak' => 10]);
        $this->assertEquals(500, $res6['streak_bonus']);
    }

    public function testScoringIncorrectAnswer()
    {
        $mode = new NormalMode();
        $res = $mode->scoreQuestion([
            'current_score' => 1200,
            'is_correct' => false,
            'points' => 1000,
            'time_limit' => 20,
            'time_elapsed' => 5,
            'current_streak' => 4,
        ]);

        $this->assertEquals(0, $res['points_earned']);
        $this->assertEquals(0, $res['streak_bonus']);
        $this->assertEquals(0, $res['total_delta']);
        $this->assertEquals(1200, $res['new_score']);
        $this->assertEquals(0, $res['new_streak'], "Streak resets to 0");
        $this->assertEquals(false, $res['eliminated']);
    }

    public function testParticipationRules()
    {
        $mode = new NormalMode();

        // ACTIVE player can participate
        $this->assert($mode->canParticipate(['status' => 'ACTIVE'], [], []));

        // Missing status defaults to ACTIVE and can participate
        $this->assert($mode->canParticipate([], [], []));

        // SPECTATOR cannot participate
        $this->assert(!$mode->canParticipate(['status' => 'SPECTATOR'], [], []));

        // ELIMINATED cannot participate unless eliminated_can_play_normal is true
        $this->assert(!$mode->canParticipate(['status' => 'ELIMINATED'], ['eliminated_can_play_normal' => 0], []));
        $this->assert($mode->canParticipate(['status' => 'ELIMINATED'], ['eliminated_can_play_normal' => 1], []));
    }
}
