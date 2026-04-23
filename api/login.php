<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../crypto_utils.php';
require_once __DIR__ . '/../GoogleAuthenticator.php';

header('Content-Type: application/json');

try {
    $data = CryptoUtils::processEncryptedRequest();
    $email = $data['email'] ?? '';
    $senhaHash = $data['senha'] ?? '';
    $codigo2FA = $data['codigo2FA'] ?? null;

    if (empty($email) || empty($senhaHash)) {
        throw new Exception('E-mail e senha são obrigatórios.');
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, nome_usuario, senha_hash, confirmado, google_2fa_secret, google_2fa_ativado FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('E-mail ou senha incorretos.');
    }

    $usuario = $result->fetch_assoc();

    if ($senhaHash !== $usuario['senha_hash']) {
        throw new Exception('E-mail ou senha incorretos.');
    }

    if (!$usuario['confirmado']) {
        throw new Exception('Por favor, confirme seu e-mail antes de fazer login.');
    }

    $tokenSessao = gerarToken();
    $expiracao = date('Y-m-d H:i:s', strtotime('+1 day'));

    $stmt = $conn->prepare("INSERT INTO sessoes (usuario_id, token_sessao, data_expiracao) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $usuario['id'], $tokenSessao, $expiracao);
    $stmt->execute();

    $response = [];

    if (empty($usuario['google_2fa_ativado']) || $usuario['google_2fa_ativado'] == 0) {
        $response = [
            'success' => true,
            'token' => $tokenSessao,
            'nome' => $usuario['nome_usuario'],
            'requires2FASetup' => true
        ];
    } elseif ($usuario['google_2fa_ativado']) {
        $ga = new GoogleAuthenticator();
        
        if ($codigo2FA === null) {
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
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
} finally {
    if (isset($conn)) $conn->close();
}

echo json_encode(CryptoUtils::prepareEncryptedResponse(
    $response,
    CryptoUtils::getLastDecryptedKey(),
    CryptoUtils::getLastIV()
));
?>