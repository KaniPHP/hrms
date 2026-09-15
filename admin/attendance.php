<?php
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/layout.php';
requireLogin();
$conn = adminDb();
$date = $_GET['date'] ?? date('Y-m-d');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $id = postInt('id');
            $s = $conn->prepare('DELETE FROM attendance_records WHERE id=?');
            $s->bind_param('i', $id);
            $s->execute();
            redirectWithFlash('/admin/attendance.php?date=' . urlencode($date), 'Attendance deleted.');
        }
        $employee = postInt('employee_id');
        $day = postDate('attendance_date');
        $status = $_POST['status'] ?? 'present';
        $allowed = ['present', 'absent', 'half_day', 'late', 'early_out', 'holiday', 'leave', 'weekly_off', 'manual_adjustment'];
        if (!in_array($status, $allowed, true)) throw new InvalidArgumentException('Invalid attendance status.');
        $work = max(0, (int)$_POST['total_work_minutes']);
        $late = max(0, (int)$_POST['late_minutes']);
        $early = max(0, (int)$_POST['early_out_minutes']);
        $overtime = max(0, (int)$_POST['overtime_minutes']);
        $remarks = trim((string)($_POST['remarks'] ?? ''));
        $shift = (int)($_POST['shift_id'] ?? 0) ?: null;
        if ($action === 'update') {
            $id = postInt('id');
            $s = $conn->prepare('UPDATE attendance_records SET employee_id=?,attendance_date=?,shift_id=?,status=?,total_work_minutes=?,late_minutes=?,early_out_minutes=?,overtime_minutes=?,remarks=? WHERE id=?');
            $s->bind_param('isisiiiisi', $employee, $day, $shift, $status, $work, $late, $early, $overtime, $remarks, $id);
            $s->execute();
        } else {
            $s = $conn->prepare('INSERT INTO attendance_records (employee_id,attendance_date,shift_id,status,total_work_minutes,late_minutes,early_out_minutes,overtime_minutes,remarks) VALUES (?,?,?,?,?,?,?,?,?)');
            $s->bind_param('isisiiiis', $employee, $day, $shift, $status, $work, $late, $early, $overtime, $remarks);
            $s->execute();
        }
        redirectWithFlash('/admin/attendance.php?date=' . urlencode($day), $action === 'update' ? 'Attendance updated.' : 'Attendance created.');
    }
} catch (Throwable $e) {
    if (isAjaxRequest()) sendAjaxError($e);
    flash($e->getMessage(), 'error');
}
$edit = null;
if (isset($_GET['edit'])) {
    $s = $conn->prepare('SELECT * FROM attendance_records WHERE id=?');
    $id = (int)$_GET['edit'];
    $s->bind_param('i', $id);
    $s->execute();
    $edit = $s->get_result()->fetch_assoc();
}
$employees = $conn->query('SELECT id,employee_code,full_name FROM employees ORDER BY full_name');
$rows = $conn->prepare("SELECT a.*,e.employee_code,e.full_name FROM attendance_records a JOIN employees e ON e.id=a.employee_id WHERE a.attendance_date=? ORDER BY e.full_name");
$rows->bind_param('s', $date);
$rows->execute();
$result = $rows->get_result();
$notice = consumeFlash();
adminHeader('Attendance', 'attendance');
?>
<?php if ($notice): ?><div class="flash <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endif; ?><div class="page-heading">
    <div>
        <p class="eyebrow">PUNCH TO ATTENDANCE</p>
        <h2><?= $edit ? 'Edit attendance' : 'Daily attendance' ?></h2>
    </div>
    <form class="inline-form"><input type="date" name="date" value="<?= e($date) ?>">
        <button class="btn btn-primary">View date</button>
    </form>
</div>
<div class="panel form-panel">
    <form method="post" data-ajax-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?><div class="form-grid"><label>Employee<select name="employee_id" required><?php while ($emp = $employees->fetch_assoc()): ?><option value="<?= (int)$emp['id'] ?>" <?= (($edit['employee_id'] ?? '') == $emp['id']) ? 'selected' : '' ?>><?= e($emp['employee_code'] . ' - ' . $emp['full_name']) ?></option><?php endwhile; ?></select></label><label>Date<input type="date" name="attendance_date" required value="<?= e($edit['attendance_date'] ?? $date) ?>"></label><label>Status<select name="status"><?php foreach (['present', 'absent', 'half_day', 'late', 'early_out', 'holiday', 'leave', 'weekly_off', 'manual_adjustment'] as $v): ?><option value="<?= $v ?>" <?= (($edit['status'] ?? 'present') === $v) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $v))) ?></option><?php endforeach; ?></select></label><label>Work minutes<input type="number" min="0" name="total_work_minutes" value="<?= (int)($edit['total_work_minutes'] ?? 0) ?>"></label><label>Late minutes<input type="number" min="0" name="late_minutes" value="<?= (int)($edit['late_minutes'] ?? 0) ?>"></label><label>Early-out minutes<input type="number" min="0" name="early_out_minutes" value="<?= (int)($edit['early_out_minutes'] ?? 0) ?>"></label><label>Overtime minutes<input type="number" min="0" name="overtime_minutes" value="<?= (int)($edit['overtime_minutes'] ?? 0) ?>"></label><label>Remarks<input name="remarks" value="<?= e($edit['remarks'] ?? '') ?>"></label></div><button class="btn btn-primary"><?= $edit ? 'Update attendance' : 'Add attendance' ?></button>
    </form>
</div>
<div class="panel table-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Work</th>
                    <th>Late</th>
                    <th>Early out</th>
                    <th>Overtime</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody><?php if (!$result->num_rows): ?><tr>
                        <td colspan="8" class="empty-state">No attendance records for this date.</td>
                    </tr><?php endif;
                        while ($row = $result->fetch_assoc()): ?><tr>
                        <td><strong><?= e($row['full_name']) ?></strong><br><small><?= e($row['employee_code']) ?></small></td>
                        <td><?= e($row['attendance_date']) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></td>
                        <td><?= (int)$row['total_work_minutes'] ?></td>
                        <td><?= (int)$row['late_minutes'] ?></td>
                        <td><?= (int)$row['early_out_minutes'] ?></td>
                        <td><?= (int)$row['overtime_minutes'] ?></td>
                        <td><a class="btn btn-small" href="?date=<?= e($date) ?>&edit=<?= (int)$row['id'] ?>">Edit</a>
                            <form class="inline-form" method="post" data-ajax-form onsubmit="return confirm('Delete attendance?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-small">Delete</button></form>
                        </td>
                    </tr><?php endwhile; ?></tbody>
        </table>
    </div>
</div><?php adminFooter(); ?>