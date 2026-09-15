<?php
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/layout.php';
requireLogin();
$conn = adminDb();
$employeeId = (int)($_GET['employee_id'] ?? 0);
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
$employees = $conn->query('SELECT id,employee_code,full_name FROM employees ORDER BY full_name');
if ($employeeId > 0) {
    $stmt = $conn->prepare(
        'SELECT e.employee_code,e.full_name,b.leave_type_code,b.opening_balance,b.earned_balance,
                b.utilized_balance,b.closing_balance,b.carry_forward
         FROM employee_leave_balances b JOIN employees e ON e.id=b.employee_id
         WHERE b.month_year=? AND e.id=?
         ORDER BY e.full_name, FIELD(b.leave_type_code,"WO","EL","FL","CPL","CL")'
    );
    $stmt->bind_param('si', $month, $employeeId);
} else {
    $stmt = $conn->prepare(
        'SELECT e.employee_code,e.full_name,b.leave_type_code,b.opening_balance,b.earned_balance,
                b.utilized_balance,b.closing_balance,b.carry_forward
         FROM employee_leave_balances b JOIN employees e ON e.id=b.employee_id
         WHERE b.month_year=?
         ORDER BY e.full_name, FIELD(b.leave_type_code,"WO","EL","FL","CPL","CL")'
    );
    $stmt->bind_param('s', $month);
}
$stmt->execute();
$rows = $stmt->get_result();
$grouped = [];
while ($row = $rows->fetch_assoc()) {
    $key = (string)$row['employee_code'];
    if (!isset($grouped[$key])) {
        $grouped[$key] = ['name' => $row['full_name'], 'code' => $row['employee_code'], 'items' => []];
    }
    $grouped[$key]['items'][] = $row;
}
$perPage = 3;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil(count($grouped) / $perPage));
$page = min($page, $totalPages);
$visibleGroups = array_slice($grouped, ($page - 1) * $perPage, $perPage, true);
$queryBase = ['month' => $month];
if ($employeeId > 0) {
    $queryBase['employee_id'] = $employeeId;
}
adminHeader('Leave Balances', 'leave_balances');
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">EMPLOYEE ENTITLEMENTS</p>
        <h2>Available and utilized leave</h2>
        <p class="muted">Three employee cards are shown per page.</p>
    </div>
    <form method="get" class="filter-card"><label>Employee<select name="employee_id">
                <option value="0">All employees</option><?php while ($employee = $employees->fetch_assoc()): ?><option value="<?= (int)$employee['id'] ?>" <?= $employeeId === (int)$employee['id'] ? 'selected' : '' ?>><?= e($employee['employee_code'] . ' - ' . $employee['full_name']) ?></option><?php endwhile; ?>
            </select></label><label>Month and year<input type="month" name="month" value="<?= e($month) ?>"></label><button class="btn btn-primary">Apply filter</button></form>
</div>
<?php if (!$grouped): ?><div class="panel empty-state">No leave balances configured for <?= e($month) ?>.</div><?php endif; ?>
<div class="employee-card-grid"><?php foreach ($visibleGroups as $employee): ?><section class="employee-leave-card">
            <div class="employee-card-header">
                <div class="employee-avatar"><?= e(strtoupper(substr($employee['name'], 0, 1))) ?></div>
                <div>
                    <h3><?= e($employee['name']) ?></h3><small><?= e($employee['code']) ?></small>
                </div><span class="status-pill status-leave"><?= count($employee['items']) ?> leave types</span>
            </div>
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
                    <tbody><?php foreach ($employee['items'] as $row): ?><tr>
                                <td><span class="leave-code"><?= e($row['leave_type_code']) ?></span></td>
                                <td><?= e((string)$row['opening_balance']) ?></td>
                                <td><?= e((string)$row['earned_balance']) ?></td>
                                <td><?= e((string)$row['utilized_balance']) ?></td>
                                <td><strong class="balance-available"><?= e((string)$row['closing_balance']) ?></strong></td>
                                <td><?= e((string)$row['carry_forward']) ?></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </section><?php endforeach; ?></div>
<?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Leave balance pages"><?php for ($i = 1; $i <= $totalPages; $i++): $queryBase['page'] = $i; ?><a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= e(http_build_query($queryBase)) ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
<?php adminFooter(); ?>