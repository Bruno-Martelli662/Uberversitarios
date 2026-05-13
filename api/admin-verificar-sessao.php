<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = null;

try {
    $token = $_COOKIE['adminToken'] ?? '';

    if ($token === '') {
        throw new Exception('Sessão admin ausente.');
    }

    $conn = getAdminDBConnection();

    $stmt = $conn->prepare("
        SELECT id, email
        FROM user_adm
        WHERE admin_sessao_token = ?
        AND admin_sessao_expira > NOW()
    ");

    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta.');
    }

    $stmt->bind_param("s", $token);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Sessão admin inválida ou expirada.');
    }

    $admin = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'admin' => true,
        'email' => $admin['email']
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'admin' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} finally {
    if ($conn) {
        $conn->close();
    }
}
?>