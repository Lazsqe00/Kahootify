<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);

// Xử lý xóa ván đấu nếu có yêu cầu
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $roomId = intval($_GET['id_room'] ?? $_GET['id'] ?? 0);
    if ($roomId > 0) {
        $check = db_fetch_one("SELECT id_room FROM room WHERE id_room = :r AND id_creator = :c", [
            ':r' => $roomId,
            ':c' => $creatorId
        ]);
        if ($check) {
            db_execute("DELETE FROM room WHERE id_room = :r", [':r' => $roomId]);
            set_flash_message('success', 'Báo cáo ván đấu đã được xóa thành công!');
        } else {
            set_flash_message('danger', 'Không tìm thấy ván đấu hoặc bạn không có quyền xóa!');
        }
    }
    redirect('modules/reports/list.php');
}

// Lấy danh sách các phòng đã kết thúc của giáo viên hiện tại
$sql = "
    SELECT 
        r.id_room,
        r.pin,
        r.roomadd,
        r.roomupdate,
        q.id_quiz,
        q.name as quiz_name,
        COUNT(p.id_player) as total_players,
        COALESCE(MAX(p.score), 0) as top_score,
        (
            SELECT nickname 
            FROM players 
            WHERE id_room = r.id_room AND is_kicked = 0 
            ORDER BY score DESC, playeradd ASC 
            LIMIT 1
        ) as winner_nickname
    FROM room r
    JOIN quiz q ON r.id_quiz = q.id_quiz
    LEFT JOIN players p ON r.id_room = p.id_room AND p.is_kicked = 0
    WHERE r.id_creator = :c AND r.status = 'finished'
    GROUP BY r.id_room
    ORDER BY r.id_room DESC
";

$reports = db_fetch_all($sql, [':c' => $creatorId]);

// Tính toán các chỉ số tóm tắt nhanh
$totalCompletedGames = count($reports);
$totalClassParticipants = array_sum(array_column($reports, 'total_players'));
$allTimeTopScore = !empty($reports) ? max(array_column($reports, 'top_score')) : 0;

$pageTitle = "Báo cáo & Thống kê ván đấu | Reports & Analytics";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container my-4">
    <!-- Tiêu đề trang -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="font-weight-bold mb-1" style="color: var(--kahoot-purple);">
                <i class="fas fa-chart-pie mr-2"></i>Báo cáo & Thống kê ván đấu
            </h2>
            <p class="text-muted mb-0">Theo dõi, phân tích kết quả học tập và xuất bảng điểm chi tiết các ván đấu đã tổ chức.</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-kahoot btn-kahoot-purple">
                <i class="fas fa-play-circle mr-1"></i> Tổ chức ván mới
            </a>
        </div>
    </div>

    <!-- Khối thẻ chỉ số tổng quan nhanh -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #46178F, #6a2cc9); border-radius: 12px;">
                <div class="card-body d-flex justify-content-between align-items-center p-3">
                    <div>
                        <div class="text-uppercase small font-weight-bold" style="opacity: 0.85;">Ván đấu đã hoàn thành</div>
                        <h2 class="font-weight-bold mb-0 mt-1"><?= number_format($totalCompletedGames) ?></h2>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.35;">
                        <i class="fas fa-trophy"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #1368ce, #2980b9); border-radius: 12px;">
                <div class="card-body d-flex justify-content-between align-items-center p-3">
                    <div>
                        <div class="text-uppercase small font-weight-bold" style="opacity: 0.85;">Tổng lượt người chơi</div>
                        <h2 class="font-weight-bold mb-0 mt-1"><?= number_format($totalClassParticipants) ?></h2>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.35;">
                        <i class="fas fa-user-friends"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #26890c, #2ecc71); border-radius: 12px;">
                <div class="card-body d-flex justify-content-between align-items-center p-3">
                    <div>
                        <div class="text-uppercase small font-weight-bold" style="opacity: 0.85;">Điểm kỷ lục cao nhất</div>
                        <h2 class="font-weight-bold mb-0 mt-1"><?= number_format($allTimeTopScore) ?> <small style="font-size: 0.9rem;">pts</small></h2>
                    </div>
                    <div style="font-size: 2.5rem; opacity: 0.35;">
                        <i class="fas fa-crown"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng danh sách báo cáo -->
    <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
            <h5 class="font-weight-bold mb-0 text-dark">
                <i class="fas fa-history mr-2 text-primary"></i>Lịch sử các ván đấu đã kết thúc
            </h5>
            <span class="badge badge-pill badge-secondary py-2 px-3"><?= count($reports) ?> ván đấu</span>
        </div>

        <div class="card-body p-0">
            <?php if (empty($reports)): ?>
                <div class="text-center py-5 px-3">
                    <div class="mb-3">
                        <i class="fas fa-clipboard-list fa-4x text-muted" style="opacity: 0.5;"></i>
                    </div>
                    <h4 class="font-weight-bold text-dark">Chưa có báo cáo ván đấu nào!</h4>
                    <p class="text-muted max-w-500 mx-auto" style="max-width: 500px;">
                        Các ván đấu Kahoot sau khi bạn tổ chức và kết thúc sẽ tự động được lưu trữ và tổng hợp phân tích học tập chi tiết tại đây.
                    </p>
                    <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-kahoot btn-kahoot-green font-weight-bold">
                        <i class="fas fa-play mr-1"></i> Tổ chức ván Kahoot ngay
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>Tên bài Quiz</th>
                                <th class="text-center" style="width: 130px;">Mã PIN</th>
                                <th style="width: 170px;">Thời gian tổ chức</th>
                                <th class="text-center" style="width: 120px;">Người chơi</th>
                                <th style="width: 200px;">Người dẫn đầu / Điểm</th>
                                <th class="text-center" style="width: 260px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $index => $rep): ?>
                                <tr>
                                    <td class="text-center font-weight-bold text-muted">
                                        <?= $index + 1 ?>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold text-dark" style="font-size: 1.05rem;">
                                            <a href="<?= BASE_URL ?>modules/reports/detail.php?id_room=<?= $rep['id_room'] ?>" class="text-dark text-decoration-none">
                                                <?= htmlspecialchars($rep['quiz_name']) ?>
                                            </a>
                                        </div>
                                        <small class="text-muted">ID ván: #<?= $rep['id_room'] ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-warning text-dark font-weight-bold px-2 py-1" style="font-size: 0.95rem; letter-spacing: 1px;">
                                            <?= htmlspecialchars($rep['pin']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <div><i class="far fa-calendar-alt mr-1"></i><?= date('d/m/Y', strtotime($rep['roomadd'] ?? 'now')) ?></div>
                                        <div><i class="far fa-clock mr-1"></i><?= date('H:i', strtotime($rep['roomadd'] ?? 'now')) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-pill badge-info px-3 py-1 font-weight-bold">
                                            <i class="fas fa-users mr-1"></i><?= intval($rep['total_players']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($rep['total_players'] > 0 && !empty($rep['winner_nickname'])): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-crown text-warning mr-2" title="Quán quân ván đấu"></i>
                                                <div>
                                                    <span class="font-weight-bold text-dark"><?= htmlspecialchars($rep['winner_nickname']) ?></span>
                                                    <span class="badge badge-success font-weight-bold ml-1"><?= number_format($rep['top_score']) ?> pts</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small font-italic">Không có người chơi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <!-- Xem chi tiết -->
                                            <a href="<?= BASE_URL ?>modules/reports/detail.php?id_room=<?= $rep['id_room'] ?>" 
                                               class="btn btn-sm btn-primary font-weight-bold px-2 py-1" 
                                               title="Xem báo cáo chi tiết">
                                                <i class="fas fa-eye mr-1"></i> Chi tiết
                                            </a>

                                            <!-- Xuất Excel / CSV -->
                                            <a href="<?= BASE_URL ?>modules/reports/export_csv.php?id_room=<?= $rep['id_room'] ?>" 
                                               class="btn btn-sm btn-success font-weight-bold px-2 py-1" 
                                               title="Tải bảng điểm CSV">
                                                <i class="fas fa-file-download mr-1"></i> CSV
                                            </a>

                                            <!-- Xóa báo cáo ván này -->
                                            <a href="<?= BASE_URL ?>modules/reports/list.php?action=delete&id_room=<?= $rep['id_room'] ?>" 
                                               class="btn btn-sm btn-outline-danger px-2 py-1" 
                                               onclick="return confirm('Bạn có chắc chắn muốn xóa báo cáo ván đấu PIN: <?= htmlspecialchars($rep['pin']) ?> (Bài: <?= htmlspecialchars(addslashes($rep['quiz_name'])) ?>)? Dữ liệu điểm số liên quan sẽ bị xóa.');" 
                                               title="Xóa báo cáo ván này">
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
