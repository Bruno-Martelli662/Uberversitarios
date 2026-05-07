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

$codigo = random_int(100000, 999999);

$expira = date(
    "Y-m-d H:i:s",
    strtotime("+5 minutes")
);

$stmt = $conn->prepare("
    UPDATE usuarios
    SET telegram_codigo = ?,
        telegram_codigo_expira = ?,
        telegram_verificado = 0
    WHERE id = ?
");

$stmt->bind_param(
    "ssi",
    $codigo,
    $expira,
    $user_id
);

$stmt->execute();

$link = "https://t.me/uberversitarios_bot";

echo json_encode([
    "codigo" => $codigo,
    "link" => $link
]);