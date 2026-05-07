<?php
header('Content-Type: application/json; charset=utf-8');

setcookie('authToken', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => false
]);

echo json_encode([
    'success' => true,
    'message' => 'Logout realizado com sucesso.'
]);
?>