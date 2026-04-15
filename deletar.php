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

    echo "Email digitado: [$email] <br>";

    $sql = "SELECT id, nome_usuario, senha_hash FROM usuarios WHERE LOWER(email) = LOWER(?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        echo "Usuário encontrado: " . $user['nome_usuario'] . "<br>";

        echo "Senha digitada: [$senha]<br>";
        echo "Senha no banco: [" . $user['senha_hash'] . "]<br>";

        if (hash('sha256', $senha) === $user['senha_hash']) {

            // Deletar usuário
            $delete = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
            $delete->bind_param("i", $user['id']);

            if ($delete->execute()) {
                echo "Usuário deletado com sucesso!";
            } else {
                echo "Erro ao deletar: " . $delete->error;
            }

            $delete->close();

        } else {
            echo " Senha incorreta.";
        }

    } else {
        echo "Usuário não encontrado.";
    }

    $stmt->close();
}

$conn->close();
?>