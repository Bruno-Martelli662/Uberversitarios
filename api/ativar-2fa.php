<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../GoogleAuthenticator.php';
require_once __DIR__ . '/../crypto_utils.php';

header('Content-Type: application/json');

try {
    $data = CryptoUtils::processEncryptedRequest();
    
    $codigo = $data['codigo'] ?? null;
    $secret = $data['secret'] ?? null;
    
    if (!$codigo || !$secret) {
        throw new Exception('Dados inválidos para ativação do 2FA');
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
    
    $user_id = $result->fetch_assoc()['usuario_id'];
    
    $ga = new GoogleAuthenticator();
    
    if (!$ga->verifyCode($secret, $codigo, 2)) {
        throw new Exception('Código de verificação inválido');
    }
    
    $stmt = $conn->prepare("UPDATE usuarios SET google_2fa_secret = ?, google_2fa_ativado = TRUE WHERE id = ?");
    $stmt->bind_param("si", $secret, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao ativar 2FA no banco de dados');
    }
    
    $response = ['success' => true, 'message' => 'Autenticação em dois fatores ativada com sucesso!'];
    
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
    
    error_log("Erro ativar-2fa.php: " . $e->getMessage());
}
?>