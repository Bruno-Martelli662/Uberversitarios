<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cripto.php';
header('Content-Type: application/json; charset=utf-8');

// Oculta erros visuais e ativa o modo estrito do banco
ini_set('display_errors', 0);
ini_set('log_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Método não permitido.");
    }

    // Suporta envelope cifrado (criptografia híbrida), JSON puro (Fetch) e Formulário Padrão
    $payload = json_decode(file_get_contents('php://input'), true);

    if (is_array($payload) && isset($payload['encryptedKey'], $payload['iv'], $payload['encryptedData'])) {
        $data = Cripto::descriptografarEnvelope($payload);
    } else {
        $data = is_array($payload) ? $payload : [];
    }

    $email = trim($data['email'] ?? $_POST['email'] ?? '');
    $senha = trim($data['password'] ?? $_POST['password'] ?? '');

    if ($email === '' || $senha === '') {
        throw new Exception("E-mail e senha são obrigatórios.");
    }

    if (!validarEmail($email)) {
        throw new Exception("E-mail inválido.");
    }

    if (mb_strlen($senha) < 8 || mb_strlen($senha) > 200) {
        throw new Exception("Senha inválida.");
    }

    $conn = getAuthDBConnection();

    // 1. Verifica credenciais
    $stmt = $conn->prepare("SELECT id, nome_usuario, senha_hash FROM usuarios WHERE LOWER(email) = LOWER(?)");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Usuário não encontrado com este e-mail.");
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if (hash('sha256', $senha) !== $user['senha_hash']) {
        throw new Exception("A senha digitada está incorreta.");
    }

    $usuario_id = $user['id'];

    // 2. Coleta dados do usuário para o cofre LGPD
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $usuario_dados = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    unset($usuario_dados['senha_hash']); // Segurança

    $connRead = getReadDBConnection();
    $stmtRead = $connRead->prepare("SELECT * FROM viagens WHERE motorista_id = ? OR passageiro_id = ?");
    $stmtRead->bind_param("ii", $usuario_id, $usuario_id);
    $stmtRead->execute();
    
    $res_viagens = $stmtRead->get_result();
    $viagens_dados = [];
    while($v = $res_viagens->fetch_assoc()) {
        $viagens_dados[] = $v;
    }
    $stmtRead->close();
    $connRead->close();

    // 3. Salva no cofre LGPD
    $pacote_lgpd = json_encode(['usuario' => $usuario_dados, 'viagens' => $viagens_dados]);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt_cofre = $conn->prepare("INSERT INTO lgpd_arquivamento (usuario_id, dados_json, ip_origem) VALUES (?, ?, ?)");
    $stmt_cofre->bind_param("iss", $usuario_id, $pacote_lgpd, $ip);
    $stmt_cofre->execute();
    $stmt_cofre->close();

    // 4. REGISTRA O LOG ANTES DE DELETAR (a FK depende do usuário ainda existir)
    registrarLog($usuario_id, 'EXCLUSAO', "O usuário excluiu a própria conta e os dados foram movidos para o cofre LGPD.");

    // 5. Deleta da base principal
    $stmt_del = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt_del->bind_param("i", $usuario_id);
    $stmt_del->execute();
    $stmt_del->close();
    
    $conn->close();

    // Limpa o cookie de sessão do servidor
    setcookie('authToken', '', time() - 3600, '/');

    echo json_encode(['success' => true, 'message' => 'Conta e dados excluídos com sucesso!']);

} catch (Exception $e) {
    error_log("Erro na deleção de usuário: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>