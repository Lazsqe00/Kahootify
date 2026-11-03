<?php

require_once __DIR__ . '/BaseQuestionMode.php';

/**
 * Class NormalMode
 * Default Kahootify question mode. Preserves 100% of existing behavior:
 * - All ACTIVE players participate.
 * - Points calculated based on answer correctness, speed, and answer streak.
 * - No players are eliminated.
 * - Standard phase lifecycle: [INTRO, QUESTION, RESULT, LEADERBOARD].
 */
class NormalMode extends BaseQuestionMode
{
    public const ID = 'normal';

    public function getId(): string
    {
        return self::ID;
    }

    public function getLabel(): string
    {
        return 'Classic Quiz';
    }

    public function getDescription(): string
    {
        return 'Standard quiz mode, scoring based on response speed and answer streak.';
    }

    public function getIcon(): string
    {
        return '⚡';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['multiple_choice', 'text'];
    }

    public function getPhases(array $question): array
    {
        return ['intro', 'question', 'result', 'leaderboard'];
    }

    public function canParticipate(array $player, array $room, array $question, array $context = []): bool
    {
        $status = strtoupper((string)($player['status'] ?? 'ACTIVE'));

        if ($status === 'ACTIVE') {
            return true;
        }

        // Eliminated players can only play normal questions if the room explicitly allows it
        if ($status === 'ELIMINATED' && !empty($room['eliminated_can_play_normal'])) {
            return true;
        }

        // SPECTATOR and unauthorized ELIMINATED cannot participate
        return false;
    }

    public function scoreQuestion(array $params): array
    {
        $currentScore = intval($params['current_score'] ?? ($params['score'] ?? 0));
        $isCorrect = !empty($params['is_correct']);
        $basePoints = intval($params['points'] ?? 1000);
        $timeLimit = max(1, intval($params['time_limit'] ?? 20));
        $timeElapsed = max(0, intval($params['time_elapsed'] ?? 0));
        $currentStreak = intval($params['current_streak'] ?? 0);

        if ($isCorrect) {
            $ratio = min(1.0, $timeElapsed / $timeLimit);
            $pointsEarned = intval(round($basePoints * (1.0 - ($ratio / 2.0))));
            $newStreak = $currentStreak + 1;

            if ($newStreak === 2) {
                $streakBonus = 100;
            } elseif ($newStreak === 3) {
                $streakBonus = 200;
            } elseif ($newStreak === 4) {
                $streakBonus = 300;
            } elseif ($newStreak >= 5) {
                $streakBonus = 500;
            } else {
                $streakBonus = 0;
            }

            $totalDelta = $pointsEarned + $streakBonus;
            $newScore = $currentScore + $totalDelta;

            return [
                'points_earned' => $pointsEarned,
                'streak_bonus'  => $streakBonus,
                'total_delta'   => $totalDelta,
                'new_score'     => $newScore,
                'new_streak'    => $newStreak,
                'is_correct'    => true,
                'eliminated'    => false,
            ];
        }

        return [
            'points_earned' => 0,
            'streak_bonus'  => 0,
            'total_delta'   => 0,
            'new_score'     => $currentScore,
            'new_streak'    => 0,
            'is_correct'    => false,
            'eliminated'    => false,
        ];
    }

    public function afterScoring(array $player, array $scoreResult, array $room, array $question): array
    {
        // Normal mode never eliminates players
        return [
            'new_score'              => $scoreResult['new_score'],
            'new_streak'             => $scoreResult['new_streak'],
            'status'                 => $player['status'] ?? 'ACTIVE',
            'eliminated_at_question' => $player['eliminated_at_question'] ?? null,
            'elimination_order'      => $player['elimination_order'] ?? null,
            'eliminated'             => false,
        ];
    }
}
