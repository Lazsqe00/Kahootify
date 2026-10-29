<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$quizzes = db_fetch_all(
    "SELECT q.*, (SELECT COUNT(*) FROM questions WHERE id_quiz = q.id_quiz) as question_count
     FROM quiz q
     WHERE q.id_creator = :c
     ORDER BY q.id_quiz DESC",
    [':c' => $creatorId]
);

$pageTitle = "My Kahoots | Quiz Management";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

$flash = get_flash_message();
?>

<div class="container my-5">
    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="font-weight-bold" style="color: var(--kahoot-purple);">
                <i class="fas fa-th-large mr-2"></i>My Kahoots
            </h2>
            <p class="text-muted">View, edit questions, or host a live game session.</p>
        </div>
        <a href="<?= BASE_URL ?>modules/quiz/create.php" class="btn btn-kahoot btn-kahoot-green">
            <i class="fas fa-plus mr-1"></i> Create New Quiz
        </a>
    </div>

    <div class="row">
        <?php if (empty($quizzes)): ?>
            <div class="col-12 text-center py-5">
                <div class="card border-0 shadow-sm p-5" style="border-radius: 12px;">
                    <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                    <h4>You have no quizzes yet!</h4>
                    <p class="text-muted">Get started by creating your very first trivia quiz.</p>
                    <div>
                        <a href="<?= BASE_URL ?>modules/quiz/create.php" class="btn btn-kahoot btn-kahoot-purple">
                            <i class="fas fa-plus-circle mr-1"></i> Create Kahoot Now
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($quizzes as $q): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 12px;">
                        <div style="height: 160px; background: linear-gradient(135deg, var(--kahoot-purple), var(--kahoot-purple-light)); display: flex; align-items: center; justify-content: center; position: relative; border-top-left-radius: 12px; border-top-right-radius: 12px; overflow: hidden;">
                            <img src="<?= BASE_URL ?>assets/img/cover.png" alt="Cover" style="max-height: 100%; object-fit: cover; opacity: 0.35; width: 100%;">
                            <div class="position-absolute text-center px-3">
                                <h5 class="text-white font-weight-bold mb-1"><?= htmlspecialchars($q['name']) ?></h5>
                                <span class="badge badge-warning text-dark font-weight-bold">
                                    <i class="fas fa-question-circle mr-1"></i> <?= $q['question_count'] ?> questions
                                </span>
                            </div>
                        </div>

                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <p class="text-muted small mb-3">
                                <?= !empty($q['description']) ? htmlspecialchars(mb_strimwidth($q['description'], 0, 100, '...')) : '<i>No description</i>' ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <small class="text-muted"><?= date('M d, Y', strtotime($q['quizadd'] ?? $q['created_at'] ?? 'now')) ?></small>
                                <div class="d-flex align-items-center">
                                    <a href="<?= BASE_URL ?>modules/host/create_room.php?id_quiz=<?= $q['id_quiz'] ?>" class="btn btn-sm btn-primary font-weight-bold mr-2" title="Play">
                                        <i class="fas fa-play mr-1"></i> Play
                                    </a>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary font-weight-bold dropdown-toggle" type="button" id="quizActions_<?= $q['id_quiz'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Actions">
                                            <i class="fas fa-ellipsis-v mr-1"></i> Actions
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right shadow-sm" aria-labelledby="quizActions_<?= $q['id_quiz'] ?>">
                                            <a class="dropdown-item" href="<?= BASE_URL ?>modules/quiz/edit.php?id=<?= $q['id_quiz'] ?>">
                                                <i class="fas fa-edit text-success mr-2"></i> Edit
                                            </a>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>modules/quiz/duplicate.php?id=<?= $q['id_quiz'] ?>">
                                                <i class="fas fa-copy text-info mr-2"></i> Duplicate
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>modules/quiz/delete.php?id=<?= $q['id_quiz'] ?>" onclick="return confirm('Are you sure you want to delete this quiz?');">
                                                <i class="fas fa-trash-alt mr-2"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
