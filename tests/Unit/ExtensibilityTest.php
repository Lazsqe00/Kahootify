<?php

require_once __DIR__ . '/../../includes/modes/QuestionModeInterface.php';
require_once __DIR__ . '/../../includes/modes/BaseQuestionMode.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';
require_once __DIR__ . '/../../includes/modes/GameStateManager.php';

/**
 * Mock mode created to prove that a developer can add an entirely new question mode
 * with a unique phase and custom lifecycle WITHOUT modifying the core game engine.
 */
class DummySurvivalMode extends BaseQuestionMode
{
    public function getId(): string
    {
        return 'dummy-survival';
    }

    public function getLabel(): string
    {
        return 'Sinh tồn (Dummy Survival)';
    }

    public function getDescription(): string
    {
        return 'Chế độ thử nghiệm với phase chuẩn bị riêng (prep phase) và luật loại tức thì.';
    }

    public function getIcon(): string
    {
        return 'fas fa-shield-alt';
    }

    public function getSupportedQuestionTypes(): array
    {
        return ['multiple_choice', 'true_false'];
    }

    public function getDefaultConfig(): array
    {
        return [
            'prep_time_sec' => 5,
            'multiplier' => 2,
        ];
    }

    public function validateConfig(array $config): array
    {
        $prepTime = intval($config['prep_time_sec'] ?? 5);
        if ($prepTime < 3 || $prepTime > 60) {
            $prepTime = 5;
        }
        return [
            'prep_time_sec' => $prepTime,
            'multiplier' => max(1, intval($config['multiplier'] ?? 2)),
        ];
    }

    public function getPhases(array $question): array
    {
        // Custom phases: starts with intro, followed by 'prep', then 'question', 'result', 'leaderboard'
        return ['intro', 'prep', 'question', 'result', 'leaderboard'];
    }

    public function canParticipate(array $player, array $room, array $question, array $context = []): bool
    {
        $status = strtoupper((string)($player['status'] ?? 'ACTIVE'));
        if ($status !== 'ACTIVE') {
            return false;
        }
        // Custom rule: only active players with score >= 50 can enter this survival challenge
        return intval($player['score'] ?? 0) >= 50;
    }

    public function scoreQuestion(array $params): array
    {
        $isCorrect = !empty($params['isCorrect'] ?? ($params['is_correct'] ?? false));
        $multiplier = intval($params['config']['multiplier'] ?? 2);

        if ($isCorrect) {
            return [
                'pointsEarned' => 500 * $multiplier,
                'streakBonus'  => 100,
                'totalDelta'   => 500 * $multiplier + 100,
                'isEliminated' => false,
            ];
        }

        return [
            'pointsEarned' => 0,
            'streakBonus'  => 0,
            'totalDelta'   => -200,
            'isEliminated' => true,
        ];
    }

    public function afterScoring(array $player, array $scoreResult, array $room, array $question): array
    {
        return [
            'status' => !empty($scoreResult['isEliminated']) ? 'ELIMINATED' : 'ACTIVE',
            'shouldEndGame' => false,
        ];
    }
}

class ExtensibilityTest
{
    public function testRegisterNewModeWithoutEngineChanges(): void
    {
        $mode = new DummySurvivalMode();
        QuestionModeRegistry::register($mode);

        assert(QuestionModeRegistry::has('dummy-survival'), 'Registry must recognize newly registered dummy-survival mode');
        $resolved = QuestionModeRegistry::get('dummy-survival');
        assert($resolved->getId() === 'dummy-survival', 'Registry must return the registered instance');
        assert($resolved->getLabel() === 'Sinh tồn (Dummy Survival)');
        assert($resolved->getSupportedQuestionTypes() === ['multiple_choice', 'true_false']);
    }

    public function testEngineDispatchesCustomPhases(): void
    {
        $question = [
            'id_question' => 888,
            'mode' => 'dummy-survival',
            'mode_config' => json_encode(['prep_time_sec' => 8, 'multiplier' => 3])
        ];

        // Core engine GameStateManager queries initial phase from registry without any if (mode === '...')
        $initialStatus = GameStateManager::getInitialQuestionStatus($question);
        assert($initialStatus === 'prep_888', "Engine must route to 'prep_888' based on mode's custom phase sequence (got {$initialStatus})");

        // Engine parses status correctly
        $parsed = GameStateManager::parseStatus($initialStatus);
        assert($parsed['phase'] === 'prep', "Engine parser must extract custom phase 'prep'");
        assert($parsed['question_id'] === 888, "Engine parser must extract question_id 888");
    }

    public function testEngineDispatchesCustomParticipationAndScoring(): void
    {
        $mode = QuestionModeRegistry::get('dummy-survival');

        // Participation rule test
        $lowScorePlayer = ['id_player' => 1, 'status' => 'ACTIVE', 'score' => 40];
        $eligiblePlayer = ['id_player' => 2, 'status' => 'ACTIVE', 'score' => 100];
        $eliminatedPlayer = ['id_player' => 3, 'status' => 'ELIMINATED', 'score' => 200];

        assert($mode->canParticipate($lowScorePlayer, [], []) === false, 'Player with score < 50 should not participate in dummy-survival');
        assert($mode->canParticipate($eligiblePlayer, [], []) === true, 'Player with score >= 50 should participate');
        assert($mode->canParticipate($eliminatedPlayer, [], []) === false, 'Eliminated player should not participate');

        // Scoring pure function test
        $winScore = $mode->scoreQuestion([
            'isCorrect' => true,
            'config' => ['multiplier' => 3]
        ]);
        assert($winScore['totalDelta'] === 1600, 'Correct answer with multiplier 3 should give 500*3 + 100 = 1600');
        assert($winScore['isEliminated'] === false);

        $afterWin = $mode->afterScoring($eligiblePlayer, $winScore, [], []);
        assert($afterWin['status'] === 'ACTIVE');

        $loseScore = $mode->scoreQuestion([
            'isCorrect' => false,
            'config' => ['multiplier' => 3]
        ]);
        assert($loseScore['totalDelta'] === -200);
        assert($loseScore['isEliminated'] === true);

        $afterLose = $mode->afterScoring($eligiblePlayer, $loseScore, [], []);
        assert($afterLose['status'] === 'ELIMINATED');
    }
}
