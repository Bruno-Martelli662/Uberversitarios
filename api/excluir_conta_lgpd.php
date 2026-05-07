<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token não fornecido']);
    exit;
}

$conn = getAuthDBConnection();

// 1. Identifica o usuário
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

// 2. Coleta todos os dados para o cofre
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario_dados = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Remove a senha do backup por segurança
unset($usuario_dados['senha_hash']);

// Coleta viagens
$stmt = $conn->prepare("SELECT * FROM viagens WHERE motorista_id = ? OR passageiro_id = ?");
$stmt->bind_param("ii", $usuario_id, $usuario_id);
$stmt->execute();
$viagens_dados = [];
$res_viagens = $stmt->get_result();
while($v = $res_viagens->fetch_assoc()) {
    $viagens_dados[] = $v;
}
$stmt->close();

// 3. Monta o pacote JSON e salva no Cofre
$pacote_lgpd = json_encode([
    'usuario' => $usuario_dados,
    'viagens' => $viagens_dados
]);
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$stmt_cofre = $conn->prepare("INSERT INTO lgpd_arquivamento (usuario_id, dados_json, ip_origem) VALUES (?, ?, ?)");
$stmt_cofre->bind_param("iss", $usuario_id, $pacote_lgpd, $ip);
$stmt_cofre->execute();
$stmt_cofre->close();

// 4. Deleta o usuário da base ativa (O ON DELETE CASCADE nas chaves estrangeiras apagará sessões e viagens)
$stmt_del = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt_del->bind_param("i", $usuario_id);

if ($stmt_del->execute()) {
    echo json_encode(['success' => true, 'message' => 'Conta e dados excluídos com sucesso.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir a conta.']);
}

$stmt_del->close();
$conn->close();
?>