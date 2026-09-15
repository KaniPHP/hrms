<?php
require_once __DIR__ . '/layout.php';
$db = employeeDb();
$id = employeeId();
$month = date('Y-m');
$s = $db->prepare('SELECT COUNT(*) n FROM leave_applications WHERE employee_id=? AND status="pending"');
$s->bind_param('i', $id);
$s->execute();
$pending = (int)$s->get_result()->fetch_assoc()['n'];
$s = $db->prepare('SELECT COALESCE(SUM(closing_balance),0) n FROM employee_leave_balances WHERE employee_id=? AND month_year=?');
$s->bind_param('is', $id, $month);
$s->execute();
$balance = $s->get_result()->fetch_assoc()['n'];
employeeHeader('Employee Dashboard'); ?><div class="welcome-banner">
    <div><span class="status-pill status-present">● Self service</span>
        <h2>Welcome, <?= e($_SESSION['hrms_employee_name']) ?> 👋</h2>
        <p>Review your leave and attendance records securely.</p>
    </div>
</div>
<div class="stats-grid">
    <div class="stat-card primary">
        <p class="label">Available leave</p>
        <p class="value"><?= e((string)$balance) ?></p>
    </div>
    <div class="stat-card warning">
        <p class="label">Pending requests</p>
        <p class="value"><?= $pending ?></p>
    </div>
</div><?php employeeFooter(); ?>