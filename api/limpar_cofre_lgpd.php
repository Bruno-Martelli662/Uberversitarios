<?php
require_once __DIR__ . '/../config.php';

// SEGURANÇA MÁXIMA: Verifica se o script está rodando via Linha de Comando (CLI)
// Se alguém tentar abrir isso pelo navegador, a execução é bloqueada.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Acesso negado. Este script é interno e só pode ser executado via terminal do servidor.\n");
}

$conn = getAdminDBConnection();

// Deleta registros cuja data de exclusão seja superior a 6 meses (Marco Civil)
$sql = "DELETE FROM lgpd_arquivamento WHERE data_exclusao < DATE_SUB(NOW(), INTERVAL 6 MONTH)";

if ($conn->query($sql) === TRUE) {
    // Usamos \n em vez de <br> pois a saída vai para o terminal/log do servidor
    echo "[" . date('Y-m-d H:i:s') . "] Limpeza do cofre LGPD concluída. Registros removidos: " . $conn->affected_rows . "\n";
} else {
    error_log("Erro ao limpar cofre LGPD: " . $conn->error);
    echo "[" . date('Y-m-d H:i:s') . "] Erro durante a limpeza do cofre.\n";
}

$conn->close();
?>