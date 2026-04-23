<?php
include "db.php";

$id = intval($_GET["id"]);

$conn->query("DELETE FROM viagens WHERE id = $id");

echo json_encode(["status" => "ok"]);
?>