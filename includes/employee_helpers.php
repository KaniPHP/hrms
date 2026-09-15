<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_helpers.php';
requireEmployee();

function employeeDb(): mysqli { return Database::connection(); }
function employeeId(): int { return (int)$_SESSION['hrms_employee_id']; }
