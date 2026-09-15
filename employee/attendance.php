<?php
require_once __DIR__ . '/layout.php';
$db = employeeDb();
$id = employeeId();
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
$s = $db->prepare('SELECT attendance_date,status,total_work_minutes,late_minutes,early_out_minutes,overtime_minutes,remarks FROM attendance_records WHERE employee_id=? AND attendance_date LIKE CONCAT(?,"%") ORDER BY attendance_date DESC');
$s->bind_param('is', $id, $month);
$s->execute();
$rows = $s->get_result();
employeeHeader('My Attendance', 'attendance'); ?>
<div class="page-heading">
    <div>
        <p class="eyebrow">ATTENDANCE HISTORY</p>
        <h2>My attendance</h2>
    </div>
    <form class="inline-form">
        <input type="month" name="month" value="<?= e($month) ?>">
        <button class="btn btn-primary">View month</button>
    </form>
</div>
<div class="panel table-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Work minutes</th>
                    <th>Late</th>
                    <th>Early out</th>
                    <th>Overtime</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody><?php while ($r = $rows->fetch_assoc()): ?><tr>
                        <td><?= e(displayDate($r['attendance_date'])) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $r['status']))) ?></td>
                        <td><?= $r['total_work_minutes'] ?></td>
                        <td><?= $r['late_minutes'] ?></td>
                        <td><?= $r['early_out_minutes'] ?></td>
                        <td><?= $r['overtime_minutes'] ?></td>
                        <td><?= e((string)$r['remarks']) ?></td>
                    </tr><?php endwhile;
                        if (!$rows->num_rows): ?><tr>
                        <td colspan="7" class="empty-state">No attendance records for this month.</td>
                    </tr><?php endif; ?></tbody>
        </table>
    </div>
</div><?php employeeFooter(); ?>