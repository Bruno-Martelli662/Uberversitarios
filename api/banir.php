<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// >>> Bloqueia qualquer requisição não autenticada como admin <
$adminId = exigirAdminAutenticado();

$conn = getAdminDBConnection();

$id = intval($_GET["id"] ?? 0);

if ($id <= 0) {
    echo json_encode(["status" => "erro", "message" => "ID inválido"]);
    $conn->close();
    exit;
}

// Impede banir outro admin
$check_sql = "
    SELECT u.email 
    FROM usuarios u 
    JOIN user_adm a ON u.email = a.email 
    WHERE u.id = ?
";
$stmt_check = $conn->prepare($check_sql);
$stmt_check->bind_param("i", $id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    echo json_encode([
        "status"  => "erro",
        "message" => "Ação negada: Não é possível banir um administrador."
    ]);
    $stmt_check->close();
    $conn->close();
    exit;
}
$stmt_check->close();

$stmt = $conn->prepare("UPDATE usuarios SET banido = 1 WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();
$conn->close();

// Registra auditoria com o admin que executou a ação
registrarLog(null, 'ALTERACAO', "Admin {$adminId} baniu o usuário ID {$id}");

echo json_encode(["status" => "ok"]);
?>