<?php
ob_clean();

require_once 'config.php';
require_once 'crypto_utils.php';

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
    
    error_log("Dados recebidos para recuperação de senha");

    $email = $data['email'] ?? '';

    if (empty($email)) {
        throw new Exception('E-mail é obrigatório.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('E-mail inválido.');
    }

    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT id, nome_usuario FROM usuarios WHERE email = ?");
    if (!$stmt) {
        throw new Exception('Erro na preparação da consulta: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        throw new Exception('Erro na execução da consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Nenhuma conta encontrada com este e-mail.');
    }

    $usuario = $result->fetch_assoc();
    $stmt->close();

    $tokenRecuperacao = gerarToken();
    $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $conn->prepare("UPDATE usuarios SET token_recuperacao = ?, token_recuperacao_expira = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Erro na preparação da atualização: ' . $conn->error);
    }
    
    $stmt->bind_param("ssi", $tokenRecuperacao, $expiracao, $usuario['id']);

    if (!$stmt->execute()) {
        error_log("Erro SQL ao atualizar token: " . $stmt->error);
        throw new Exception("Erro ao atualizar token de recuperação");
    }
    $stmt->close();

    $linkRecuperacao = SITE_URL . "/nova-senha.html?token=" . urlencode($tokenRecuperacao);
    $assunto = "Recuperação de Senha - " . SITE_NAME;
    $mensagem = "
    <h1>Olá {$usuario['nome_usuario']},</h1>
    <p>Recebemos uma solicitação para redefinir sua senha.</p>
    <p>Clique no link abaixo para criar uma nova senha:</p>
    <p><a href='{$linkRecuperacao}'>{$linkRecuperacao}</a></p>
    <p>Se você não solicitou esta alteração, ignore este e-mail.</p>
    <p>Este link expira em 1 hora.</p>
    <p>Atenciosamente,<br>Equipe " . SITE_NAME . "</p>
    ";
    
    if (!enviarEmail($email, $assunto, $mensagem)) {
        error_log("Falha ao enviar e-mail de recuperação para: $email");
        throw new Exception("Falha ao enviar e-mail de recuperação");
    }

    $response = [
        'success' => true,
        'message' => 'Um e-mail com instruções foi enviado para seu endereço.'
    ];

} catch (Exception $e) {
    error_log("Erro na recuperação de senha: " . $e->getMessage());
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
    } else {
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