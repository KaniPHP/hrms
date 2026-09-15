<?php
require_once __DIR__ . '/includes/auth.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    } else {
        if (($_POST['role'] ?? 'admin') === 'employee' && loginEmployee($email, $password)) {
            redirect('/employee/dashboard.php');
        }
        if (loginAdmin($email, $password)) {
            redirect('/admin/dashboard.php');
        }

        $errors[] = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS Portal Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-shell">
            <div class="login-hero">
                <div class="logo">HRMS Pro</div>
                <h1>Attendance, Leave & Shift Management</h1>
                <p>Monitor factory attendance, approve leave balances, and ensure payroll-ready reporting for your operations team.</p>

                <ul class="hero-features">
                    <li><span class="dot"></span> Real-time shift punch tracking</li>
                    <li><span class="dot"></span> Leave priority logic and auto-balancing</li>
                    <li><span class="dot"></span> Monthly attendance roll-up and approvals</li>
                </ul>
            </div>

            <div class="login-form-wrap">
                <div class="login-card">
                    <span class="brand-badge">Admin & employee portal</span>
                    <h2>Welcome back</h2>
                    <p class="subtext">Admins use email. Employees use the documented username and password format.</p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($errors[0], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="index.php">
                        <div class="form-group">
                            <label for="role">Sign in as</label>
                            <select id="role" name="role"><option value="admin">Administrator</option><option value="employee">Employee</option></select>
                        </div>
                        <div class="form-group">
                            <label for="email">Email / employee username</label>
                            <input type="text" id="email" name="email" placeholder="admin@hrms.com or ravi2026" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Enter password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Login to dashboard</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
