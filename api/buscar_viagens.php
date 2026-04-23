<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'sistema_autenticacao';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()]);
    exit;
}

try {
    $sql = "SELECT v.*, u.nome_usuario as motorista_nome 
            FROM viagens v 
            JOIN usuarios u ON v.motorista_id = u.id 
            WHERE v.passageiro_id IS NULL 
            ORDER BY v.criada_em DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $viagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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