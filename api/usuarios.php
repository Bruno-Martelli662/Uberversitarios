<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getAdminDBConnection();

$sql = "
    SELECT 
        u.id, 
        u.nome_usuario, 
        u.email,
        CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END AS is_admin
    FROM usuarios u
    LEFT JOIN user_adm a ON u.email = a.email
    WHERE u.banido = 0
";

$result = $conn->query($sql);
$usuarios = [];

while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

$conn->close();

echo json_encode($usuarios, JSON_UNESCAPED_UNICODE);
?>