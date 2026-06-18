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

    // Criptografia híbrida: decifra o envelope, se vier cifrado (mantém compatibilidade com texto puro)
    if (is_array($payload) && isset($payload['encryptedKey'], $payload['iv'], $payload['encryptedData'])) {
        $data = Cripto::descriptografarEnvelope($payload);
    } else {
        $data = $payload;
    }

    if (!is_array($data)) {
        throw new Exception('JSON inválido ou vazio.');
    }

    error_log("Dados recebidos para recuperação de senha");

    $email = trim($data['email'] ?? '');

    if ($email === '') {
        throw new Exception('E-mail é obrigatório.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('E-mail inválido.');
    }

    $conn = getAuthDBConnection();

    $stmt = $conn->prepare("SELECT id, nome_usuario FROM usuarios WHERE email = ?");

    if (!$stmt) {
        throw new Exception('Erro na preparação da consulta: ' . $conn->error);
    }

    $stmt->bind_param("s", $email);

    if (!$stmt->execute()) {
        throw new Exception('Erro na execução da consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Nenhuma conta encontrada com este e-mail.');
    }

    $usuario = $result->fetch_assoc();
    if ($usuario) {
        $usuario['nome_usuario'] = Cripto::decifrarBDSeguro($usuario['nome_usuario']);
    }
    $stmt->close();

    $tokenRecuperacao = gerarToken();
    $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $conn->prepare("
        UPDATE usuarios 
        SET token_recuperacao = ?, token_recuperacao_expira = ? 
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro na preparação da atualização: ' . $conn->error);
    }

    $stmt->bind_param("ssi", $tokenRecuperacao, $expiracao, $usuario['id']);

    if (!$stmt->execute()) {
        error_log("Erro SQL ao atualizar token: " . $stmt->error);
        throw new Exception("Erro ao atualizar token de recuperação");
    }

    $stmt->close();

    $linkRecuperacao = SITE_URL . "/html/nova-senha.html?token=" . urlencode($tokenRecuperacao);
    $assunto = "Recuperação de Senha - " . SITE_NAME;

    $mensagem = "
        <h1>Olá {$usuario['nome_usuario']},</h1>
        <p>Recebemos uma solicitação para redefinir sua senha.</p>
        <p>Clique no link abaixo para criar uma nova senha:</p>
        <p><a href='{$linkRecuperacao}'>{$linkRecuperacao}</a></p>
        <p>Se você não solicitou esta alteração, ignore este e-mail.</p>
        <p>Este link expira em 1 hora.</p>
        <p>Atenciosamente,<br>Equipe " . SITE_NAME . "</p>
    ";

    if (!enviarEmail($email, $assunto, $mensagem)) {
        error_log("Falha ao enviar e-mail de recuperação para: " . $email);
        throw new Exception("Falha ao enviar e-mail de recuperação");
    }

    $response = [
        'success' => true,
        'message' => 'Um e-mail com instruções foi enviado para seu endereço.'
    ];

} catch (Exception $e) {
    error_log("Erro na recuperação de senha: " . $e->getMessage());

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