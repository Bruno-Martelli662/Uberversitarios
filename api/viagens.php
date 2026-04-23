<?php
include "db.php";

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

echo json_encode($viagens);
?>