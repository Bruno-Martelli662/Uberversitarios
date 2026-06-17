<?php
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cripto.php';

header('Content-Type: application/json; charset=utf-8');

$conn = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido.');
    }

    $payload = json_decode(file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        throw new Exception('JSON inválido.');
    }

    // Criptografia híbrida: decifra o envelope, se vier cifrado (mantém compatibilidade com texto puro)
    if (isset($payload['encryptedKey'], $payload['iv'], $payload['encryptedData'])) {
        $data = Cripto::descriptografarEnvelope($payload);
    } else {
        $data = $payload;
    }

    $token = trim($data['token'] ?? '');
    $codigo = trim($data['codigo'] ?? '');

    if ($token === '' || $codigo === '') {
        throw new Exception('Token e código são obrigatórios.');
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new Exception('Token inválido.');
    }

    if (!preg_match('/^\d{6}$/', $codigo)) {
        throw new Exception('Código inválido.');
    }

    $conn = getAdminDBConnection();

    $stmt = $conn->prepare("
        SELECT id, codigo_login_hash, codigo_login_expira, email_login_confirmado, email_login_expira
        FROM user_adm
        WHERE email_login_token = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta.');
    }

    $stmt->bind_param("s", $token);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Token inválido.');
    }

    $admin = $result->fetch_assoc();
    $stmt->close();

    if ((int)$admin['email_login_confirmado'] !== 1) {
        throw new Exception('E-mail ainda não confirmado.');
    }

    if (strtotime($admin['email_login_expira']) < time()) {
        throw new Exception('Confirmação de e-mail expirada.');
    }

    if (empty($admin['codigo_login_hash']) || empty($admin['codigo_login_expira'])) {
        throw new Exception('Nenhum código Telegram foi solicitado.');
    }

    if (strtotime($admin['codigo_login_expira']) < time()) {
        throw new Exception('Código Telegram expirado.');
    }

    if (hash('sha256', $codigo) !== $admin['codigo_login_hash']) {
        throw new Exception('Código incorreto.');
    }

    $adminToken = gerarToken();
    $expiraSessao = date('Y-m-d H:i:s', strtotime('+2 hours'));

    $stmt = $conn->prepare("
        UPDATE user_adm
        SET 
            admin_sessao_token = ?,
            admin_sessao_expira = ?,
            email_login_token = NULL,
            email_login_expira = NULL,
            email_login_confirmado = 0,
            codigo_login_hash = NULL,
            codigo_login_expira = NULL
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro ao criar sessão admin.');
    }

    $stmt->bind_param("ssi", $adminToken, $expiraSessao, $admin['id']);

    if (!$stmt->execute()) {
        throw new Exception('Erro ao salvar sessão admin.');
    }

    $stmt->close();

    setcookie('adminToken', $adminToken, [
        'expires' => time() + 7200,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => false
    ]);

    $response = [
        'success' => true,
        'message' => 'Login admin realizado com sucesso.'
    ];

} catch (Exception $e) {
    error_log("Erro admin-verificar-codigo.php: " . $e->getMessage());

    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];

} finally {
    if ($conn) {
        $conn->close();
    }
}

if (ob_get_level()) ob_clean();

echo json_encode($response, JSON_UNESCAPED_UNICODE);

if (ob_get_level()) ob_end_flush();

exit;
?>