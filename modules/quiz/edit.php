<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$quizId    = intval($_GET['id'] ?? 0);

$quiz = db_fetch_one("SELECT * FROM quiz WHERE id_quiz = :q AND id_creator = :c", [
    ':q' => $quizId,
    ':c' => $creatorId
]);

if (!$quiz) {
    set_flash_message('danger', 'Quiz not found or you do not have permission to edit it!');
    redirect('modules/quiz/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_question') {
    $qText     = trim($_POST['question_text'] ?? '');
    $timeLimit = intval($_POST['time_limit'] ?? 20);
    if (!in_array($timeLimit, [10, 20, 30, 60])) {
        $timeLimit = 20;
    }
    $points    = intval($_POST['points'] ?? 1000);
    $correctIdx = intval($_POST['correct_answer'] ?? 0);
    $answers   = $_POST['answers'] ?? [];

    if (!empty($qText) && count($answers) === 4) {
        $qId = db_insert(
            "INSERT INTO questions (id_quiz, text, points, time_limit) VALUES (:qz, :txt, :pts, :time)",
            [
                ':qz'   => $quizId,
                ':txt'  => $qText,
                ':pts'  => $points,
                ':time' => $timeLimit
            ]
        );

        foreach ($answers as $idx => $ansText) {
            $isCorrect = ($idx === $correctIdx) ? 1 : 0;
            db_insert(
                "INSERT INTO answers (id_question, is_correct, text) VALUES (:qid, :corr, :txt)",
                [
                    ':qid'  => $qId,
                    ':corr' => $isCorrect,
                    ':txt'  => trim($ansText)
                ]
            );
        }

        set_flash_message('success', 'Question added successfully!');
        redirect('modules/quiz/edit.php?id=' . $quizId);
    } else {
        set_flash_message('danger', 'Please enter question text and provide all 4 answer choices!');
    }
}

if (isset($_GET['delete_question'])) {
    $delQId = intval($_GET['delete_question']);
    db_execute("DELETE FROM questions WHERE id_question = :qid AND id_quiz = :quizId", [
        ':qid'    => $delQId,
        ':quizId' => $quizId
    ]);
    set_flash_message('info', 'Question deleted!');
    redirect('modules/quiz/edit.php?id=' . $quizId);
}

$questions = db_fetch_all("SELECT * FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $quizId]);
foreach ($questions as &$q) {
    $q['answers'] = db_fetch_all("SELECT * FROM answers WHERE id_question = :qid ORDER BY id_answer ASC", [':qid' => $q['id_question']]);
}
unset($q);

$pageTitle = "Edit Quiz: " . htmlspecialchars($quiz['name']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container my-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="<?= BASE_URL ?>modules/quiz/list.php" class="text-muted font-weight-bold">
                <i class="fas fa-chevron-left mr-1"></i> Back to My Kahoots
            </a>
            <h2 class="font-weight-bold mt-1" style="color: var(--kahoot-purple);">
                <?= htmlspecialchars($quiz['name']) ?>
            </h2>
        </div>
        <div>
            <a href="<?= BASE_URL ?>modules/host/create_room.php?id_quiz=<?= $quiz['id_quiz'] ?>" class="btn btn-kahoot btn-kahoot-purple">
                <i class="fas fa-play mr-1"></i> Host Game Now
            </a>
        </div>
    </div>

    <div class="row">

        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-header bg-white py-3">
                    <h5 class="font-weight-bold mb-0 text-success">
                        <i class="fas fa-plus-circle mr-2"></i>Add New Question
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="add_question">

                        <div class="form-group">
                            <label class="font-weight-bold">Question Text <span class="text-danger">*</span></label>
                            <input type="text" name="question_text" class="form-control" required>
                        </div>

                        <div class="row">
                            <div class="col-6 form-group">
                                <label class="font-weight-bold">Time Limit</label>
                                <select name="time_limit" class="form-control">
                                    <option value="10">10 seconds</option>
                                    <option value="20" selected>20 seconds (Standard)</option>
                                    <option value="30">30 seconds</option>
                                    <option value="60">60 seconds</option>
                                </select>
                            </div>
                            <div class="col-6 form-group">
                                <label class="font-weight-bold">Points</label>
                                <select name="points" class="form-control">
                                    <option value="1000" selected>1,000 points (Standard)</option>
                                    <option value="2000">2,000 points (Double)</option>
                                    <option value="500">500 points</option>
                                </select>
                            </div>
                        </div>

                        <label class="font-weight-bold mt-2">
                            4 Answer Choices (Select radio button for CORRECT answer) <span class="text-danger">*</span>
                        </label>

                        <div class="input-group mb-2">
                            <div class="input-group-prepend">
                                <span class="input-group-text text-white font-weight-bold" style="background-color: var(--kahoot-red); min-width: 55px;">
                                    🔺 1
                                </span>
                                <div class="input-group-text bg-white">
                                    <input type="radio" name="correct_answer" value="0" checked title="Select if this is the correct answer">
                                </div>
                            </div>
                            <input type="text" name="answers[]" class="form-control" required>
                        </div>

                        <div class="input-group mb-2">
                            <div class="input-group-prepend">
                                <span class="input-group-text text-white font-weight-bold" style="background-color: var(--kahoot-blue); min-width: 55px;">
                                    🔷 2
                                </span>
                                <div class="input-group-text bg-white">
                                    <input type="radio" name="correct_answer" value="1" title="Select if this is the correct answer">
                                </div>
                            </div>
                            <input type="text" name="answers[]" class="form-control" required>
                        </div>

                        <div class="input-group mb-2">
                            <div class="input-group-prepend">
                                <span class="input-group-text text-white font-weight-bold" style="background-color: var(--kahoot-yellow); min-width: 55px;">
                                    🟡 3
                                </span>
                                <div class="input-group-text bg-white">
                                    <input type="radio" name="correct_answer" value="2" title="Select if this is the correct answer">
                                </div>
                            </div>
                            <input type="text" name="answers[]" class="form-control" required>
                        </div>

                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text text-white font-weight-bold" style="background-color: var(--kahoot-green); min-width: 55px;">
                                    🟩 4
                                </span>
                                <div class="input-group-text bg-white">
                                    <input type="radio" name="correct_answer" value="3" title="Select if this is the correct answer">
                                </div>
                            </div>
                            <input type="text" name="answers[]" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-kahoot btn-kahoot-green btn-block">
                            <i class="fas fa-plus mr-1"></i> Save & Add Question
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="font-weight-bold mb-0 text-dark">
                        <i class="fas fa-list-ol mr-2 text-primary"></i>Questions List (<?= count($questions) ?>)
                    </h5>
                    <?php if (count($questions) > 0): ?>
                        <span class="badge badge-success px-3 py-1">Ready to host</span>
                    <?php else: ?>
                        <span class="badge badge-warning px-3 py-1">No questions yet</span>
                    <?php endif; ?>
                </div>

                <div class="card-body p-3">
                    <?php if (empty($questions)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-question-circle fa-3x mb-3" style="opacity: 0.4;"></i>
                            <p>Add your first question using the form on the left!</p>
                        </div>
                    <?php else: ?>
                        <?php
                        $colors = [KAHOOT_COLOR_RED, KAHOOT_COLOR_BLUE, KAHOOT_COLOR_YELLOW, KAHOOT_COLOR_GREEN];
                        $icons  = [KAHOOT_ICON_RED, KAHOOT_ICON_BLUE, KAHOOT_ICON_YELLOW, KAHOOT_ICON_GREEN];
                        ?>
                        <?php foreach ($questions as $idx => $q): ?>
                            <div class="border rounded p-3 mb-3 bg-light position-relative shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge badge-primary font-weight-bold px-2 py-1">
                                        Question <?= $idx + 1 ?> (<?= $q['time_limit'] ?>s - <?= $q['points'] ?> pts)
                                    </span>
                                    <a href="<?= BASE_URL ?>modules/quiz/edit.php?id=<?= $quizId ?>&delete_question=<?= $q['id_question'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Delete this question?');"
                                       title="Delete question">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                                <h6 class="font-weight-bold text-dark mb-2"><?= htmlspecialchars($q['text']) ?></h6>

                                <div class="row no-gutters">
                                    <?php foreach ($q['answers'] as $aIdx => $a): ?>
                                        <div class="col-6 p-1">
                                            <div class="p-2 text-white small rounded font-weight-bold d-flex align-items-center justify-content-between" style="background-color: <?= $colors[$aIdx % 4] ?>;">
                                                <span><?= $icons[$aIdx % 4] ?> <?= htmlspecialchars($a['text']) ?></span>
                                                <?php if ($a['is_correct']): ?>
                                                    <span class="badge badge-light text-dark font-weight-bold ml-1">✓ CORRECT</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
