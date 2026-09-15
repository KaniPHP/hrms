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
            $s = $conn->prepare('DELETE FROM monthly_attendance_processing WHERE id=?');
            $s->bind_param('i', $id);
            $s->execute();
            auditLog($conn, 'monthly_processing', $id, 'delete');
            redirectWithFlash('/admin/processing.php', 'Processing run deleted.');
        }
        $month = trim((string)($_POST['process_month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) throw new InvalidArgumentException('Enter a valid processing month.');
        $status = $_POST['status'] ?? 'pending';
        if (!in_array($status, ['pending', 'processing', 'completed', 'review_required'], true)) throw new InvalidArgumentException('Invalid processing status.');
        $employees = (int)$conn->query('SELECT COUNT(*) c FROM employees')->fetch_assoc()['c'];
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $s = $conn->prepare('SELECT COUNT(*) c FROM attendance_records WHERE attendance_date BETWEEN ? AND ?');
        $s->bind_param('ss', $start, $end);
        $s->execute();
        $records = (int)$s->get_result()->fetch_assoc()['c'];
        $completed = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        if ($action === 'update') {
            $id = postInt('id');
            $duplicate = $conn->prepare('SELECT id FROM monthly_attendance_processing WHERE process_month=? AND id<>? LIMIT 1');
            $duplicate->bind_param('si', $month, $id);
            $duplicate->execute();
            if ($duplicate->get_result()->num_rows > 0) {
                throw new InvalidArgumentException('A processing run already exists for this month and year. Edit the existing run instead.');
            }
            $s = $conn->prepare('UPDATE monthly_attendance_processing SET process_month=?,status=?,total_employees=?,total_attendance_records=?,completed_at=? WHERE id=?');
            $s->bind_param('ssiisi', $month, $status, $employees, $records, $completed, $id);
            $s->execute();
        } else {
            $duplicate = $conn->prepare('SELECT id FROM monthly_attendance_processing WHERE process_month=? LIMIT 1');
            $duplicate->bind_param('s', $month);
            $duplicate->execute();
            if ($duplicate->get_result()->num_rows > 0) {
                throw new InvalidArgumentException('A processing run already exists for this month and year. Edit the existing run instead.');
            }
            $s = $conn->prepare('INSERT INTO monthly_attendance_processing (process_month,processed_by,status,total_employees,total_attendance_records,completed_at) VALUES (?,NULL,?,?,?,?)');
            $s->bind_param('ssiis', $month, $status, $employees, $records, $completed);
        }
        $s->execute();
        auditLog($conn, 'monthly_processing', $action === 'update' ? $id : $s->insert_id, $action === 'update' ? 'update' : 'create', ['process_month' => $month, 'status' => $status]);
        redirectWithFlash('/admin/processing.php', $action === 'update' ? 'Processing run updated.' : 'Processing run created.');
    }
} catch (Throwable $e) {
    if (isAjaxRequest()) sendAjaxError($e);
    flash($e->getMessage(), 'error');
}
$edit = null;
if (isset($_GET['edit'])) {
    $s = $conn->prepare('SELECT * FROM monthly_attendance_processing WHERE id=?');
    $id = (int)$_GET['edit'];
    $s->bind_param('i', $id);
    $s->execute();
    $edit = $s->get_result()->fetch_assoc();
}
$pageSize = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalRows = (int)$conn->query('SELECT COUNT(*) AS total FROM monthly_attendance_processing')->fetch_assoc()['total'];
$totalPages = max(1, (int)ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$rows = $conn->prepare('SELECT * FROM monthly_attendance_processing ORDER BY created_at DESC LIMIT ? OFFSET ?');
$rows->bind_param('ii', $pageSize, $offset);
$rows->execute();
$rows = $rows->get_result();
$notice = consumeFlash();
adminHeader('Monthly Processing', 'processing');
?>
<?php if ($notice): ?><div class="flash <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endif; ?><div class="page-heading">
    <div>
        <p class="eyebrow">AUTOMATED CONTROL CENTRE</p>
        <h2><?= $edit ? 'Edit processing run' : 'Monthly attendance processing' ?></h2>
    </div><a class="btn btn-primary" href="<?= BASE_URL ?>/admin/processing.php"><?= $edit ? 'Cancel edit' : '＋ New run' ?></a>
</div>
<div class="panel form-panel">
    <form method="post" data-ajax-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?>
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
            <label>Process month<input type="month" name="process_month" required value="<?= e($edit['process_month'] ?? date('Y-m')) ?>"></label>
            <label>Status<select name="status">
                    <?php foreach (['pending', 'processing', 'completed', 'review_required'] as $v): ?>
                        <option value="<?= $v ?>" <?= (($edit['status'] ?? 'pending') === $v) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $v))) ?></option>
                    <?php endforeach; ?>
                </select></label>
        </div>
        <p class="muted">Employee and attendance totals are calculated from current schema data.</p>
        <button class="btn btn-primary"><?= $edit ? 'Update run' : 'Create run' ?></button>
    </form>
</div>
<div class="panel table-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Status</th>
                    <th>Employees</th>
                    <th>Attendance records</th>
                    <th>Completed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody><?php if (!$rows->num_rows): ?><tr>
                        <td colspan="6" class="empty-state">No monthly runs yet.</td>
                    </tr><?php endif;
                        while ($row = $rows->fetch_assoc()): ?><tr>
                        <td><?= e($row['process_month']) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></td>
                        <td><?= (int)$row['total_employees'] ?></td>
                        <td><?= (int)$row['total_attendance_records'] ?></td>
                        <td><?= e((string)$row['completed_at']) ?></td>
                        <td><a class="btn btn-small" href="?edit=<?= (int)$row['id'] ?>">Edit</a>
                            <form class="inline-form" method="post" data-ajax-form onsubmit="return confirm('Delete processing run?')">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button class="btn btn-small">Delete</button>
                            </form>
                        </td>
                    </tr><?php endwhile; ?></tbody>
        </table>
    </div>
</div>
<?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Monthly processing pages"><?php for ($i = 1; $i <= $totalPages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
<?php adminFooter(); ?>