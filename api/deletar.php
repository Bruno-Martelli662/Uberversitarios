<?php
$host = "localhost";
$user = "Uberversitarios";
$pass = "Pucpr@1234";
$db = "sistema_autenticacao";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $senha = trim($_POST['password']);

    $sql = "SELECT id, nome_usuario, senha_hash FROM usuarios WHERE LOWER(email) = LOWER(?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Erro na query: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        if (hash('sha256', $senha) === $user['senha_hash']) {

            $delete = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
            $delete->bind_param("i", $user['id']);

            if ($delete->execute()) {
                echo "<h2>Conta deletada com sucesso!</h2>";
                echo "<p>Redirecionando para a página inicial...</p>";
                header("refresh:2;url=../html/index.html");
                exit();
            } else {
                echo "Erro ao deletar.";
            }
            $delete->close();
        } else {
            echo "Senha incorreta.";
        }
    } else {
        echo "Usuário não encontrado.";
    }
    $stmt->close();
}
$conn->close();
?>