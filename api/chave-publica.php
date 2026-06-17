<?php
/**
 * api/chave-publica.php
 * ---------------------
 * S.3.1.a — Entrega a chave pública do servidor para o front montar a
 * criptografia híbrida. O front busca este endpoint, importa a chave e a
 * usa para cifrar a chave de sessão (RSA-OAEP).
 *
 * O "certificado" exigido no requisito é justamente esta chave pública:
 * dá para tirar o print da resposta deste endpoint na aba Network, ou do
 * console.log que o cripto.js imprime ao recebê-la.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$caminho = __DIR__ . '/../keys/public.key';

if (!is_readable($caminho)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Chave pública indisponível no servidor.'
    ]);
    exit;
}

$pem = trim(file_get_contents($caminho));

echo json_encode([
    'success'      => true,
    'algoritmo'    => 'RSA-OAEP',
    'hash'         => 'SHA-1',          // precisa casar com o front (Web Crypto) e com OPENSSL_PKCS1_OAEP_PADDING
    'cifraSessao'  => 'AES-256-CBC',
    'chavePublica' => $pem
], JSON_UNESCAPED_SLASHES);
