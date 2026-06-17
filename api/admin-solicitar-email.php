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

    // Criptografia híbrida: decifra o envelope, se vier cifrado (mantém compatibilidade com texto puro)
    if (is_array($payload) && isset($payload['encryptedKey'], $payload['iv'], $payload['encryptedData'])) {
        $data = Cripto::descriptografarEnvelope($payload);
    } else {
        $data = $payload;
    }

    if (!is_array($data)) {
        throw new Exception('JSON inválido.');
    }

    $email = trim($data['email'] ?? '');

    if ($email === '') {
        throw new Exception('E-mail é obrigatório.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('E-mail inválido.');
    }

    $conn = getAdminDBConnection();

    $stmt = $conn->prepare("
        SELECT id, email
        FROM user_adm
        WHERE email = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta.');
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Administrador não encontrado.');
    }

    $admin = $result->fetch_assoc();
    $stmt->close();

    $token = gerarToken();
    $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $conn->prepare("
        UPDATE user_adm
        SET 
            email_login_token = ?,
            email_login_expira = ?,
            email_login_confirmado = 0,
            codigo_login_hash = NULL,
            codigo_login_expira = NULL,
            admin_sessao_token = NULL,
            admin_sessao_expira = NULL
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro ao preparar atualização.');
    }

    $stmt->bind_param("ssi", $token, $expira, $admin['id']);

    if (!$stmt->execute()) {
        throw new Exception('Erro ao salvar token de e-mail.');
    }

    $stmt->close();

    $link = SITE_URL . "/api/admin-confirmar-email.php?token=" . urlencode($token);

    $assunto = "Confirmação de acesso administrativo - " . SITE_NAME;

    $mensagem = "
        <h2>Acesso administrativo</h2>
        <p>Foi solicitado um login administrativo para este e-mail.</p>
        <p>Clique no link abaixo para confirmar a primeira etapa:</p>
        <p><a href='{$link}'>{$link}</a></p>
        <p>Este link expira em 10 minutos.</p>
        <p>Se você não solicitou este acesso, ignore este e-mail.</p>
    ";

    if (!enviarEmail($email, $assunto, $mensagem)) {
        throw new Exception('Falha ao enviar e-mail de confirmação.');
    }

    $response = [
        'success' => true,
        'message' => 'E-mail de confirmação enviado.'
    ];

} catch (Exception $e) {
    error_log("Erro admin-solicitar-email.php: " . $e->getMessage());

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