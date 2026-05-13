<?php
session_start();

if (!isset($_SESSION['2fa_user'])) {
    die("Acesso negado");
}

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
    UPDATE user_adm
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
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativar 2FA</title>

    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<div class="container">

    <div class="form-container">

        <h2 class="title">
            Ativar 2FA Telegram
        </h2>

        <p style="text-align:center;">
            1. Clique no botão abaixo<br>
            2. Envie o código para o bot<br>
            3. Volte aqui e clique em verificar
        </p>

        <div style="text-align:center; margin:20px 0;">

            <button
                onclick="window.open(
                    'https://web.telegram.org/k/#@uberversitarios_bot',
                    '_blank'
                )"
                class="btn btn-primary big"
                style="width:100%;"
            >
                Conversar com o BOT
            </button>

        </div>

        <div style="text-align:center;">

            <small>
                Seu código:
            </small>

            <p
                style="
                    font-size: 28px;
                    font-weight: bold;
                    margin-top: 10px;
                "
            >
                <?= $codigo ?>
            </p>

            <small>
                Expira em 5 minutos
            </small>

        </div>

        <button
            id="ativar-btn"
            class="btn btn-primary big"
            style="width:100%; margin-top:20px;"
        >
            Já enviei o código
        </button>

        <div
            id="mensagem"
            style="
                margin-top:15px;
                text-align:center;
            "
        ></div>

    </div>

</div>

<script>

document
.getElementById('ativar-btn')
.addEventListener('click', () => {

    fetch('../bot2fa/verificar_telegram.php')

    .then(res => res.json())

    .then(data => {

        document
        .getElementById('mensagem')
        .innerHTML = data.mensagem;

        if (data.status == "success") {

            setTimeout(() => {

                window.location.href =
                    "../html/admin_logado.html";

            }, 1500);

        }

    });

});

</script>

</body>
</html>