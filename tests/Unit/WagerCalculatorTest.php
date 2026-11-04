<?php

require_once __DIR__ . '/../../includes/modes/WagerCalculator.php';

class WagerCalculatorTest
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

    public function testTierPercentLookup()
    {
        // Star of Hope tiers
        $this->assertEquals(0.00, WagerCalculator::getTierPercent('tier_0'));
        $this->assertEquals(0.15, WagerCalculator::getTierPercent('tier_15'));
        $this->assertEquals(0.30, WagerCalculator::getTierPercent('tier_30'));
        $this->assertEquals(0.40, WagerCalculator::getTierPercent('tier_40'));

        // Legacy tiers
        $this->assertEquals(0.10, WagerCalculator::getTierPercent('tier_10'));
        $this->assertEquals(0.25, WagerCalculator::getTierPercent('tier_25'));
        $this->assertEquals(0.50, WagerCalculator::getTierPercent('tier_50'));
        $this->assertEquals(1.00, WagerCalculator::getTierPercent('tier_100'));
        $this->assertEquals(null, WagerCalculator::getTierPercent('invalid_tier'));

        // Label assertions
        $this->assertEquals('No Star', WagerCalculator::getTierLabel('tier_0'));
        $this->assertEquals('Star 15%', WagerCalculator::getTierLabel('tier_15'));
        $this->assertEquals('Star 30%', WagerCalculator::getTierLabel('tier_30'));
        $this->assertEquals('Star 40%', WagerCalculator::getTierLabel('tier_40'));
        $this->assertEquals('10%', WagerCalculator::getTierLabel('tier_10'));
        $this->assertEquals('25%', WagerCalculator::getTierLabel('tier_25'));
        $this->assertEquals('50%', WagerCalculator::getTierLabel('tier_50'));
        $this->assertEquals('100% (ALL-IN)', WagerCalculator::getTierLabel('tier_100'));
    }

    public function testStarOfHopeNoStarSafety()
    {
        // tier_0 (0%): If incorrect, 0 deduction (score remains untouched, not eliminated)
        $resWrong = WagerCalculator::calculateWagerResult([
            'currentScore' => 1000,
            'tierPercent'  => 0.00,
            'isCorrect'    => false,
            'basePoints'   => 1000,
        ]);
        $this->assertEquals(0, $resWrong['wagerAmount']);
        $this->assertEquals(0, $resWrong['delta']);
        $this->assertEquals(1000, $resWrong['newScore']);
        $this->assertEquals(false, $resWrong['eliminated']);

        // tier_0 (0%): If correct, earns basePoints
        $resRight = WagerCalculator::calculateWagerResult([
            'currentScore' => 1000,
            'tierPercent'  => 0.00,
            'isCorrect'    => true,
            'basePoints'   => 800,
        ]);
        $this->assertEquals(0, $resRight['wagerAmount']);
        $this->assertEquals(800, $resRight['delta']);
        $this->assertEquals(1800, $resRight['newScore']);

        // tier_15 (15%): If correct = question points + 15% (1000 + 150 = 1150)
        $resStar15Right = WagerCalculator::calculateWagerResult([
            'currentScore' => 1000,
            'tierPercent'  => 0.15,
            'isCorrect'    => true,
            'basePoints'   => 1000,
        ]);
        $this->assertEquals(1150, $resStar15Right['delta']);
        $this->assertEquals(2150, $resStar15Right['newScore']);

        // tier_15 (15%): If wrong = -15% of question points (-150)
        $resStar15Wrong = WagerCalculator::calculateWagerResult([
            'currentScore' => 1000,
            'tierPercent'  => 0.15,
            'isCorrect'    => false,
            'basePoints'   => 1000,
        ]);
        $this->assertEquals(-150, $resStar15Wrong['delta']);
        $this->assertEquals(850, $resStar15Wrong['newScore']);

        // tier_30 (30%): If correct = 1000 + 300 = 1300
        $resStar30Right = WagerCalculator::calculateWagerResult([
            'currentScore' => 500,
            'tierPercent'  => 0.30,
            'isCorrect'    => true,
            'basePoints'   => 1000,
        ]);
        $this->assertEquals(1300, $resStar30Right['delta']);
        $this->assertEquals(1800, $resStar30Right['newScore']);

        // tier_40 (40%): If wrong = -400
        $resStar40Wrong = WagerCalculator::calculateWagerResult([
            'currentScore' => 500,
            'tierPercent'  => 0.40,
            'isCorrect'    => false,
            'basePoints'   => 1000,
        ]);
        $this->assertEquals(-400, $resStar40Wrong['delta']);
        $this->assertEquals(100, $resStar40Wrong['newScore']);
    }

    public function testStandardWagerAmountRoundingDownTo10()
    {
        // 1000 pts * 25% = 250 -> 250
        $this->assertEquals(250, WagerCalculator::calculateWagerAmount(1000, 0.25));

        // 1035 pts * 10% = 103.5 -> floor(10.35) * 10 = 100
        $this->assertEquals(100, WagerCalculator::calculateWagerAmount(1035, 0.10));

        // 755 pts * 50% = 377.5 -> floor(37.75) * 10 = 370
        $this->assertEquals(370, WagerCalculator::calculateWagerAmount(755, 0.50));
    }

    public function testMinWagerAmountEnforcement()
    {
        // Score = 40, tier = 10% -> raw = 4, but min is 10 -> amount = 10
        $this->assertEquals(10, WagerCalculator::calculateWagerAmount(40, 0.10));

        // Custom minWagerAmount = 20: score = 50, tier = 10% -> raw = 5, min is 20 -> amount = 20
        $this->assertEquals(20, WagerCalculator::calculateWagerAmount(50, 0.10, ['minWagerAmount' => 20]));
    }

    public function testScoreLowerThanMinWagerAmountBetsEntireScore()
    {
        // "Nếu currentScore < minWagerAmount: wagerAmount = currentScore (toàn bộ điểm, không làm tròn)"
        $this->assertEquals(7, WagerCalculator::calculateWagerAmount(7, 0.10, ['minWagerAmount' => 10]));
        $this->assertEquals(3, WagerCalculator::calculateWagerAmount(3, 0.50, ['minWagerAmount' => 10]));
        $this->assertEquals(1, WagerCalculator::calculateWagerAmount(1, 0.25, ['minWagerAmount' => 10]));
    }

    public function testAllInBetsEntireScoreWithoutRounding()
    {
        // "tier là 100% (ALL-IN): wagerAmount = currentScore (toàn bộ điểm, không làm tròn)"
        $this->assertEquals(1237, WagerCalculator::calculateWagerAmount(1237, 1.00));
        $this->assertEquals(15, WagerCalculator::calculateWagerAmount(15, 1.00));
        $this->assertEquals(9, WagerCalculator::calculateWagerAmount(9, 1.00));
    }

    public function testWagerAmountNeverExceedsCurrentScore()
    {
        // Score = 8, min = 10 -> cannot exceed 8
        $this->assertEquals(8, WagerCalculator::calculateWagerAmount(8, 0.10, ['minWagerAmount' => 10]));
    }

    public function testCalculateWagerResultCorrectAnswer()
    {
        // Current score = 1000, 50% = 500, correct -> newScore = 1500
        $res = WagerCalculator::calculateWagerResult([
            'currentScore' => 1000,
            'tierPercent'  => 0.50,
            'isCorrect'    => true,
            'speedBonus'   => 150,
            'config'       => ['speedBonusEnabled' => false],
        ]);

        $this->assertEquals(500, $res['wagerAmount']);
        $this->assertEquals(500, $res['delta']);
        $this->assertEquals(1500, $res['newScore']);
        $this->assertEquals(false, $res['eliminated']);
    }

    public function testCalculateWagerResultWithSpeedBonusEnabled()
    {
        // Current score = 1000, 25% = 250, correct, speedBonus = 120 -> delta = 370, newScore = 1370
        $res = WagerCalculator::calculateWagerResult([
            'currentScore' => 1000,
            'tierPercent'  => 0.25,
            'isCorrect'    => true,
            'speedBonus'   => 120,
            'config'       => ['speedBonusEnabled' => true],
        ]);

        $this->assertEquals(250, $res['wagerAmount']);
        $this->assertEquals(370, $res['delta']);
        $this->assertEquals(1370, $res['newScore']);
        $this->assertEquals(false, $res['eliminated']);
    }

    public function testCalculateWagerResultIncorrectAnswer()
    {
        // Current score = 1000, 25% = 250, incorrect -> delta = -250, newScore = 750
        $res = WagerCalculator::calculateWagerResult([
            'currentScore' => 1000,
            'tierPercent'  => 0.25,
            'isCorrect'    => false,
        ]);

        $this->assertEquals(250, $res['wagerAmount']);
        $this->assertEquals(-250, $res['delta']);
        $this->assertEquals(750, $res['newScore']);
        $this->assertEquals(false, $res['eliminated']);
    }

    public function testAllInLossCausesElimination()
    {
        // Current score = 1000, ALL-IN (100%) = 1000, incorrect -> newScore = 0, eliminated = true
        $res = WagerCalculator::calculateWagerResult([
            'currentScore' => 1000,
            'tierPercent'  => 1.00,
            'isCorrect'    => false,
        ]);

        $this->assertEquals(1000, $res['wagerAmount']);
        $this->assertEquals(-1000, $res['delta']);
        $this->assertEquals(0, $res['newScore']);
        $this->assertEquals(true, $res['eliminated'], "newScore = 0 must trigger elimination");
    }

    public function testFinalRankingOrderingAndTies()
    {
        $players = [
            ['id_player' => 1, 'nickname' => 'Alice', 'score' => 1500, 'status' => 'ACTIVE'],
            ['id_player' => 2, 'nickname' => 'Bob',   'score' => 1500, 'status' => 'ACTIVE'],
            ['id_player' => 3, 'nickname' => 'Charlie','score' => 800,  'status' => 'ACTIVE'],
            ['id_player' => 4, 'nickname' => 'David', 'score' => 0,    'status' => 'ELIMINATED', 'eliminated_at_question' => 3, 'elimination_order' => 2],
            ['id_player' => 5, 'nickname' => 'Eve',   'score' => 0,    'status' => 'ELIMINATED', 'eliminated_at_question' => 3, 'elimination_order' => 1],
            ['id_player' => 6, 'nickname' => 'Frank', 'score' => 0,    'status' => 'ELIMINATED', 'eliminated_at_question' => 1, 'elimination_order' => 3],
            ['id_player' => 7, 'nickname' => 'Grace', 'score' => 0,    'status' => 'SPECTATOR'],
        ];

        $ranked = WagerCalculator::calculateFinalRanking($players);

        // Alice & Bob tie for 1st
        $this->assertEquals(1, $ranked[0]['rank']);
        $this->assertEquals(1, $ranked[1]['rank']);

        // Charlie is 3rd
        $this->assertEquals(3, $ranked[2]['rank']);
        $this->assertEquals('Charlie', $ranked[2]['nickname']);

        // David & Eve both eliminated at question 3 -> tie for 4th
        $this->assertEquals(4, $ranked[3]['rank']);
        $this->assertEquals(4, $ranked[4]['rank']);

        // Frank eliminated earlier at question 1 -> ranked lower
        $this->assert($ranked[5]['rank'] > 4, "Frank should be ranked below David & Eve");

        // Grace (SPECTATOR) has rank = null
        $this->assertEquals(null, $ranked[6]['rank']);
        $this->assertEquals(true, $ranked[6]['is_spectator']);
    }

    public function testFinalRankingWhenAllPlayersEliminatedSameQuestion()
    {
        // "Tất cả ACTIVE cùng bị loại trong một câu -> kết thúc, những người này đồng hạng nhất"
        $players = [
            ['id_player' => 1, 'nickname' => 'Alice', 'score' => 0, 'status' => 'ELIMINATED', 'eliminated_at_question' => 2],
            ['id_player' => 2, 'nickname' => 'Bob',   'score' => 0, 'status' => 'ELIMINATED', 'eliminated_at_question' => 2],
            ['id_player' => 3, 'nickname' => 'Carl',  'score' => 0, 'status' => 'ELIMINATED', 'eliminated_at_question' => 1],
        ];

        $ranked = WagerCalculator::calculateFinalRanking($players);

        $this->assertEquals(1, $ranked[0]['rank'], "Alice should be 1st tied");
        $this->assertEquals(1, $ranked[1]['rank'], "Bob should be 1st tied");
        $this->assert($ranked[2]['rank'] > 1, "Carl was eliminated earlier at Q1, should be ranked lower");
    }

    public function testCheckGameEndOnlyOneActiveLeaves()
    {
        // 2 players initially, after a wager question only 1 is active -> game ends
        $players = [
            ['id_player' => 1, 'status' => 'ACTIVE'],
            ['id_player' => 2, 'status' => 'ELIMINATED', 'eliminated_at_question' => 2],
        ];

        $end = WagerCalculator::checkGameEnd($players, 5, 'wager', 2);
        $this->assertEquals(true, $end['isFinished']);
        $this->assertEquals('only_one_active', $end['reason']);
        $this->assertEquals(1, $end['winner']['id_player']);
    }

    public function testCheckGameEndSoloPlayerDoesNotEndEarly()
    {
        // "Chỉ 1 người chơi trong phòng: không áp dụng điều kiện 'còn 1 người thì kết thúc'"
        $players = [
            ['id_player' => 1, 'status' => 'ACTIVE'],
        ];

        $end = WagerCalculator::checkGameEnd($players, 3, 'wager', 1);
        $this->assertEquals(false, $end['isFinished'], "Solo game should continue while questions remain");
    }

    public function testCheckGameEndAllActiveEliminatedInWager()
    {
        $players = [
            ['id_player' => 1, 'status' => 'ELIMINATED', 'eliminated_at_question' => 4],
            ['id_player' => 2, 'status' => 'ELIMINATED', 'eliminated_at_question' => 4],
            ['id_player' => 3, 'status' => 'ELIMINATED', 'eliminated_at_question' => 2],
        ];

        $end = WagerCalculator::checkGameEnd($players, 2, 'wager', 3);
        $this->assertEquals(true, $end['isFinished']);
        $this->assertEquals('all_active_eliminated', $end['reason']);
        $this->assertEquals(2, count($end['tiedWinners']), "Players 1 and 2 should be tied winners");
    }

    public function testCheckGameEndNoQuestionsLeft()
    {
        $players = [
            ['id_player' => 1, 'score' => 2000, 'status' => 'ACTIVE'],
            ['id_player' => 2, 'score' => 1500, 'status' => 'ACTIVE'],
        ];

        $end = WagerCalculator::checkGameEnd($players, 0, 'normal', 2);
        $this->assertEquals(true, $end['isFinished']);
        $this->assertEquals('no_questions_left', $end['reason']);
        $this->assertEquals(1, $end['winner']['id_player']);
    }

    public function testMissedWagersTracking()
    {
        $player = ['id_player' => 1, 'consecutive_missed_wagers' => 0];

        // 1. Missing a wager question -> counter becomes 1
        $res1 = WagerCalculator::updateMissedWagers($player, 'wager', false, 2);
        $this->assertEquals(1, $res1['consecutive_missed_wagers']);
        $this->assertEquals(false, $res1['eliminated']);

        // 2. A normal question in-between -> does not change counter!
        $player['consecutive_missed_wagers'] = 1;
        $res2 = WagerCalculator::updateMissedWagers($player, 'normal', false, 2);
        $this->assertEquals(1, $res2['consecutive_missed_wagers'], "Normal question must not change counter");
        $this->assertEquals(false, $res2['eliminated']);

        // 3. Missing another wager question -> counter becomes 2 -> eliminated! (maxMissedWagers = 2)
        $res3 = WagerCalculator::updateMissedWagers($player, 'wager', false, 2);
        $this->assertEquals(2, $res3['consecutive_missed_wagers']);
        $this->assertEquals(true, $res3['eliminated']);

        // 4. Wagering successfully -> resets counter to 0
        $res4 = WagerCalculator::updateMissedWagers(['consecutive_missed_wagers' => 1], 'wager', true, 2);
        $this->assertEquals(0, $res4['consecutive_missed_wagers']);
        $this->assertEquals(false, $res4['eliminated']);
    }
}
