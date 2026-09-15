<?php
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/layout.php';
requireLogin();
$conn = adminDb();
$employeeId = (int)($_GET['employee_id'] ?? 0);
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : '';
$employees = $conn->query('SELECT id,employee_code,full_name FROM employees ORDER BY full_name');
if ($employeeId > 0 && $month !== '') {
    $stmt = $conn->prepare('SELECT l.*,e.employee_code,e.full_name FROM leave_applications l JOIN employees e ON e.id=l.employee_id WHERE l.employee_id=? AND DATE_FORMAT(l.from_date,"%Y-%m")=? ORDER BY l.from_date DESC,l.created_at DESC');
    $stmt->bind_param('is', $employeeId, $month);
} elseif ($employeeId > 0) {
    $stmt = $conn->prepare('SELECT l.*,e.employee_code,e.full_name FROM leave_applications l JOIN employees e ON e.id=l.employee_id WHERE l.employee_id=? ORDER BY l.from_date DESC,l.created_at DESC');
    $stmt->bind_param('i', $employeeId);
} elseif ($month !== '') {
    $stmt = $conn->prepare('SELECT l.*,e.employee_code,e.full_name FROM leave_applications l JOIN employees e ON e.id=l.employee_id WHERE DATE_FORMAT(l.from_date,"%Y-%m")=? ORDER BY l.from_date DESC,l.created_at DESC');
    $stmt->bind_param('s', $month);
} else {
    $stmt = $conn->prepare('SELECT l.*,e.employee_code,e.full_name FROM leave_applications l JOIN employees e ON e.id=l.employee_id ORDER BY l.from_date DESC,l.created_at DESC');
}
$stmt->execute();
$applications = $stmt->get_result();
if ($employeeId > 0 && $month !== '') {
    $transactions = $conn->prepare('SELECT t.*,e.employee_code,e.full_name FROM leave_transactions t JOIN employees e ON e.id=t.employee_id WHERE t.employee_id=? AND DATE_FORMAT(t.created_at,"%Y-%m")=? ORDER BY t.created_at DESC');
    $transactions->bind_param('is', $employeeId, $month);
} elseif ($employeeId > 0) {
    $transactions = $conn->prepare('SELECT t.*,e.employee_code,e.full_name FROM leave_transactions t JOIN employees e ON e.id=t.employee_id WHERE t.employee_id=? ORDER BY t.created_at DESC');
    $transactions->bind_param('i', $employeeId);
} elseif ($month !== '') {
    $transactions = $conn->prepare('SELECT t.*,e.employee_code,e.full_name FROM leave_transactions t JOIN employees e ON e.id=t.employee_id WHERE DATE_FORMAT(t.created_at,"%Y-%m")=? ORDER BY t.created_at DESC');
    $transactions->bind_param('s', $month);
} else {
    $transactions = $conn->prepare('SELECT t.*,e.employee_code,e.full_name FROM leave_transactions t JOIN employees e ON e.id=t.employee_id ORDER BY t.created_at DESC');
}
$transactions->execute();
$history = $transactions->get_result();
$applicationGroups = [];
while ($row = $applications->fetch_assoc()) {
    $applicationGroups[$row['employee_code'] . '|' . $row['full_name']][] = $row;
}
$transactionGroups = [];
while ($row = $history->fetch_assoc()) {
    $transactionGroups[$row['employee_code'] . '|' . $row['full_name']][] = $row;
}
$perPage = 3;
$applicationPage = max(1, (int)($_GET['application_page'] ?? 1));
$transactionPage = max(1, (int)($_GET['transaction_page'] ?? 1));
$applicationPages = max(1, (int)ceil(count($applicationGroups) / $perPage));
$transactionPages = max(1, (int)ceil(count($transactionGroups) / $perPage));
$applicationPage = min($applicationPage, $applicationPages);
$transactionPage = min($transactionPage, $transactionPages);
$visibleApplications = array_slice($applicationGroups, ($applicationPage - 1) * $perPage, $perPage, true);
$visibleTransactions = array_slice($transactionGroups, ($transactionPage - 1) * $perPage, $perPage, true);
$applicationQuery = ['employee_id' => $employeeId, 'month' => $month, 'transaction_page' => $transactionPage];
$transactionQuery = ['employee_id' => $employeeId, 'month' => $month, 'application_page' => $applicationPage];
$selectedEmployeeLabel = 'All employees';
if ($employeeId > 0) {
    $employeeFilter = $conn->prepare('SELECT CONCAT(employee_code, " - ", full_name) AS label FROM employees WHERE id=?');
    $employeeFilter->bind_param('i', $employeeId);
    $employeeFilter->execute();
    $employeeFilterResult = $employeeFilter->get_result()->fetch_assoc();
    $selectedEmployeeLabel = $employeeFilterResult['label'] ?? 'Selected employee';
}
$selectedPeriodLabel = $month !== '' ? date('F Y', strtotime($month . '-01')) : 'All months';
adminHeader('Leave History', 'leave_history');
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">AUDITABLE LEAVE RECORDS</p>
        <h2>Employee leave history</h2>
        <p class="muted">Filter applications and transactions by employee, month, and year.</p>
    </div>
    <form method="get" class="filter-card"><label>Employee<select name="employee_id">
                <option value="0">All employees</option><?php while ($employee = $employees->fetch_assoc()): ?><option value="<?= (int)$employee['id'] ?>" <?= $employeeId === $employee['id'] ? 'selected' : '' ?>><?= e($employee['employee_code'] . ' - ' . $employee['full_name']) ?></option><?php endwhile; ?>
            </select></label><label>Month and year<input type="month" name="month" value="<?= e($month) ?>"></label><button class="btn btn-primary">Apply filter</button></form>
</div>
<?php if (!$applicationGroups): ?><div class="panel empty-state">No leave applications found for the selected filters.</div><?php endif; ?>
<div class="employee-card-grid"><?php foreach ($visibleApplications as $key => $items): [$code, $name] = explode('|', $key, 2); ?><section class="employee-leave-card">
            <div class="employee-card-header">
                <div class="employee-avatar"><?= e(strtoupper(substr($name, 0, 1))) ?></div>
                <div>
                    <h3><?= e($name) ?></h3><small><?= e($code) ?></small>
                </div><span class="status-pill status-leave"><?= count($items) ?> application(s)</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Dates</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($items as $row): ?><tr>
                                <td class="leave-code"><?= e($row['leave_type_code']) ?></td>
                                <td><?= e(displayDate($row['from_date']) . ' → ' . displayDate($row['to_date'])) ?></td>
                                <td><?= e((string)$row['days_requested']) ?></td>
                                <td><?= e(ucfirst($row['status'])) ?></td>
                                <td><?= e((string)$row['reason']) ?></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </section><?php endforeach; ?></div>
<?php if ($applicationPages > 1): ?><nav class="pagination" aria-label="Leave application pages"><?php for ($i = 1; $i <= $applicationPages; $i++): $applicationQuery['application_page'] = $i; ?><a class="<?= $i === $applicationPage ? 'active' : '' ?>" href="?<?= e(http_build_query($applicationQuery)) ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
<div class="section-heading">
    <div>
        <h2>Balance transactions</h2>
        <p class="muted">Showing <?= e($selectedEmployeeLabel) ?> · <?= e($selectedPeriodLabel) ?>.</p>
    </div>
</div>
<?php if (!$transactionGroups): ?><div class="panel empty-state">No balance transactions found for the selected filters.</div><?php endif; ?>
<div class="employee-card-grid"><?php foreach ($visibleTransactions as $key => $items): [$code, $name] = explode('|', $key, 2); ?><section class="employee-leave-card compact">
            <div class="employee-card-header">
                <div class="employee-avatar"><?= e(strtoupper(substr($name, 0, 1))) ?></div>
                <div>
                    <h3><?= e($name) ?></h3><small><?= e($code) ?></small>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Transaction</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($items as $row): ?><tr>
                                <td class="leave-code"><?= e($row['leave_type_code']) ?></td>
                                <td><?= e(ucwords(str_replace('_', ' ', $row['transaction_type']))) ?></td>
                                <td><?= e((string)$row['amount']) ?></td>
                                <td><?= e((string)$row['reference_type']) ?></td>
                                <td><?= e(displayDateTime($row['created_at'])) ?></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </section><?php endforeach; ?></div>
<?php if ($transactionPages > 1): ?><nav class="pagination" aria-label="Balance transaction pages"><?php for ($i = 1; $i <= $transactionPages; $i++): $transactionQuery['transaction_page'] = $i; ?><a class="<?= $i === $transactionPage ? 'active' : '' ?>" href="?<?= e(http_build_query($transactionQuery)) ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
<?php adminFooter(); ?>