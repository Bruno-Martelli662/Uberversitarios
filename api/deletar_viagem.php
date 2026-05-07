<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getAdminDBConnection();

$id = intval($_GET["id"] ?? 0);

if ($id <= 0) {
    echo json_encode(["status" => "erro", "message" => "ID inválido"]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM viagens WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(["status" => "ok"]);
?>