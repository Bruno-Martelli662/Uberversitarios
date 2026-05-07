<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = '8600549211:AAE3gQnnAT88apnnTXqrYTVeU6DyYWV3uwc';

$base = "https://api.telegram.org/bot{$token}";

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "sistema_autenticacao"
);


$offset_file = "offset.txt";

$offset = 0;

if (file_exists($offset_file)) {
    $offset = (int) file_get_contents($offset_file);
}


while (true) {

    $resposta = file_get_contents(
        "{$base}/getUpdates?offset=" . ($offset + 1)
    );

    $dados = json_decode($resposta, true);

    if (!isset($dados['result'])) {
        sleep(2);
        continue;
    }

    foreach ($dados['result'] as $update) {

        $offset = $update['update_id'];

        file_put_contents(
            $offset_file,
            $offset
        );

        if (!isset($update['message'])) {
            continue;
        }

        $chat_id = $update['message']['chat']['id'];

        $texto = trim(
            $update['message']['text'] ?? ''
        );

        if (str_starts_with($texto, '/')) {
            continue;
        }

        echo "Recebido: {$texto}" . PHP_EOL;

        if (empty($texto)) {
            continue;
        }

        $stmt = $conn->prepare("
            SELECT id,
                   telegram_codigo_expira
            FROM user_adm
            WHERE telegram_codigo = ?
        ");

        $stmt->bind_param("s", $texto);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $user = $result->fetch_assoc();

            if (
                strtotime(
                    $user['telegram_codigo_expira']
                ) < time()
            ) {

                $msg = "Código expirado.";

            } else {

                $stmt2 = $conn->prepare("
                    UPDATE user_adm
                    SET telegram_verificado = 1
                    WHERE id = ?
                ");

                $stmt2->bind_param(
                    "i",
                    $user['id']
                );

                $stmt2->execute();

                $msg = "Código validado!";
            }

        } else {

            $msg = "Código inválido.";
        }

        $data = [
            'chat_id' => $chat_id,
            'text' => $msg
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' =>
                    "Content-type: application/x-www-form-urlencoded",
                'content' =>
                    http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);

        file_get_contents(
            "{$base}/sendMessage",
            false,
            $context
        );
    }

    sleep(2);
}