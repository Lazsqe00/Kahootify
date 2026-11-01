<?php

require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';
require_once __DIR__ . '/../../includes/modes/NormalMode.php';

class ModeRegistryTest
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

    public function testRegistryContainsNormalAndWagerModes()
    {
        QuestionModeRegistry::reset();
        $this->assert(QuestionModeRegistry::has('normal'), "Registry should have 'normal' mode");
        $this->assert(QuestionModeRegistry::has('wager'), "Registry should have 'wager' mode");

        $wager = QuestionModeRegistry::get('wager');
        $this->assertEquals('wager', $wager->getId());
        $this->assertEquals(['intro', 'wager', 'question', 'result', 'leaderboard'], $wager->getPhases([]));
    }

    public function testFallbackToNormalOnUnknownMode()
    {
        QuestionModeRegistry::reset();
        $mode = QuestionModeRegistry::get('non_existent_mode_xyz');
        $this->assert($mode instanceof NormalMode, "Unknown mode must fallback to NormalMode");
        $this->assertEquals('normal', $mode->getId());

        $emptyMode = QuestionModeRegistry::get('');
        $this->assertEquals('normal', $emptyMode->getId());

        $nullMode = QuestionModeRegistry::get(null);
        $this->assertEquals('normal', $nullMode->getId());
    }

    public function testDynamicRegistration()
    {
        QuestionModeRegistry::reset();

        $dummyMode = new class extends BaseQuestionMode {
            public function getId(): string { return 'mock_test_mode'; }
            public function getLabel(): string { return 'Mock Mode'; }
            public function getDescription(): string { return 'Testing custom mode'; }
            public function getIcon(): string { return '🧪'; }
            public function getSupportedQuestionTypes(): array { return ['multiple_choice']; }
            public function getPhases(array $question): array { return ['intro', 'mock_phase', 'result']; }
            public function canParticipate(array $player, array $room, array $question, array $context = []): bool { return true; }
            public function scoreQuestion(array $params): array { return ['points_earned' => 999]; }
            public function afterScoring(array $player, array $scoreResult, array $room, array $question): array { return []; }
        };

        QuestionModeRegistry::register($dummyMode);
        $this->assert(QuestionModeRegistry::has('mock_test_mode'), "Custom mode should be registered");

        $retrieved = QuestionModeRegistry::get('mock_test_mode');
        $this->assertEquals('mock_test_mode', $retrieved->getId());
        $this->assertEquals(['intro', 'mock_phase', 'result'], $retrieved->getPhases([]));
    }
}
