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

            // Auto-heal: Ensure all existing quizzes with review mode enabled have a valid unique 6-digit PIN
            $quizzesWithoutPin = db_fetch_all(
                "SELECT id_quiz FROM quiz WHERE is_review_mode = 1 AND (review_pin IS NULL OR TRIM(review_pin) = '')"
            );
            foreach ($quizzesWithoutPin as $row) {
                $newPin = self::generateUniqueReviewPin();
                db_execute(
                    "UPDATE quiz SET review_pin = :pin WHERE id_quiz = :q",
                    [':pin' => $newPin, ':q' => $row['id_quiz']]
                );
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
                 WHERE q.review_pin = :pin AND q.is_review_mode = 1 AND (q.is_team_mode = 0 OR q.is_team_mode IS NULL) AND (q.game_type = 'classic' OR q.game_type IS NULL) AND (q.mode = 'normal' OR q.mode IS NULL)",
                [':pin' => $cleanPin]
            );

            return $quiz ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve media URL for question or answer image/video.
     *
     * @param string|null $path
     * @return string|null
     */
    public static function resolveMediaUrl(?string $path): ?string {
        if (empty($path)) {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
        $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        if (str_starts_with($cleanPath, 'assets/img/')) {
            return $baseUrl . '/' . $cleanPath;
        }
        $filename = basename($path);
        $diskPath = dirname(__DIR__) . '/assets/img/questions/' . $filename;
        if (is_file($diskPath)) {
            return $baseUrl . '/assets/img/questions/' . rawurlencode($filename);
        }
        return $baseUrl . '/' . $cleanPath;
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
            "SELECT id_question, text, image, points, time_limit, question_type, sort_order
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
            "SELECT id_answer, id_question, text, image_path, is_correct
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
                'answer_text' => $ans['text'] ?? '',
                'text'        => $ans['text'] ?? '',
                'image'       => self::resolveMediaUrl($ans['image_path'] ?? null),
                'image_path'  => $ans['image_path'] ?? null,
                'is_correct'  => intval($ans['is_correct']) === 1
            ];
        }

        $formatted = [];
        foreach ($questions as $q) {
            $qId = intval($q['id_question']);
            $formatted[] = [
                'id_question'   => $qId,
                'question_text' => $q['text'] ?? '',
                'text'          => $q['text'] ?? '',
                'image'         => self::resolveMediaUrl($q['image'] ?? null),
                'points'        => intval($q['points'] ?? 1000),
                'time_limit'    => intval($q['time_limit'] ?? 20),
                'question_type' => $q['question_type'] ?? 'multiple_choice',
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
    /**
     * Get existing PIN or generate and persist a new 6-digit PIN for a review quiz.
     */
    public static function getOrGeneratePin(int $quizId, ?string $existingPin = null): string {
        $clean = trim((string)$existingPin);
        if ($clean !== '') {
            return $clean;
        }
        $newPin = self::generateUniqueReviewPin();
        try {
            db_execute("UPDATE quiz SET review_pin = :p WHERE id_quiz = :q", [':p' => $newPin, ':q' => $quizId]);
        } catch (Throwable $e) {}
        return $newPin;
    }

    public static function buildReviewUrl(string $pin): string {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
        return rtrim($baseUrl, '/') . '/modules/review/practice.php?pin=' . urlencode(trim($pin));
    }
}
}
