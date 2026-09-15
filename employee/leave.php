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
        $s = $db->prepare('SELECT id,leave_type_code,from_date,to_date,days_requested,status,reason FROM leave_applications WHERE employee_id=? AND from_date <= LAST_DAY(CONCAT(?,"-01")) AND to_date >= CONCAT(?,"-01") ORDER BY created_at DESC');
        $s->bind_param('iss', $id, $month, $month); $s->execute(); $result = $s->get_result();
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
            auditLog($db, 'leave_application', $leave, 'cancel', ['employee_id' => $id]);
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
        $halfDaySession = $_POST['half_day_session'] ?? '';
        if ($leaveDay === 'half' && !in_array($halfDaySession, ['forenoon', 'afternoon'], true)) {
            throw new InvalidArgumentException('Please select whether the half-day leave is Forenoon or Afternoon.');
        }
        if ($leaveDay === 'half') {
            $reason = '[Half day: ' . ucfirst($halfDaySession) . '] ' . $reason;
        }
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
        auditLog($db, 'leave_application', $s->insert_id, 'create', ['employee_id' => $id, 'from_date' => $from, 'to_date' => $to, 'status' => 'pending']);
        sendAjaxJson(true, 'Leave request submitted for admin approval.', 'success', '/employee/leave.php?month=' . urlencode($month));
    }
} catch (Throwable $e) {
    if (isAjaxRequest()) sendAjaxError($e);
    $error = $e->getMessage();
}
employeeHeader('My Leave', 'leave'); ?><div id="employee-leave-flash"></div><div class="leave-overview-layout">
    <section class="leave-intro-card">
        <p class="eyebrow">LEAVE SELF SERVICE</p>
        <h2>Balance &amp; history</h2>
        <p>Plan your time off, review your monthly entitlement, and track every request from one place.</p>
        <form class="leave-month-form" id="leave-month-form">
            <label>View month<input type="month" name="month" value="<?= e($month) ?>"></label>
            <button class="btn btn-light" type="submit">Load balance</button>
        </form>
        <div class="leave-intro-note">Selected month: <strong id="leave-month-label"><?= e($month) ?></strong></div>
    </section>
    <section class="panel form-panel leave-request-panel">
    <div class="leave-section-heading"><div><p class="eyebrow">QUICK REQUEST</p><h3>Request leave</h3><p class="muted">Choose your dates and duration. Half-day requests require a session.</p></div><span class="leave-step">1</span></div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" id="employee-leave-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create">
        <div class="form-grid"><label>Leave type<select name="leave_type_code" required>
                    <option>WO</option>
                    <option>EL</option>
                    <option>FL</option>
                    <option>CPL</option>
                    <option>CL</option>
                </select></label><label>From<input type="text" name="from_date" required placeholder="dd/mm/yyyy" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" maxlength="10" aria-label="From date (dd/mm/yyyy)"></label><label>To<input type="text" name="to_date" required placeholder="dd/mm/yyyy" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" maxlength="10" aria-label="To date (dd/mm/yyyy)"></label><label>Duration<select name="leave_day">
                    <option value="full">Full day</option>
                    <option value="half">Half day (0.5)</option>
                </select></label><label class="half-day-session-field">Half-day session<select name="half_day_session">
                    <option value="">Select session</option>
                    <option value="forenoon">Forenoon</option>
                    <option value="afternoon">Afternoon</option>
                </select></label><label>Days<input type="number" min=".5" step=".5" name="days_requested" required readonly></label><label>Reason<input name="reason" maxlength="255"></label></div><button class="btn btn-primary">Submit request</button>
    </form>
    </section>
</div>
<section class="panel balance-panel employee-balance-panel">
    <div class="panel-header"><div><p class="eyebrow">MONTHLY ENTITLEMENT</p><h3>Leave balance cards</h3></div><span class="status-pill status-leave" id="balance-period"><?= e($month) ?></span></div>
    <div id="leave-balance-cards" class="employee-balance-grid"></div>
    <div id="leave-balances-pagination" class="pagination"></div>
</section>
<section class="panel table-panel employee-history-panel">
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
    <div id="leave-history-pagination" class="pagination"></div>
</div><?php employeeFooter(); ?>