<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cripto.php';

header('Content-Type: application/json');

function normalizar($texto) {

    $texto = mb_strtolower(trim($texto));
    $texto = preg_replace('/\s+/', ' ', $texto);

    return $texto;
}

try {

    $payload = json_decode(
        file_get_contents('php://input'),
        true
    );

    // Criptografia híbrida: decifra o envelope, se vier cifrado
    if (is_array($payload) && isset($payload['encryptedKey'], $payload['iv'], $payload['encryptedData'])) {
        $data = Cripto::descriptografarEnvelope($payload);
    } else {
        $data = is_array($payload) ? $payload : [];
    }

    $token = trim($data['token'] ?? '');

    if (!validarTokenHex($token)) {
        throw new Exception('Token inválido.');
    }
    
    $r1 = normalizar($data['resposta1'] ?? '');
    $r2 = normalizar($data['resposta2'] ?? '');
    
    if ($r1 === '' || $r2 === '') {
        throw new Exception('Respostas obrigatórias.');
    }
    
    if (mb_strlen($r1) > 100 || mb_strlen($r2) > 100) {
        throw new Exception('Respostas muito longas.');
    }

    $conn = getAdminDBConnection();

    $stmt = $conn->prepare("
        SELECT

        id,

        resposta_seguranca_1_hash,
        resposta_seguranca_2_hash,

        tentativas_pergunta,
        bloqueado_pergunta_ate

        FROM user_adm
        WHERE email_login_token = ?
    ");

    $stmt->bind_param("s", $token);

    $stmt->execute();

    $result = $stmt->get_result();

    $admin = $result->fetch_assoc();

    if (!$admin) {
        throw new Exception('Admin inválido.');
    }

    if (
        $admin['bloqueado_pergunta_ate']
        &&
        strtotime(
            $admin['bloqueado_pergunta_ate']
        ) > time()
    ) {

        throw new Exception(
            'Bloqueado por 2 horas.'
        );
    }

    $ok1 = hash('sha256', $r1)
        === $admin['resposta_seguranca_1_hash'];

    $ok2 = hash('sha256', $r2)
        === $admin['resposta_seguranca_2_hash'];

    if (!$ok1 || !$ok2) {

        $tentativas =
            $admin['tentativas_pergunta'] + 1;

        if ($tentativas >= 2) {

            $bloqueio = date(
                'Y-m-d H:i:s',
                strtotime('+2 hours')
            );

            $stmt = $conn->prepare("
                UPDATE user_adm
                SET

                tentativas_pergunta = 0,
                bloqueado_pergunta_ate = ?

                WHERE id = ?
            ");

            $stmt->bind_param(
                "si",
                $bloqueio,
                $admin['id']
            );

            $stmt->execute();

            throw new Exception(
                'Bloqueado por 2 horas.'
            );
        }

        $stmt = $conn->prepare("
            UPDATE user_adm
            SET tentativas_pergunta = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ii",
            $tentativas,
            $admin['id']
        );

        $stmt->execute();

        throw new Exception(
            'Perguntas incorretas.'
        );
    }

    $adminToken =
        bin2hex(random_bytes(32));

    $stmt = $conn->prepare("
        UPDATE user_adm
        SET

        admin_sessao_token = ?,
        admin_sessao_expira =
            DATE_ADD(NOW(), INTERVAL 2 HOUR),

        tentativas_pergunta = 0,
        bloqueado_pergunta_ate = NULL

        WHERE id = ?
    ");

    $stmt->bind_param(
        "si",
        $adminToken,
        $admin['id']
    );

    $stmt->execute();

    setcookie('adminToken', $adminToken, [

        'expires' => time() + 7200,

        'path' => '/',

        'httponly' => true,

        'samesite' => 'Lax',

        'secure' => false
    ]);

    echo json_encode([
        'success' => true
    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()
    ]);
}
?>