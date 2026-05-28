<?php
ob_start();

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$conn = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido.');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        throw new Exception('JSON inválido ou vazio.');
    }

    error_log("Dados recebidos: " . json_encode(array_diff_key($data, ['senha' => ''])));

    $nome = trim($data['nome'] ?? '');
    $email = trim($data['email'] ?? '');
    $telefone = trim($data['telefone'] ?? '');
    $senhaHash = trim($data['senha'] ?? '');
    
    if ($nome === '') {
        throw new Exception('Nome é obrigatório.');
    }

    if ($email === '') {
        throw new Exception('E-mail é obrigatório.');
    }

    if ($telefone === '') {
        throw new Exception('Telefone é obrigatório.');
    }

    if ($senhaHash === '') {
        throw new Exception('Senha é obrigatória.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('E-mail inválido.');
    }

    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (!validarNome($nome)) {
        throw new Exception('Nome inválido.');
    }

    if (!validarHashSenha($senhaHash)) {
        throw new Exception('Hash de senha inválido.');
    }
    
    if (strlen($telefone) < 10 || strlen($telefone) > 11) {
        throw new Exception('Telefone inválido.');
    }

    $conn = getAuthDBConnection();

    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    
    if (!$stmt) {
        throw new Exception('Erro na preparação da consulta: ' . $conn->error);
    }

    $stmt->bind_param("s", $email);

    if (!$stmt->execute()) {
        throw new Exception('Erro na execução da consulta: ' . $stmt->error);
    }

    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        throw new Exception('Este e-mail já está cadastrado.');
    }

    $stmt->close();

    $tokenConfirmacao = gerarToken();

    $stmt = $conn->prepare("
        INSERT INTO usuarios 
        (nome_usuario, email, telefone, senha_hash, token_confirmacao) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception('Erro na preparação da inserção: ' . $conn->error);
    }

    $stmt->bind_param("sssss", $nome, $email, $telefone, $senhaHash, $tokenConfirmacao);
    
    if (!$stmt->execute()) {
        error_log("Erro SQL ao inserir usuário: " . $stmt->error);
        throw new Exception('Erro ao cadastrar usuário.');
    }

    $userId = $conn->insert_id;
    $stmt->close();
    
    // REGISTRO DO LOG AQUI
    registrarLog($userId, 'ESCRITA', "Novo usuário cadastrado com e-mail: $email");

    error_log("Usuário cadastrado com sucesso. ID: " . $userId);

    $linkConfirmacao = SITE_URL . "/api/confirmar-email.php?token=" . urlencode($tokenConfirmacao);

    $assunto = "Confirme seu e-mail";
    $mensagem = "
        <h1>Olá {$nome},</h1>
        <p>Obrigado por se cadastrar em nosso serviço!</p>
        <p>Por favor, clique no link abaixo para confirmar seu e-mail:</p>
        <p><a href='{$linkConfirmacao}'>{$linkConfirmacao}</a></p>
        <p>Se você não solicitou este cadastro, ignore este e-mail.</p>
        <p>Atenciosamente,<br>Equipe " .
        SITE_NAME . "</p>
    ";

    if (!enviarEmail($email, $assunto, $mensagem)) {
        error_log("Falha ao enviar e-mail de confirmação para: " . $email);
    }

    $response = [
        'success' => true,
        'message' => 'Cadastro realizado com sucesso!'
    ];

} catch (Exception $e) {
    error_log("Erro no cadastro: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
} finally {
    if ($conn) {
        $conn->close();
    }
}

if (ob_get_level()) {
    ob_clean();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

if (ob_get_level()) {
    ob_end_flush();
}

exit;
?>