<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

function normalizar($texto) {

    $texto = mb_strtolower(trim($texto));
    $texto = preg_replace('/\s+/', ' ', $texto);

    return $texto;
}

try {

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    $token = trim($data['token'] ?? '');

    $pergunta1 =
        trim($data['pergunta1'] ?? '');

    $pergunta2 =
        trim($data['pergunta2'] ?? '');

    $resposta1 =
        trim($data['resposta1'] ?? '');

    $resposta2 =
        trim($data['resposta2'] ?? '');

    if (
        !$token ||
        !$pergunta1 ||
        !$pergunta2 ||
        !$resposta1 ||
        !$resposta2
    ) {
        throw new Exception(
            'Preencha todos os campos.'
        );
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

        pergunta_seguranca_1 = ?,
        resposta_seguranca_1_hash = ?,

        pergunta_seguranca_2 = ?,
        resposta_seguranca_2_hash = ?,

        perguntas_configuradas = 1

        WHERE email_login_token = ?
    ");

    $stmt->bind_param(
        "sssss",

        $pergunta1,
        $hash1,

        $pergunta2,
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