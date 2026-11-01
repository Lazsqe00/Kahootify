<?php

require_once __DIR__ . '/QuestionModeInterface.php';
require_once __DIR__ . '/BaseQuestionMode.php';
require_once __DIR__ . '/NormalMode.php';

/**
 * Class QuestionModeRegistry
 * Central registry for all question modes in Kahootify.
 */
class QuestionModeRegistry
{
    /**
     * @var array<string, QuestionModeInterface>
     */
    private static array $modes = [];

    /**
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Initialize built-in modes
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::register(new NormalMode());

        // WagerMode will be registered when its file exists
        if (file_exists(__DIR__ . '/WagerMode.php')) {
            require_once __DIR__ . '/WagerMode.php';
            if (class_exists('WagerMode')) {
                self::register(new WagerMode());
            }
        }

        self::$initialized = true;
    }

    /**
     * Register a new question mode
     */
    public static function register(QuestionModeInterface $mode): void
    {
        self::$modes[$mode->getId()] = $mode;
    }

    /**
     * Unregister a mode (primarily for testing)
     */
    public static function unregister(string $modeId): void
    {
        unset(self::$modes[$modeId]);
    }

    /**
     * Reset registry to initial state (useful in test suites)
     */
    public static function reset(): void
    {
        self::$modes = [];
        self::$initialized = false;
        self::init();
    }

    /**
     * Check if a mode is registered
     */
    public static function has(string $modeId): bool
    {
        self::init();
        return isset(self::$modes[$modeId]);
    }

    /**
     * Retrieve a question mode by ID.
     * Fallbacks to 'normal' with a warning log if not found.
     */
    public static function get(?string $modeId): QuestionModeInterface
    {
        self::init();
        $key = trim((string)$modeId);

        if ($key === '' || !isset(self::$modes[$key])) {
            if ($key !== '' && $key !== 'normal') {
                error_log(sprintf("[QuestionModeRegistry] Warning: Unknown mode '%s', falling back to 'normal'.", $key));
            }
            return self::$modes['normal'];
        }

        return self::$modes[$key];
    }

    /**
     * Get all registered modes
     *
     * @return array<string, QuestionModeInterface>
     */
    public static function getAll(): array
    {
        self::init();
        return self::$modes;
    }

    /**
     * Get the default mode (NormalMode)
     */
    public static function getDefault(): QuestionModeInterface
    {
        self::init();
        return self::$modes['normal'];
    }
}
