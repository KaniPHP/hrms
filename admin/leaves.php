<?php
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/layout.php';
requireLogin();
$conn = adminDb();
function markRejectedLeaveAbsent(mysqli $conn, int $employeeId, string $fromDate, string $toDate): void
{
    $employeeQuery = $conn->prepare('SELECT shift_id FROM employees WHERE id=? LIMIT 1');
    $employeeQuery->bind_param('i', $employeeId);
    $employeeQuery->execute();
    $employee = $employeeQuery->get_result()->fetch_assoc();
    if (!$employee) {
        throw new InvalidArgumentException('Employee for the leave application was not found.');
    }

    $punchQuery = $conn->prepare('SELECT COUNT(*) AS total FROM attendance_punches WHERE employee_id=? AND punch_date=?');
    $attendanceQuery = $conn->prepare(
        'INSERT INTO attendance_records
         (employee_id,attendance_date,shift_id,status,total_work_minutes,late_minutes,early_out_minutes,overtime_minutes,remarks)
         VALUES (?,?,?,"absent",0,0,0,0,?)
         ON DUPLICATE KEY UPDATE
         shift_id=VALUES(shift_id),
         status=IF(status IN ("present","late","early_out","manual_adjustment"), status, "absent"),
         remarks=IF(status IN ("present","late","early_out","manual_adjustment"), remarks, VALUES(remarks))'
    );
    $remarks = 'Absent: leave application rejected and no punch was recorded.';
    $period = new DatePeriod(
        new DateTimeImmutable($fromDate),
        new DateInterval('P1D'),
        (new DateTimeImmutable($toDate))->modify('+1 day')
    );
    foreach ($period as $date) {
        $day = $date->format('Y-m-d');
        $punchQuery->bind_param('is', $employeeId, $day);
        $punchQuery->execute();
        if ((int)$punchQuery->get_result()->fetch_assoc()['total'] > 0) {
            continue;
        }
        $shiftId = $employee['shift_id'] !== null ? (int)$employee['shift_id'] : null;
        $attendanceQuery->bind_param('isis', $employeeId, $day, $shiftId, $remarks);
        $attendanceQuery->execute();
    }
}
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $action = $_POST['action'] ?? '';
        if (in_array($action, ['approve', 'reject'], true)) {
            $id = postInt('id');
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $current = $conn->prepare('SELECT status FROM leave_applications WHERE id=?');
            $current->bind_param('i', $id);
            $current->execute();
            $application = $current->get_result()->fetch_assoc();
            if (!$application) {
                throw new InvalidArgumentException('Leave application not found.');
            }
            if ($application['status'] !== 'pending') {
                throw new InvalidArgumentException('Only pending leave applications can be reviewed.');
            }
            $conn->begin_transaction();
            $review = $conn->prepare('UPDATE leave_applications SET status=? WHERE id=? AND status="pending"');
            $review->bind_param('si', $status, $id);
            $review->execute();
            if ($review->affected_rows !== 1) {
                throw new RuntimeException('The leave application was already reviewed.');
            }
            if ($status === 'rejected') {
                $applicationDetails = $conn->prepare('SELECT employee_id,from_date,to_date FROM leave_applications WHERE id=?');
                $applicationDetails->bind_param('i', $id);
                $applicationDetails->execute();
                $details = $applicationDetails->get_result()->fetch_assoc();
                markRejectedLeaveAbsent($conn, (int)$details['employee_id'], $details['from_date'], $details['to_date']);
            }
            auditLog($conn, 'leave_application', $id, $action, ['status' => $status]);
            $conn->commit();
            redirectWithFlash('/admin/leaves.php', $status === 'approved' ? 'Leave approved.' : 'Leave rejected.');
        }
        if ($action === 'delete') {
            $id = postInt('id');
            $s = $conn->prepare('DELETE FROM leave_applications WHERE id=?');
            $s->bind_param('i', $id);
            $s->execute();
            auditLog($conn, 'leave_application', $id, 'delete');
            redirectWithFlash('/admin/leaves.php', 'Leave application deleted.');
        }
        $employee = postInt('employee_id');
        $activeEmployee = $conn->prepare('SELECT id FROM employees WHERE id=? AND status="active" LIMIT 1');
        $activeEmployee->bind_param('i', $employee);
        $activeEmployee->execute();
        if (!$activeEmployee->get_result()->num_rows) {
            throw new InvalidArgumentException('Only active employees can receive attendance or leave records.');
        }
        $type = postText('leave_type_code', 20);
        $from = postDate('from_date');
        $to = postDate('to_date');
        if ($to < $from) throw new InvalidArgumentException('The end date must follow the start date.');
        $days = (float)($_POST['days_requested'] ?? 0);
        if ($days <= 0) throw new InvalidArgumentException('Days requested must be greater than zero.');
        $status = $_POST['status'] ?? 'pending';
        if (!in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) throw new InvalidArgumentException('Invalid leave status.');
        $reason = trim((string)($_POST['reason'] ?? ''));
        $id = 0;
        if ($action === 'update') {
            $id = postInt('id');
        }
        if (in_array($status, ['pending', 'approved'], true)) {
            $overlap = $conn->prepare(
                'SELECT id
                 FROM leave_applications
                 WHERE employee_id=?
                   AND status IN ("pending","approved")
                   AND from_date <= ?
                   AND to_date >= ?
                   AND id <> ?
                 LIMIT 1'
            );
            $overlap->bind_param('issi', $employee, $to, $from, $id);
            $overlap->execute();
            if ($overlap->get_result()->num_rows > 0) {
                throw new InvalidArgumentException('This employee already has leave covering one or more selected dates.');
            }
        }
        if ($action === 'update') {
            $s = $conn->prepare('UPDATE leave_applications SET employee_id=?,leave_type_code=?,from_date=?,to_date=?,days_requested=?,status=?,reason=? WHERE id=?');
            $s->bind_param('isssdssi', $employee, $type, $from, $to, $days, $status, $reason, $id);
            $s->execute();
            auditLog($conn, 'leave_application', $id, 'update', ['employee_id' => $employee, 'from_date' => $from, 'to_date' => $to, 'status' => $status]);
        } else {
            $s = $conn->prepare('INSERT INTO leave_applications (employee_id,leave_type_code,from_date,to_date,days_requested,status,reason) VALUES (?,?,?,?,?,?,?)');
            $s->bind_param('isssdss', $employee, $type, $from, $to, $days, $status, $reason);
            $s->execute();
            auditLog($conn, 'leave_application', $s->insert_id, 'create', ['employee_id' => $employee, 'from_date' => $from, 'to_date' => $to, 'status' => $status]);
        }
        redirectWithFlash('/admin/leaves.php', $action === 'update' ? 'Leave updated.' : 'Leave application created.');
    }
} catch (Throwable $e) {
    flash($e->getMessage(), 'error');
}
$edit = null;
if (isset($_GET['edit'])) {
    $s = $conn->prepare('SELECT * FROM leave_applications WHERE id=?');
    $id = (int)$_GET['edit'];
    $s->bind_param('i', $id);
    $s->execute();
    $edit = $s->get_result()->fetch_assoc();
}
$employees = $conn->query('SELECT id,employee_code,full_name FROM employees WHERE status="active" ORDER BY full_name');
$types = $conn->query('SELECT code,name FROM leave_types WHERE active=1 ORDER BY priority_order');
$pageSize = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalResult = $conn->query('SELECT COUNT(*) AS total FROM leave_applications');
$totalRows = (int)$totalResult->fetch_assoc()['total'];
$totalPages = max(1, (int)ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$rowsStatement = $conn->prepare(
    'SELECT l.*,e.full_name,e.employee_code
     FROM leave_applications l
     JOIN employees e ON e.id=l.employee_id
     ORDER BY l.created_at DESC
     LIMIT ? OFFSET ?'
);
$rowsStatement->bind_param('ii', $pageSize, $offset);
$rowsStatement->execute();
$rows = $rowsStatement->get_result();
$notice = consumeFlash();
adminHeader('Leave Management', 'leaves');
?>
<?php if ($notice): ?><div class="flash <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endif; ?><div class="page-heading">
    <div>
        <p class="eyebrow">PRIORITY: WO → EL → FL → CPL → CL</p>
        <h2><?= $edit ? 'Edit application' : 'Leave applications' ?></h2>
    </div><a class="btn btn-primary" href="<?= BASE_URL ?>/admin/leaves.php"><?= $edit ? 'Cancel edit' : '+ New application' ?></a>
</div>
<div class="panel form-panel">
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
    <?php endif; ?>
    <div class="form-grid"><label>Employee<select name="employee_id" required>
        <?php while ($emp = $employees->fetch_assoc()): ?>
            <option value="<?= (int)$emp['id'] ?>" <?= (($edit['employee_id'] ?? '') == $emp['id']) ? 'selected' : '' ?>>
            <?= e($emp['employee_code'] . ' - ' . $emp['full_name']) ?>
        </option><?php endwhile; ?>
    </select>
</label>
<label>Leave type<select name="leave_type_code" required><?php while ($t = $types->fetch_assoc()): ?>
    <option value="<?= e($t['code']) ?>" <?= (($edit['leave_type_code'] ?? '') === $t['code']) ? 'selected' : '' ?>>
        <?= e($t['code'] . ' - ' . $t['name']) ?></option>
        <?php endwhile; ?></select></label>
        <label>From date<input type="text" name="from_date" required value="<?= e($edit && $edit['from_date'] ? displayDate($edit['from_date']) : '') ?>" placeholder="dd/mm/yyyy" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" maxlength="10" aria-label="From date (dd/mm/yyyy)"></label>
        <label>To date<input type="text" name="to_date" required value="<?= e($edit && $edit['to_date'] ? displayDate($edit['to_date']) : '') ?>" placeholder="dd/mm/yyyy" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" maxlength="10" aria-label="To date (dd/mm/yyyy)"></label>
        <label>Days requested<input type="number" min="0.5" step="0.5" name="days_requested" required value="<?= e((string)($edit['days_requested'] ?? 1)) ?>"></label>
        <label>Status<select name="status"><?php foreach (['pending', 'approved', 'rejected', 'cancelled'] as $v): ?>
            <option value="<?= $v ?>" <?= (($edit['status'] ?? 'pending') === $v) ? 'selected' : '' ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></label><label>Reason<input name="reason" value="<?= e($edit['reason'] ?? '') ?>"></label></div><button class="btn btn-primary"><?= $edit ? 'Update application' : 'Create application' ?></button></form>
</div>
<div class="panel table-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody><?php if (!$rows->num_rows): ?><tr>
                        <td colspan="7" class="empty-state">No leave applications yet.</td>
                    </tr><?php endif;
                        while ($row = $rows->fetch_assoc()): ?><tr>
                        <td><strong><?= e($row['full_name']) ?></strong><br><small><?= e($row['employee_code']) ?></small></td>
                        <td><?= e($row['leave_type_code']) ?></td>
                        <td><?= e($row['from_date']) ?> → <?= e($row['to_date']) ?></td>
                        <td><?= e((string)$row['days_requested']) ?></td>
                        <td><?= e((string)$row['reason']) ?></td>
                        <td><?= e(ucfirst($row['status'])) ?></td>
                        <td><?php if ($row['status'] === 'pending'): ?>
                            <form class="inline-form" method="post" onsubmit="return confirm('Approve this leave application?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-small" type="submit">Approve</button></form>
                            <form class="inline-form" method="post" onsubmit="return confirm('Reject this leave application?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-small" type="submit">Reject</button></form>
                        <?php endif; ?><a class="btn btn-small" href="?edit=<?= (int)$row['id'] ?>">Edit</a>
                            <form class="inline-form" method="post" onsubmit="return confirm('Delete application?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-small">Delete</button></form>
                        </td>
                    </tr><?php endwhile; ?></tbody>
        </table>
    </div>
    </div>
    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Leave application pages">
            <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                <a class="<?= $pageNumber === $page ? 'active' : '' ?>" href="?page=<?= $pageNumber ?>"><?= $pageNumber ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
    <?php adminFooter(); ?>