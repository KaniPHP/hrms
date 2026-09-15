<?php
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/layout.php';
requireLogin();
$conn = adminDb();
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $id = postInt('id');
            $s = $conn->prepare('DELETE FROM shifts WHERE id=?');
            $s->bind_param('i', $id);
            $s->execute();
            auditLog($conn, 'shift', $id, 'delete');
            redirectWithFlash('/admin/shifts.php', 'Shift deleted.');
        }
        $name = postText('shift_name', 100);
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';
        $grace = max(0, (int)($_POST['grace_minutes'] ?? 0));
        $late = max(0, (int)($_POST['late_threshold_minutes'] ?? 0));
        $overtime = max(0, (int)($_POST['overtime_after_minutes'] ?? 0));
        $night = (int)($_POST['is_night_shift'] ?? 0);
        if (!$start || !$end) throw new InvalidArgumentException('Enter valid shift times.');
        if ($action === 'update') {
            $id = postInt('id');
            $s = $conn->prepare('UPDATE shifts SET shift_name=?,start_time=?,end_time=?,grace_minutes=?,late_threshold_minutes=?,overtime_after_minutes=?,is_night_shift=? WHERE id=?');
            $s->bind_param('sssiiiii', $name, $start, $end, $grace, $late, $overtime, $night, $id);
            $s->execute();
            auditLog($conn, 'shift', $id, 'update', ['shift_name' => $name, 'start_time' => $start, 'end_time' => $end]);
            redirectWithFlash('/admin/shifts.php', 'Shift updated.');
        }
        $s = $conn->prepare('INSERT INTO shifts (shift_name,start_time,end_time,grace_minutes,late_threshold_minutes,overtime_after_minutes,is_night_shift) VALUES (?,?,?,?,?,?,?)');
        $s->bind_param('sssiiii', $name, $start, $end, $grace, $late, $overtime, $night);
        $s->execute();
        auditLog($conn, 'shift', $s->insert_id, 'create', ['shift_name' => $name, 'start_time' => $start, 'end_time' => $end]);
        redirectWithFlash('/admin/shifts.php', 'Shift created.');
    }
} catch (Throwable $e) {
    if (isAjaxRequest()) sendAjaxError($e);
    flash($e->getMessage(), 'error');
}
$edit = null;
if (isset($_GET['edit'])) {
    $s = $conn->prepare('SELECT * FROM shifts WHERE id=?');
    $id = (int)$_GET['edit'];
    $s->bind_param('i', $id);
    $s->execute();
    $edit = $s->get_result()->fetch_assoc();
}
$pageSize = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalRows = (int)$conn->query('SELECT COUNT(*) AS total FROM shifts')->fetch_assoc()['total'];
$totalPages = max(1, (int)ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$rows = $conn->prepare('SELECT * FROM shifts ORDER BY start_time LIMIT ? OFFSET ?');
$rows->bind_param('ii', $pageSize, $offset);
$rows->execute();
$rows = $rows->get_result();
$notice = consumeFlash();
adminHeader('Shift Setup', 'shifts');
?>
<?php if ($notice): ?><div class="flash <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endif; ?><div class="page-heading">
    <div>
        <p class="eyebrow">TIME CONFIGURATION</p>
        <h2><?= $edit ? 'Edit shift' : 'Shift timings' ?></h2>
    </div><a class="btn btn-primary" href="<?= BASE_URL ?>/admin/shifts.php"><?= $edit ? 'Cancel edit' : '+ Add shift' ?></a>
</div>
<div class="panel form-panel">
    <form method="post" data-ajax-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <?php endif; ?><div class="form-grid">
            <label>Shift name<input name="shift_name" required value="<?= e($edit['shift_name'] ?? '') ?>"></label>
            <label>Start time<input type="time" name="start_time" required value="<?= e(substr($edit['start_time'] ?? '09:00', 0, 5)) ?>"></label>
            <label>End time<input type="time" name="end_time" required value="<?= e(substr($edit['end_time'] ?? '18:00', 0, 5)) ?>"></label>
            <label>Grace minutes<input type="number" min="0" name="grace_minutes" value="<?= (int)($edit['grace_minutes'] ?? 10) ?>"></label>
            <label>Late threshold<input type="number" min="0" name="late_threshold_minutes" value="<?= (int)($edit['late_threshold_minutes'] ?? 15) ?>"></label>
            <label>Overtime after<input type="number" min="0" name="overtime_after_minutes" value="<?= (int)($edit['overtime_after_minutes'] ?? 60) ?>"></label>
            <label><input type="checkbox" name="is_night_shift" value="1" <?= !empty($edit['is_night_shift']) ? 'checked' : '' ?>> Overnight shift</label>
        </div>
        <button class="btn btn-primary"><?= $edit ? 'Update shift' : 'Create shift' ?></button>
    </form>
</div>
<div class="card-grid">
    <?php while ($row = $rows->fetch_assoc()): ?>
        <div class="shift-card">
            <div class="shift-icon">⏱</div>
            <h3><?= e($row['shift_name']) ?></h3>
            <div class="shift-time"><?= e(substr($row['start_time'], 0, 5)) ?>
                <span>→</span> <?= e(substr($row['end_time'], 0, 5)) ?>
            </div>
            <p>Grace <?= (int)$row['grace_minutes'] ?> min · Late after <?= (int)$row['late_threshold_minutes'] ?> min</p>
            <a class="btn btn-small" href="?edit=<?= (int)$row['id'] ?>">Edit</a>
            <form class="inline-form" method="post" data-ajax-form onsubmit="return confirm('Delete this shift?')">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button class="btn btn-small">Delete</button>
            </form>
        </div>
    <?php endwhile; ?>
</div>
<?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Shift pages"><?php for ($i = 1; $i <= $totalPages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>

<?php adminFooter(); ?>