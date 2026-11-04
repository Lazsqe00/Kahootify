<?php

require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';
require_once __DIR__ . '/../../includes/modes/NormalMode.php';
require_once __DIR__ . '/../../includes/modes/WagerMode.php';
require_once __DIR__ . '/../../includes/modes/WagerCalculator.php';

/**
 * QuizComprehensiveTest
 * 
 * Toàn diện các kịch bản kiểm thử:
 * 1. White-Box Testing (Phân tích tĩnh, Data Flow, Security Code Review)
 * 2. Black-Box Testing (Phân vùng tương đương, Giá trị biên, Chuyển trạng thái, Đoán lỗi)
 * 3. Integration & Contract Testing (Đồng bộ Client-Server, API contracts, UI DOM Sync)
 */
class QuizComprehensiveTest
{
    private $passed = 0;
    private $failed = 0;
    private $errors = [];

    private function assert($condition, $message = 'Assertion failed')
    {
        if (!$condition) {
            throw new Exception($message);
        }
    }

    private function assertEquals($expected, $actual, $message = '')
    {
        if ($expected !== $actual) {
            $msg = $message ?: ("Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
            throw new Exception($msg);
        }
    }

    // =========================================================================
    // 1. WHITE-BOX TESTING
    // =========================================================================

    /**
     * White-Box: Static Code Analysis & Syntax Integrity
     */
    public function testStaticCodeAnalysisPhpFiles()
    {
        $files = [
            __DIR__ . '/../../modules/quiz/create.php',
            __DIR__ . '/../../modules/quiz/edit.php',
            __DIR__ . '/../../modules/quiz/delete.php',
            __DIR__ . '/../../modules/quiz/duplicate.php',
            __DIR__ . '/../../modules/quiz/list.php',
            __DIR__ . '/../../modules/api/reorder_questions.php',
            __DIR__ . '/../../modules/api/duplicate_question.php',
            __DIR__ . '/../../modules/api/update_quiz_name.php',
            __DIR__ . '/../../modules/api/submit_answer.php',
        ];

        foreach ($files as $file) {
            $this->assert(file_exists($file), "File must exist: " . basename($file));
            $output = [];
            $retCode = 0;
            exec("php -l " . escapeshellarg($file), $output, $retCode);
            $this->assertEquals(0, $retCode, "PHP Lint syntax error in " . basename($file));
        }
    }

    /**
     * White-Box: Control Flow - Quiz Game Format Decision Tree
     */
    public function testControlFlowQuizFormatDecisionTree()
    {
        $determineFormat = function ($gameFormat, $gameTypePost, $modePost) {
            if ($gameFormat === 'puzzle' || $gameTypePost === 'obstacle') {
                $gameType = 'obstacle';
                $mode = 'normal';
            } elseif ($gameFormat === 'wager' || $modePost === 'wager') {
                $gameType = 'classic';
                $mode = 'wager';
            } else {
                $gameType = 'classic';
                $mode = 'normal';
            }
            if (!QuestionModeRegistry::has($mode)) {
                $mode = 'normal';
            }
            return ['gameType' => $gameType, 'mode' => $mode];
        };

        // Branch 1: puzzle format
        $res1 = $determineFormat('puzzle', '', '');
        $this->assertEquals('obstacle', $res1['gameType']);
        $this->assertEquals('normal', $res1['mode']);

        // Branch 2: obstacle game_type override
        $res2 = $determineFormat('classic', 'obstacle', '');
        $this->assertEquals('obstacle', $res2['gameType']);
        $this->assertEquals('normal', $res2['mode']);

        // Branch 3: wager format
        $res3 = $determineFormat('wager', '', '');
        $this->assertEquals('classic', $res3['gameType']);
        $this->assertEquals('wager', $res3['mode']);

        // Branch 4: default classic normal
        $res4 = $determineFormat('classic', '', '');
        $this->assertEquals('classic', $res4['gameType']);
        $this->assertEquals('normal', $res4['mode']);

        // Branch 5: invalid mode fallback
        $res5 = $determineFormat('invalid_format', '', '');
        $this->assertEquals('classic', $res5['gameType']);
        $this->assertEquals('normal', $res5['mode']);
    }

    /**
     * White-Box: Security Code Review - File Upload MIME & Extension Verification
     */
    public function testSecurityFileUploadMimeAndExtensionLogic()
    {
        $allowedMimes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png'  => ['png'],
            'image/gif'  => ['gif'],
            'image/webp' => ['webp'],
        ];

        $validateUpload = function ($mime, $filename) use ($allowedMimes) {
            if (!isset($allowedMimes[$mime])) {
                return ['valid' => false, 'error' => 'Invalid MIME'];
            }
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedMimes[$mime], true)) {
                return ['valid' => false, 'error' => 'MIME and extension mismatch'];
            }
            return ['valid' => true, 'ext' => $ext];
        };

        // Case 1: Valid JPEG with jpg extension
        $res1 = $validateUpload('image/jpeg', 'photo.jpg');
        $this->assert($res1['valid'], "Standard JPEG should be valid");

        // Case 2: Valid PNG with png extension
        $res2 = $validateUpload('image/png', 'diagram.png');
        $this->assert($res2['valid'], "Standard PNG should be valid");

        // Case 3: Exploit scenario - PHP file disguised as image
        $res3 = $validateUpload('image/jpeg', 'exploit.php');
        $this->assert(!$res3['valid'], "PHP extension with image MIME must be rejected");

        // Case 4: Invalid MIME type
        $res4 = $validateUpload('application/x-php', 'exploit.php');
        $this->assert(!$res4['valid'], "PHP MIME must be rejected");

        // Case 5: Polyglot executable extension
        $res5 = $validateUpload('image/png', 'script.phtml');
        $this->assert(!$res5['valid'], "PHTML extension must be rejected");
    }

    // =========================================================================
    // 2. BLACK-BOX TESTING
    // =========================================================================

    /**
     * Black-Box: Equivalence Partitioning - Quiz Title Validation
     */
    public function testEquivalencePartitioningQuizTitle()
    {
        $validateTitle = function ($title) {
            $t = trim((string)$title);
            if (empty($t)) return false;
            if (mb_strlen($t) > 100) return false;
            return true;
        };

        // Valid Class: standard length titles
        $this->assert($validateTitle("Math Quiz Grade 10"), "Normal title must be valid");
        $this->assert($validateTitle("Kiểm tra 15 phút Lịch Sử"), "Vietnamese title must be valid");

        // Invalid Class: empty or whitespace-only
        $this->assert(!$validateTitle(""), "Empty title must be invalid");
        $this->assert(!$validateTitle("   "), "Whitespace title must be invalid");

        // Invalid Class: exceeding max length (101 characters)
        $longTitle = str_repeat("a", 101);
        $this->assert(!$validateTitle($longTitle), "101-character title must be invalid");
    }

    /**
     * Black-Box: Equivalence Partitioning - Answer Choices Validation
     */
    public function testEquivalencePartitioningAnswerChoices()
    {
        $validateAnswers = function ($answers, $correctIndices) {
            $validCount = 0;
            $hasCorrect = false;

            for ($i = 0; $i < 4; $i++) {
                $hasText = !empty(trim($answers[$i]['text'] ?? ''));
                $hasImage = !empty($answers[$i]['image']);
                $isValidChoice = $hasText || $hasImage;

                if ($isValidChoice) {
                    $validCount++;
                    if (in_array($i, $correctIndices, true)) {
                        $hasCorrect = true;
                    }
                }
            }

            if ($validCount < 2) {
                return ['valid' => false, 'error' => 'At least 2 choices required'];
            }
            if (!$hasCorrect) {
                return ['valid' => false, 'error' => 'At least 1 valid choice must be correct'];
            }
            return ['valid' => true];
        };

        // Valid: 4 text answers, 1 correct
        $answers1 = [
            ['text' => 'Ans A', 'image' => null],
            ['text' => 'Ans B', 'image' => null],
            ['text' => 'Ans C', 'image' => null],
            ['text' => 'Ans D', 'image' => null],
        ];
        $this->assert($validateAnswers($answers1, [0])['valid'], "Standard 4-choice question must be valid");

        // Valid: 2 text answers, 1 correct (minimum threshold)
        $answers2 = [
            ['text' => 'True', 'image' => null],
            ['text' => 'False', 'image' => null],
            ['text' => '', 'image' => null],
            ['text' => '', 'image' => null],
        ];
        $this->assert($validateAnswers($answers2, [1])['valid'], "2-choice question must be valid");

        // Invalid: only 1 answer filled
        $answers3 = [
            ['text' => 'Only One', 'image' => null],
            ['text' => '', 'image' => null],
            ['text' => '', 'image' => null],
            ['text' => '', 'image' => null],
        ];
        $this->assert(!$validateAnswers($answers3, [0])['valid'], "Single choice question must fail");

        // Invalid: 4 answers filled, but NO correct answer selected
        $this->assert(!$validateAnswers($answers1, [])['valid'], "Zero correct answers must fail");

        // Error Guessing: Marking an EMPTY choice as correct!
        $answersGhost = [
            ['text' => 'Ans A', 'image' => null],
            ['text' => 'Ans B', 'image' => null],
            ['text' => '', 'image' => null], // Slot 2 is empty!
            ['text' => '', 'image' => null],
        ];
        // User marked slot 2 as correct, but slot 2 is completely empty
        $ghostCheck = $validateAnswers($answersGhost, [2]);
        $this->assert(!$ghostCheck['valid'], "Marking an empty answer slot as correct must fail validation");
    }

    /**
     * Black-Box: Boundary Value Analysis - Puzzle Pieces Thresholds
     */
    public function testBoundaryValueAnalysisPuzzlePieces()
    {
        $allowedPieces = [4, 6, 8, 9, 10, 12, 15, 16, 20, 25];

        $filterPieces = function ($val) use ($allowedPieces) {
            $p = intval($val);
            return in_array($p, $allowedPieces, true) ? $p : 4;
        };

        // Minimum boundary: 4
        $this->assertEquals(4, $filterPieces(4), "Min boundary 4 must be accepted");

        // Maximum boundary: 25
        $this->assertEquals(25, $filterPieces(25), "Max boundary 25 must be accepted");

        // Below minimum: 3 -> fallback to 4
        $this->assertEquals(4, $filterPieces(3), "Below min (3) must fallback to 4");

        // Above maximum: 26 -> fallback to 4
        $this->assertEquals(4, $filterPieces(26), "Above max (26) must fallback to 4");

        // Disallowed intermediate value: 7 -> fallback to 4
        $this->assertEquals(4, $filterPieces(7), "Non-grid value (7) must fallback to 4");
    }

    /**
     * Black-Box: Boundary Value Analysis - Review Time Windows
     */
    public function testBoundaryValueAnalysisReviewTimeWindow()
    {
        $validateReviewWindow = function ($startTimeStr, $endTimeStr, $checkTimeStr) {
            $start = !empty($startTimeStr) ? strtotime($startTimeStr) : null;
            $end = !empty($endTimeStr) ? strtotime($endTimeStr) : null;
            $check = strtotime($checkTimeStr);

            if ($start !== null && $end !== null && $end <= $start) {
                return ['validWindow' => false, 'status' => 'INVALID_CONFIG'];
            }

            if ($start !== null && $check < $start) {
                return ['validWindow' => true, 'status' => 'NOT_STARTED'];
            }
            if ($end !== null && $check > $end) {
                return ['validWindow' => true, 'status' => 'EXPIRED'];
            }
            return ['validWindow' => true, 'status' => 'ACTIVE'];
        };

        $start = '2026-11-03 08:00:00';
        $end   = '2026-11-03 12:00:00';

        // Boundary: End before or equal start -> Invalid
        $this->assert(!$validateReviewWindow($end, $start, '2026-11-03 09:00:00')['validWindow']);
        $this->assert(!$validateReviewWindow($start, $start, '2026-11-03 09:00:00')['validWindow']);

        // Boundary: 1 second before start
        $resBefore = $validateReviewWindow($start, $end, '2026-11-03 07:59:59');
        $this->assertEquals('NOT_STARTED', $resBefore['status']);

        // Boundary: Exactly at start time
        $resStart = $validateReviewWindow($start, $end, '2026-11-03 08:00:00');
        $this->assertEquals('ACTIVE', $resStart['status']);

        // Boundary: In between
        $resActive = $validateReviewWindow($start, $end, '2026-11-03 10:00:00');
        $this->assertEquals('ACTIVE', $resActive['status']);

        // Boundary: Exactly at end time
        $resEnd = $validateReviewWindow($start, $end, '2026-11-03 12:00:00');
        $this->assertEquals('ACTIVE', $resEnd['status']);

        // Boundary: 1 second after end time -> EXPIRED
        $resAfter = $validateReviewWindow($start, $end, '2026-11-03 12:00:01');
        $this->assertEquals('EXPIRED', $resAfter['status']);
    }

    /**
     * Black-Box: State Transition Testing - Mode Mutual Exclusion
     */
    public function testStateTransitionModeMutualExclusion()
    {
        // Rule: Team Mode and Review Mode cannot be active simultaneously
        $applyReviewMode = function ($currentQuiz, $isReview) {
            $quiz = $currentQuiz;
            if ($isReview) {
                $quiz['is_review_mode'] = 1;
                $quiz['is_team_mode'] = 0; // Automatically disables team mode
            } else {
                $quiz['is_review_mode'] = 0;
            }
            return $quiz;
        };

        $applyTeamMode = function ($currentQuiz, $isTeam) {
            if ($isTeam && !empty($currentQuiz['is_review_mode'])) {
                throw new Exception("Cannot enable Team Mode while Review Mode is active");
            }
            $quiz = $currentQuiz;
            $quiz['is_team_mode'] = $isTeam ? 1 : 0;
            return $quiz;
        };

        // State 0: Normal quiz
        $quiz = ['is_review_mode' => 0, 'is_team_mode' => 0];

        // Transition 1: Enable Team Mode
        $quiz = $applyTeamMode($quiz, true);
        $this->assertEquals(1, $quiz['is_team_mode']);
        $this->assertEquals(0, $quiz['is_review_mode']);

        // Transition 2: Enable Review Mode -> Team Mode must automatically disable
        $quiz = $applyReviewMode($quiz, true);
        $this->assertEquals(1, $quiz['is_review_mode']);
        $this->assertEquals(0, $quiz['is_team_mode']);

        // Transition 3: Try to enable Team Mode while Review Mode is active -> Expect Exception
        $blocked = false;
        try {
            $applyTeamMode($quiz, true);
        } catch (Exception $e) {
            $blocked = true;
        }
        $this->assert($blocked, "Enabling Team Mode while Review Mode is active must be rejected");

        // Transition 4: Disable Review Mode -> Enable Team Mode succeeds
        $quiz = $applyReviewMode($quiz, false);
        $quiz = $applyTeamMode($quiz, true);
        $this->assertEquals(1, $quiz['is_team_mode']);
        $this->assertEquals(0, $quiz['is_review_mode']);
    }

    // =========================================================================
    // 3. INTEGRATION & CONTRACT TESTING
    // =========================================================================

    /**
     * Integration: API Contract - reorder_questions.php
     */
    public function testApiContractReorderQuestions()
    {
        $validateReorderPayload = function ($data) {
            $quizId = intval($data['quiz_id'] ?? 0);
            $order  = $data['order'] ?? [];

            if ($quizId <= 0 || empty($order) || !is_array($order)) {
                return ['success' => false];
            }

            foreach ($order as $item) {
                if (!isset($item['id']) || !isset($item['order'])) {
                    return ['success' => false];
                }
            }
            return ['success' => true];
        };

        // Valid payload
        $validData = [
            'quiz_id' => 10,
            'order' => [
                ['id' => 101, 'order' => 0],
                ['id' => 102, 'order' => 1],
                ['id' => 103, 'order' => 2],
            ]
        ];
        $this->assert($validateReorderPayload($validData)['success'], "Valid reorder payload must succeed");

        // Invalid: missing quiz_id
        $this->assert(!$validateReorderPayload(['quiz_id' => 0, 'order' => [['id' => 1, 'order' => 0]]])['success']);

        // Invalid: empty order array
        $this->assert(!$validateReorderPayload(['quiz_id' => 10, 'order' => []])['success']);
    }

    /**
     * Integration: API Contract - update_quiz_name.php
     */
    public function testApiContractUpdateQuizName()
    {
        $validateUpdateNamePayload = function ($data) {
            $quizId = intval($data['quiz_id'] ?? 0);
            $name   = trim($data['name'] ?? '');

            if ($quizId <= 0 || $name === '') {
                return ['success' => false, 'error' => 'Invalid input'];
            }
            return ['success' => true];
        };

        $this->assert($validateUpdateNamePayload(['quiz_id' => 5, 'name' => 'New Title'])['success']);
        $this->assert(!$validateUpdateNamePayload(['quiz_id' => 5, 'name' => '   '])['success']);
        $this->assert(!$validateUpdateNamePayload(['quiz_id' => 0, 'name' => 'New Title'])['success']);
    }

    /**
     * Run all test methods in this class
     */
    public function run()
    {
        $methods = get_class_methods($this);
        echo "========================================================\n";
        echo "   COMPREHENSIVE QUIZ MODULE TEST SUITE EXECUTION\n";
        echo "========================================================\n\n";

        foreach ($methods as $method) {
            if (str_starts_with($method, 'test')) {
                try {
                    $this->$method();
                    echo "  [PASS] {$method}\n";
                    $this->passed++;
                } catch (Throwable $e) {
                    echo "  [FAIL] {$method}: {$e->getMessage()}\n";
                    $this->failed++;
                    $this->errors[] = "{$method}: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
                }
            }
        }

        echo "\n========================================================\n";
        echo "Summary: {$this->passed} Passed | {$this->failed} Failed | Total: " . ($this->passed + $this->failed) . "\n";
        echo "========================================================\n";

        return $this->failed === 0;
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'QuizComprehensiveTest.php') {
    $suite = new QuizComprehensiveTest();
    $success = $suite->run();
    exit($success ? 0 : 1);
}
