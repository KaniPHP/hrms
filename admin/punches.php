<?php
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/layout.php';
requireLogin();
$conn = adminDb();
$transactionStarted = false;
function recalculateAttendance(mysqli $conn, int $employeeId, string $attendanceDate): void
{
    $employeeQuery = $conn->prepare(
        'SELECT e.shift_id, s.start_time, s.end_time, s.grace_minutes, s.overtime_after_minutes, s.is_night_shift
         FROM employees e LEFT JOIN shifts s ON s.id=e.shift_id WHERE e.id=? LIMIT 1'
    );
    $employeeQuery->bind_param('i', $employeeId);
    $employeeQuery->execute();
    $employee = $employeeQuery->get_result()->fetch_assoc();
    if (!$employee) {
        throw new InvalidArgumentException('Employee was not found.');
    }

    $nextDate = (new DateTimeImmutable($attendanceDate))->modify('+1 day')->format('Y-m-d');
    $isOvernight = !empty($employee['is_night_shift']) ||
        ($employee['start_time'] !== null && strcmp((string)$employee['end_time'], (string)$employee['start_time']) <= 0);
    $punchSql = $isOvernight
        ? 'SELECT punch_type, punch_date, punch_time FROM attendance_punches WHERE employee_id=? AND (punch_date=? OR punch_date=?) ORDER BY punch_time'
        : 'SELECT punch_type, punch_date, punch_time FROM attendance_punches WHERE employee_id=? AND punch_date=? ORDER BY punch_time';
    $punchQuery = $conn->prepare($punchSql);
    if ($isOvernight) {
        $punchQuery->bind_param('iss', $employeeId, $attendanceDate, $nextDate);
    } else {
        $punchQuery->bind_param('is', $employeeId, $attendanceDate);
    }
    $punchQuery->execute();
    $punches = $punchQuery->get_result();
    $firstIn = null;
    $lastOut = null;
    while ($punch = $punches->fetch_assoc()) {
        if ($punch['punch_type'] === 'in' && $punch['punch_date'] === $attendanceDate && $firstIn === null) {
            $firstIn = new DateTimeImmutable($punch['punch_time']);
        }
        if ($punch['punch_type'] === 'out') {
            $lastOut = new DateTimeImmutable($punch['punch_time']);
        }
    }

    $status = 'missing_punch';
    $work = 0;
    $late = 0;
    $early = 0;
    $overtime = 0;
    $shiftId = $employee['shift_id'] !== null ? (int)$employee['shift_id'] : null;
    $shiftStart = null;
    $shiftEnd = null;
    if ($employee['start_time'] !== null) {
        $shiftStart = new DateTimeImmutable($attendanceDate . ' ' . $employee['start_time']);
        $shiftEndDate = $isOvernight ? $nextDate : $attendanceDate;
        $shiftEnd = new DateTimeImmutable($shiftEndDate . ' ' . $employee['end_time']);
    }
    if ($firstIn && $shiftStart) {
        // Late minutes begin after the configured grace period.
        $late = max(0, (int)floor(($firstIn->getTimestamp() - $shiftStart->getTimestamp()) / 60) - (int)$employee['grace_minutes']);
    }
    if ($lastOut && $shiftEnd) {
        $minutesAfterShiftEnd = (int)floor(($lastOut->getTimestamp() - $shiftEnd->getTimestamp()) / 60);
        $early = max(0, -$minutesAfterShiftEnd);
        // Overtime starts only after the configured post-shift threshold.
        $overtime = max(0, $minutesAfterShiftEnd - (int)$employee['overtime_after_minutes']);
    }
    if ($firstIn && $lastOut && $lastOut >= $firstIn) {
        $work = (int)floor(($lastOut->getTimestamp() - $firstIn->getTimestamp()) / 60);
        $status = $late > 0 ? 'late' : ($early > 0 ? 'early_out' : 'present');
    }

    $existing = $conn->prepare('SELECT id FROM attendance_records WHERE employee_id=? AND attendance_date=? LIMIT 1');
    $existing->bind_param('is', $employeeId, $attendanceDate);
    $existing->execute();
    $record = $existing->get_result()->fetch_assoc();
    $remarks = $status === 'missing_punch'
        ? 'Attendance requires manual punch correction.'
        : 'Calculated from punches. Late: ' . $late . ' min; Early out: ' . $early . ' min; Overtime: ' . $overtime . ' min.';
    if ($record) {
        $update = $conn->prepare(
            'UPDATE attendance_records SET shift_id=?,status=?,total_work_minutes=?,late_minutes=?,early_out_minutes=?,overtime_minutes=?,remarks=? WHERE id=?'
        );
        $recordId = (int)$record['id'];
        $update->bind_param('isiiiisi', $shiftId, $status, $work, $late, $early, $overtime, $remarks, $recordId);
        $update->execute();
    } else {
        $insert = $conn->prepare(
            'INSERT INTO attendance_records (employee_id,attendance_date,shift_id,status,total_work_minutes,late_minutes,early_out_minutes,overtime_minutes,remarks)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $insert->bind_param('isisiiiis', $employeeId, $attendanceDate, $shiftId, $status, $work, $late, $early, $overtime, $remarks);
        $insert->execute();
    }
}
$date = date('Y-m-d');
try {
    $date = normalizeDate((string)($_GET['date'] ?? $date), 'Please select a valid date in DD/MM/YYYY format.');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $employee = postInt('employee_id');
        $punchDate = postDate('punch_date');
        $punchTime = trim((string)($_POST['punch_time'] ?? ''));
        $type = $_POST['punch_type'] ?? '';
        $parsedTime = DateTimeImmutable::createFromFormat('!H:i', $punchTime);
        $timeErrors = DateTimeImmutable::getLastErrors();
        $hasTimeErrors = is_array($timeErrors) && ($timeErrors['warning_count'] > 0 || $timeErrors['error_count'] > 0);
        if (!$parsedTime || $hasTimeErrors || $parsedTime->format('H:i') !== $punchTime || !in_array($type, ['in', 'out'], true)) {
            throw new InvalidArgumentException('Enter a valid punch date, time, and punch type.');
        }
        $employeeShift = $conn->prepare(
            'SELECT e.shift_id, s.shift_name, s.start_time, s.end_time, s.is_night_shift
             FROM employees e LEFT JOIN shifts s ON s.id=e.shift_id
             WHERE e.id=? AND e.status="active" LIMIT 1'
        );
        $employeeShift->bind_param('i', $employee);
        $employeeShift->execute();
        $shift = $employeeShift->get_result()->fetch_assoc();
        if (!$shift) {
            throw new InvalidArgumentException('The selected employee could not be found.');
        }
        if ($shift['shift_id'] === null) {
            throw new InvalidArgumentException('Assign a shift to this employee before recording punches.');
        }
        $shiftId = (int)$shift['shift_id'];
        $dateTime = $punchDate . ' ' . $punchTime . ':00';
        $isNightShift = !empty($shift['is_night_shift']) ||
            strcmp((string)$shift['end_time'], (string)$shift['start_time']) <= 0;
        $shiftStart = new DateTimeImmutable($punchDate . ' ' . $shift['start_time']);
        $shiftEndDate = $isNightShift && $type === 'in'
            ? (new DateTimeImmutable($punchDate))->modify('+1 day')->format('Y-m-d')
            : $punchDate;
        $shiftEnd = new DateTimeImmutable($shiftEndDate . ' ' . $shift['end_time']);
        $submittedPunch = new DateTimeImmutable($dateTime);
        if ($type === 'in') {
            if ($submittedPunch < $shiftStart) {
                throw new InvalidArgumentException(
                    'Punch-in for ' . $shift['shift_name'] . ' cannot be before ' .
                    date('d/m/Y H:i', $shiftStart->getTimestamp()) . '.'
                );
            }
            $openShift = $conn->prepare(
                'SELECT p.punch_time
                 FROM attendance_punches p
                 WHERE p.employee_id=? AND p.shift_id=? AND p.punch_type="in"
                 AND NOT EXISTS (
                     SELECT 1 FROM attendance_punches out_punch
                     WHERE out_punch.employee_id=p.employee_id
                     AND out_punch.shift_id=p.shift_id
                     AND out_punch.punch_type="out"
                     AND out_punch.punch_time > p.punch_time
                 )
                 ORDER BY p.punch_time DESC LIMIT 1'
            );
            $openShift->bind_param('ii', $employee, $shiftId);
            $openShift->execute();
            if ($openShift->get_result()->fetch_assoc()) {
                throw new InvalidArgumentException('This employee already has an open punch-in for the assigned shift. Record punch-out before another punch-in.');
            }
        } else {
            $inDate = $isNightShift ? (new DateTimeImmutable($punchDate))->modify('-1 day')->format('Y-m-d') : $punchDate;
            $priorIn = $conn->prepare(
                'SELECT punch_time FROM attendance_punches
                 WHERE employee_id=? AND shift_id=? AND punch_type="in" AND punch_date=?
                 ORDER BY punch_time DESC LIMIT 1'
            );
            $priorIn->bind_param('iis', $employee, $shiftId, $inDate);
            $priorIn->execute();
            $priorInRow = $priorIn->get_result()->fetch_assoc();
            if (!$priorInRow) {
                throw new InvalidArgumentException('Punch-in must be recorded before punch-out for this shift.');
            }
            if ($submittedPunch <= new DateTimeImmutable($priorInRow['punch_time'])) {
                throw new InvalidArgumentException('Punch-out must be after the employee punch-in time.');
            }
        }
        $conn->begin_transaction();
        $transactionStarted = true;
        $duplicate = $conn->prepare(
            'SELECT id FROM attendance_punches
             WHERE employee_id=? AND punch_date=? AND punch_type=?
             LIMIT 1'
        );
        $duplicate->bind_param('iss', $employee, $punchDate, $type);
        $duplicate->execute();
        if ($duplicate->get_result()->num_rows > 0) {
            throw new InvalidArgumentException(
                'A punch-' . $type . ' already exists for this employee on ' . displayDate($punchDate) . '. Please use the existing record.'
            );
        }
        $s = $conn->prepare('INSERT INTO attendance_punches (employee_id,shift_id,punch_date,punch_time,punch_type,source,remarks) VALUES (?,?,?, ?,?,"manual",?)');
        $remarks = trim((string)($_POST['remarks'] ?? ''));
        $s->bind_param('iissss', $employee, $shiftId, $punchDate, $dateTime, $type, $remarks);
        $s->execute();
        auditLog($conn, 'attendance_punch', $s->insert_id, 'create', ['employee_id' => $employee, 'shift_id' => $shiftId, 'punch_type' => $type, 'punch_date' => $punchDate]);
        $shiftCheck = $conn->prepare('SELECT s.is_night_shift FROM employees e LEFT JOIN shifts s ON s.id=e.shift_id WHERE e.id=?');
        $shiftCheck->bind_param('i', $employee);
        $shiftCheck->execute();
        $shiftCheckRow = $shiftCheck->get_result()->fetch_assoc();
        if ($type === 'out' && !empty($shiftCheckRow['is_night_shift'])) {
            $attendanceDate = (new DateTimeImmutable($punchDate))->modify('-1 day')->format('Y-m-d');
            recalculateAttendance($conn, $employee, $attendanceDate);
        } else {
            recalculateAttendance($conn, $employee, $punchDate);
        }
        $conn->commit();
        $transactionStarted = false;
        redirectWithFlash('/admin/punches.php?date=' . urlencode($punchDate), 'Punch recorded.');
    }
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
        $transactionStarted = false;
    }
    if (isAjaxRequest()) {
        sendAjaxError($e);
    }
    flash($e->getMessage(), 'error');
}
$employees = $conn->query(
    'SELECT e.id,e.employee_code,e.full_name,s.shift_name,s.start_time,s.end_time
     FROM employees e LEFT JOIN shifts s ON s.id=e.shift_id
     WHERE e.status="active" ORDER BY e.full_name'
);
$punchType = in_array($_GET['punch_type'] ?? '', ['in', 'out'], true) ? $_GET['punch_type'] : '';
$punchFilterSql = $punchType !== '' ? ' AND p.punch_type=?' : '';
$rows = $conn->prepare(
    'SELECT p.*, e.employee_code, e.full_name, s.shift_name, s.start_time, s.end_time
     FROM attendance_punches p
     JOIN employees e ON e.id=p.employee_id
     LEFT JOIN shifts s ON s.id=p.shift_id
     WHERE p.punch_date=?' . $punchFilterSql . ' ORDER BY p.punch_time DESC LIMIT ? OFFSET ?'
);
$pageSize = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$countRows = $conn->prepare('SELECT COUNT(*) AS total FROM attendance_punches WHERE punch_date=?' . ($punchType !== '' ? ' AND punch_type=?' : ''));
if ($punchType !== '') {
    $countRows->bind_param('ss', $date, $punchType);
} else {
    $countRows->bind_param('s', $date);
}
$countRows->execute();
$totalRows = (int)$countRows->get_result()->fetch_assoc()['total'];
$totalPages = max(1, (int)ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
if ($punchType !== '') {
    $rows->bind_param('ssii', $date, $punchType, $pageSize, $offset);
} else {
    $rows->bind_param('sii', $date, $pageSize, $offset);
}
$rows->execute();
$result = $rows->get_result();
$notice = consumeFlash();
adminHeader('Punch Register', 'punches');
?>
<?php if ($notice): ?><div class="flash <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endif; ?>
<div class="page-heading">
    <div><p class="eyebrow">SHIFT PUNCH TRACKING</p><h2><?= $punchType !== '' ? 'Punch ' . strtoupper($punchType) . ' records' : 'Employee punches' ?></h2></div>
    <form class="inline-form" data-date-form><input type="text" name="date" value="<?= e(displayDate($date)) ?>" placeholder="dd/mm/yyyy" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" maxlength="10" autocomplete="off" required aria-label="View date (dd/mm/yyyy)"><button class="btn btn-primary">View date</button></form>
</div>
<div class="panel form-panel">
    <form method="post" data-ajax-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="form-grid">
            <label>Employee<select name="employee_id" required><?php while ($employee = $employees->fetch_assoc()): ?><option value="<?= (int)$employee['id'] ?>"><?= e($employee['employee_code'] . ' - ' . $employee['full_name'] . ($employee['shift_name'] ? ' · ' . $employee['shift_name'] : ' · No shift assigned')) ?></option><?php endwhile; ?></select></label>
            <label>Date<input type="text" name="punch_date" value="<?= e(displayDate($date)) ?>" placeholder="dd/mm/yyyy" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" maxlength="10" autocomplete="off" required aria-label="Punch date (dd/mm/yyyy)"></label>
            <label>Time<input type="time" name="punch_time" required></label>
            <label>Type<select name="punch_type" required><option value="in">Punch in</option><option value="out">Punch out</option></select></label>
            <label>Remarks<input name="remarks" maxlength="255"></label>
        </div>
        <button class="btn btn-primary">Record punch</button>
    </form>
</div>
<div class="panel table-panel"><div class="panel-header"><h3>Punches for <?= e(displayDate($date)) ?></h3></div><div class="table-wrap"><table>
    <thead><tr><th>Employee</th><th>Shift</th><th>Time</th><th>Type</th><th>Source</th><th>Remarks</th></tr></thead>
    <tbody><?php if (!$result->num_rows): ?><tr><td colspan="6" class="empty-state">No punches recorded for this date.</td></tr><?php endif; ?>
    <?php while ($row = $result->fetch_assoc()): ?><tr><td><strong><?= e($row['full_name']) ?></strong><br><small><?= e($row['employee_code']) ?></small></td><td><?= e($row['shift_name'] ?? 'No shift') ?><br><small><?= $row['start_time'] !== null ? e(substr($row['start_time'], 0, 5) . ' - ' . substr($row['end_time'], 0, 5)) : '' ?></small></td><td><?= e(date('d/m/Y H:i', strtotime($row['punch_time']))) ?></td><td><?= e(strtoupper($row['punch_type'])) ?></td><td><?= e(ucfirst($row['source'])) ?></td><td><?= e((string)$row['remarks']) ?></td></tr><?php endwhile; ?></tbody>
</table></div></div>
<?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Punch pages"><?php for ($i = 1; $i <= $totalPages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="?date=<?= e(displayDate($date)) ?>&punch_type=<?= e($punchType) ?>&page=<?= $i ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
<?php adminFooter(); ?>
