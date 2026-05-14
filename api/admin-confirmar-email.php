<?php
require_once __DIR__ . '/../config.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    die("Token inválido.");
}

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    die("Token inválido.");
}

$conn = getAdminDBConnection();

$stmt = $conn->prepare("
    SELECT id
    FROM user_adm
    WHERE email_login_token = ?
    AND email_login_expira > NOW()
");

if (!$stmt) {
    die("Erro interno.");
}

$stmt->bind_param("s", $token);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Token inválido ou expirado.");
}

$admin = $result->fetch_assoc();

$stmt->close();

$stmt = $conn->prepare("
    UPDATE user_adm
    SET email_login_confirmado = 1
    WHERE id = ?
");

if (!$stmt) {
    die("Erro interno.");
}

$stmt->bind_param("i", $admin['id']);
$stmt->execute();

$stmt->close();

$stmt = $conn->prepare("
    SELECT perguntas_configuradas
    FROM user_adm
    WHERE id = ?
");

if (!$stmt) {
    die("Erro interno.");
}

$stmt->bind_param("i", $admin['id']);
$stmt->execute();

$result = $stmt->get_result();

$config = $result->fetch_assoc();

$stmt->close();

if ((int)$config['perguntas_configuradas'] !== 1) {

    header(
        "Location: ../html/admin_primeira_config.html?token="
        . urlencode($token)
    );

    $conn->close();

    exit;
}

header(
    "Location: ../html/admin-telegram.html?token="
    . urlencode($token)
);

$conn->close();

exit;
?>