<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cripto.php';

header('Content-Type: application/json; charset=utf-8');

// >>> Bloqueia qualquer requisição não autenticada como admin <
$adminId = exigirAdminAutenticado();

$conn = getAdminDBConnection();

$sql = "
SELECT v.*, u.nome_usuario AS motorista_nome
FROM viagens v
JOIN usuarios u ON v.motorista_id = u.id
";

$result = $conn->query($sql);

$viagens = [];

while ($row = $result->fetch_assoc()) {
    // S.3.2: nome do motorista vem cifrado -> decifra
    $row['motorista_nome'] = Cripto::decifrarBDSeguro($row['motorista_nome']);
    $viagens[] = $row;
}

$conn->close();

registrarLog(null, 'LEITURA', "Admin {$adminId} listou viagens no painel.");

echo json_encode($viagens, JSON_UNESCAPED_UNICODE);
?>