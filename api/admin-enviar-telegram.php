<?php
ob_start();

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido.');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        throw new Exception('JSON inválido.');
    }

    $token = trim($data['token'] ?? '');

    if ($token === '') {
        throw new Exception('Token é obrigatório.');
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new Exception('Token inválido.');
    }

    $conn = getAdminDBConnection();

    $stmt = $conn->prepare("
        SELECT id, telegram_id, email_login_confirmado, email_login_expira
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

    if (empty($admin['telegram_id'])) {
        throw new Exception('Telegram não vinculado ao administrador.');
    }

    $codigo = random_int(100000, 999999);
    $codigoHash = hash('sha256', (string)$codigo);
    $expira = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    $stmt = $conn->prepare("
        UPDATE user_adm
        SET codigo_login_hash = ?, codigo_login_expira = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro ao preparar atualização.');
    }

    $stmt->bind_param("ssi", $codigoHash, $expira, $admin['id']);

    if (!$stmt->execute()) {
        throw new Exception('Erro ao salvar código Telegram.');
    }

    $stmt->close();

    $mensagem = "Código de acesso admin Uberversitários: {$codigo}\nExpira em 5 minutos.";

    if (!enviarTelegram($admin['telegram_id'], $mensagem)) {
        throw new Exception('Falha ao enviar código pelo Telegram.');
    }

    $response = [
        'success' => true,
        'message' => 'Código enviado no Telegram.'
    ];

} catch (Exception $e) {
    error_log("Erro admin-enviar-telegram.php: " . $e->getMessage());

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