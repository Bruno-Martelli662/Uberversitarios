<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = null;

try {
    $conn = getReadDBConnection();

    $sql = "
        SELECT 
            v.id,
            v.origem,
            v.destino,
            v.veiculo,
            v.contato,
            v.criada_em,
            u.nome_usuario AS motorista_nome
        FROM viagens v
        JOIN usuarios u ON v.motorista_id = u.id
        WHERE v.passageiro_id IS NULL
        ORDER BY v.criada_em DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta.');
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $viagens = [];

    while ($viagem = $result->fetch_assoc()) {
        $viagens[] = [
            'id' => $viagem['id'],
            'motorista' => $viagem['motorista_nome'],
            'origem' => $viagem['origem'],
            'destino' => $viagem['destino'],
            'veiculo' => $viagem['veiculo'],
            'contato' => $viagem['contato'],
            'data' => date('d/m/Y H:i', strtotime($viagem['criada_em']))
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'viagens' => $viagens
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Erro buscar_viagens.php: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar viagens.'
    ], JSON_UNESCAPED_UNICODE);

} finally {
    if ($conn) {
        $conn->close();
    }
}
?>