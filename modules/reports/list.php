<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $roomId = intval($_GET['id_room'] ?? $_GET['id'] ?? 0);
    if ($roomId > 0) {
        $check = db_fetch_one("SELECT id_room FROM room WHERE id_room = :r AND id_creator = :c", [
            ':r' => $roomId,
            ':c' => $creatorId
        ]);
        if ($check) {
            db_execute("DELETE FROM room WHERE id_room = :r", [':r' => $roomId]);
            set_flash_message('success', 'Đã xóa báo cáo phòng chơi thành công!');
        } else {
            set_flash_message('danger', 'Không tìm thấy phòng chơi hoặc bạn không có quyền xóa!');
        }
    }
    redirect('modules/reports/list.php');
}

// Fetch all finished rooms hosted by current creator
$sql = "
    SELECT 
        r.id_room,
        r.pin,
        r.status as room_status,
        r.roomadd,
        r.roomupdate,
        r.is_team_mode,
        q.id_quiz,
        q.name as quiz_name,
        COUNT(p.id_player) as total_players,
        COALESCE(MAX(p.score), 0) as top_score,
        (
            SELECT nickname 
            FROM players 
            WHERE id_room = r.id_room 
            ORDER BY score DESC, playeradd ASC 
            LIMIT 1
        ) as winner_nickname
    FROM room r
    JOIN quiz q ON r.id_quiz = q.id_quiz
    LEFT JOIN players p ON r.id_room = p.id_room
    WHERE r.id_creator = :c AND r.status = 'finished'
    GROUP BY r.id_room
    ORDER BY r.id_room DESC
";

$reports = db_fetch_all($sql, [':c' => $creatorId]);

// Compute summaries
$totalCompletedGames = count($reports);
$totalClassParticipants = array_sum(array_column($reports, 'total_players'));
$allTimeTopScore = !empty($reports) ? max(array_column($reports, 'top_score')) : 0;

$pageTitle = "Báo cáo phòng chơi | Kahootify";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container my-4" style="max-width: 1140px;">

    <!-- 1. Header & Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h3 class="font-weight-bold text-dark mb-1">
                <i class="fas fa-chart-line mr-2" style="color: #46178f;"></i>Báo cáo & Lịch sử phòng chơi
            </h3>
            <p class="text-muted small mb-0">
                Xem lại kết quả của các phiên chơi đã kết thúc và xuất bảng điểm.
            </p>
        </div>
        <div class="mt-2 mt-sm-0">
            <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-primary font-weight-bold px-3 py-2" style="background: #46178f; border-color: #46178f; border-radius: 8px;">
                <i class="fas fa-play mr-1"></i> Bắt đầu phòng chơi mới
            </a>
        </div>
    </div>

    <!-- 2. Clean Summary Bar -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; background: #ffffff;">
        <div class="card-body py-3 px-4">
            <div class="row text-center text-md-left">
                <div class="col-6 col-md-4 border-right py-1">
                    <div class="text-muted small font-weight-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Phòng đã hoàn thành</div>
                    <div class="h4 font-weight-bold text-dark mb-0 mt-1"><?= number_format($totalCompletedGames) ?> <small class="text-muted font-weight-normal" style="font-size: 0.85rem;">phòng</small></div>
                </div>
                <div class="col-6 col-md-4 border-right py-1">
                    <div class="text-muted small font-weight-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Tổng lượt tham gia</div>
                    <div class="h4 font-weight-bold text-dark mb-0 mt-1"><?= number_format($totalClassParticipants) ?> <small class="text-muted font-weight-normal" style="font-size: 0.85rem;">người</small></div>
                </div>
                <div class="col-12 col-md-4 py-1 mt-3 mt-md-0">
                    <div class="text-muted small font-weight-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Điểm kỷ lục</div>
                    <div class="h4 font-weight-bold text-dark mb-0 mt-1"><?= number_format($allTimeTopScore) ?> <small class="text-muted font-weight-normal" style="font-size: 0.85rem;">điểm</small></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Sessions Data Table -->
    <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden; background: #ffffff;">
        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="font-weight-bold text-dark mb-0">
                <i class="fas fa-history text-muted mr-2"></i>Danh sách các phòng đã chơi (<?= count($reports) ?>)
            </h6>
        </div>

        <?php if (empty($reports)): ?>
            <div class="text-center py-5 px-3">
                <div class="mx-auto mb-3 text-muted" style="font-size: 40px; opacity: 0.5;">
                    <i class="far fa-folder-open"></i>
                </div>
                <h6 class="font-weight-bold text-dark mb-1">Chưa có báo cáo phòng chơi nào</h6>
                <p class="text-muted mx-auto small mb-3" style="max-width: 420px;">
                    Sau khi bạn tạo phòng và hoàn thành phiên chơi trực tiếp, bảng điểm và kết quả sẽ tự động lưu tại đây.
                </p>
                <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-sm btn-primary font-weight-bold px-3 py-2" style="background: #46178f; border-color: #46178f; border-radius: 8px;">
                    <i class="fas fa-play mr-1"></i> Chơi phòng đầu tiên
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="reportsTable" style="font-size: 0.95rem;">
                    <thead class="bg-light text-muted" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        <tr>
                            <th class="py-3 px-4 border-top-0">Bài Quiz</th>
                            <th class="py-3 text-center border-top-0" style="width: 120px;">Mã PIN</th>
                            <th class="py-3 border-top-0" style="width: 180px;">Thời gian chơi</th>
                            <th class="py-3 text-center border-top-0" style="width: 130px;">Số người</th>
                            <th class="py-3 border-top-0" style="width: 220px;">Quán quân (Hạng 1)</th>
                            <th class="py-3 text-right pr-4 border-top-0" style="width: 200px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $index => $rep): ?>
                            <tr>
                                <td class="py-3 px-4">
                                    <a href="<?= BASE_URL ?>modules/reports/detail.php?id_room=<?= $rep['id_room'] ?>" class="font-weight-bold text-dark text-decoration-none">
                                        <?= htmlspecialchars($rep['quiz_name']) ?>
                                    </a>
                                    <?php if (!empty($rep['is_team_mode'])): ?>
                                        <span class="badge badge-info ml-1 font-weight-bold" style="font-size: 0.7rem; border-radius: 4px;">
                                            <i class="fas fa-users mr-1"></i>Team
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge badge-light border font-monospace text-dark px-2 py-1" style="font-size: 0.88rem; letter-spacing: 1px;">
                                        <?= htmlspecialchars($rep['pin']) ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="text-dark font-weight-medium" style="font-size: 0.9rem;">
                                        <?= date('d/m/Y', strtotime($rep['roomadd'] ?? 'now')) ?>
                                    </div>
                                    <small class="text-muted">
                                        <?= date('H:i', strtotime($rep['roomadd'] ?? 'now')) ?>
                                    </small>
                                </td>
                                <td class="py-3 text-center">
                                    <strong class="text-dark"><?= intval($rep['total_players']) ?></strong>
                                    <small class="text-muted ml-1">người</small>
                                </td>
                                <td class="py-3">
                                    <?php if ($rep['total_players'] > 0 && !empty($rep['winner_nickname'])): ?>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-crown text-warning mr-1" title="Quán quân"></i>
                                            <strong class="text-dark mr-1 text-truncate" style="max-width: 120px;" title="<?= htmlspecialchars($rep['winner_nickname']) ?>">
                                                <?= htmlspecialchars($rep['winner_nickname']) ?>
                                            </strong>
                                            <small class="badge badge-light border text-muted">
                                                <?= number_format($rep['top_score']) ?>đ
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small font-italic">Chưa có người chơi</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 text-right pr-4">
                                    <div class="d-inline-flex align-items-center" style="gap: 6px;">
                                        <a href="<?= BASE_URL ?>modules/reports/detail.php?id_room=<?= $rep['id_room'] ?>" class="btn btn-sm btn-outline-primary font-weight-bold" title="Xem chi tiết" style="border-radius: 6px; padding: 4px 10px; font-size: 0.82rem;">
                                            <i class="fas fa-eye mr-1"></i> Chi tiết
                                        </a>
                                        <a href="<?= BASE_URL ?>modules/reports/export_csv.php?id_room=<?= $rep['id_room'] ?>" class="btn btn-sm btn-outline-success font-weight-bold" title="Tải file Excel/CSV" style="border-radius: 6px; padding: 4px 10px; font-size: 0.82rem;">
                                            <i class="fas fa-file-excel mr-1"></i> CSV
                                        </a>
                                        <a href="<?= BASE_URL ?>modules/reports/list.php?action=delete&id_room=<?= $rep['id_room'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa báo cáo phòng PIN: <?= htmlspecialchars($rep['pin']) ?>?');" title="Xóa báo cáo" style="border-radius: 6px; padding: 4px 8px; font-size: 0.82rem;">
                                            <i class="far fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Integrated Pagination Footer -->
            <div id="reportsPagination" class="px-4 py-3 border-top bg-light"></div>
        <?php endif; ?>
    </div>

</div>

<script src="<?= BASE_URL ?>assets/js/reports-pagination.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('reportsTable') && document.getElementById('reportsPagination')) {
        new SaasTablePagination({
            table: '#reportsTable',
            container: '#reportsPagination',
            itemNoun: 'phòng',
            pageSize: 10,
            allowedPageSizes: [10, 25, 50]
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
