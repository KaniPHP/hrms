<?php
require_once __DIR__ . '/layout.php';
$db = employeeDb();
$id = employeeId();
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
$error = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
        $response = ['success' => true, 'month' => $month, 'balances' => [], 'history' => []];
        $s = $db->prepare('SELECT leave_type_code,opening_balance,earned_balance,utilized_balance,closing_balance,carry_forward FROM employee_leave_balances WHERE employee_id=? AND month_year=? ORDER BY FIELD(leave_type_code,"WO","EL","FL","CPL","CL")');
        $s->bind_param('is', $id, $month); $s->execute(); $result = $s->get_result();
        while ($row = $result->fetch_assoc()) $response['balances'][] = $row;
        $s = $db->prepare('SELECT id,leave_type_code,from_date,to_date,days_requested,status,reason FROM leave_applications WHERE employee_id=? ORDER BY created_at DESC');
        $s->bind_param('i', $id); $s->execute(); $result = $s->get_result();
        while ($row = $result->fetch_assoc()) $response['history'][] = $row;
        sendAjaxJson(true, 'Leave data loaded.', 'success', '/employee/leave.php?month=' . urlencode($month), $response);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $action = $_POST['action'] ?? '';
        if ($action === 'cancel') {
            $leave = (int)($_POST['id'] ?? 0);
            $s = $db->prepare('UPDATE leave_applications SET status="cancelled" WHERE id=? AND employee_id=? AND status="pending"');
            $s->bind_param('ii', $leave, $id);
            $s->execute();
            if ($s->affected_rows !== 1) throw new RuntimeException('Only pending requests can be cancelled.');
            sendAjaxJson(true, 'Leave request cancelled.', 'success', '/employee/leave.php?month=' . urlencode($month));
        }
        $type = trim((string)($_POST['leave_type_code'] ?? ''));
        $from = postDate('from_date');
        $to = postDate('to_date');
        $reason = trim((string)($_POST['reason'] ?? ''));
        $days = (float)($_POST['days_requested'] ?? 0);
        if (!in_array($type, ['WO', 'EL', 'FL', 'CPL', 'CL'], true) || $to < $from || $days <= 0) throw new InvalidArgumentException('Please enter valid leave details.');
        $leaveDay = $_POST['leave_day'] ?? 'full';
        if (!in_array($leaveDay, ['full', 'half'], true)) throw new InvalidArgumentException('Select a valid leave duration.');
        $expected = $leaveDay === 'half' ? 0.5 + (0) : ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1);
        if ($leaveDay === 'half' && $from !== $to || abs($days - (float)$expected) > 0.001) throw new InvalidArgumentException('Days must match the selected dates and duration.');
        $s = $db->prepare('SELECT closing_balance FROM employee_leave_balances WHERE employee_id=? AND leave_type_code=? AND month_year=?');
        $m = substr($from, 0, 7);
        $s->bind_param('iss', $id, $type, $m);
        $s->execute();
        $b = $s->get_result()->fetch_assoc();
        if (!$b) throw new InvalidArgumentException('No leave balance is configured for this month.');
        $s = $db->prepare('SELECT COALESCE(SUM(days_requested),0) total FROM leave_applications WHERE employee_id=? AND leave_type_code=? AND status IN ("pending","approved") AND from_date<=? AND to_date>=?');
        $s->bind_param('isss', $id, $type, $to, $from);
        $s->execute();
        $reserved = (float)$s->get_result()->fetch_assoc()['total'];
        if ($days > (float)$b['closing_balance'] - $reserved + 0.001) throw new InvalidArgumentException('Insufficient leave balance for this month.');
        $s = $db->prepare('SELECT id FROM leave_applications WHERE employee_id=? AND status IN ("pending","approved") AND from_date<=? AND to_date>=? LIMIT 1');
        $s->bind_param('iss', $id, $to, $from);
        $s->execute();
        if ($s->get_result()->num_rows) throw new InvalidArgumentException('You already have leave covering one or more selected dates.');
        $s = $db->prepare('INSERT INTO leave_applications(employee_id,leave_type_code,from_date,to_date,days_requested,status,reason) VALUES(?,?,?,?,?,"pending",?)');
        $s->bind_param('isssds', $id, $type, $from, $to, $days, $reason);
        $s->execute();
        sendAjaxJson(true, 'Leave request submitted for admin approval.', 'success', '/employee/leave.php?month=' . urlencode($month));
    }
} catch (Throwable $e) {
    if (isAjaxRequest()) sendAjaxError($e);
    $error = $e->getMessage();
}
employeeHeader('My Leave', 'leave'); ?><div id="employee-leave-flash"></div><div class="page-heading">
    <div>
        <p class="eyebrow">LEAVE SELF SERVICE</p>
        <h2>Balance & history</h2>
    </div>
    <form class="inline-form" id="leave-month-form"><input type="month" name="month" value="<?= e($month) ?>"><button class="btn btn-primary" type="submit">View month</button></form>
</div><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><div class="panel table-panel">
    <h3>Leave balance — <span id="leave-month-label"><?= e($month) ?></span></h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Opening</th>
                    <th>Earned</th>
                    <th>Taken</th>
                    <th>Available</th>
                    <th>Carry forward</th>
                </tr>
            </thead>
            <tbody id="leave-balances-body"><tr><td colspan="6" class="empty-state">Loading balances...</td></tr></tbody>
        </table>
    </div>
</div>
<div class="panel form-panel">
    <h3>Request leave</h3>
    <form method="post" id="employee-leave-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create">
        <div class="form-grid"><label>Leave type<select name="leave_type_code" required>
                    <option>WO</option>
                    <option>EL</option>
                    <option>FL</option>
                    <option>CPL</option>
                    <option>CL</option>
                </select></label><label>From<input type="date" name="from_date" required></label><label>To<input type="date" name="to_date" required></label><label>Duration<select name="leave_day">
                    <option value="full">Full day</option>
                    <option value="half">Half day (0.5)</option>
                </select></label><label>Days<input type="number" min=".5" step=".5" name="days_requested" required readonly></label><label>Reason<input name="reason" maxlength="255"></label></div><button class="btn btn-primary">Submit request</button>
    </form>
</div>
<div class="panel table-panel">
    <h3>Request history</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="leave-history-body"><tr><td colspan="6" class="empty-state">Loading history...</td></tr></tbody>
        </table>
    </div>
</div><?php employeeFooter(); ?>