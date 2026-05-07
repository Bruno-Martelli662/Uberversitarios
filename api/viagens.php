<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getAdminDBConnection();

$sql = "
SELECT v.*, u.nome_usuario AS motorista_nome
FROM viagens v
JOIN usuarios u ON v.motorista_id = u.id
";

$result = $conn->query($sql);

$viagens = [];

while ($row = $result->fetch_assoc()) {
    $viagens[] = $row;
}

$conn->close();

echo json_encode($viagens, JSON_UNESCAPED_UNICODE);
?>