<?php
ob_start();

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = null;

try {
    session_start();

    $token = $_COOKIE['authToken'] ?? $headers['Authorization'] ?? $_GET['token'] ?? '';
    $token = trim($token);
    if (!validarTokenHex($token)) {
        throw new Exception('Token inválido');
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

    if ($result->num_rows > 0) {
        $sessao = $result->fetch_assoc();

        $_SESSION['user_id'] = $sessao['usuario_id'];

        $response = [
            'autenticado' => true
        ];
    } else {
        $response = [
            'autenticado' => false
        ];
    }

    $stmt->close();

} catch (Exception $e) {
    error_log("Erro verificar-sessao.php: " . $e->getMessage());

    $response = [
        'autenticado' => false,
        'erro' => $e->getMessage()
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