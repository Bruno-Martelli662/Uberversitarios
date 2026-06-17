<?php
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cripto.php';
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

    // >>> Valida sessão e obtém o ID do usuário logado <
    $usuarioId = exigirUsuarioLogado();

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

    $codigo = trim($data['codigo'] ?? '');
    $secret = trim($data['secret'] ?? '');

    if (!validarCodigoNumerico($codigo, 6)) {
        throw new Exception('Código 2FA inválido.');
    }

    // Regex de validação — secret Base32 (RFC 4648)
    if (!preg_match('/^[A-Z2-7]{16,32}$/', $secret)) {
        throw new Exception('Secret 2FA inválido.');
    }

    $ga = new GoogleAuthenticator();

    if (!$ga->verifyCode($secret, $codigo, 2)) {
        throw new Exception('Código de verificação inválido');
    }

    $conn = getAuthDBConnection();

    $stmt = $conn->prepare("
        UPDATE usuarios 
        SET google_2fa_secret = ?, google_2fa_ativado = TRUE 
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro na preparação da atualização.');
    }

    $stmt->bind_param("si", $secret, $usuarioId);

    if (!$stmt->execute()) {
        throw new Exception('Erro ao ativar 2FA no banco de dados');
    }

    error_log("ativar-2fa: UPDATE executado. usuarioId={$usuarioId}, affected_rows=" . $stmt->affected_rows);

    $stmt->close();

    registrarLog($usuarioId, 'ALTERACAO', "Usuário {$usuarioId} ativou 2FA.");

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