<?php

/**
 * Class WagerCalculator
 * Pure calculation functions for the Wager question mode.
 * Contains 100% deterministic logic, strictly isolated for testing.
 */
class WagerCalculator
{
    public const TIER_10  = 'tier_10';
    public const TIER_25  = 'tier_25';
    public const TIER_50  = 'tier_50';
    public const TIER_100 = 'tier_100';

    public const TIERS = [
        self::TIER_10  => ['percent' => 0.10, 'label' => '10%'],
        self::TIER_25  => ['percent' => 0.25, 'label' => '25%'],
        self::TIER_50  => ['percent' => 0.50, 'label' => '50%'],
        self::TIER_100 => ['percent' => 1.00, 'label' => 'ALL-IN'],
    ];

    /**
     * Get tier percentage or null if invalid tier ID
     */
    public static function getTierPercent(string $tierId): ?float
    {
        if (isset(self::TIERS[$tierId])) {
            return self::TIERS[$tierId]['percent'];
        }
        $clean = trim($tierId);
        return match ($clean) {
            '10%', '0.1', '0.10'         => 0.10,
            '25%', '0.25'                => 0.25,
            '50%', '0.5', '0.50'         => 0.50,
            '100%', 'ALL-IN', '1.0', '1' => 1.00,
            default                      => null,
        };
    }

    /**
     * Get human-friendly tier label / percentage string
     */
    public static function getTierLabel(string $tierId): string
    {
        $clean = trim($tierId);
        return match ($clean) {
            'tier_10', '10%', '0.1', '0.10'          => '10%',
            'tier_25', '25%', '0.25'                 => '25%',
            'tier_50', '50%', '0.5', '0.50'          => '50%',
            'tier_100', '100%', '1.0', '1', 'ALL-IN' => '100% (ALL-IN)',
            default                                  => $clean,
        };
    }

    /**
     * Calculate wager amount based on score and tier percent.
     *
     * Rule:
     * - wagerAmount = floor(currentScore * tierPercent / 10) * 10, min minWagerAmount.
     * - If currentScore < minWagerAmount, or tier is 100% (ALL-IN):
     *   wagerAmount = currentScore (full score, no rounding).
     * - wagerAmount never exceeds currentScore.
     */
    public static function calculateWagerAmount(int $currentScore, float $tierPercent, array $config = []): int
    {
        $minWagerAmount = intval($config['minWagerAmount'] ?? ($config['min_wager_amount'] ?? 10));
        if ($minWagerAmount < 1) {
            $minWagerAmount = 10;
        }

        if ($currentScore <= 0) {
            return 0;
        }

        // If score is lower than minWagerAmount OR tier is ALL-IN (100%): full score, no rounding
        if ($currentScore < $minWagerAmount || abs($tierPercent - 1.0) < 0.0001) {
            return $currentScore;
        }

        $raw = $currentScore * $tierPercent;
        $amount = (int)(floor($raw / 10.0) * 10);

        if ($amount < $minWagerAmount) {
            $amount = $minWagerAmount;
        }

        if ($amount > $currentScore) {
            $amount = $currentScore;
        }

        return $amount;
    }

    /**
     * Pure function: calculate wager result for a question.
     *
     * @param array $params
     *   - currentScore (int)
     *   - tierPercent (float)
     *   - isCorrect (bool)
     *   - speedBonus (int, optional)
     *   - config (array, optional: minWagerAmount, speedBonusEnabled)
     *
     * @return array [
     *   'wagerAmount' => int,
     *   'delta'       => int,
     *   'newScore'    => int,
     *   'eliminated'  => bool
     * ]
     */
    public static function calculateWagerResult(array $params): array
    {
        $currentScore = max(0, intval($params['currentScore'] ?? ($params['current_score'] ?? 0)));
        $tierPercent = floatval($params['tierPercent'] ?? ($params['tier_percent'] ?? 0));
        $isCorrect = !empty($params['isCorrect'] ?? ($params['is_correct'] ?? false));
        $speedBonus = intval($params['speedBonus'] ?? ($params['speed_bonus'] ?? 0));
        $config = (array)($params['config'] ?? []);
        $speedBonusEnabled = !empty($config['speedBonusEnabled'] ?? ($config['speed_bonus_enabled'] ?? false));

        $wagerAmount = self::calculateWagerAmount($currentScore, $tierPercent, $config);

        if ($isCorrect) {
            $effectiveBonus = $speedBonusEnabled ? max(0, $speedBonus) : 0;
            $delta = $wagerAmount + $effectiveBonus;
            $newScore = $currentScore + $delta;
        } else {
            $delta = -$wagerAmount;
            $newScore = max(0, $currentScore - $wagerAmount);
        }

        $eliminated = ($newScore <= 0);

        return [
            'wagerAmount' => $wagerAmount,
            'delta'       => $delta,
            'newScore'    => $newScore,
            'eliminated'  => $eliminated,
        ];
    }

    /**
     * Calculate final ranking for all players:
     * 1. ACTIVE: ordered by score DESC. Same score = tied rank.
     * 2. ELIMINATED: ordered by elimination recency (eliminated_at_question DESC, elimination_order DESC).
     *    Eliminated at the same question = tied rank.
     *    Special case: If all players in the room were eliminated in the same round and no ACTIVE remain,
     *    they all tie for 1st place.
     * 3. SPECTATOR: not ranked (rank = null).
     *
     * @param array $players Array of player arrays
     * @return array Array of players sorted with 'rank' and 'rank_label' assigned
     */
    public static function calculateFinalRanking(array $players): array
    {
        $active = [];
        $eliminated = [];
        $spectators = [];

        foreach ($players as $p) {
            $status = strtoupper((string)($p['status'] ?? 'ACTIVE'));
            if ($status === 'ACTIVE') {
                $active[] = $p;
            } elseif ($status === 'ELIMINATED') {
                $eliminated[] = $p;
            } else {
                $spectators[] = $p;
            }
        }

        // 1. Sort ACTIVE by score DESC, then playeradd / id_player ASC
        usort($active, function ($a, $b) {
            $scoreDiff = intval($b['score'] ?? 0) - intval($a['score'] ?? 0);
            if ($scoreDiff !== 0) return $scoreDiff;
            return intval($a['id_player'] ?? 0) - intval($b['id_player'] ?? 0);
        });

        // 2. Sort ELIMINATED by elimination question DESC, elimination order DESC
        usort($eliminated, function ($a, $b) {
            $qDiff = intval($b['eliminated_at_question'] ?? 0) - intval($a['eliminated_at_question'] ?? 0);
            if ($qDiff !== 0) return $qDiff;
            $oDiff = intval($b['elimination_order'] ?? 0) - intval($a['elimination_order'] ?? 0);
            if ($oDiff !== 0) return $oDiff;
            return intval($a['id_player'] ?? 0) - intval($b['id_player'] ?? 0);
        });

        $rankedResult = [];
        $currentRank = 1;

        if (!empty($active)) {
            // Rank ACTIVE players
            foreach ($active as $idx => $player) {
                if ($idx > 0) {
                    $prevScore = intval($active[$idx - 1]['score'] ?? 0);
                    $currScore = intval($player['score'] ?? 0);
                    if ($currScore === $prevScore) {
                        $player['rank'] = $active[$idx - 1]['rank'];
                    } else {
                        $player['rank'] = $idx + 1;
                    }
                } else {
                    $player['rank'] = 1;
                }
                $player['is_spectator'] = false;
                $active[$idx] = $player;
                $rankedResult[] = $player;
            }
            $currentRank = count($active) + 1;

            // Rank ELIMINATED players below all ACTIVE players
            foreach ($eliminated as $idx => $player) {
                if ($idx > 0) {
                    $prevQ = intval($eliminated[$idx - 1]['eliminated_at_question'] ?? 0);
                    $currQ = intval($player['eliminated_at_question'] ?? 0);
                    if ($currQ === $prevQ && $currQ > 0) {
                        $player['rank'] = $eliminated[$idx - 1]['rank'];
                    } else {
                        $player['rank'] = $currentRank + $idx;
                    }
                } else {
                    $player['rank'] = $currentRank;
                }
                $player['is_spectator'] = false;
                $eliminated[$idx] = $player;
                $rankedResult[] = $player;
            }
        } else {
            // No ACTIVE players remaining (e.g. all players eliminated)
            // If all players were eliminated in the same round, they all tie for 1st place!
            $firstGroupQ = !empty($eliminated) ? intval($eliminated[0]['eliminated_at_question'] ?? 0) : 0;
            $currentRankCounter = 1;

            foreach ($eliminated as $idx => $player) {
                if ($idx > 0) {
                    $prevQ = intval($eliminated[$idx - 1]['eliminated_at_question'] ?? 0);
                    $currQ = intval($player['eliminated_at_question'] ?? 0);
                    if ($currQ === $prevQ) {
                        $player['rank'] = $eliminated[$idx - 1]['rank'];
                    } else {
                        $player['rank'] = $idx + 1;
                    }
                } else {
                    $player['rank'] = 1;
                }
                $player['is_spectator'] = false;
                $eliminated[$idx] = $player;
                $rankedResult[] = $player;
            }
        }

        // SPECTATORS get no rank
        foreach ($spectators as $sp) {
            $sp['rank'] = null;
            $sp['is_spectator'] = true;
            $rankedResult[] = $sp;
        }

        return $rankedResult;
    }

    /**
     * Check game end condition:
     * - Only checks after an elimination question (wager mode).
     * - If exactly 1 ACTIVE remains (and room started with >= 2 players): ends immediately, that player wins.
     * - If 0 questions left: ends, highest score ACTIVE wins.
     * - If all ACTIVE eliminated in the same question: ends, all tie for 1st place.
     * - If room only has 1 player from the start: early 1-player win rule does NOT trigger.
     *
     * @param array $players Current list of players
     * @param int $questionsLeft Count of remaining unplayed questions
     * @param string $lastQuestionMode Mode of the question just completed
     * @param int $initialActiveCount How many active players the room started with
     * @return array [
     *   'isFinished' => bool,
     *   'reason'     => string,
     *   'winner'     => array|null,
     *   'tiedWinners'=> array
     * ]
     */
    public static function checkGameEnd(
        array $players,
        int $questionsLeft,
        string $lastQuestionMode,
        int $initialActiveCount = 0
    ): array {
        $active = [];
        $eliminated = [];

        foreach ($players as $p) {
            $status = strtoupper((string)($p['status'] ?? 'ACTIVE'));
            if ($status === 'ACTIVE') {
                $active[] = $p;
            } elseif ($status === 'ELIMINATED') {
                $eliminated[] = $p;
            }
        }

        $activeCount = count($active);

        // Case A: After a wager question
        if ($lastQuestionMode === 'wager') {
            // A1: All active players were eliminated in this question
            if ($activeCount === 0) {
                // Find those eliminated at the highest question
                $maxQ = 0;
                foreach ($eliminated as $el) {
                    $q = intval($el['eliminated_at_question'] ?? 0);
                    if ($q > $maxQ) $maxQ = $q;
                }
                $tied = array_values(array_filter($eliminated, function ($el) use ($maxQ) {
                    return intval($el['eliminated_at_question'] ?? 0) === $maxQ;
                }));

                return [
                    'isFinished'  => true,
                    'reason'      => 'all_active_eliminated',
                    'winner'      => count($tied) === 1 ? $tied[0] : null,
                    'tiedWinners' => $tied,
                ];
            }

            // A2: Exactly 1 ACTIVE remains, and the game started with >= 2 active players
            if ($activeCount === 1 && $initialActiveCount >= 2) {
                return [
                    'isFinished'  => true,
                    'reason'      => 'only_one_active',
                    'winner'      => $active[0],
                    'tiedWinners' => [$active[0]],
                ];
            }
        }

        // Case B: No questions left
        if ($questionsLeft <= 0) {
            if ($activeCount > 0) {
                usort($active, function ($a, $b) {
                    return intval($b['score'] ?? 0) - intval($a['score'] ?? 0);
                });
                $topScore = intval($active[0]['score'] ?? 0);
                $tied = array_values(array_filter($active, function ($a) use ($topScore) {
                    return intval($a['score'] ?? 0) === $topScore;
                }));

                return [
                    'isFinished'  => true,
                    'reason'      => 'no_questions_left',
                    'winner'      => count($tied) === 1 ? $tied[0] : null,
                    'tiedWinners' => $tied,
                ];
            } else {
                return [
                    'isFinished'  => true,
                    'reason'      => 'no_questions_left_all_eliminated',
                    'winner'      => null,
                    'tiedWinners' => [],
                ];
            }
        }

        // Game continues
        return [
            'isFinished'  => false,
            'reason'      => 'in_progress',
            'winner'      => null,
            'tiedWinners' => [],
        ];
    }

    /**
     * Update consecutive missed wagers counter.
     * - Only counts consecutive wager questions missed.
     * - Normal questions in-between neither reset nor increment the counter.
     * - Wagering on a wager question resets the counter to 0.
     * - If maxMissedWagers > 0 and counter >= maxMissedWagers, player is eliminated.
     *
     * @param array $player Player record
     * @param string $questionMode Mode of current question ('wager', 'normal', etc.)
     * @param bool $wagered Whether the player submitted a wager
     * @param int $maxMissedWagers Room configuration (0 = disabled)
     * @return array [
     *   'consecutive_missed_wagers' => int,
     *   'eliminated'                => bool
     * ]
     */
    public static function updateMissedWagers(
        array $player,
        string $questionMode,
        bool $wagered,
        int $maxMissedWagers
    ): array {
        $counter = intval($player['consecutive_missed_wagers'] ?? 0);

        if ($questionMode === 'wager') {
            if ($wagered) {
                $counter = 0;
            } else {
                $counter++;
            }
        }

        $eliminated = false;
        if ($maxMissedWagers > 0 && $counter >= $maxMissedWagers) {
            $eliminated = true;
        }

        return [
            'consecutive_missed_wagers' => $counter,
            'eliminated'                => $eliminated,
        ];
    }
}
