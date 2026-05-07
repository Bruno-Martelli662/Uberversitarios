<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header("Content-Type: application/json; charset=UTF-8");

date_default_timezone_set('America/Sao_Paulo');

function carregarConfiguracaoSecreta() {
    $arquivo = __DIR__ . '/logo_projeto.png';

    if (!file_exists($arquivo)) {
        $arquivo = __DIR__ . '/../logo_projeto.png';
    }

    if (!file_exists($arquivo)) {
        error_log("Arquivo logo_projeto.png não encontrado.");
        die(json_encode([
            'success' => false,
            'message' => 'Arquivo de configuração não encontrado.'
        ]));
    }

    $conteudo = file_get_contents($arquivo);

    $posIend = strpos($conteudo, "IEND");

    if ($posIend === false) {
        error_log("Marcador IEND não encontrado na imagem.");
        die(json_encode([
            'success' => false,
            'message' => 'Configuração inválida.'
        ]));
    }

    $posDados = $posIend + 8;
    $dados = substr($conteudo, $posDados);

    $config = json_decode(base64_decode($dados), true);

    if (!is_array($config)) {
        error_log("Falha ao decodificar configuração esteganografada.");
        die(json_encode([
            'success' => false,
            'message' => 'Erro ao carregar configuração.'
        ]));
    }

    return $config;
}

$config = carregarConfiguracaoSecreta();

define('SITE_URL', $config['SITE_URL']);
define('SITE_NAME', $config['SITE_NAME']);

define('DB_HOST', $config['DB_HOST']);
define('DB_NAME', $config['DB_NAME']);

/*
 * Usuários segregados do banco.
 * Cada usuário deve ter permissões diferentes no MariaDB/MySQL.
 */
define('DB_AUTH_USER', $config['DB_AUTH_USER']);
define('DB_AUTH_PASS', $config['DB_AUTH_PASS']);

define('DB_READ_USER', $config['DB_READ_USER']);
define('DB_READ_PASS', $config['DB_READ_PASS']);

define('DB_WRITE_USER', $config['DB_WRITE_USER']);
define('DB_WRITE_PASS', $config['DB_WRITE_PASS']);

define('DB_ADMIN_USER', $config['DB_ADMIN_USER']);
define('DB_ADMIN_PASS', $config['DB_ADMIN_PASS']);

define('SMTP_HOST', $config['SMTP_HOST']);
define('SMTP_PORT', $config['SMTP_PORT']);
define('SMTP_USERNAME', $config['SMTP_USERNAME']);
define('SMTP_PASSWORD', $config['SMTP_PASSWORD']);
define('SMTP_FROM_EMAIL', $config['SMTP_FROM_EMAIL']);
define('SMTP_FROM_NAME', $config['SMTP_FROM_NAME']);
define('SMTP_SECURE', $config['SMTP_SECURE']);
define('SMTP_DEBUG', $config['SMTP_DEBUG']);

function criarConexao($usuario, $senha) {
    $conn = new mysqli(DB_HOST, $usuario, $senha, DB_NAME);

    if ($conn->connect_error) {
        error_log("Erro na conexão com o banco: " . $conn->connect_error);

        die(json_encode([
            'success' => false,
            'message' => 'Erro na conexão com o banco de dados'
        ]));
    }

    $conn->set_charset("utf8mb4");

    return $conn;
}

/*
 * Conexão para autenticação:
 * login, cadastro, confirmação de e-mail, recuperação de senha, 2FA e sessões.
 */
function getAuthDBConnection() {
    return criarConexao(DB_AUTH_USER, DB_AUTH_PASS);
}

/*
 * Conexão somente leitura:
 * listagens e consultas públicas.
 */
function getReadDBConnection() {
    return criarConexao(DB_READ_USER, DB_READ_PASS);
}

/*
 * Conexão de escrita comum:
 * cadastro de viagens e alterações normais da aplicação.
 */
function getWriteDBConnection() {
    return criarConexao(DB_WRITE_USER, DB_WRITE_PASS);
}

/*
 * Conexão administrativa:
 * banir usuários, listar dados administrativos, deletar viagens etc.
 */
function getAdminDBConnection() {
    return criarConexao(DB_ADMIN_USER, DB_ADMIN_PASS);
}

/*
 * Mantido temporariamente para compatibilidade.
 * Assim os arquivos antigos não quebram enquanto a migração é feita aos poucos.
 */
function getDBConnection() {
    return getAuthDBConnection();
}

// FUNÇÃO PARA REGISTRAR LOGS DE AUDITORIA
function registrarLog($usuario_id, $acao, $descricao) {
    $conn = getAdminDBConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt = $conn->prepare("
        INSERT INTO logs_acao (usuario_id, acao, descricao, ip_origem)
        VALUES (?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param("isss", $usuario_id, $acao, $descricao, $ip);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Erro ao preparar log de auditoria: " . $conn->error);
    }

    $conn->close();
}

function gerarToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function enviarEmail($para, $assunto, $mensagem, $html = true) {
    $autoloadPath = __DIR__ . '/vendor/autoload.php';

    if (!file_exists($autoloadPath)) {
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    }

    if (!file_exists($autoloadPath)) {
        error_log("Erro: Arquivo autoload.php não encontrado em: $autoloadPath");
        return false;
    }

    require_once $autoloadPath;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        if (SMTP_DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        if (is_array($para)) {
            foreach ($para as $email => $nome) {
                $mail->addAddress($email, $nome);
            }
        } else {
            $mail->addAddress($para);
        }

        $mail->isHTML($html);
        $mail->Subject = $assunto;
        $mail->Body = $mensagem;

        if (!$html) {
            $mail->AltBody = strip_tags($mensagem);
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Erro ao enviar e-mail para {$para}: " . $mail->ErrorInfo);
        return false;
    }
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    return preg_match('/^([1-9]{2}|0[1-9]{2})?(9?[2-9][0-9]{7,8})$/', $telefone);
}
?>