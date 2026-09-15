<?php
require_once __DIR__ . '/config.php';

function loginAdmin(string $email, string $password): bool {
    require_once __DIR__ . '/AuthService.php';
    return (new AuthService())->login($email, $password);
}
function loginEmployee(string $username, string $password): bool {
    require_once __DIR__ . '/AuthService.php';
    return (new AuthService())->loginEmployee($username, $password);
}

function logoutAdmin(): void {
    require_once __DIR__ . '/AuthService.php';
    (new AuthService())->logout();
}
?>
