<?php
include "db.php";
require_once __DIR__ . '/../config.php'; // Adicionado para carregar as funções globais

$id = intval($_GET["id"]);
$conn->query("DELETE FROM viagens WHERE id = $id");

// REGISTRO DO LOG AQUI
registrarLog(null, 'EXCLUSAO', "A viagem com ID $id foi deletada do banco de dados.");

echo json_encode(["status" => "ok"]);
?>