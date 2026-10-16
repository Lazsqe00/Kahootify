<?php
/**
 * THÀNH VIÊN 1: Dashboard Thống kê và Quản lý chung của Creator
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$pageTitle = "Dashboard | Kahoot!";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

$creatorId = intval($_SESSION['id_creator']);

// Thống kê nhanh
$totalQuizzes = db_count('quiz', 'id_creator = :c', [':c' => $creatorId]);
$totalRooms   = db_count('room', 'id_creator = :c', [':c' => $creatorId]);
$totalPlayers = db_fetch_one(
    "SELECT COUNT(*) as total FROM players p JOIN room r ON p.id_room = r.id_room WHERE r.id_creator = :c",
    [':c' => $creatorId]
)['total'] ?? 0;

// Lấy danh sách Quizzes của người dùng
$quizzes = db_fetch_all(
    "SELECT q.*, (SELECT COUNT(*) FROM questions WHERE id_quiz = q.id_quiz) as question_count 
     FROM quiz q 
     WHERE q.id_creator = :c 
     ORDER BY q.id_quiz DESC",
    [':c' => $creatorId]
);
?>

<div class="container my-4">
    <!-- Header Dashboard -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="font-weight-bold mb-1" style="color: var(--kahoot-purple);">
                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
            </h2>
            <p class="text-muted mb-0">Welcome, <b><?= htmlspecialchars($_SESSION['nameCreator']) ?></b>! Manage your quizzes and hosted games.</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>modules/quiz/create.php" class="btn btn-kahoot btn-kahoot-green">
                <i class="fas fa-plus-circle mr-2"></i> Create New Kahoot
            </a>
        </div>
    </div>

    <!-- 3 Thẻ thống kê nhanh -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #46178F, #6a2cc9); border-radius: 10px;">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <div>
                        <h6 class="text-uppercase mb-2 font-weight-bold" style="opacity: 0.85;">Kahoots Created</h6>
                        <h2 class="display-4 font-weight-bold mb-0"><?= $totalQuizzes ?></h2>
                    </div>
                    <div style="font-size: 3rem; opacity: 0.3;">
                        <i class="fas fa-question-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #1368ce, #2980b9); border-radius: 10px;">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <div>
                        <h6 class="text-uppercase mb-2 font-weight-bold" style="opacity: 0.85;">Hosted Sessions</h6>
                        <h2 class="display-4 font-weight-bold mb-0"><?= $totalRooms ?></h2>
                    </div>
                    <div style="font-size: 3rem; opacity: 0.3;">
                        <i class="fas fa-broadcast-tower"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #26890c, #2ecc71); border-radius: 10px;">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <div>
                        <h6 class="text-uppercase mb-2 font-weight-bold" style="opacity: 0.85;">Total Players</h6>
                        <h2 class="display-4 font-weight-bold mb-0"><?= $totalPlayers ?></h2>
                    </div>
                    <div style="font-size: 3rem; opacity: 0.3;">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Danh sách Kahoots -->
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="font-weight-bold mb-0 text-dark">
                <i class="fas fa-list-alt mr-2 text-primary"></i>My Kahoots List
            </h5>
            <span class="badge badge-secondary badge-pill py-2 px-3"><?= count($quizzes) ?> quizzes</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($quizzes)): ?>
                <div class="text-center py-5">
                    <img src="<?= BASE_URL ?>assets/img/cover.png" alt="No quiz" class="img-fluid mb-3" style="max-height: 120px; opacity: 0.6;">
                    <h5 class="text-muted font-weight-bold">You haven't created any quizzes yet!</h5>
                    <p class="text-muted">Click the "Create New Kahoot" button above to get started.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Quiz Name</th>
                                <th class="text-center" style="width: 140px;">Questions</th>
                                <th style="width: 180px;">Created Date</th>
                                <th class="text-center" style="width: 280px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $index => $q): ?>
                                <tr>
                                    <td class="font-weight-bold text-muted"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="font-weight-bold text-dark" style="font-size: 1.05rem;">
                                            <?= htmlspecialchars($q['name']) ?>
                                        </div>
                                        <?php if (!empty($q['description'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars(mb_strimwidth($q['description'], 0, 70, '...')) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-pill <?= $q['question_count'] > 0 ? 'badge-primary' : 'badge-warning' ?> px-3 py-2">
                                            <?= $q['question_count'] ?> Qs
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('M d, Y H:i', strtotime($q['quizadd'] ?? $q['created_at'] ?? 'now')) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <!-- Chơi ngay (Host phòng) -->
                                            <a href="<?= BASE_URL ?>modules/host/create_room.php?id_quiz=<?= $q['id_quiz'] ?>" class="btn btn-sm btn-primary font-weight-bold px-3" title="Host game room">
                                                <i class="fas fa-play mr-1"></i> Play
                                            </a>
                                            <!-- Chỉnh sửa câu hỏi -->
                                            <a href="<?= BASE_URL ?>modules/quiz/edit.php?id=<?= $q['id_quiz'] ?>" class="btn btn-sm btn-success font-weight-bold px-3" title="Edit quiz">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </a>
                                            <!-- Xóa -->
                                            <a href="<?= BASE_URL ?>modules/quiz/delete.php?id=<?= $q['id_quiz'] ?>" class="btn btn-sm btn-danger font-weight-bold px-3" onclick="return confirm('Are you sure you want to delete this quiz and all its questions?');" title="Delete quiz">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
