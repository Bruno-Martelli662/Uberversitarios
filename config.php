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
    $conteudo = file_get_contents($arquivo);

    $posIend = strpos($conteudo, "IEND") + 8; 
    $dados = substr($conteudo, $posIend);

    return json_decode(base64_decode($dados), true);
}

$config = carregarConfiguracaoSecreta();

define('SITE_URL', $config['SITE_URL']);
define('SITE_NAME', $config['SITE_NAME']);

define('DB_HOST', $config['DB_HOST']);
define('DB_USER', $config['DB_USER']);
define('DB_PASS', $config['DB_PASS']);
define('DB_NAME', $config['DB_NAME']);

define('SMTP_HOST', $config['SMTP_HOST']);
define('SMTP_PORT', $config['SMTP_PORT']);
define('SMTP_USERNAME', $config['SMTP_USERNAME']);
define('SMTP_PASSWORD', $config['SMTP_PASSWORD']);
define('SMTP_FROM_EMAIL', $config['SMTP_FROM_EMAIL']);
define('SMTP_FROM_NAME', $config['SMTP_FROM_NAME']);
define('SMTP_SECURE', $config['SMTP_SECURE']);
define('SMTP_DEBUG', $config['SMTP_DEBUG']);

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("Erro na conexão com o banco: " . $conn->connect_error);
        die(json_encode([
            'success' => false,
            'message' => 'Erro na conexão com o banco de dados'
        ]));
    }
    
    return $conn;
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