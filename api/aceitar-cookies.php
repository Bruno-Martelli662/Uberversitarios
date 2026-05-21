<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getWriteDBConnection();

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

$stmt = $conn->prepare("
    INSERT INTO consentimentos_cookies (usuario_id, aceitou, ip_origem, user_agent)
    VALUES (NULL, 1, ?, ?)
");

$stmt->bind_param("ss", $ip, $userAgent);
$stmt->execute();
$stmt->close();

registrarLog(null, 'ESCRITA', 'Consentimento de cookies LGPD aceito.');

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Consentimento registrado.'
]);
?>