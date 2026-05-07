<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getAdminDBConnection();

$result = $conn->query("SELECT id, nome_usuario, email FROM usuarios WHERE banido = 0");

$usuarios = [];

while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

$conn->close();

echo json_encode($usuarios, JSON_UNESCAPED_UNICODE);
?>