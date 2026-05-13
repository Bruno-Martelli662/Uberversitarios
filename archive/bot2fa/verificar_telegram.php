<?php

session_start();

header('Content-Type: application/json');

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "sistema_autenticacao"
);

$user_id = $_SESSION['2fa_user'];

$stmt = $conn->prepare("
    SELECT telegram_verificado
    FROM user_adm
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

if ($user['telegram_verificado'] == 1) {

    echo json_encode([

        "status" => "success",

        "mensagem" =>
            "<span style='color:green'>
                ✅ 2FA aprovado!
            </span>"

    ]);

} else {

    echo json_encode([

        "status" => "error",

        "mensagem" =>
            "<span style='color:red'>
                ❌ Código ainda não validado
            </span>"

    ]);
}