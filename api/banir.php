<?php
include "db.php";

$id = intval($_GET["id"]);

$conn->query("UPDATE usuarios SET banido = 1 WHERE id = $id");

echo json_encode(["status" => "ok"]);
?>