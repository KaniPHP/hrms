<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';

$conn = getDbConnection();

$stats = [
    'employees' => (int) $conn->query("SELECT COUNT(*) AS total FROM employees WHERE status <> 'inactive'")->fetch_assoc()['total'],
    'present' => (int) $conn->query("SELECT COUNT(DISTINCT employee_id) AS total FROM attendance_records WHERE attendance_date = CURDATE() AND status IN ('present', 'late', 'half_day', 'early_out', 'manual_adjustment')")->fetch_assoc()['total'],
    'absent' => (int) $conn->query("SELECT COUNT(DISTINCT employee_id) AS total FROM attendance_records WHERE attendance_date = CURDATE() AND status = 'absent'")->fetch_assoc()['total'],
    'leave' => (int) $conn->query("SELECT COUNT(DISTINCT employee_id) AS total FROM leave_applications WHERE status = 'approved' AND from_date <= CURDATE() AND to_date >= CURDATE()")->fetch_assoc()['total'],
    'pending' => (int) $conn->query("SELECT COUNT(*) AS total FROM leave_applications WHERE status = 'pending'")->fetch_assoc()['total'],
    'punch_in' => (int) $conn->query("SELECT COUNT(*) AS total FROM attendance_punches WHERE punch_date = CURDATE() AND punch_type = 'in'")->fetch_assoc()['total'],
    'punch_out' => (int) $conn->query("SELECT COUNT(*) AS total FROM attendance_punches WHERE punch_date = CURDATE() AND punch_type = 'out'")->fetch_assoc()['total'],
    'missing_punch' => (int) $conn->query(
        "SELECT COUNT(*) AS total FROM (
            SELECT a.employee_id
            FROM attendance_records a
            WHERE a.attendance_date = CURDATE() AND a.status = 'missing_punch'
            UNION
            SELECT p.employee_id
            FROM attendance_punches p
            JOIN employees e ON e.id = p.employee_id
            JOIN shifts s ON s.id = e.shift_id
            WHERE p.punch_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
              AND p.punch_type = 'in'
              AND (s.is_night_shift = 1 OR s.end_time <= s.start_time)
              AND NOT EXISTS (
                  SELECT 1 FROM attendance_punches out_punch
                  WHERE out_punch.employee_id = p.employee_id
                    AND out_punch.shift_id = p.shift_id
                    AND out_punch.punch_type = 'out'
                    AND out_punch.punch_date = CURDATE()
                    AND out_punch.punch_time > p.punch_time
              )
        ) missing"
    )->fetch_assoc()['total'],
];
$today = date('d/m/Y');

$dashboardPageSize = 6;
$dashboardPage = max(1, (int)($_GET['attendance_page'] ?? 1));
$dashboardTotal = (int)$conn->query('SELECT COUNT(*) AS total FROM attendance_records')->fetch_assoc()['total'];
$dashboardPages = max(1, (int)ceil($dashboardTotal / $dashboardPageSize));
$dashboardPage = min($dashboardPage, $dashboardPages);
$dashboardOffset = ($dashboardPage - 1) * $dashboardPageSize;
$recentRecords = $conn->prepare(
    'SELECT e.employee_code, e.full_name, a.attendance_date, a.status, a.total_work_minutes
     FROM attendance_records a JOIN employees e ON e.id = a.employee_id
     ORDER BY a.attendance_date DESC LIMIT ? OFFSET ?'
);
$recentRecords->bind_param('ii', $dashboardPageSize, $dashboardOffset);
$recentRecords->execute();
$recentRecords = $recentRecords->get_result();
$leaveSummary = $conn->query("SELECT leave_type_code, SUM(CASE WHEN transaction_type IN ('debit', 'carry_forward') THEN amount ELSE 0 END) AS used, SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) AS credited FROM leave_transactions GROUP BY leave_type_code ORDER BY leave_type_code");
?>
<?php adminHeader('Dashboard', 'dashboard'); ?>
<div class="welcome-banner">
    <div><span class="status-pill status-present">● System online</span>
        <h2>Good day, <?= e($_SESSION['hrms_admin_name'] ?? 'Admin') ?> 👋</h2>
        <p>Here is your workforce snapshot for <?= date('d M Y') ?>.</p>
    </div><a class="btn btn-light" href="<?= BASE_URL ?>/admin/processing.php">Run monthly processing →</a>
</div>
<section class="stats-grid">
    <a class="stat-card primary" href="<?= BASE_URL ?>/admin/employees.php" title="View employee details">
        <div class="label">Employees</div>
        <p class="value"><?php echo $stats['employees']; ?></p>
    </a>
    <a class="stat-card success" href="<?= BASE_URL ?>/admin/attendance.php?date=<?= e($today) ?>" title="View today's attendance">
        <div class="label">Present Today</div>
        <p class="value"><?php echo $stats['present']; ?></p>
    </a>
    <a class="stat-card danger" href="<?= BASE_URL ?>/admin/attendance.php?date=<?= e($today) ?>" title="View today's absent employees">
        <div class="label">Absent Today</div>
        <p class="value"><?php echo $stats['absent']; ?></p>
    </a>
    <a class="stat-card warning" href="<?= BASE_URL ?>/admin/leave_history.php" title="View approved leave details">
        <div class="label">On Leave</div>
        <p class="value"><?php echo $stats['leave']; ?></p>
    </a>
    <a class="stat-card danger" href="<?= BASE_URL ?>/admin/leaves.php" title="Review pending leave applications">
        <div class="label">Pending Leaves</div>
        <p class="value"><?php echo $stats['pending']; ?></p>
    </a>
    <a class="stat-card primary" href="<?= BASE_URL ?>/admin/punches.php?date=<?= e($today) ?>&punch_type=in" title="View today's punch-in records">
        <div class="label">Punch In Today</div>
        <p class="value"><?php echo $stats['punch_in']; ?></p>
    </a>
    <a class="stat-card success" href="<?= BASE_URL ?>/admin/punches.php?date=<?= e($today) ?>&punch_type=out" title="View today's punch-out records">
        <div class="label">Punch Out Today</div>
        <p class="value"><?php echo $stats['punch_out']; ?></p>
    </a>
    <a class="stat-card warning" href="<?= BASE_URL ?>/admin/attendance.php?date=<?= e($today) ?>" title="Review missing punch attendance">
        <div class="label">Missing Punches</div>
        <p class="value"><?php echo $stats['missing_punch']; ?></p>
    </a>
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
                        <th>Work</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $recentRecords->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['full_name']); ?><br><small><?php echo htmlspecialchars($row['employee_code']); ?></small></td>
                            <td><?php echo htmlspecialchars(displayDate($row['attendance_date'])); ?></td>
                            <td><span class="status-pill status-<?php echo $row['status'] === 'present' ? 'present' : ($row['status'] === 'late' ? 'late' : ($row['status'] === 'leave' ? 'leave' : 'absent')); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['status']))); ?></span></td>
                            <td><?= (int)$row['total_work_minutes'] < 60 ? (int)$row['total_work_minutes'] . ' min' : e(displayMinutes((int)$row['total_work_minutes'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php if ($dashboardPages > 1): ?><nav class="pagination" aria-label="Dashboard attendance pages"><?php for ($i = 1; $i <= $dashboardPages; $i++): ?><a class="<?= $i === $dashboardPage ? 'active' : '' ?>" href="?attendance_page=<?= $i ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
        </div>
    </div>

  
</section>
<?php adminFooter(); ?>