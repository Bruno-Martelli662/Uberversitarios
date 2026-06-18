<?php
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cripto.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
        throw new Exception('JSON inválido ou vazio.');
    }

    $email = trim($data['email'] ?? '');

    if ($email === '') {
        throw new Exception('E-mail é obrigatório.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('E-mail inválido.');
    }

    $conn = getAuthDBConnection();

    $stmt = $conn->prepare("
        SELECT id, nome_usuario, confirmado
        FROM usuarios
        WHERE email = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro na preparação da consulta.');
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Nenhuma conta encontrada com este e-mail.');
    }

    $usuario = $result->fetch_assoc();
    if ($usuario) {
        $usuario['nome_usuario'] = Cripto::decifrarBDSeguro($usuario['nome_usuario']);
    }
    $stmt->close();

    if ((int)$usuario['confirmado'] === 1) {
        throw new Exception('Este e-mail já foi confirmado.');
    }

    $tokenConfirmacao = gerarToken();

    $stmt = $conn->prepare("
        UPDATE usuarios
        SET token_confirmacao = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro ao atualizar token de confirmação.');
    }

    $stmt->bind_param("si", $tokenConfirmacao, $usuario['id']);

    if (!$stmt->execute()) {
        throw new Exception('Erro ao salvar novo token de confirmação.');
    }

    $stmt->close();

    $linkConfirmacao = SITE_URL . "/api/confirmar-email.php?token=" . urlencode($tokenConfirmacao);

    $assunto = "Confirme seu e-mail";

    $mensagem = "
        <h1>Olá {$usuario['nome_usuario']},</h1>
        <p>Recebemos uma solicitação para reenviar seu e-mail de confirmação.</p>
        <p>Clique no link abaixo para confirmar sua conta:</p>
        <p><a href='{$linkConfirmacao}'>{$linkConfirmacao}</a></p>
        <p>Se você não solicitou este reenvio, ignore este e-mail.</p>
        <p>Atenciosamente,<br>Equipe " . SITE_NAME . "</p>
    ";

    if (!enviarEmail($email, $assunto, $mensagem)) {
        throw new Exception('Falha ao enviar e-mail de confirmação.');
    }

    $response = [
        'success' => true,
        'message' => 'E-mail de confirmação reenviado com sucesso.'
    ];

} catch (Exception $e) {
    error_log("Erro reenviar-confirmacao.php: " . $e->getMessage());

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