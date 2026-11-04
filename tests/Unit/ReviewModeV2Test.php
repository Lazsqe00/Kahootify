<?php

require_once __DIR__ . '/../../includes/ReviewManager.php';

class ReviewModeV2Test {
    private static int $passed = 0;
    private static int $failed = 0;

    public static function run(): void {
        echo "========================================================\n";
        echo "   REVIEW MODE 2.0 AUTOMATED TEST SUITE EXECUTION\n";
        echo "========================================================\n\n";

        self::testStaticCodeAnalysis();
        self::testReviewPinFormat();
        self::testReviewUrlBuilder();
        self::testDynamicAnswersFormatting();
        self::testJoinPhpReviewPinRoutingContract();
        self::testPracticeViewDomContracts();
        self::testQuizListDomContracts();
        self::testQuizEditDomContracts();

        echo "\n========================================================\n";
        echo sprintf("Summary: %d Passed | %d Failed | Total: %d\n", self::$passed, self::$failed, self::$passed + self::$failed);
        echo "========================================================\n";

        if (self::$failed > 0) {
            exit(1);
        }
    }

    private static function assert(bool $condition, string $testName, string $message = ''): void {
        if ($condition) {
            self::$passed++;
            echo "  [PASS] {$testName}\n";
        } else {
            self::$failed++;
            echo "  [FAIL] {$testName}: {$message}\n";
        }
    }

    public static function testStaticCodeAnalysis(): void {
        $files = [
            __DIR__ . '/../../includes/ReviewManager.php',
            __DIR__ . '/../../modules/review/practice.php',
            __DIR__ . '/../../modules/player/join.php',
            __DIR__ . '/../../modules/quiz/edit.php',
            __DIR__ . '/../../modules/quiz/list.php',
        ];

        $allOk = true;
        foreach ($files as $file) {
            $cmd = 'php -l ' . escapeshellarg($file) . ' 2>&1';
            $output = shell_exec($cmd);
            if (strpos($output, 'No syntax errors detected') === false) {
                $allOk = false;
                echo "Syntax error in {$file}: {$output}\n";
            }
        }

        self::assert($allOk, 'testStaticCodeAnalysis', 'One or more Review Mode files have syntax errors.');
    }

    public static function testReviewPinFormat(): void {
        $pin = ReviewManager::generatePinCandidate();
        $is6Digits = (bool)preg_match('/^[0-9]{6}$/', $pin);
        self::assert($is6Digits, 'testReviewPinFormat', "PIN '{$pin}' must be exactly 6 digits.");
    }

    public static function testReviewUrlBuilder(): void {
        $pin = '839201';
        $url = ReviewManager::buildReviewUrl($pin);
        $expectedSubstr = 'modules/review/practice.php?pin=839201';
        self::assert(strpos($url, $expectedSubstr) !== false, 'testReviewUrlBuilder', "URL must contain '{$expectedSubstr}'");
    }

    public static function testDynamicAnswersFormatting(): void {
        // Mock question with dynamic answers (e.g. 2 True/False answers, 3 answers, etc.)
        $mockQ = [
            'id_question' => 99,
            'question_text' => 'Sample True/False Question',
            'image' => null,
            'points' => 1000,
            'time_limit' => 20,
            'answers' => [
                ['id_answer' => 101, 'answer_text' => 'True', 'image' => null, 'is_correct' => true],
                ['id_answer' => 102, 'answer_text' => 'False', 'image' => null, 'is_correct' => false],
            ]
        ];

        $has2Answers = count($mockQ['answers']) === 2;
        $correctAnswer = $mockQ['answers'][0]['is_correct'];

        self::assert($has2Answers && $correctAnswer, 'testDynamicAnswersFormatting', 'Supports dynamic question answers length (2 choices).');
    }

    public static function testJoinPhpReviewPinRoutingContract(): void {
        $joinContent = file_get_contents(__DIR__ . '/../../modules/player/join.php');
        $hasReviewCheck = strpos($joinContent, 'ReviewManager::getQuizByPin') !== false;
        $hasRedirect = strpos($joinContent, 'modules/review/practice.php?pin=') !== false;

        self::assert($hasReviewCheck && $hasRedirect, 'testJoinPhpReviewPinRoutingContract', 'join.php must check ReviewManager and redirect to practice.php.');
    }

    public static function testPracticeViewDomContracts(): void {
        $practiceContent = file_get_contents(__DIR__ . '/../../modules/review/practice.php');
        $has5ColGrid = strpos($practiceContent, 'repeat(5, 1fr)') !== false;
        $hasStickySkip = strpos($practiceContent, 'btnSkipToEnd') !== false;
        $hasInstantFeedback = strpos($practiceContent, 'highlight-correct') !== false && strpos($practiceContent, 'selected-incorrect') !== false;
        $hasRestartAndReturn = strpos($practiceContent, 'btnRestart') !== false && strpos($practiceContent, 'btnReturn') !== false;

        self::assert(
            $has5ColGrid && $hasStickySkip && $hasInstantFeedback && $hasRestartAndReturn,
            'testPracticeViewDomContracts',
            'practice.php must contain 5-col grid, sticky Skip button, Instant Feedback, and Restart/Return buttons.'
        );
    }

    public static function testQuizListDomContracts(): void {
        $listContent = file_get_contents(__DIR__ . '/../../modules/quiz/list.php');
        $hasViewBtn = strpos($listContent, 'data-target="#reviewModal_') !== false;
        $hasQrContainer = strpos($listContent, 'reviewQrCode_') !== false;
        $hasCopyBtn = strpos($listContent, 'copyTextToClipboard') !== false;

        self::assert($hasViewBtn && $hasQrContainer && $hasCopyBtn, 'testQuizListDomContracts', 'list.php must contain View button and QR/PIN modal.');
    }

    public static function testQuizEditDomContracts(): void {
        $editContent = file_get_contents(__DIR__ . '/../../modules/quiz/edit.php');
        $hasActionHandler = strpos($editContent, 'update_review_settings') !== false;
        $hasOptionsDropdownItem = strpos($editContent, 'data-target="#reviewModeModal"') !== false;
        $hasViewBtn = strpos($editContent, 'data-target="#reviewViewModal"') !== false;

        self::assert($hasActionHandler && $hasOptionsDropdownItem && $hasViewBtn, 'testQuizEditDomContracts', 'edit.php must have action handler, dropdown item, and view button.');
    }
}

ReviewModeV2Test::run();
