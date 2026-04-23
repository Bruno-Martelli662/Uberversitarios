<?php
ob_clean();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../crypto_utils.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = null;
$canEncrypt = false;

try {
    $data = CryptoUtils::processEncryptedRequest();
    $canEncrypt = CryptoUtils::canSendEncryptedResponse();
    
    error_log("Dados recebidos: " . json_encode(array_diff_key($data, ['senha' => ''])));
    
    $nome = $data['nome'] ?? '';
    $email = $data['email'] ?? '';
    $telefone = $data['telefone'] ?? '';
    $senhaHash = $data['senha'] ?? '';

    if (empty($nome)) {
        throw new Exception('Nome é obrigatório.');
    }
    if (empty($email)) {
        throw new Exception('E-mail é obrigatório.');
    }
    if (empty($telefone)) {
        throw new Exception('Telefone é obrigatório.');
    }
    if (empty($senhaHash)) {
        throw new Exception('Senha é obrigatória.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('E-mail inválido.');
    }

    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) < 10 || strlen($telefone) > 11) {
        throw new Exception('Telefone inválido.');
    }

    $conn = getDBConnection();

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

    $stmt = $conn->prepare("INSERT INTO usuarios (nome_usuario, email, telefone, senha_hash, token_confirmacao) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Erro na preparação da inserção: ' . $conn->error);
    }
    
    $stmt->bind_param("sssss", $nome, $email, $telefone, $senhaHash, $tokenConfirmacao);

    if (!$stmt->execute()) {
        error_log("Erro SQL ao inserir usuário: " . $stmt->error);
        throw new Exception('Erro ao cadastrar usuário: ' . $stmt->error);
    }

    $userId = $conn->insert_id;
    error_log("Usuário cadastrado com sucesso. ID: $userId");

    $linkConfirmacao = SITE_URL . "/confirmar-email.php?token=" . urlencode($tokenConfirmacao);
    $assunto = "Confirme seu e-mail";
    $mensagem = "
    <h1>Olá {$nome},</h1>
    <p>Obrigado por se cadastrar em nosso serviço!</p>
    <p>Por favor, clique no link abaixo para confirmar seu e-mail:</p>
    <p><a href='{$linkConfirmacao}'>{$linkConfirmacao}</a></p>
    <p>Se você não solicitou este cadastro, ignore este e-mail.</p>
    <p>Atenciosamente,<br>Equipe " . SITE_NAME . "</p>
    ";
    
    if (!enviarEmail($email, $assunto, $mensagem)) {
        error_log("Falha ao enviar e-mail de confirmação para: $email");
    }

    $response = ['success' => true, 'message' => 'Cadastro realizado com sucesso!'];

} catch (Exception $e) {
    error_log("Erro no cadastro: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    $canEncrypt = false;
} finally {
    if (isset($conn)) $conn->close();
}

try {
    if (ob_get_level()) {
        ob_clean();
    }
    
    if ($canEncrypt && $response) {
        $encryptedResponse = CryptoUtils::prepareEncryptedResponse($response);
        echo json_encode($encryptedResponse, JSON_UNESCAPED_UNICODE);
        error_log("Resposta criptografada enviada com sucesso");
    } else {
        error_log("Enviando resposta não criptografada. canEncrypt: " . ($canEncrypt ? 'true' : 'false'));
        $finalResponse = $response ?: [
            'success' => false,
            'message' => 'Erro interno do servidor'
        ];
        echo json_encode($finalResponse, JSON_UNESCAPED_UNICODE);
    }
    
    if (ob_get_level()) {
        ob_end_flush();
    }
    
} catch (Exception $e) {
    if (ob_get_level()) {
        ob_clean();
    }
    
    error_log("Erro ao preparar resposta final: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor ao processar resposta'
    ], JSON_UNESCAPED_UNICODE);
    
    if (ob_get_level()) {
        ob_end_flush();
    }
}

exit;
?>