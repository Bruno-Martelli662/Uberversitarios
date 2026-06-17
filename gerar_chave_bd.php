<?php
/**
 * gerar_chave_bd.php  —  S.3.2.a + S.3.2.b
 * ----------------------------------------
 * Gera a chave simétrica (AES-256) usada para cifrar campos sensíveis do BD
 * e a guarda PROTEGIDA por gestão de segredos: mascarada com XOR sobre o
 * poema (a mesma chave de máscara do config.php), em chave_bd.enc.
 *
 * Rode no terminal do servidor (assim o log cai no console):
 *     /opt/lampp/bin/php gerar_chave_bd.php
 *
 * S.3.2.a -> imprime a chave gerada (base64)
 * S.3.2.b -> loga a operação de armazenamento no formato usuario:hostname>...
 *
 * Rode UMA vez (ou ao rotacionar a chave). Se você apagar/rotacionar a chave,
 * os dados já gravados com a chave antiga deixam de decifrar.
 */

require_once __DIR__ . '/cripto.php';   // só para Cripto::logConsole

// --- lê o poema (mesma chave de máscara do config.php), em bytes crus ---
function lerPoemaMascara(): string
{
    $arquivo = __DIR__ . '/poema.txt';
    if (!file_exists($arquivo)) {
        $arquivo = __DIR__ . '/../poema.txt';
    }
    if (!file_exists($arquivo)) {
        fwrite(STDERR, "poema.txt (chave da máscara) não encontrado.\n");
        exit(1);
    }
    return file_get_contents($arquivo);
}

// --- máscara XOR idêntica à do config.php (byte a byte, poema repetido) ---
function mascararXorLocal(string $dados, string $chave): string
{
    if ($chave === '' || $dados === '') {
        return $dados;
    }
    $repeticoes = (int) ceil(strlen($dados) / strlen($chave));
    $keystream  = substr(str_repeat($chave, $repeticoes), 0, strlen($dados));
    return $dados ^ $keystream;
}

// 1) gera a chave AES-256 (32 bytes)
$chave = random_bytes(32);

// S.3.2.a — mostra a chave gerada
echo "==================================================================\n";
echo " S.3.2.a  Chave simétrica do BD gerada (AES-256)\n";
echo "==================================================================\n";
echo " base64: " . base64_encode($chave) . "\n";
echo " hex:    " . bin2hex($chave) . "\n";
echo " bytes:  " . strlen($chave) . "\n\n";

// 2) protege a chave com gestão de segredos (XOR + poema) e grava
$poema     = lerPoemaMascara();
$mascarada = mascararXorLocal($chave, $poema);
$arquivo   = __DIR__ . '/chave_bd.enc';

$ok = file_put_contents($arquivo, base64_encode($mascarada));
if ($ok === false) {
    fwrite(STDERR, "Falha ao gravar chave_bd.enc\n");
    exit(1);
}

// S.3.2.b — loga a operação de armazenamento (usuario:hostname>...)
Cripto::logConsole(
    'Chave simétrica do BD armazenada (protegida por máscara XOR + poema) em chave_bd.enc — ' .
    strlen($mascarada) . ' bytes mascarados'
);

echo "\n S.3.2.b  Chave protegida e armazenada em: " . $arquivo . "\n";
echo " (sem o poema, o conteúdo de chave_bd.enc é inútil)\n";
echo " Prévia mascarada (base64): " . substr(base64_encode($mascarada), 0, 44) . "...\n";