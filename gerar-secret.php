<?php
require_once 'config.php';
require_once 'GoogleAuthenticator.php';
require_once 'crypto_utils.php';

header('Content-Type: application/json');

try {
    $requestData = [];
    if (!empty(file_get_contents('php://input'))) {
        $requestData = CryptoUtils::processEncryptedRequest();
    }

    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    
    if (!$token) {
        throw new Exception('Token de autenticação não fornecido');
    }
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT usuario_id FROM sessoes WHERE token_sessao = ? AND data_expiracao > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Sessão inválida ou expirada');
    }

    $ga = new GoogleAuthenticator();
    $secret = $ga->createSecret();
    
    $issuer = urlencode('Experiencia Criativa');
    $label = urlencode('ProjetoExp');
    $otpauth = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";
    
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth);
    
    $response = [
        'success' => true,
        'secret' => $secret,
        'qrCodeUrl' => $qrCodeUrl
    ];
    
    if (CryptoUtils::canSendEncryptedResponse()) {
        $encryptedResponse = CryptoUtils::prepareEncryptedResponse($response);
        echo json_encode($encryptedResponse);
    } else {
        echo json_encode($response);
    }
    
} catch (Exception $e) {
    $errorResponse = ['success' => false, 'message' => $e->getMessage()];
    
    if (CryptoUtils::canSendEncryptedResponse()) {
        $encryptedError = CryptoUtils::prepareEncryptedResponse($errorResponse);
        echo json_encode($encryptedError);
    } else {
        echo json_encode($errorResponse);
    }
    
    error_log("Erro gerar-secret.php: " . $e->getMessage());
}
?>