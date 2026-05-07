<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Método não permitido.");
}

$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['password'] ?? '');

if ($email === '' || $senha === '') {
    die("E-mail e senha são obrigatórios.");
}

$conn = getAuthDBConnection();

// 1. Verifica credenciais
$stmt = $conn->prepare("SELECT id, nome_usuario, senha_hash FROM usuarios WHERE LOWER(email) = LOWER(?)");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Usuário não encontrado.");
}

$user = $result->fetch_assoc();
$stmt->close();

if (hash('sha256', $senha) !== $user['senha_hash']) {
    $conn->close();
    die("Senha incorreta.");
}

// 2. Coleta todos os dados para o cofre LGPD
$usuario_id = $user['id'];

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario_dados = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Remove a senha do backup
unset($usuario_dados['senha_hash']);

$stmt = $conn->prepare("SELECT * FROM viagens WHERE motorista_id = ? OR passageiro_id = ?");
$stmt->bind_param("ii", $usuario_id, $usuario_id);
$stmt->execute();
$viagens_dados = [];
$res_viagens = $stmt->get_result();
while($v = $res_viagens->fetch_assoc()) {
    $viagens_dados[] = $v;
}
$stmt->close();

// 3. Salva no cofre
$pacote_lgpd = json_encode(['usuario' => $usuario_dados, 'viagens' => $viagens_dados]);
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$stmt_cofre = $conn->prepare("INSERT INTO lgpd_arquivamento (usuario_id, dados_json, ip_origem) VALUES (?, ?, ?)");
$stmt_cofre->bind_param("iss", $usuario_id, $pacote_lgpd, $ip);
$stmt_cofre->execute();
$stmt_cofre->close();

// 4. Deleta da base principal
$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);

if ($stmt->execute()) {
    // Limpa o cookie de sessão local (se houver)
    setcookie('authToken', '', time() - 3600, '/');
    
    echo "<h2 style='font-family: sans-serif; text-align: center; margin-top: 50px; color: #ff5a5f;'>Conta e dados excluídos com sucesso!</h2>";
    echo "<p style='font-family: sans-serif; text-align: center;'>Redirecionando para a página inicial...</p>";
    
    // Script injetado para limpar o localStorage (onde você guarda o token no JS)
    echo "<script>localStorage.removeItem('authToken'); setTimeout(function(){ window.location.href='../html/index.html'; }, 2500);</script>";
} else {
    echo "Erro ao deletar conta.";
}

$stmt->close();
$conn->close();
?>