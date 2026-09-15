<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';

$conn = getDbConnection();

$stats = [
    'employees' => (int) $conn->query("SELECT COUNT(*) AS total FROM employees WHERE status <> 'inactive'")->fetch_assoc()['total'],
    'present' => (int) $conn->query("SELECT COUNT(DISTINCT employee_id) AS total FROM attendance_records WHERE attendance_date = CURDATE() AND status IN ('present', 'late', 'half_day', 'early_out', 'manual_adjustment')")->fetch_assoc()['total'],
    'leave' => (int) $conn->query("SELECT COUNT(DISTINCT employee_id) AS total FROM leave_applications WHERE status = 'approved' AND from_date <= CURDATE() AND to_date >= CURDATE()")->fetch_assoc()['total'],
    'pending' => (int) $conn->query("SELECT COUNT(*) AS total FROM leave_applications WHERE status = 'pending'")->fetch_assoc()['total'],
];

$recentRecords = $conn->query("SELECT e.employee_code, e.full_name, a.attendance_date, a.status, a.total_work_minutes FROM attendance_records a JOIN employees e ON e.id = a.employee_id ORDER BY a.attendance_date DESC LIMIT 6");
$leaveSummary = $conn->query("SELECT leave_type_code, SUM(CASE WHEN transaction_type IN ('debit', 'carry_forward') THEN amount ELSE 0 END) AS used, SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) AS credited FROM leave_transactions GROUP BY leave_type_code ORDER BY leave_type_code");
?>
<?php adminHeader('Dashboard', 'dashboard'); ?>
            <div class="welcome-banner"><div><span class="status-pill status-present">● System online</span><h2>Good day, <?= e($_SESSION['hrms_admin_name'] ?? 'Admin') ?> 👋</h2><p>Here is your workforce snapshot for <?= date('d M Y') ?>.</p></div><a class="btn btn-light" href="<?= BASE_URL ?>/admin/processing.php">Run monthly processing →</a></div>
        <section class="stats-grid">
            <div class="stat-card primary">
                <div class="label">Employees</div>
                <p class="value"><?php echo $stats['employees']; ?></p>
            </div>
            <div class="stat-card success">
                <div class="label">Present Today</div>
                <p class="value"><?php echo $stats['present']; ?></p>
            </div>
            <div class="stat-card warning">
                <div class="label">On Leave</div>
                <p class="value"><?php echo $stats['leave']; ?></p>
            </div>
            <div class="stat-card danger">
                <div class="label">Pending Leaves</div>
                <p class="value"><?php echo $stats['pending']; ?></p>
            </div>
        </section>

        <section class="content-grid">
            <div class="panel">
                <div class="panel-header">
                    <h3>Recent Attendance</h3>
                    <span class="status-pill status-leave">Live overview</span>
                </div>
                <div class="panel-body table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Minutes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recentRecords->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?><br><small><?php echo htmlspecialchars($row['employee_code']); ?></small></td>
                                    <td><?php echo htmlspecialchars($row['attendance_date']); ?></td>
                                    <td><span class="status-pill status-<?php echo $row['status'] === 'present' ? 'present' : ($row['status'] === 'late' ? 'late' : ($row['status'] === 'leave' ? 'leave' : 'absent')); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['status']))); ?></span></td>
                                    <td><?php echo (int)$row['total_work_minutes']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Leave Summary</h3>
                </div>
                <div class="panel-body">
                    <ul class="metrics-list">
                        <?php while ($row = $leaveSummary->fetch_assoc()): ?>
                            <li>
                                <span><?php echo htmlspecialchars($row['leave_type_code']); ?></span>
                                <strong><?php echo number_format((float) $row['credited'], 2); ?></strong>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </section>
<?php adminFooter(); ?>
