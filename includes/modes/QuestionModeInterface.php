<?php

/**
 * Interface QuestionModeInterface
 * Defines the contract that every question mode must implement.
 */
interface QuestionModeInterface
{
    /**
     * Unique identifier for the mode (e.g. 'normal', 'wager')
     */
    public function getId(): string;

    /**
     * Human-readable label for UI display
     */
    public function getLabel(): string;

    /**
     * Short description of how the mode works
     */
    public function getDescription(): string;

    /**
     * Icon representation (emoji or icon class, e.g. '⚡', '🎲')
     */
    public function getIcon(): string;

    /**
     * List of supported question types ('multiple_choice', 'text', etc.)
     * Excludes non-scorable questions like polls/surveys if mode requires correct answer.
     */
    public function getSupportedQuestionTypes(): array;

    /**
     * Check if a specific question type is supported by this mode
     */
    public function isQuestionTypeSupported(string $questionType): bool;

    /**
     * Default configuration for this mode (e.g. wager time limit, tiers)
     */
    public function getDefaultConfig(): array;

    /**
     * Validate and sanitize mode configuration
     */
    public function validateConfig(array $config): array;

    /**
     * Ordered list of phases for this question
     * e.g. normal: ['intro', 'question', 'result', 'leaderboard']
     *      wager:  ['intro', 'wager', 'question', 'result', 'leaderboard']
     */
    public function getPhases(array $question): array;

    /**
     * Check whether a player can participate in this question
     *
     * @param array $player Player record (including status: ACTIVE, ELIMINATED, SPECTATOR)
     * @param array $room Room state record
     * @param array $question Question record
     * @param array $context Additional context (e.g. whether player placed a wager)
     */
    public function canParticipate(array $player, array $room, array $question, array $context = []): bool;

    /**
     * Pure scoring calculation for a player's answer or lack thereof
     *
     * @param array $params Calculation parameters (currentScore, isCorrect, timeElapsed, timeLimit, config, etc.)
     * @return array Calculation outcome: pointsEarned/wagerAmount, delta, newScore, bonus, etc.
     */
    public function scoreQuestion(array $params): array;

    /**
     * Post-scoring hook (e.g. elimination check, streak update, logging)
     *
     * @param array $player Current player record
     * @param array $scoreResult Result from scoreQuestion
     * @param array $room Current room record
     * @param array $question Current question record
     * @return array Updated player state (newScore, status, eliminatedAtQuestion, etc.)
     */
    public function afterScoring(array $player, array $scoreResult, array $room, array $question): array;

    /**
     * Render the HTML settings panel in the question editor (modules/quiz/edit.php)
     */
    public function renderEditorSettings(array $question): string;

    /**
     * Render the host view for a specific phase
     */
    public function renderHostPhaseView(string $phase, array $data): string;

    /**
     * Render the player view for a specific phase
     */
    public function renderPlayerPhaseView(string $phase, array $data): string;
}
