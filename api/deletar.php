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

$stmt = $conn->prepare("
    SELECT id, nome_usuario, senha_hash 
    FROM usuarios 
    WHERE LOWER(email) = LOWER(?)
");

if (!$stmt) {
    die("Erro na query.");
}

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

$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");

if (!$stmt) {
    $conn->close();
    die("Erro ao preparar exclusão.");
}

$stmt->bind_param("i", $user['id']);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();

    echo "<h2>Conta deletada com sucesso!</h2>";
    echo "<p>Redirecionando para a página inicial...</p>";
    header("refresh:2;url=../html/index.html");
    exit();
}

$stmt->close();
$conn->close();

echo "Erro ao deletar conta.";
?>