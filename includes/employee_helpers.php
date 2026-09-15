<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/AuthService.php';
requireEmployee();

if (!empty($_SESSION['hrms_employee_id'])) {
    $activeCheck = Database::connection()->prepare('SELECT id FROM employees WHERE id=? AND status="active" LIMIT 1');
    $activeEmployeeId = (int)$_SESSION['hrms_employee_id'];
    $activeCheck->bind_param('i', $activeEmployeeId);
    $activeCheck->execute();
    if (!$activeCheck->get_result()->num_rows) {
        (new AuthService())->logout();
    }
}

function employeeDb(): mysqli { return Database::connection(); }
function employeeId(): int { return (int)$_SESSION['hrms_employee_id']; }
