<?php
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/layout.php';
requireLogin();
$conn = adminDb();
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $action = $_POST['action'] ?? '';
        if (in_array($action, ['approve', 'reject'], true)) {
            $id = postInt('id');
            $conn->begin_transaction();
            $s = $conn->prepare('SELECT employee_id,leave_type_code,days_requested,status,from_date FROM leave_applications WHERE id=? FOR UPDATE');
            $s->bind_param('i', $id); $s->execute(); $application = $s->get_result()->fetch_assoc();
            if (!$application || $application['status'] !== 'pending') throw new InvalidArgumentException('Only pending applications can be reviewed.');
            if ($action === 'reject') {
                $s = $conn->prepare('UPDATE leave_applications SET status="rejected",approved_by=?,approved_at=NOW() WHERE id=?');
                $adminId=(int)$_SESSION['hrms_admin_id']; $s->bind_param('ii',$adminId,$id); $s->execute();
                $conn->commit(); redirectWithFlash('/admin/leaves.php','Leave rejected.');
            }
            $month=substr($application['from_date'],0,7); $s=$conn->prepare('UPDATE employee_leave_balances SET utilized_balance=utilized_balance+?,closing_balance=closing_balance-? WHERE employee_id=? AND leave_type_code=? AND month_year=? AND closing_balance>=?');
            $days=(float)$application['days_requested']; $appEmployee=(int)$application['employee_id']; $appType=(string)$application['leave_type_code'];
            $s->bind_param('ddissd',$days,$days,$appEmployee,$appType,$month,$days);$s->execute();
            if($s->affected_rows!==1) throw new InvalidArgumentException('Insufficient balance to approve this request.');
            $adminId=(int)$_SESSION['hrms_admin_id'];$s=$conn->prepare('UPDATE leave_applications SET status="approved",approved_by=?,approved_at=NOW() WHERE id=?');$s->bind_param('ii',$adminId,$id);$s->execute();
            $s=$conn->prepare('INSERT INTO leave_transactions(employee_id,leave_type_code,transaction_type,amount,reference_type,reference_id,remarks) VALUES(?,?,"debit",?,"leave_application",?,?)');$remarks='Approved leave deduction';$s->bind_param('isdis',$appEmployee,$appType,$days,$id,$remarks);$s->execute();
            $conn->commit(); redirectWithFlash('/admin/leaves.php','Leave approved.');
        }
        if ($action === 'delete') {
            $id = postInt('id');
            $s = $conn->prepare('DELETE FROM leave_applications WHERE id=?');
            $s->bind_param('i', $id);
            $s->execute();
            redirectWithFlash('/admin/leaves.php', 'Leave application deleted.');
        }
        $employee = postInt('employee_id');
        $type = postText('leave_type_code', 20);
        if (!in_array($type, ['WO', 'EL', 'FL', 'CPL', 'CL'], true)) {
            throw new InvalidArgumentException('Invalid leave type. Use WO, EL, FL, CPL, or CL.');
        }
        $from = postDate('from_date');
        $to = postDate('to_date');
        if ($to < $from) throw new InvalidArgumentException('The end date must follow the start date.');
        $fromDate = new DateTimeImmutable($from);
        $toDate = new DateTimeImmutable($to);
        $leaveDay = $_POST['leave_day'] ?? 'full';
        if (!in_array($leaveDay, ['full', 'half'], true)) {
            throw new InvalidArgumentException('Select a valid leave duration.');
        }
        if ($leaveDay === 'half' && $from !== $to) {
            throw new InvalidArgumentException('Half-day leave must use the same from and to date.');
        }
        $days = $leaveDay === 'half' ? 0.5 : (float)$fromDate->diff($toDate)->days + 1;
        $submittedDays = (float)($_POST['days_requested'] ?? 0);
        if (abs($submittedDays - $days) > 0.001) {
            throw new InvalidArgumentException('Days requested must match the selected from and to dates.');
        }
        $editingId = $action === 'update' ? postInt('id') : 0;
        $month = substr($from, 0, 7);
        $balance = leaveBalance($conn, $employee, $type, $month);
        if (!$balance) {
            throw new InvalidArgumentException("No {$type} balance is configured for this employee for {$month}.");
        }
        $reserved = $conn->prepare(
            'SELECT COALESCE(SUM(days_requested), 0) AS total
             FROM leave_applications
             WHERE employee_id=? AND leave_type_code=?
             AND status IN ("pending", "approved")
             AND from_date <= ? AND to_date >= ? AND id <> ?'
        );
        $reserved->bind_param('isssi', $employee, $type, $to, $from, $editingId);
        $reserved->execute();
        $reservedDays = (float)$reserved->get_result()->fetch_assoc()['total'];
        $available = (float)$balance['closing_balance'] - $reservedDays;
        if ($days > $available + 0.001) {
            throw new InvalidArgumentException(
                "{$type} leave balance is insufficient. Available: " . number_format(max(0, $available), 2) .
                " day(s); requested: " . number_format($days, 2) . "."
            );
        }
        $overlap = $conn->prepare(
            'SELECT id, status FROM leave_applications
             WHERE employee_id = ?
             AND status IN ("pending", "approved")
             AND from_date <= ?
             AND to_date >= ?
             AND id <> ?
             LIMIT 1'
        );
        $overlap->bind_param('issi', $employee, $to, $from, $editingId);
        $overlap->execute();
        $existingLeave = $overlap->get_result()->fetch_assoc();
        if ($existingLeave) {
            throw new InvalidArgumentException(
                'This employee already has a ' . $existingLeave['status'] .
                ' leave application covering one or more selected dates.'
            );
        }
        $status = $_POST['status'] ?? 'pending';
        if (!in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) throw new InvalidArgumentException('Invalid leave status.');
        $reason = trim((string)($_POST['reason'] ?? ''));
        $oldStatus = null;
        $conn->begin_transaction();
        if ($action === 'update') {
            $id = $editingId;
            $old = $conn->prepare('SELECT status FROM leave_applications WHERE id=?');
            $old->bind_param('i', $id);
            $old->execute();
            $oldStatus = $old->get_result()->fetch_assoc()['status'] ?? null;
            if ($oldStatus === 'approved') {
                throw new InvalidArgumentException('Approved leave cannot be edited because its balance transaction is already posted.');
            }
            $s = $conn->prepare('UPDATE leave_applications SET employee_id=?,leave_type_code=?,from_date=?,to_date=?,days_requested=?,status=?,reason=? WHERE id=?');
            $s->bind_param('isssdssi', $employee, $type, $from, $to, $days, $status, $reason, $id);
            $s->execute();
            $savedId = $id;
        } else {
            $s = $conn->prepare('INSERT INTO leave_applications (employee_id,leave_type_code,from_date,to_date,days_requested,status,reason) VALUES (?,?,?,?,?,?,?)');
            $s->bind_param('isssdss', $employee, $type, $from, $to, $days, $status, $reason);
            $s->execute();
            $savedId = $conn->insert_id;
        }
        if ($status === 'approved') {
            $debit = $conn->prepare(
                'UPDATE employee_leave_balances
                 SET utilized_balance=utilized_balance+?, closing_balance=closing_balance-?
                 WHERE employee_id=? AND leave_type_code=? AND month_year=? AND closing_balance>=?'
            );
            $debit->bind_param('ddissd', $days, $days, $employee, $type, $month, $days);
            $debit->execute();
            if ($debit->affected_rows !== 1) {
                throw new InvalidArgumentException('Unable to debit the leave balance. The available balance may have changed.');
            }
            $transaction = $conn->prepare(
                'INSERT INTO leave_transactions (employee_id,leave_type_code,transaction_type,amount,reference_type,reference_id,remarks)
                 VALUES (?,?,"debit",?,"leave_application",?,?)'
            );
            $remarks = 'Approved leave deduction';
            $transaction->bind_param('isdis', $employee, $type, $days, $savedId, $remarks);
            $transaction->execute();
        }
        $conn->commit();
        redirectWithFlash('/admin/leaves.php', $action === 'update' ? 'Leave updated.' : 'Leave application created.');
    }
} catch (Throwable $e) {
    $conn->rollback();
    if (isAjaxRequest()) sendAjaxError($e);
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
$employees = $conn->query('SELECT id,employee_code,full_name FROM employees ORDER BY full_name');
$types = $conn->query('SELECT code,name FROM leave_types WHERE active=1 ORDER BY priority_order');
$balanceMonth = date('Y-m');
$balanceRows = $conn->query(
    "SELECT employee_id, leave_type_code, opening_balance, earned_balance, utilized_balance, closing_balance, carry_forward
     FROM employee_leave_balances WHERE month_year='" . $conn->real_escape_string($balanceMonth) . "'"
);
$balanceData = [];
while ($balance = $balanceRows->fetch_assoc()) {
    $balanceData[(string)$balance['employee_id']][(string)$balance['leave_type_code']] = $balance;
}
$rows = $conn->query('SELECT l.*,e.full_name,e.employee_code FROM leave_applications l JOIN employees e ON e.id=l.employee_id ORDER BY l.created_at DESC');
$notice = consumeFlash();
adminHeader('Leave Management', 'leaves');
?>
<?php if ($notice): ?><div class="flash <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endif; ?>
<div class="page-heading">
    <div>
        <p class="eyebrow">PRIORITY: WO → EL → FL → CPL → CL</p>
        <h2><?= $edit ? 'Edit application' : 'Leave applications' ?></h2>
    </div><a class="btn btn-primary" href="<?= BASE_URL ?>/admin/leaves.php"><?= $edit ? 'Cancel edit' : '+ New application' ?></a>
</div>
<div class="panel form-panel">
    <form method="post" data-ajax-form><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
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
                    <?php endwhile; ?>
                </select></label>
            <label>From date<input type="date" name="from_date" required value="<?= e($edit['from_date'] ?? '') ?>"></label>
            <label>To date<input type="date" name="to_date" required value="<?= e($edit['to_date'] ?? '') ?>"></label>
            <label>Leave duration<select name="leave_day" required>
                    <option value="full" <?= ((float)($edit['days_requested'] ?? 1) === 0.5) ? '' : 'selected' ?>>Full day</option>
                    <option value="half" <?= ((float)($edit['days_requested'] ?? 1) === 0.5) ? 'selected' : '' ?>>Half day (0.5)</option>
                </select></label>
            <label>Days requested<input type="number" min="0.5" step="0.5" name="days_requested" required readonly value="<?= e((string)($edit['days_requested'] ?? 1)) ?>"></label>
            <label>Status<select name="status"><?php foreach (['pending', 'approved', 'rejected', 'cancelled'] as $v): ?>
                        <option value="<?= $v ?>" <?= (($edit['status'] ?? 'pending') === $v) ? 'selected' : '' ?>><?= ucfirst($v) ?></option><?php endforeach; ?>
                </select></label>
            <label>Reason<input name="reason" value="<?= e($edit['reason'] ?? '') ?>"></label>
        </div>
        <div class="panel balance-panel">
            <div class="panel-header"><h3>Employee leave balance</h3><span class="status-pill status-leave"><?= e($balanceMonth) ?></span></div>
            <div id="leave-balance-cards" class="balance-grid"><p class="muted">Select an employee to view available, utilized, and carry-forward leave.</p></div>
        </div>
        <button class="btn btn-primary"><?= $edit ? 'Update application' : 'Create application' ?></button>
    </form>
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
                        <td><?php if ($row['status'] === 'pending'): ?><form class="inline-form" method="post" data-ajax-form><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-small">Approve</button></form><form class="inline-form" method="post" data-ajax-form><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-small">Reject</button></form><?php endif; ?><a class="btn btn-small" href="?edit=<?= (int)$row['id'] ?>">Edit</a>
                            <form class="inline-form" method="post" data-ajax-form onsubmit="return confirm('Delete application?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-small">Delete</button></form>
                        </td>
                    </tr><?php endwhile; ?></tbody>
        </table>
    </div>
</div><?php adminFooter(); ?>
<script>
window.HRMSLeaveBalances = <?= json_encode($balanceData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>