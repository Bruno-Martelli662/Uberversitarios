<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cripto.php';
header('Content-Type: application/json; charset=utf-8');

$token = trim($_GET['token'] ?? '');
if (!validarTokenHex($token)) {
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
}

$conn = getAuthDBConnection();

$stmt = $conn->prepare("SELECT usuario_id FROM sessoes WHERE token_sessao = ? AND data_expiracao > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sessão inválida']);
    exit;
}
$usuario_id = $result->fetch_assoc()['usuario_id'];
$stmt->close();

$stmt = $conn->prepare("SELECT nome_usuario, email, telefone, confirmado FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$dados_pessoais = $stmt->get_result()->fetch_assoc();
$stmt->close();

// S.3.2: nome vem cifrado do BD -> decifra (tolerante a registros antigos em texto)
if ($dados_pessoais) {
    $dados_pessoais['nome_usuario'] = Cripto::decifrarBDSeguro($dados_pessoais['nome_usuario']);
}

$connRead = getReadDBConnection();
$stmtViagens = $connRead->prepare("SELECT id, origem, destino, veiculo, criada_em FROM viagens WHERE motorista_id = ?");
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

echo json_encode(['success' => true, 'dados' => ['pessoais' => $dados_pessoais, 'viagens' => $viagens]]);
?>