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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$nome = $_POST['Nome'] ?? '';
$veiculo = $_POST['Veiculo'] ?? '';
$placa = $_POST['Placa'] ?? '';
$contato = $_POST['Contato'] ?? '';
$inicial = $_POST['Inicial'] ?? '';
$final = $_POST['Final'] ?? '';

if (empty($nome) || empty($veiculo) || empty($placa) || empty($contato) || empty($inicial) || empty($final)) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
    exit;
}

$contato = preg_replace('/[^0-9]/', '', $contato);
if (strlen($contato) < 10 || strlen($contato) > 11) {
    echo json_encode(['success' => false, 'message' => 'Telefone inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = :telefone");
    $stmt->execute([':telefone' => $contato]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario) {
        $motorista_id = $usuario['id'];
    } else {
        $senha_temp = bin2hex(random_bytes(8));
        $senha_hash = password_hash($senha_temp, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome_usuario, email, telefone, senha_hash, confirmado) 
                               VALUES (:nome, :email, :telefone, :senha, 1)");
        
        $email_temp = $contato . '@uberversitarios.com';
        
        $stmt->execute([
            ':nome' => $nome,
            ':email' => $email_temp,
            ':telefone' => $contato,
            ':senha' => $senha_hash
        ]);
        
        $motorista_id = $pdo->lastInsertId();
    }
    
    $sql = "INSERT INTO viagens (motorista_id, veiculo, contato, origem, destino) 
            VALUES (:motorista_id, :veiculo, :contato, :origem, :destino)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':motorista_id' => $motorista_id,
        ':veiculo' => $veiculo . ' - ' . $placa,
        ':contato' => $contato,
        ':origem' => $inicial,
        ':destino' => $final
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Viagem cadastrada com sucesso!']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar viagem: ' . $e->getMessage()]);
}
?>