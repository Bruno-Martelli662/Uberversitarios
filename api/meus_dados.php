<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token não fornecido']);
    exit;
}

$conn = getAuthDBConnection();

// Valida a sessão e pega o ID do usuário
$stmt = $conn->prepare("SELECT usuario_id FROM sessoes WHERE token_sessao = ? AND data_expiracao > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida']);
    exit;
}
$usuario_id = $result->fetch_assoc()['usuario_id'];
$stmt->close();

// Busca os dados pessoais
$stmt = $conn->prepare("SELECT id, nome_usuario, email, telefone, confirmado, google_2fa_ativado FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$dados_pessoais = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Busca o histórico de viagens (como motorista)
$connRead = getReadDBConnection();
$stmtViagens = $connRead->prepare("SELECT origem, destino, veiculo, criada_em FROM viagens WHERE motorista_id = ?");
$stmtViagens->bind_param("i", $usuario_id);
$stmtViagens->execute();
$resultadoViagens = $stmtViagens->get_result();
$viagens = [];
while ($row = $resultadoViagens->fetch_assoc()) {
    $viagens[] = $row;
}
$stmtViagens->close();
$connRead->close();
$conn->close();

echo json_encode([
    'success' => true,
    'dados' => [
        'pessoais' => $dados_pessoais,
        'viagens' => $viagens
    ]
]);
?>