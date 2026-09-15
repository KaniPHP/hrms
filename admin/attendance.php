<?php
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/layout.php';
requireLogin();
$conn = adminDb();
$date = date('Y-m-d');
try {
    $date = normalizeDate((string)($_GET['date'] ?? $date), 'Please select a valid date in DD/MM/YYYY format.');
} catch (Throwable $e) {
    flash($e->getMessage(), 'error');
}
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
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Please select a valid attendance status.');
        }
        $employeeCheck = $conn->prepare('SELECT id FROM employees WHERE id=? LIMIT 1');
        $employeeCheck->bind_param('i', $employee);
        $employeeCheck->execute();
        if (!$employeeCheck->get_result()->num_rows) {
            throw new InvalidArgumentException('The selected employee could not be found. Please choose an active employee.');
        }
        $numericFields = [
            'total_work_minutes' => 'total work minutes',
            'late_minutes' => 'late minutes',
            'early_out_minutes' => 'early-out minutes',
            'overtime_minutes' => 'overtime minutes',
        ];
        $numericValues = [];
        foreach ($numericFields as $field => $label) {
            $rawValue = trim((string)($_POST[$field] ?? '0'));
            if (!preg_match('/^\d+$/', $rawValue)) {
                throw new InvalidArgumentException('Please enter a whole number of 0 or more for ' . $label . '.');
            }
            $numericValues[$field] = (int)$rawValue;
        }
        $work = $numericValues['total_work_minutes'];
        $late = $numericValues['late_minutes'];
        $early = $numericValues['early_out_minutes'];
        $overtime = $numericValues['overtime_minutes'];
        $remarks = trim((string)($_POST['remarks'] ?? ''));
        $shift = (int)($_POST['shift_id'] ?? 0) ?: null;
        if ($action === 'update') {
            $id = postInt('id');
            $duplicate = $conn->prepare('SELECT id FROM attendance_records WHERE employee_id=? AND attendance_date=? AND id<>? LIMIT 1');
            $duplicate->bind_param('isi', $employee, $day, $id);
            $duplicate->execute();
            if ($duplicate->get_result()->num_rows) {
                throw new InvalidArgumentException('Attendance already exists for this employee on ' . displayDate($day) . '. Edit the existing record instead.');
            }
            $s = $conn->prepare('UPDATE attendance_records SET employee_id=?,attendance_date=?,shift_id=?,status=?,total_work_minutes=?,late_minutes=?,early_out_minutes=?,overtime_minutes=?,remarks=? WHERE id=?');
            $s->bind_param('isisiiiisi', $employee, $day, $shift, $status, $work, $late, $early, $overtime, $remarks, $id);
            $s->execute();
        } else {
            $duplicate = $conn->prepare('SELECT id FROM attendance_records WHERE employee_id=? AND attendance_date=? LIMIT 1');
            $duplicate->bind_param('is', $employee, $day);
            $duplicate->execute();
            if ($duplicate->get_result()->num_rows) {
                throw new InvalidArgumentException('Attendance already exists for this employee on ' . displayDate($day) . '. You cannot create a duplicate record.');
            }
            $s = $conn->prepare('INSERT INTO attendance_records (employee_id,attendance_date,shift_id,status,total_work_minutes,late_minutes,early_out_minutes,overtime_minutes,remarks) VALUES (?,?,?,?,?,?,?,?,?)');
            $s->bind_param('isisiiiis', $employee, $day, $shift, $status, $work, $late, $early, $overtime, $remarks);
            $s->execute();
        }
        redirectWithFlash('/admin/attendance.php?date=' . urlencode($day), $action === 'update' ? 'Attendance updated.' : 'Attendance created.');
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    if ((int)$e->getCode() === 1062 || strpos($message, 'uniq_employee_date') !== false) {
        $duplicateDate = '';
        try {
            $duplicateDate = displayDate(normalizeDate((string)($_POST['attendance_date'] ?? '')));
        } catch (Throwable $ignored) {
            $duplicateDate = 'the selected date';
        }
        $message = 'Attendance already exists for this employee on ' . $duplicateDate . '. Please edit the existing attendance record instead of creating another one.';
    }
    if (isAjaxRequest()) sendAjaxJson(false, $message, 'error');
    flash($message, 'error');
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
$pageSize = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$countRows = $conn->prepare('SELECT COUNT(*) AS total FROM attendance_records WHERE attendance_date=?');
$countRows->bind_param('s', $date);
$countRows->execute();
$totalRows = (int)$countRows->get_result()->fetch_assoc()['total'];
$totalPages = max(1, (int)ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$rows = $conn->prepare("SELECT a.*,e.employee_code,e.full_name FROM attendance_records a JOIN employees e ON e.id=a.employee_id WHERE a.attendance_date=? ORDER BY e.full_name LIMIT ? OFFSET ?");
$rows->bind_param('sii', $date, $pageSize, $offset);
$rows->execute();
$result = $rows->get_result();
$notice = consumeFlash();
adminHeader('Attendance', 'attendance');
?>
<?php if ($notice): ?><div class="flash <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endif; ?>
<div class="page-heading">
    <div>
        <p class="eyebrow">PUNCH TO ATTENDANCE</p>
        <h2><?= $edit ? 'Edit attendance' : 'Daily attendance' ?></h2>
    </div>
    <form class="inline-form"><input type="text" name="date" value="<?= e(displayDate($date)) ?>" placeholder="dd/mm/yyyy" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" maxlength="10" aria-label="Date (dd/mm/yyyy)">
        <button class="btn btn-primary">View date</button>
    </form>
</div>
<div class="panel form-panel">
    <form method="post" data-ajax-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="form-grid"><label>Employee<select name="employee_id" required><?php while ($emp = $employees->fetch_assoc()): ?><option value="<?= (int)$emp['id'] ?>" <?= (($edit['employee_id'] ?? '') == $emp['id']) ? 'selected' : '' ?>><?= e($emp['employee_code'] . ' - ' . $emp['full_name']) ?></option><?php endwhile; ?></select></label>
            <label>Date<input type="text" name="attendance_date" required value="<?= e(displayDate($edit['attendance_date'] ?? $date)) ?>" placeholder="dd/mm/yyyy" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" maxlength="10" aria-label="Attendance date (dd/mm/yyyy)"></label>
            <label>Status<select name="status"><?php foreach (['present', 'absent', 'half_day', 'late', 'early_out', 'holiday', 'leave', 'weekly_off', 'manual_adjustment'] as $v): ?><option value="<?= $v ?>" <?= (($edit['status'] ?? 'present') === $v) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $v))) ?></option><?php endforeach; ?></select></label>
            <label>Work minutes<input type="number" min="0" name="total_work_minutes" value="<?= (int)($edit['total_work_minutes'] ?? 0) ?>"></label>
            <label>Late minutes<input type="number" min="0" name="late_minutes" value="<?= (int)($edit['late_minutes'] ?? 0) ?>"></label>
            <label>Early-out minutes<input type="number" min="0" name="early_out_minutes" value="<?= (int)($edit['early_out_minutes'] ?? 0) ?>"></label>
            <label>Overtime minutes<input type="number" min="0" name="overtime_minutes" value="<?= (int)($edit['overtime_minutes'] ?? 0) ?>"></label>
            <label>Remarks<input name="remarks" value="<?= e($edit['remarks'] ?? '') ?>"></label>
        </div>

        <button class="btn btn-primary"><?= $edit ? 'Update attendance' : 'Add attendance' ?></button>
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
                        <td><?= e(displayDate($row['attendance_date'])) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></td>
                        <td><?= (int)$row['total_work_minutes'] ?></td>
                        <td><?= (int)$row['late_minutes'] ?></td>
                        <td><?= (int)$row['early_out_minutes'] ?></td>
                        <td><?= (int)$row['overtime_minutes'] ?></td>
                        <td><a class="btn btn-small" href="?date=<?= e($date) ?>&page=<?= $page ?>&edit=<?= (int)$row['id'] ?>">Edit</a>
                            <form class="inline-form" method="post" data-ajax-form onsubmit="return confirm('Delete attendance?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-small">Delete</button></form>
                        </td>
                    </tr><?php endwhile; ?></tbody>
        </table>
    </div>
</div>
<?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Attendance pages">
        <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
            <a class="<?= $pageNumber === $page ? 'active' : '' ?>" href="?date=<?= e($date) ?>&page=<?= $pageNumber ?>"><?= $pageNumber ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
<?php adminFooter(); ?>