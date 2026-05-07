<?php
// Oculta erros brutos na tela (segurança) mas joga para o log do servidor
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config.php';

// Configura o MySQLi para lançar exceções em caso de falha (evita o Erro 500 silencioso)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Método não permitido.");
}

$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['password'] ?? '');

if ($email === '' || $senha === '') {
    die("E-mail e senha são obrigatórios.");
}

try {
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

    $usuario_id = $user['id'];

    // 2. Coleta dados do usuário para o cofre LGPD
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $usuario_dados = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Remove a senha do backup por segurança
    unset($usuario_dados['senha_hash']);

    // -> CORREÇÃO AQUI <-
    // O usuário uberv_auth não tem permissão para ler a tabela viagens.
    // Abrimos temporariamente a conexão de leitura (uberv_read) para buscar os dados.
    $connRead = getReadDBConnection();
    $stmtRead = $connRead->prepare("SELECT * FROM viagens WHERE motorista_id = ? OR passageiro_id = ?");
    $stmtRead->bind_param("ii", $usuario_id, $usuario_id);
    $stmtRead->execute();
    $res_viagens = $stmtRead->get_result();
    $viagens_dados = [];
    while($v = $res_viagens->fetch_assoc()) {
        $viagens_dados[] = $v;
    }
    $stmtRead->close();
    $connRead->close();

    // 3. Salva no cofre LGPD (usando a conexão Auth que recebeu a permissão)
    $pacote_lgpd = json_encode(['usuario' => $usuario_dados, 'viagens' => $viagens_dados]);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt_cofre = $conn->prepare("INSERT INTO lgpd_arquivamento (usuario_id, dados_json, ip_origem) VALUES (?, ?, ?)");
    $stmt_cofre->bind_param("iss", $usuario_id, $pacote_lgpd, $ip);
    $stmt_cofre->execute();
    $stmt_cofre->close();

    // 4. Deleta da base principal
    $stmt_del = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt_del->bind_param("i", $usuario_id);
    $stmt_del->execute();
    $stmt_del->close();
    
    $conn->close();

    // Limpa a sessão no navegador (Cookies)
    setcookie('authToken', '', time() - 3600, '/');

    // Resposta de sucesso + Script para limpar o LocalStorage do JS e redirecionar
    echo "<h2 style='font-family: sans-serif; text-align: center; margin-top: 50px; color: #ff5a5f;'>Conta e dados excluídos com sucesso!</h2>";
    echo "<p style='font-family: sans-serif; text-align: center;'>Redirecionando para a página inicial...</p>";
    echo "<script>
            localStorage.removeItem('authToken'); 
            setTimeout(function(){ window.location.href='../html/index.html'; }, 2500);
          </script>";

} catch (Exception $e) {
    // Se estourar algum erro no MySQL ou no PHP, você verá exatamente o que é nesta tela
    error_log("Erro no arquivo deletar.php: " . $e->getMessage());
    die("<div style='color:#333; text-align:center; margin-top: 50px; font-family: sans-serif;'>
            <h2 style='color:red;'>Ocorreu um erro no servidor</h2>
            <p><strong>Detalhes técnicos para correção:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <button style='padding: 10px 20px; cursor: pointer;' onclick='window.history.back()'>Voltar</button>
         </div>");
}
?>