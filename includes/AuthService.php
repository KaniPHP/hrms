<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class AuthService
{
    public function login(string $email, string $password): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, full_name, password_hash, role FROM hrms_admins WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['hrms_user_id'] = (int) $admin['id'];
        $_SESSION['hrms_user_role'] = 'admin';
        $_SESSION['hrms_admin_id'] = (int) $admin['id'];
        $_SESSION['hrms_admin_name'] = $admin['full_name'];
        $_SESSION['hrms_admin_role'] = $admin['role'];
        return true;
    }

    public function loginEmployee(string $username, string $password): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, full_name, password_hash, login_username FROM employees
             WHERE login_username=? AND status <> "inactive" LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        if (!$employee || !$employee['password_hash'] || !password_verify($password, $employee['password_hash'])) return false;
        session_regenerate_id(true);
        $_SESSION['hrms_user_id'] = (int)$employee['id'];
        $_SESSION['hrms_user_role'] = 'employee';
        $_SESSION['hrms_employee_id'] = (int)$employee['id'];
        $_SESSION['hrms_employee_name'] = $employee['full_name'];
        $_SESSION['hrms_employee_username'] = $employee['login_username'];
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('/index.php');
    }
}
