<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido.');
    }

    $nome = trim($_POST['Nome'] ?? '');
    $veiculo = trim($_POST['Veiculo'] ?? '');
    $placa = trim($_POST['Placa'] ?? '');
    $contato = trim($_POST['Contato'] ?? '');
    $inicial = trim($_POST['Inicial'] ?? '');
    $final = trim($_POST['Final'] ?? '');

    if ($nome === '' || $veiculo === '' || $placa === '' || $contato === '' || $inicial === '' || $final === '') {
        throw new Exception('Todos os campos são obrigatórios.');
    }

    $contato = preg_replace('/[^0-9]/', '', $contato);

    if (strlen($contato) < 10 || strlen($contato) > 11) {
        throw new Exception('Telefone inválido.');
    }

    $conn = getWriteDBConnection();

    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE telefone = ?");

    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta de usuário.');
    }

    $stmt->bind_param("s", $contato);
    $stmt->execute();

    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();

    $stmt->close();

    if ($usuario) {
        $motoristaId = $usuario['id'];
    } else {
        $senhaTemp = bin2hex(random_bytes(8));
        $senhaHash = hash('sha256', $senhaTemp);
        $emailTemp = $contato . '@uberversitarios.com';

        $stmt = $conn->prepare("
            INSERT INTO usuarios 
            (nome_usuario, email, telefone, senha_hash, confirmado)
            VALUES (?, ?, ?, ?, 1)
        ");

        if (!$stmt) {
            throw new Exception('Erro ao preparar cadastro de usuário temporário.');
        }

        $stmt->bind_param("ssss", $nome, $emailTemp, $contato, $senhaHash);

        if (!$stmt->execute()) {
            throw new Exception('Erro ao cadastrar usuário temporário.');
        }

        $motoristaId = $conn->insert_id;
        $stmt->close();
    }

    $veiculoCompleto = $veiculo . ' - ' . $placa;

    $stmt = $conn->prepare("
        INSERT INTO viagens 
        (motorista_id, veiculo, contato, origem, destino)
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception('Erro ao preparar cadastro da viagem.');
    }

    $stmt->bind_param("issss", $motoristaId, $veiculoCompleto, $contato, $inicial, $final);

    if (!$stmt->execute()) {
        throw new Exception('Erro ao cadastrar viagem.');
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Viagem cadastrada com sucesso!'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Erro processar_cadastro_viagem.php: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} finally {
    if ($conn) {
        $conn->close();
    }
}
?>