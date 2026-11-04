<?php

require_once __DIR__ . '/../config/database.php';

if (!class_exists('ReviewManager')) {
class ReviewManager {
    /**
     * Generate a 6-digit PIN candidate string.
     *
     * @return string
     */
    public static function generatePinCandidate(): string {
        return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique 6-digit review PIN that does not collide with
     * active rooms or other active review quizzes.
     *
     * @return string
     */
    public static function generateUniqueReviewPin(): string {
        $attempts = 0;
        $maxAttempts = 100;

        do {
            $pin = self::generatePinCandidate();
            $attempts++;

            try {
                $roomCollision = db_fetch_one(
                    "SELECT id_room FROM room WHERE pin = :pin AND status != 'finished'",
                    [':pin' => $pin]
                );

                $quizCollision = false;
                try {
                    $quizCollision = db_fetch_one(
                        "SELECT id_quiz FROM quiz WHERE review_pin = :pin AND is_review_mode = 1",
                        [':pin' => $pin]
                    );
                } catch (Throwable $e) {
                    $quizCollision = false;
                }

                if (!$roomCollision && !$quizCollision) {
                    return $pin;
                }
            } catch (Throwable $e) {
                // Database offline or unreachable (e.g. static unit tests)
                return $pin;
            }
        } while ($attempts < $maxAttempts);

        return str_pad((string)(time() % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Ensure the review_pin column exists in the quiz table (self-healing migration).
     */
    public static function ensureSchema(): void {
        static $checked = false;
        if ($checked) return;

        try {
            $col = db_fetch_one("SHOW COLUMNS FROM `quiz` LIKE 'review_pin'");
            if (!$col) {
                db_execute("ALTER TABLE `quiz` ADD COLUMN `review_pin` VARCHAR(10) NULL DEFAULT NULL AFTER `is_review_mode`");
            }
            $checked = true;
        } catch (Throwable $e) {
            // Silently continue if DB connection isn't available during offline static analysis
        }
    }

    /**
     * Enable review mode for a quiz and assign a PIN.
     *
     * @param int $quizId
     * @param int $creatorId
     * @return array ['success' => bool, 'pin' => string, 'message' => string]
     */
    public static function enableReviewMode(int $quizId, int $creatorId): array {
        self::ensureSchema();

        $quiz = db_fetch_one(
            "SELECT id_quiz, is_review_mode, review_pin FROM quiz WHERE id_quiz = :q AND id_creator = :c",
            [':q' => $quizId, ':c' => $creatorId]
        );

        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found or unauthorized access.'];
        }

        $pin = $quiz['review_pin'] ?? null;
        if (empty($pin)) {
            $pin = self::generateUniqueReviewPin();
        }

        db_execute(
            "UPDATE quiz SET is_review_mode = 1, review_pin = :pin WHERE id_quiz = :q AND id_creator = :c",
            [':pin' => $pin, ':q' => $quizId, ':c' => $creatorId]
        );

        return [
            'success' => true,
            'pin' => $pin,
            'message' => 'Review mode enabled successfully.'
        ];
    }

    /**
     * Disable review mode for a quiz.
     *
     * @param int $quizId
     * @param int $creatorId
     * @return array ['success' => bool, 'message' => string]
     */
    public static function disableReviewMode(int $quizId, int $creatorId): array {
        self::ensureSchema();

        $quiz = db_fetch_one(
            "SELECT id_quiz FROM quiz WHERE id_quiz = :q AND id_creator = :c",
            [':q' => $quizId, ':c' => $creatorId]
        );

        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found or unauthorized access.'];
        }

        db_execute(
            "UPDATE quiz SET is_review_mode = 0 WHERE id_quiz = :q AND id_creator = :c",
            [':q' => $quizId, ':c' => $creatorId]
        );

        return [
            'success' => true,
            'message' => 'Review mode disabled successfully.'
        ];
    }

    /**
     * Find an active review quiz by its 6-digit review PIN.
     *
     * @param string $pin
     * @return array|null
     */
    public static function getQuizByPin(string $pin): ?array {
        self::ensureSchema();

        $cleanPin = trim($pin);
        if ($cleanPin === '') {
            return null;
        }

        try {
            $quiz = db_fetch_one(
                "SELECT q.*, c.name AS creator_name
                 FROM quiz q
                 LEFT JOIN creators c ON q.id_creator = c.id_creator
                 WHERE q.review_pin = :pin AND q.is_review_mode = 1",
                [':pin' => $cleanPin]
            );

            return $quiz ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Retrieve all questions and answers for a review quiz.
     * The number of answers (2, 3, 4, etc.) and presence of images/text
     * is completely dynamic as configured in edit.php.
     *
     * @param int $quizId
     * @return array
     */
    public static function getReviewQuestions(int $quizId): array {
        $questions = db_fetch_all(
            "SELECT id_question, question_text, image, points, time_limit, sort_order
             FROM questions
             WHERE id_quiz = :q
             ORDER BY sort_order ASC, id_question ASC",
            [':q' => $quizId]
        );

        if (empty($questions)) {
            return [];
        }

        $questionIds = array_column($questions, 'id_question');
        $inClause = implode(',', array_map('intval', $questionIds));

        $answers = db_fetch_all(
            "SELECT id_answer, id_question, answer_text, image, is_correct
             FROM answers
             WHERE id_question IN ($inClause)
             ORDER BY id_question ASC, id_answer ASC"
        );

        $answersByQ = [];
        foreach ($answers as $ans) {
            $qId = intval($ans['id_question']);
            if (!isset($answersByQ[$qId])) {
                $answersByQ[$qId] = [];
            }
            $answersByQ[$qId][] = [
                'id_answer'   => intval($ans['id_answer']),
                'answer_text' => $ans['answer_text'] ?? '',
                'image'       => $ans['image'] ?? null,
                'is_correct'  => intval($ans['is_correct']) === 1
            ];
        }

        $formatted = [];
        foreach ($questions as $q) {
            $qId = intval($q['id_question']);
            $formatted[] = [
                'id_question'   => $qId,
                'question_text' => $q['question_text'] ?? '',
                'image'         => $q['image'] ?? null,
                'points'        => intval($q['points'] ?? 1000),
                'time_limit'    => intval($q['time_limit'] ?? 20),
                'answers'       => $answersByQ[$qId] ?? []
            ];
        }

        return $formatted;
    }

    /**
     * Build the direct URL for a student to join a review session.
     *
     * @param string $pin
     * @return string
     */
    public static function buildReviewUrl(string $pin): string {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
        return rtrim($baseUrl, '/') . '/modules/review/practice.php?pin=' . urlencode(trim($pin));
    }
}
}
