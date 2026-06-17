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

    $resposta1 = trim($data['resposta1'] ?? '');
    $resposta2 = trim($data['resposta2'] ?? '');
    
    if (!validarTokenHex($token)) {
        throw new Exception('Token inválido.');
    }
    
    if ($resposta1 === '' || $resposta2 === '') {
        throw new Exception('Preencha todos os campos.');
    }
    
    if (mb_strlen($resposta1) > 100 || mb_strlen($resposta2) > 100) {
        throw new Exception('Respostas muito longas.');
    }

    $hash1 = hash(
        'sha256',
        normalizar($resposta1)
    );

    $hash2 = hash(
        'sha256',
        normalizar($resposta2)
    );

    $conn = getAdminDBConnection();

    $stmt = $conn->prepare("
        UPDATE user_adm
        SET

        resposta_seguranca_1_hash = ?,
        resposta_seguranca_2_hash = ?,

        perguntas_configuradas = 1

        WHERE email_login_token = ?
    ");

    $stmt->bind_param(
        "sss",

        $hash1,
        $hash2,

        $token
    );

    $stmt->execute();

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