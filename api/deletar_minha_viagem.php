<?php
ob_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$conn = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido.');
    }

    $usuarioId = exigirUsuarioLogado();

    $data = json_decode(file_get_contents('php://input'), true);
    $viagemId = intval($data['viagem_id'] ?? 0);

    if ($viagemId <= 0) {
        throw new Exception('ID da viagem inválido.');
    }

    $conn = getWriteDBConnection();

    // Verifica se a viagem existe e pertence ao usuário
    $stmt = $conn->prepare("
        SELECT motorista_id 
        FROM viagens 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $viagemId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Viagem não encontrada.');
    }

    $viagem = $result->fetch_assoc();
    $stmt->close();

    if ((int)$viagem['motorista_id'] !== $usuarioId) {
        throw new Exception('Você não tem permissão para apagar esta viagem.');
    }

    // Registra log ANTES de deletar (a FK não importa aqui, mas mantém o padrão)
    registrarLog($usuarioId, 'EXCLUSAO', "Usuário {$usuarioId} deletou a própria viagem ID {$viagemId}.");

    $stmt = $conn->prepare("DELETE FROM viagens WHERE id = ? AND motorista_id = ?");
    $stmt->bind_param("ii", $viagemId, $usuarioId);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao apagar viagem.');
    }
    
    $stmt->close();

    $response = [
        'success' => true,
        'message' => 'Viagem apagada com sucesso.'
    ];

} catch (Exception $e) {
    error_log("Erro deletar_minha_viagem.php: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
} finally {
    if ($conn) {
        $conn->close();
    }
}

if (ob_get_level()) ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>