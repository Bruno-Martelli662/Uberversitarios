<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cripto.php';
header('Content-Type: application/json');

$token = trim($_GET['token'] ?? '');
if (!validarTokenHex($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

$conn = getAuthDBConnection();
$stmt = $conn->prepare("
    SELECT u.nome_usuario, u.telefone
    FROM sessoes s
    JOIN usuarios u ON s.usuario_id = u.id
    WHERE s.token_sessao = ? AND s.data_expiracao > NOW()
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

if ($user) {
    echo json_encode(['success' => true, 'nome' => Cripto::decifrarBDSeguro($user['nome_usuario']), 'telefone' => $user['telefone']]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido ou expirado']);
}