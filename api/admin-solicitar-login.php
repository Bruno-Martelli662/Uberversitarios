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
        SELECT id, email, telegram_id
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
        throw new Exception('Erro ao salvar código.');
    }

    $stmt->close();

    $mensagem = "Código de acesso admin Uberversitários: {$codigo}\nExpira em 5 minutos.";

    if (!enviarTelegram($admin['telegram_id'], $mensagem)) {
        throw new Exception('Falha ao enviar código pelo Telegram.');
    }

    $response = [
        'success' => true,
        'message' => 'Código enviado para o Telegram.'
    ];

} catch (Exception $e) {
    error_log("Erro admin-solicitar-login.php: " . $e->getMessage());

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