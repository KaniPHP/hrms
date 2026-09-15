<?php

declare(strict_types=1);

function adminHeader(string $title, string $active = 'dashboard'): void
{
    requireAdmin();
    $menus = [
        'dashboard' => ['📊', 'Dashboard', '/admin/dashboard.php'],
        'employees' => ['👥', 'Employees', '/admin/employees.php'],
        'shifts' => ['⏱️', 'Shifts', '/admin/shifts.php'],
        'punches' => ['↔️', 'Punches', '/admin/punches.php'],
        'attendance' => ['🗓️', 'Attendance', '/admin/attendance.php'],
        'leaves' => ['🌿', 'Leave Management', '/admin/leaves.php'],
        'leave_balances' => ['💳', 'Leave Balances', '/admin/leave_balances.php'],
        'leave_history' => ['📜', 'Leave History', '/admin/leave_history.php'],
        'processing' => ['⚙️', 'Monthly Processing', '/admin/processing.php'],
    ];
?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | HRMS Pro</title>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
        <script src="<?= BASE_URL ?>/assets/js/app.js?v=4" defer></script>
        <script src="<?= BASE_URL ?>/assets/js/<?= e($active) ?>.js?v=4" defer></script>
    </head>

    <body class="admin-body" data-page="<?= e($active) ?>">
        <div class="app-shell">
            <aside class="sidebar">
                <a class="side-brand" href="<?= BASE_URL ?>/admin/dashboard.php"><span>✦</span> HRMS <small>PRO</small></a>
                <p class="menu-caption">WORKSPACE</p>
                <nav>
                    <?php foreach ($menus as $key => $menu): ?>
                        <a class="side-link <?= $active === $key ? 'active' : '' ?>" href="<?= BASE_URL . $menu[2] ?>">
                            <span><?= $menu[0] ?></span><?= e($menu[1]) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="side-footer"><span>Secure admin workspace</span><a class="topbar-logout" href="<?= BASE_URL ?>/admin/logout.php"><span class="logout-icon" aria-hidden="true">↪</span><span>Logout</span></a></div>
            </aside>
            <section class="main-area">
                <header class="admin-topbar">
                    <div>
                        <p class="eyebrow">MANUFACTURING HRMS</p>
                        <h1><?= e($title) ?></h1>
                    </div>
                    <div class="topbar-actions">
                        <div class="profile-chip"><span class="avatar">👤</span><span><?= e($_SESSION['hrms_admin_name'] ?? 'Super Admin') ?><small><?= e($_SESSION['hrms_admin_role'] ?? 'Super Admin') ?></small></span></div>
                        <a class="topbar-logout" href="<?= BASE_URL ?>/admin/logout.php" title="Log out of the admin panel"><span class="logout-icon" aria-hidden="true">↪</span><span>Logout</span></a>
                    </div>
                </header>
                <main class="page-content">
                <?php
            }

            function adminFooter(): void
            {
                ?>
                </main>
            </section>
        </div>
    </body>

    </html>
<?php
            }
