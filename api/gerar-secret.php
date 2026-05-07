<?php
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../GoogleAuthenticator.php';

header('Content-Type: application/json; charset=utf-8');

$conn = null;

try {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';

    if (empty($token)) {
        throw new Exception('Token de autenticação não fornecido');
    }

    $conn = getAuthDBConnection();

    $stmt = $conn->prepare("
        SELECT usuario_id 
        FROM sessoes 
        WHERE token_sessao = ? 
        AND data_expiracao > NOW()
    ");

    if (!$stmt) {
        throw new Exception('Erro na preparação da consulta.');
    }

    $stmt->bind_param("s", $token);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Sessão inválida ou expirada');
    }

    $stmt->close();

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

} catch (Exception $e) {
    error_log("Erro gerar-secret.php: " . $e->getMessage());

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