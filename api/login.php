<?php
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cripto.php';
require_once __DIR__ . '/../GoogleAuthenticator.php';

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

    if (!is_array($payload)) {
        throw new Exception('JSON inválido ou vazio.');
    }

    // Criptografia híbrida: decifra o envelope, se vier cifrado (mantém compatibilidade com texto puro)
    if (isset($payload['encryptedKey'], $payload['iv'], $payload['encryptedData'])) {
        $data = Cripto::descriptografarEnvelope($payload);
    } else {
        $data = $payload;
    }

    $email = trim($data['email'] ?? '');
    $senhaHash = trim($data['senha'] ?? '');
    $codigo2FA = trim($data['codigo2FA'] ?? '');

    if ($email === '' || $senhaHash === '') {
        throw new Exception('E-mail e senha são obrigatórios.');
    }

    if (!validarEmail($email)) {
        throw new Exception('E-mail ou senha incorretos.');
    }

    if (!validarHashSenha($senhaHash)) {
        throw new Exception('E-mail ou senha incorretos.');
    }

    if ($codigo2FA !== '' && !validarCodigoNumerico($codigo2FA, 6)) {
        throw new Exception('Código 2FA inválido.');
    }
    
    $conn = getAuthDBConnection();

    $stmt = $conn->prepare("
        SELECT id, nome_usuario, senha_hash, confirmado, google_2fa_secret, google_2fa_ativado
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
        throw new Exception('E-mail ou senha incorretos.');
    }

    $usuario = $result->fetch_assoc();
    $stmt->close();

    if ($senhaHash !== $usuario['senha_hash']) {
        throw new Exception('E-mail ou senha incorretos.');
    }

    if (!$usuario['confirmado']) {
        throw new Exception('Por favor, confirme seu e-mail antes de fazer login.');
    }

    $tokenSessao = gerarToken();
    $expiracao = date('Y-m-d H:i:s', strtotime('+1 day'));
    
    setcookie('authToken', $tokenSessao, [
        'expires' => time() + 43200,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => false
    ]);

    $stmt = $conn->prepare("
        INSERT INTO sessoes (usuario_id, token_sessao, data_expiracao)
        VALUES (?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception('Erro ao criar sessão.');
    }

    $stmt->bind_param("iss", $usuario['id'], $tokenSessao, $expiracao);
    $stmt->execute();
    $stmt->close();

    if (empty($usuario['google_2fa_ativado']) || $usuario['google_2fa_ativado'] == 0) {
        $response = [
            'success' => true,
            'token' => $tokenSessao,
            'nome' => $usuario['nome_usuario'],
            'requires2FASetup' => true
        ];
    } else {
        $ga = new GoogleAuthenticator();

        if ($codigo2FA === null || $codigo2FA === '') {
            $response = [
                'success' => true,
                'token' => $tokenSessao,
                'requires2FA' => true,
                'message' => 'Insira o código do seu autenticador'
            ];
        } elseif (!$ga->verifyCode($usuario['google_2fa_secret'], $codigo2FA, 2)) {
            throw new Exception('Código de autenticação inválido');
        } else {
            $response = [
                'success' => true,
                'token' => $tokenSessao,
                'nome' => $usuario['nome_usuario']
            ];
        }
    }

} catch (Exception $e) {
    error_log("Erro no login: " . $e->getMessage());

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