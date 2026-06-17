<?php
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cripto.php';

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

    $payload = json_decode(file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        throw new Exception('JSON inválido ou vazio.');
    }

    // Criptografia híbrida: decifra o envelope, se vier cifrado (mantém compatibilidade com texto puro)
    if (isset($payload['encryptedKey'], $payload['iv'], $payload['encryptedData'])) {
        $data = Cripto::descriptografarEnvelope($payload);
    } else {
        $data = $payload;
    }

    error_log("Dados recebidos em nova-senha.php: " . json_encode(array_diff_key($data, ['novaSenha' => ''])));

    $token = trim($data['token'] ?? '');
    $token = trim($data['token'] ?? '');
    $senhaHash = trim($data['novaSenha'] ?? '');
    
    if (!validarTokenHex($token)) {
        throw new Exception('Token de recuperação inválido.');
    }
    
    if (!validarHashSenha($senhaHash)) {
        throw new Exception('Hash de senha inválido.');
    }
    $conn = getAuthDBConnection();

    $stmt = $conn->prepare("
        SELECT id 
        FROM usuarios 
        WHERE token_recuperacao = ? 
        AND token_recuperacao_expira > NOW()
    ");
    
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

    $stmt = $conn->prepare("
        UPDATE usuarios 
        SET senha_hash = ?, token_recuperacao = NULL, token_recuperacao_expira = NULL 
        WHERE id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Erro na preparação da atualização: ' . $conn->error);
    }

    $stmt->bind_param("si", $senhaHash, $usuarioId);

    if (!$stmt->execute()) {
        error_log("Erro SQL ao atualizar senha: " . $stmt->error);
        throw new Exception('Erro ao atualizar senha.');
    }

    $stmt->close();
    
    // REGISTRO DO LOG AQUI
    registrarLog($usuarioId, 'ALTERACAO', 'Senha redefinida com sucesso via token de recuperação.');

    error_log("Senha alterada com sucesso para usuário ID: " . $usuarioId);
    
    $response = [
        'success' => true,
        'message' => 'Senha alterada com sucesso! Você será redirecionado para a página de login.'
    ];

} catch (Exception $e) {
    error_log("Erro em nova-senha.php: " . $e->getMessage());
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