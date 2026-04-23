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
    
    error_log("Dados recebidos em nova-senha.php: " . json_encode(array_diff_key($data, ['novaSenha' => ''])));

    $token = $data['token'] ?? '';
    $senhaHash = $data['novaSenha'] ?? '';

    if (empty($token)) {
        throw new Exception('Token de recuperação é obrigatório.');
    }
    if (empty($senhaHash)) {
        throw new Exception('Nova senha é obrigatória.');
    }

    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE token_recuperacao = ? AND token_recuperacao_expira > NOW()");
    if (!$stmt) {
        throw new Exception('Erro na preparação da consulta: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $token);
    if (!$stmt->execute()) {
        throw new Exception('Erro na execução da consulta: ' . $stmt->error);
    }
    
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        throw new Exception('Token inválido ou expirado.');
    }
    
    $stmt->bind_result($usuarioId);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE usuarios SET senha_hash = ?, token_recuperacao = NULL, token_recuperacao_expira = NULL WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Erro na preparação da atualização: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $senhaHash, $usuarioId);

    if (!$stmt->execute()) {
        error_log("Erro SQL ao atualizar senha: " . $stmt->error);
        throw new Exception('Erro ao atualizar senha: ' . $stmt->error);
    }

    $stmt->close();

    $response = [
        'success' => true,
        'message' => 'Senha alterada com sucesso! Você será redirecionado para a página de login.'
    ];

    error_log("Senha alterada com sucesso para usuário ID: $usuarioId");

} catch (Exception $e) {
    error_log("Erro em nova-senha.php: " . $e->getMessage());
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
        error_log("Resposta criptografada enviada com sucesso em nova-senha.php");
    } else {
        error_log("Enviando resposta não criptografada em nova-senha.php. canEncrypt: " . ($canEncrypt ? 'true' : 'false'));
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
    
    error_log("Erro ao preparar resposta final em nova-senha.php: " . $e->getMessage());
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