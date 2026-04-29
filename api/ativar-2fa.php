<?php
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../GoogleAuthenticator.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$conn = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido.');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        throw new Exception('JSON inválido ou vazio.');
    }

    $codigo = trim($data['codigo'] ?? '');
    $secret = trim($data['secret'] ?? '');

    if ($codigo === '' || $secret === '') {
        throw new Exception('Dados inválidos para ativação do 2FA');
    }

    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';

    if (empty($token)) {
        throw new Exception('Token de autenticação não fornecido');
    }

    $conn = getDBConnection();

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

    $sessao = $result->fetch_assoc();
    $userId = $sessao['usuario_id'];

    $stmt->close();

    $ga = new GoogleAuthenticator();

    if (!$ga->verifyCode($secret, $codigo, 2)) {
        throw new Exception('Código de verificação inválido');
    }

    $stmt = $conn->prepare("
        UPDATE usuarios 
        SET google_2fa_secret = ?, google_2fa_ativado = TRUE 
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro na preparação da atualização.');
    }

    $stmt->bind_param("si", $secret, $userId);

    if (!$stmt->execute()) {
        throw new Exception('Erro ao ativar 2FA no banco de dados');
    }

    $stmt->close();

    $response = [
        'success' => true,
        'message' => 'Autenticação em dois fatores ativada com sucesso!'
    ];

} catch (Exception $e) {
    error_log("Erro ativar-2fa.php: " . $e->getMessage());

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