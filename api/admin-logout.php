<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_COOKIE['adminToken'] ?? '';

if ($token !== '') {
    $conn = getAdminDBConnection();

    $stmt = $conn->prepare("
        UPDATE user_adm
        SET admin_sessao_token = NULL, admin_sessao_expira = NULL
        WHERE admin_sessao_token = ?
    ");

    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
    }

    $conn->close();
}

setcookie('adminToken', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => false
]);

echo json_encode([
    'success' => true,
    'message' => 'Logout admin realizado.'
]);
?>