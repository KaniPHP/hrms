<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

function adminDb(): mysqli
{
    return Database::connection();
}

function csrfToken(): string
{
    if (empty($_SESSION['hrms_csrf'])) {
        $_SESSION['hrms_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['hrms_csrf'];
}

function verifyCsrf(): void
{
    if (!hash_equals((string)($_SESSION['hrms_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException('Invalid form token. Please try again.');
    }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['hrms_flash'] = ['message' => $message, 'type' => $type];
}

function consumeFlash(): ?array
{
    $message = $_SESSION['hrms_flash'] ?? null;
    unset($_SESSION['hrms_flash']);
    return $message;
}

function postInt(string $key): int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if (!$value || $value < 1) {
        throw new InvalidArgumentException('Please select a valid record.');
    }
    return $value;
}

function postText(string $key, int $max = 255): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    if ($value === '' || strlen($value) > $max) {
        throw new InvalidArgumentException('Please complete all required fields.');
    }
    return $value;
}

function postDate(string $key): string
{
    return normalizeDate((string)($_POST[$key] ?? ''), 'Please enter a valid date in DD/MM/YYYY format.');
}

function normalizeDate(string $value, string $errorMessage = 'Please enter a valid date in DD/MM/YYYY format.'): string
{
    $value = trim($value);
    $formats = ['Y-m-d', 'd/m/Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat('!' . $format, $value);
        $errors = DateTime::getLastErrors();
        $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
        if ($date && !$hasErrors && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }
    throw new InvalidArgumentException($errorMessage);
}

function redirectWithFlash(string $path, string $message, string $type = 'success'): void
{
    if (isAjaxRequest()) {
        sendAjaxJson(true, $message, $type, $path);
    }
    flash($message, $type);
    redirect($path);
}

function isAjaxRequest(): bool
{
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function sendAjaxJson(bool $success, string $message, string $type = 'success', string $path = '', array $extra = []): never
{
    http_response_code($success ? 200 : 422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success' => $success,
        'type' => $type,
        'message' => $message,
        'refresh_url' => $path !== '' ? BASE_URL . $path : '',
    ], $extra), JSON_THROW_ON_ERROR);
    exit;
}

function sendAjaxError(Throwable $error): never
{
    sendAjaxJson(false, $error->getMessage(), 'error');
}

function auditLog(mysqli $conn, string $entityType, int $entityId, string $action, array $details = []): void
{
    $actorId = (int)($_SESSION['hrms_admin_id'] ?? $_SESSION['hrms_user_id'] ?? 0) ?: null;
    $ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $payload = $details ? json_encode($details, JSON_THROW_ON_ERROR) : null;
    $stmt = $conn->prepare(
        'INSERT INTO audit_logs (actor_user_id,entity_type,entity_id,action,details,ip_address)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->bind_param('isisss', $actorId, $entityType, $entityId, $action, $payload, $ipAddress);
    $stmt->execute();
}

function leaveBalance(mysqli $conn, int $employeeId, string $leaveType, string $month): ?array
{
    $stmt = $conn->prepare(
        'SELECT opening_balance, earned_balance, utilized_balance, closing_balance, carry_forward
         FROM employee_leave_balances
         WHERE employee_id=? AND leave_type_code=? AND month_year=?
         LIMIT 1'
    );
    $stmt->bind_param('iss', $employeeId, $leaveType, $month);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}
