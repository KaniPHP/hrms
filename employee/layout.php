<?php
require_once __DIR__ . '/../includes/employee_helpers.php';
function employeeHeader(string $title, string $active = 'dashboard'): void
{
    $menus = ['dashboard' => ['📊', 'Dashboard', '/employee/dashboard.php'], 'leave' => ['🌿', 'My Leave', '/employee/leave.php'], 'attendance' => ['🗓️', 'My Attendance', '/employee/attendance.php']];
?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= e($title) ?> | HRMS Pro</title>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="<?= BASE_URL ?>/assets/js/app.js?v=5" defer></script>
        <script src="<?= BASE_URL ?>/assets/js/employee.js?v=1" defer></script>
    </head>

    <body class="admin-body">
        <div class="app-shell">
            <aside class="sidebar"><a class="side-brand" href="<?= BASE_URL ?>/employee/dashboard.php"><span>✦</span> HRMS <small>EMPLOYEE</small></a>
                <p class="menu-caption">MY WORKSPACE</p>
                <nav><?php foreach ($menus as $key => $menu): ?><a class="side-link <?= $active === $key ? 'active' : '' ?>" href="<?= BASE_URL . $menu[2] ?>"><span><?= $menu[0] ?></span><?= e($menu[1]) ?></a><?php endforeach; ?></nav>
                <div class="side-footer"><span>Employee self-service</span><a href="<?= BASE_URL ?>/employee/logout.php">Log out ↗</a></div>
            </aside>
            <section class="main-area">
                <header class="admin-topbar">
                    <div>
                        <p class="eyebrow">EMPLOYEE PORTAL</p>
                        <h1><?= e($title) ?></h1>
                    </div>
                    <div class="profile-chip"><span class="avatar">👤</span><span><?= e($_SESSION['hrms_employee_name']) ?><small>Employee</small></span><a class="btn btn-small" href="<?= BASE_URL ?>/employee/logout.php">Logout</a></div>
                </header>
                <main class="page-content"><?php }
                                        function employeeFooter(): void
                                        { ?></main>
            </section>
        </div>
    </body>

    </html><?php }
