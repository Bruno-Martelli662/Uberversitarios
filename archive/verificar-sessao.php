<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../crypto_utils.php';

header('Content-Type: application/json');

try {
    $requestData = [];
    if (!empty(file_get_contents('php://input'))) {
        $requestData = CryptoUtils::processEncryptedRequest();
    }

    session_start();

    $headers = getallheaders();
    $token = $headers['Authorization'] ?? $_GET['token'] ?? '';

    if (empty($token)) {
        throw new Exception('Token não fornecido');
    }

    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT usuario_id FROM sessoes WHERE token_sessao = ? AND data_expiracao > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    $response = [];
    if ($result->num_rows > 0) {
        $_SESSION['user_id'] = $result->fetch_assoc()['usuario_id'];
        $response = ['autenticado' => true];
    } else {
        $response = ['autenticado' => false];
    }

    $stmt->close();
    $conn->close();

    if (CryptoUtils::canSendEncryptedResponse()) {
        $encryptedResponse = CryptoUtils::prepareEncryptedResponse($response);
        echo json_encode($encryptedResponse);
    } else {
        echo json_encode($response);
    }

} catch (Exception $e) {
    $errorResponse = ['autenticado' => false, 'erro' => $e->getMessage()];
    
    if (CryptoUtils::canSendEncryptedResponse()) {
        $encryptedError = CryptoUtils::prepareEncryptedResponse($errorResponse);
        echo json_encode($encryptedError);
    } else {
        echo json_encode($errorResponse);
    }
    
    error_log("Erro verificar-sessao.php: " . $e->getMessage());
}
?>