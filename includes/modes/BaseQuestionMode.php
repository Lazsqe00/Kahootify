<?php

require_once __DIR__ . '/QuestionModeInterface.php';

/**
 * Class BaseQuestionMode
 * Base abstract class providing common default behaviors for question modes.
 */
abstract class BaseQuestionMode implements QuestionModeInterface
{
    public function isQuestionTypeSupported(string $questionType): bool
    {
        return in_array($questionType, $this->getSupportedQuestionTypes(), true);
    }

    public function getDefaultConfig(): array
    {
        return [];
    }

    public function validateConfig(array $config): array
    {
        return array_merge($this->getDefaultConfig(), $config);
    }

    public function renderEditorSettings(array $question): string
    {
        return '';
    }

    public function renderHostPhaseView(string $phase, array $data): string
    {
        return '';
    }

    public function renderPlayerPhaseView(string $phase, array $data): string
    {
        return '';
    }
}
