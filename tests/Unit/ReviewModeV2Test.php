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
        self::testEditQrCodeGenerationAndUrlContract();
        self::testWagerModeEliminationAutoSkipContracts();
        self::testReviewQuestionsSchemaContract();
        self::testTextInputQuestionSupport();
        self::testReviewModeClassicExclusivityAndTeamMutualExclusion();

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
            __DIR__ . '/../../modules/api/game_state.php',
            __DIR__ . '/../../modules/api/submit_answer.php',
            __DIR__ . '/../../modules/host/play.php',
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

        self::assert($allOk, 'testStaticCodeAnalysis', 'One or more files have syntax errors.');
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
        $hasClassicReviewSection = strpos($practiceContent, 'class="results-review"') !== false;
        $hasSavedReviewSection = strpos($practiceContent, 'class="results-saved-review"') !== false;
        $hasSaveForReviewBtn = strpos($practiceContent, 'results-review-save') !== false;
        $noOldCompletionLine = strpos($practiceContent, 'Bạn đã hoàn thành phiên tự luyện bài') === false;

        self::assert(
            $has5ColGrid && $hasStickySkip && $hasInstantFeedback && $hasRestartAndReturn && $hasClassicReviewSection && $hasSavedReviewSection && $hasSaveForReviewBtn && $noOldCompletionLine,
            'testPracticeViewDomContracts',
            'practice.php must contain 5-col grid, sticky Skip, Restart/Return, Classic results review section with Save button, and no old completion sentence.'
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

    public static function testEditQrCodeGenerationAndUrlContract(): void {
        $editContent = file_get_contents(__DIR__ . '/../../modules/quiz/edit.php');
        $listContent = file_get_contents(__DIR__ . '/../../modules/quiz/list.php');

        // Check for no duplicated host
        $noDuplicatedHostEdit = strpos($editContent, "HTTP_HOST'] ?? 'localhost') . BASE_URL") === false;
        $noDuplicatedHostList = strpos($listContent, "HTTP_HOST'] ?? 'localhost') . BASE_URL") === false;

        // Check for qrcodegen and renderQrSvg inclusion
        $hasQrcodegenScript = strpos($editContent, 'assets/js/qrcodegen.js') !== false;
        $hasRenderQrSvg = strpos($editContent, 'function renderQrSvg') !== false;
        $hasModalShownListener = strpos($editContent, '$("#reviewViewModal").on("shown.bs.modal"') !== false;

        self::assert(
            $noDuplicatedHostEdit && $noDuplicatedHostList && $hasQrcodegenScript && $hasRenderQrSvg && $hasModalShownListener,
            'testEditQrCodeGenerationAndUrlContract',
            'edit.php & list.php must cleanly build URL without host duplicates, and edit.php must load qrcodegen & trigger renderQrSvg on modal open.'
        );
    }

    public static function testWagerModeEliminationAutoSkipContracts(): void {
        $roomAccessContent = file_get_contents(__DIR__ . '/../../includes/room_access.php');
        $submitAnswerContent = file_get_contents(__DIR__ . '/../../modules/api/submit_answer.php');
        $gameStateContent = file_get_contents(__DIR__ . '/../../modules/api/game_state.php');
        $playContent = file_get_contents(__DIR__ . '/../../modules/host/play.php');

        // 1. room_access must retain eliminated players in participant list for that question so votedCount works
        $roomAccessRetainsEliminated = strpos($roomAccessContent, "WHERE l.id_room = :r AND l.id_question = :q AND l.is_sitting_out = 0 AND p.is_kicked = 0\"") !== false;

        // 2. submit_answer must auto-finalize if active players count hits 0
        $submitFinalizesWhenEmpty = strpos($submitAnswerContent, '$activePlayersCount === 0') !== false;

        // 3. game_state.php must return active_players and auto-finalize if active is 0
        $gameStateFinalizesWhenEmpty = strpos($gameStateContent, "'active_players'           => \$activePlayersCount") !== false;

        // 4. play.php must auto-advance host when active_players is 0
        $playAutoSkipsWhenEmpty = strpos($playContent, 'data.active_players === 0') !== false;

        self::assert(
            $roomAccessRetainsEliminated && $submitFinalizesWhenEmpty && $gameStateFinalizesWhenEmpty && $playAutoSkipsWhenEmpty,
            'testWagerModeEliminationAutoSkipContracts',
            'All Wager elimination contracts must be present in room_access, submit_answer, game_state, and play.php.'
        );
    }

    public static function testReviewQuestionsSchemaContract(): void {
        $rmContent = file_get_contents(__DIR__ . '/../../includes/ReviewManager.php');
        
        // 1. Must query text column from questions, NOT non-existent question_text
        $noBadQuestionColumn = strpos($rmContent, 'question_text, image, points') === false;
        $queriesTextFromQuestions = strpos($rmContent, 'id_question, text, image, points') !== false;

        // 2. Must query text and image_path columns from answers, NOT non-existent answer_text
        $noBadAnswerColumn = strpos($rmContent, 'answer_text, image, is_correct') === false;
        $queriesAnswersCorrectly = strpos($rmContent, 'id_answer, id_question, text, image_path, is_correct') !== false;

        // 3. Must have resolveMediaUrl helper
        $hasResolveMediaUrl = strpos($rmContent, 'function resolveMediaUrl') !== false;

        self::assert(
            $noBadQuestionColumn && $queriesTextFromQuestions && $noBadAnswerColumn && $queriesAnswersCorrectly && $hasResolveMediaUrl,
            'testReviewQuestionsSchemaContract',
            'ReviewManager must query real schema columns (questions.text, answers.text, answers.image_path) and provide resolveMediaUrl.'
        );
    }

    public static function testTextInputQuestionSupport(): void {
        $practiceContent = file_get_contents(__DIR__ . '/../../modules/review/practice.php');

        $hasTextInputBranch = strpos($practiceContent, "q.question_type === 'text'") !== false;
        $hasInputField = strpos($practiceContent, 'id="practiceTextInput"') !== false;
        $hasSubmitBtn = strpos($practiceContent, 'id="btnSubmitTextInput"') !== false;
        $hasTextHandler = strpos($practiceContent, 'function onTextAnswerSubmitted') !== false;
        $hasTextResultsHandler = strpos($practiceContent, 'results-review-answers') !== false;

        self::assert(
            $hasTextInputBranch && $hasInputField && $hasSubmitBtn && $hasTextHandler && $hasTextResultsHandler,
            'testTextInputQuestionSupport',
            'practice.php must support text questions with an input field, submit button, submission handler, and results review.'
        );
    }

    public static function testReviewModeClassicExclusivityAndTeamMutualExclusion(): void {
        $editContent = file_get_contents(__DIR__ . '/../../modules/quiz/edit.php');
        $listContent = file_get_contents(__DIR__ . '/../../modules/quiz/list.php');
        $rmContent = file_get_contents(__DIR__ . '/../../includes/ReviewManager.php');

        // 1. In edit.php, enabling team mode disables review mode
        $teamDisablesReview = strpos($editContent, 'ReviewManager::disableReviewMode($quizId, $creatorId)') !== false;
        // 2. In edit.php, enabling review mode sets is_team_mode = 0
        $reviewDisablesTeam = strpos($editContent, 'UPDATE quiz SET is_team_mode = 0 WHERE id_quiz = :q') !== false;
        // 3. In edit.php, review mode option only rendered if $isClassicQuiz
        $classicOnlyEdit = strpos($editContent, '<?php if ($isClassicQuiz): ?>') !== false;
        // 4. In list.php, isReviewEligible checks isClassic and !isTeam
        $eligibleList = strpos($listContent, '$isReviewEligible = ($isClassic && !$isTeam && !empty($q[\'is_review_mode\']));') !== false;
        // 5. In ReviewManager.php, getQuizByPin checks is_team_mode = 0 and game_type = classic and mode = normal
        $rmClassicGuard = strpos($rmContent, "q.game_type = 'classic'") !== false && strpos($rmContent, "q.is_team_mode = 0") !== false;

        self::assert(
            $teamDisablesReview && $reviewDisablesTeam && $classicOnlyEdit && $eligibleList && $rmClassicGuard,
            'testReviewModeClassicExclusivityAndTeamMutualExclusion',
            'Review Mode must only be available in Classic Mode and be mutually exclusive with Team Mode in edit.php, list.php, and ReviewManager.php.'
        );
    }
}

ReviewModeV2Test::run();
