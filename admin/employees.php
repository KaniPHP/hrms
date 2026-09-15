<?php
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/layout.php';
requireLogin();
$conn = adminDb();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $id = postInt('id');
            $stmt = $conn->prepare('DELETE FROM employees WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            auditLog($conn, 'employee', $id, 'delete');
            redirectWithFlash('/admin/employees.php', 'Employee deleted.');
        }
        $code = postText('employee_code', 50);
        $name = postText('full_name', 150);
        $letterCount = preg_match_all('/\p{L}/u', $name);
        if ($letterCount === false || $letterCount < 4 || !preg_match('/^[\p{L}\s.\'-]+$/u', $name)) {
            throw new InvalidArgumentException('Full name must contain at least 4 letters and may only include letters, spaces, apostrophes, hyphens, or periods.');
        }
        $department = (int)($_POST['department_id'] ?? 0) ?: null;
        $shift = (int)($_POST['shift_id'] ?? 0) ?: null;
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive', 'on_leave'], true) || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('Enter valid employee details.');
        if ($action === 'update') {
            $id = postInt('id');
            $stmt = $conn->prepare('UPDATE employees SET employee_code=?,full_name=?,department_id=?,email=?,phone=?,status=?,shift_id=? WHERE id=?');
            $stmt->bind_param('ssisssii', $code, $name, $department, $email, $phone, $status, $shift, $id);
            $stmt->execute();
            auditLog($conn, 'employee', $id, 'update', ['employee_code' => $code, 'status' => $status, 'shift_id' => $shift]);
            redirectWithFlash('/admin/employees.php', 'Employee updated.');
        }
        $credentialYear = (int)date('Y');
        $namePrefix = strtolower((string)preg_replace('/[^a-z]/i', '', $name));
        $namePrefix = substr($namePrefix, 0, 4);
        $loginUsername = $namePrefix . $credentialYear;
        $suffix = 1;
        $usernameCheck = $conn->prepare('SELECT id FROM employees WHERE login_username=? LIMIT 1');
        while (true) {
            $usernameCheck->bind_param('s', $loginUsername);
            $usernameCheck->execute();
            if (!$usernameCheck->get_result()->num_rows) {
                break;
            }
            $suffix++;
            $loginUsername = $namePrefix . $credentialYear . $suffix;
        }
        $plainPassword = strtoupper(substr($namePrefix, 0, 1)) . substr($namePrefix, 1) . $credentialYear;
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO employees (employee_code,full_name,department_id,email,phone,status,shift_id,login_username,password_hash,credential_year) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('ssisssissi', $code, $name, $department, $email, $phone, $status, $shift, $loginUsername, $passwordHash, $credentialYear);
        $stmt->execute();
        auditLog($conn, 'employee', $stmt->insert_id, 'create', ['employee_code' => $code, 'status' => $status, 'shift_id' => $shift, 'login_username' => $loginUsername, 'credential_year' => $credentialYear]);
        redirectWithFlash('/admin/employees.php', 'Employee created. Login username: ' . $loginUsername . ' | Temporary password: ' . $plainPassword . ' | Credential year: ' . $credentialYear . '.');
    }
} catch (Throwable $e) {
    if (isAjaxRequest()) sendAjaxError($e);
    flash($e->getMessage(), 'error');
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare('SELECT * FROM employees WHERE id=?');
    $id = (int)$_GET['edit'];
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
}
$departments = $conn->query('SELECT id,name FROM departments ORDER BY name');
$shifts = $conn->query('SELECT id,shift_name FROM shifts ORDER BY shift_name');
$pageSize = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalRows = (int)$conn->query('SELECT COUNT(*) AS total FROM employees')->fetch_assoc()['total'];
$totalPages = max(1, (int)ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$rows = $conn->prepare('SELECT e.*,d.name department,s.shift_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN shifts s ON s.id=e.shift_id ORDER BY e.id DESC LIMIT ? OFFSET ?');
$rows->bind_param('ii', $pageSize, $offset);
$rows->execute();
$rows = $rows->get_result();
$notice = consumeFlash();
adminHeader('Employees', 'employees');
?>
<?php if ($notice): ?><div class="flash <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endif; ?>
<div class="page-heading">
    <div>
        <p class="eyebrow">PEOPLE DIRECTORY</p>
        <h2><?= $edit ? 'Edit employee' : 'Employee roster' ?></h2>
    </div><a class="btn btn-primary" href="<?= BASE_URL ?>/admin/employees.php"><?= $edit ? 'Cancel edit' : '+ Add employee' ?></a>
</div>
<div class="panel form-panel">
    <form method="post" data-ajax-form><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>"><?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="form-grid"><label>Employee code<input name="employee_code" required maxlength="50" value="<?= e($edit['employee_code'] ?? '') ?>"></label><label>Full name<input name="full_name" required minlength="4" maxlength="150" pattern="[\p{L}\s.'-]{4,}" title="Enter at least 4 letters. Letters, spaces, apostrophes, hyphens, and periods are allowed." value="<?= e($edit['full_name'] ?? '') ?>"></label><label>Department<select name="department_id">
                    <option value="">Unassigned</option><?php while ($d = $departments->fetch_assoc()): ?><option value="<?= (int)$d['id'] ?>" <?= (($edit['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endwhile; ?>
                </select></label><label>Shift<select name="shift_id">
                    <option value="">Unassigned</option><?php while ($s = $shifts->fetch_assoc()): ?><option value="<?= (int)$s['id'] ?>" <?= (($edit['shift_id'] ?? '') == $s['id']) ? 'selected' : '' ?>><?= e($s['shift_name']) ?></option><?php endwhile; ?>
                </select></label><label>Email<input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label><label>Phone<input name="phone" maxlength="30" value="<?= e($edit['phone'] ?? '') ?>"></label><label>Status<select name="status"><?php foreach (['active', 'inactive', 'on_leave'] as $v): ?><option value="<?= $v ?>" <?= (($edit['status'] ?? 'active') === $v) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $v))) ?></option><?php endforeach; ?></select></label></div><button class="btn btn-primary" type="submit"><?= $edit ? 'Update employee' : 'Create employee' ?></button>
    </form>
</div>
<div class="panel table-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Shift</th>
                    <th>Contact</th>
                    <th>Login username</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody><?php while ($row = $rows->fetch_assoc()): ?><tr>
                        <td><strong><?= e($row['full_name']) ?></strong><br><small><?= e($row['employee_code']) ?></small></td>
                        <td><?= e($row['department'] ?? 'Unassigned') ?></td>
                        <td><?= e($row['shift_name'] ?? 'Unassigned') ?></td>
                        <td><?= e($row['email'] ?? '-') ?></td>
                        <td><?= e($row['login_username'] ?? '-') ?><br><small>Year: <?= e((string)($row['credential_year'] ?? '-')) ?></small></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></td>
                        <td><a class="btn btn-small" href="?edit=<?= (int)$row['id'] ?>">Edit</a>
                            <form class="inline-form" method="post" data-ajax-form onsubmit="return confirm('Delete this employee?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-small" type="submit">Delete</button></form>
                        </td>
                    </tr><?php endwhile; ?></tbody>
        </table>
    </div>
</div>
<?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Employee pages"><?php for ($i = 1; $i <= $totalPages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
<?php adminFooter(); ?>