<?php
require_once 'config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Token de confirmação inválido.");
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT id FROM usuarios WHERE token_confirmacao = ? AND confirmado = FALSE");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Token de confirmação inválido ou já utilizado.");
}

$usuario = $result->fetch_assoc();

$stmt = $conn->prepare("UPDATE usuarios SET confirmado = TRUE, token_confirmacao = NULL WHERE id = ?");
$stmt->bind_param("i", $usuario['id']);
$stmt->execute();
$stmt->close();

echo "E-mail confirmado com sucesso! Você já pode fazer login.";

$conn->close();
?>