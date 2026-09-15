<?php
session_start();

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'hrms_db';

$baseUrl = '/HRMS';

define('BASE_URL', $baseUrl);

function getDbConnection(): mysqli {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    if ($conn->connect_error) {
        throw new RuntimeException('Database connection failed: ' . $conn->connect_error);
    }

    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function redirect(string $path): void {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function isLoggedIn(): bool {
    return isset($_SESSION['hrms_user_id'], $_SESSION['hrms_user_role']);
}

function requireLogin(): void {
    if (!isAdmin()) {
        redirect('/index.php');
    }
}

function isAdmin(): bool { return isLoggedIn() && $_SESSION['hrms_user_role'] === 'admin'; }
function isEmployee(): bool { return isLoggedIn() && $_SESSION['hrms_user_role'] === 'employee'; }
function requireAdmin(): void { if (!isAdmin()) redirect('/index.php'); }
function requireEmployee(): void { if (!isEmployee()) redirect('/index.php'); }
?>
