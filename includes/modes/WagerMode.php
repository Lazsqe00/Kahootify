<?php

require_once __DIR__ . '/BaseQuestionMode.php';
require_once __DIR__ . '/WagerCalculator.php';

/**
 * Class WagerMode
 * Implements the Wager question mode.
 */
class WagerMode extends BaseQuestionMode
{
    public const ID = 'wager';

    public function getId(): string
    {
        return self::ID;
    }

    public function getLabel(): string
    {
        return 'Wager Mode';
    }

    public function getDescription(): string
    {
        return 'Wager your points before viewing the question. Lose wager to lose points, reach zero to be eliminated!';
    }

    public function getIcon(): string
    {
        return '🎲';
    }

    public function getSupportedQuestionTypes(): array
    {
        // Only questions with definite correct answers are supported (no polls/surveys)
        return ['multiple_choice', 'text'];
    }

    public function getDefaultConfig(): array
    {
        return [
            'wagerTimeSec'      => 10,
            'wagerTiers'        => [
                ['id' => 'tier_10',  'percent' => 0.10, 'label' => '10%'],
                ['id' => 'tier_25',  'percent' => 0.25, 'label' => '25%'],
                ['id' => 'tier_50',  'percent' => 0.50, 'label' => '50%'],
                ['id' => 'tier_100', 'percent' => 1.00, 'label' => 'ALL-IN'],
            ],
            'minWagerAmount'    => 10,
                    ];
    }

    public function validateConfig(array $config): array
    {
        $defaults = $this->getDefaultConfig();

        $wagerTimeSec = isset($config['wagerTimeSec']) ? intval($config['wagerTimeSec']) : (isset($config['wager_time_sec']) ? intval($config['wager_time_sec']) : $defaults['wagerTimeSec']);
        if ($wagerTimeSec < 5 || $wagerTimeSec > 60) {
            $wagerTimeSec = 10;
        }

        $minWagerAmount = isset($config['minWagerAmount']) ? intval($config['minWagerAmount']) : (isset($config['min_wager_amount']) ? intval($config['min_wager_amount']) : $defaults['minWagerAmount']);
        if ($minWagerAmount < 1) {
            $minWagerAmount = 10;
        }

        return [
            'wagerTimeSec'      => $wagerTimeSec,
            'wagerTiers'        => $defaults['wagerTiers'],
            'minWagerAmount'    => $minWagerAmount,
            'speedBonusEnabled' => false,
        ];
    }

    public function getPhases(array $question): array
    {
        return ['intro', 'wager', 'question', 'result', 'leaderboard'];
    }

    public function canParticipate(array $player, array $room, array $question, array $context = []): bool
    {
        $status = strtoupper((string)($player['status'] ?? 'ACTIVE'));

        // Eliminated and Spectator players can never participate in wager mode
        if ($status !== 'ACTIVE') {
            return false;
        }

        // If checking specifically for question phase participation:
        // Player must have placed a wager (must not be sitting out)
        if (isset($context['phase']) && $context['phase'] === 'question') {
            if (isset($context['has_wagered']) && !$context['has_wagered']) {
                return false;
            }
        }

        return true;
    }

    public function scoreQuestion(array $params): array
    {
        return WagerCalculator::calculateWagerResult($params);
    }

    public function afterScoring(array $player, array $scoreResult, array $room, array $question): array
    {
        $isEliminated = !empty($scoreResult['eliminated']) || ($scoreResult['newScore'] ?? $scoreResult['new_score'] ?? 0) <= 0;
        $status = $isEliminated ? 'ELIMINATED' : 'ACTIVE';
        $eliminatedAt = $isEliminated ? intval($question['id_question'] ?? 0) : ($player['eliminated_at_question'] ?? null);

        $eliminationOrder = $player['elimination_order'] ?? null;
        if ($isEliminated && empty($eliminationOrder)) {
            $orderCount = intval($room['eliminated_count'] ?? 0) + 1;
            $eliminationOrder = $orderCount;
        }

        return [
            'new_score'              => intval($scoreResult['newScore'] ?? ($scoreResult['new_score'] ?? 0)),
            'status'                 => $status,
            'eliminated_at_question' => $eliminatedAt,
            'elimination_order'      => $eliminationOrder,
            'eliminated'             => $isEliminated,
        ];
    }
}
