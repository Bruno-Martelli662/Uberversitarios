<?php
include "db.php";

$result = $conn->query("SELECT id, nome_usuario, email FROM usuarios WHERE banido = 0");

$usuarios = [];

while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

echo json_encode($usuarios);
?>