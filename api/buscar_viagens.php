<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // Adicionado para carregar as funções globais

try {
    $sql = "SELECT v.*, u.nome_usuario as motorista_nome 
            FROM viagens v 
            JOIN usuarios u ON v.motorista_id = u.id 
            WHERE v.passageiro_id IS NULL 
            ORDER BY v.criada_em DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $viagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // REGISTRO DO LOG AQUI
    registrarLog(null, 'LEITURA', 'Busca realizada na lista de viagens disponíveis.');
    
    $viagens_formatadas = [];
    foreach ($viagens as $viagem) {
        $viagens_formatadas[] = [
            'id' => $viagem['id'],
            'motorista' => $viagem['motorista_nome'],
            'origem' => $viagem['origem'],
            'destino' => $viagem['destino'],
            'veiculo' => $viagem['veiculo'],
            'contato' => $viagem['contato'],
            'data' => date('d/m/Y H:i', strtotime($viagem['criada_em']))
        ];
    }
    
    echo json_encode(['success' => true, 'viagens' => $viagens_formatadas]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar viagens: ' . $e->getMessage()]);
}
?>