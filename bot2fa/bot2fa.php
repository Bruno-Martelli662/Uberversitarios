<?php

$token = '8600549211:AAE3gQnnAT88apnnTXqrYTVeU6DyYWV3uwc';
$base = "https://api.telegram.org/bot{$token}";

$offset = 0;

while (true) {

    $resposta = file_get_contents("{$base}/getUpdates?offset=" . ($offset + 1));
    $dados = json_decode($resposta, true);

    foreach ($dados['result'] as $update) {

        $offset = $update['update_id'];

        if (!isset($update['message'])) continue;

        $chat_id = $update['message']['chat']['id'];
        $texto = trim($update['message']['text'] ?? '');

        if (strpos($texto, '/start') === 0) {

            $codigo = random_int(100000, 999999);
            $msg = "Código: {$codigo}";

            file_get_contents(
                "{$base}/sendMessage?chat_id={$chat_id}&text=" . urlencode($msg)
            );
        }
    }

    sleep(2); // evita sobrecarga
}