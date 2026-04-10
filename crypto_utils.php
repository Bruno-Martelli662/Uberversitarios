<?php
require_once 'config.php';

class CryptoUtils {
    private static $privateKey;
    private static $publicKey;
    private static $cipherMethod = 'AES-256-CBC';
    private static $hashAlgo = 'sha256';
    private static $lastDecryptedKey;
    private static $lastIV;

    public static function getLastDecryptedKey() {
        return self::$lastDecryptedKey;
    }

    public static function getLastIV() {
        return self::$lastIV;
    }

    public static function init() {
        $privateKeyPath = __DIR__ . '/private.key';
        $publicKeyPath = __DIR__ . '/public.key';

        if (!file_exists($privateKeyPath) || !file_exists($publicKeyPath)) {
            throw new Exception('Arquivos de chave não encontrados em: ' . __DIR__);
        }

        $privateKeyContent = file_get_contents($privateKeyPath);
        $publicKeyContent = file_get_contents($publicKeyPath);

        if (empty($privateKeyContent) || empty($publicKeyContent)) {
            throw new Exception('Arquivos de chave estão vazios');
        }

        error_log("Tamanho da chave privada: " . strlen($privateKeyContent) . " bytes");
        error_log("Tamanho da chave pública: " . strlen($publicKeyContent) . " bytes");
        error_log("Início da chave privada: " . substr($privateKeyContent, 0, 50));
        error_log("Início da chave pública: " . substr($publicKeyContent, 0, 50));

        $privateKeyContent = str_replace(["\r\n", "\r"], "\n", trim($privateKeyContent));
        $publicKeyContent = str_replace(["\r\n", "\r"], "\n", trim($publicKeyContent));

        if (strpos($privateKeyContent, '-----BEGIN PRIVATE KEY-----') === false && 
            strpos($privateKeyContent, '-----BEGIN RSA PRIVATE KEY-----') === false) {
            throw new Exception('Formato PEM inválido na chave privada - faltam cabeçalhos');
        }

        if (strpos($publicKeyContent, '-----BEGIN PUBLIC KEY-----') === false) {
            throw new Exception('Formato PEM inválido na chave pública - faltam cabeçalhos');
        }

        self::$privateKey = openssl_pkey_get_private($privateKeyContent);
        if (!self::$privateKey) {
            $error = '';
            while ($msg = openssl_error_string()) {
                $error .= $msg . '; ';
            }
            throw new Exception('Falha ao carregar chave privada: ' . $error);
        }

        self::$publicKey = openssl_pkey_get_public($publicKeyContent);
        if (!self::$publicKey) {
            $error = '';
            while ($msg = openssl_error_string()) {
                $error .= $msg . '; ';
            }
            throw new Exception('Falha ao carregar chave pública: ' . $error);
        }

        $privateDetails = openssl_pkey_get_details(self::$privateKey);
        $publicDetails = openssl_pkey_get_details(self::$publicKey);

        if (!$privateDetails || !$publicDetails) {
            throw new Exception('Falha ao obter detalhes das chaves');
        }

        error_log("Chave privada - Tipo: " . $privateDetails['type'] . ", Bits: " . $privateDetails['bits']);
        error_log("Chave pública - Tipo: " . $publicDetails['type'] . ", Bits: " . $publicDetails['bits']);

        if ($privateDetails['type'] !== OPENSSL_KEYTYPE_RSA || $publicDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new Exception('As chaves devem ser do tipo RSA');
        }

        if ($privateDetails['bits'] < 2048 || $publicDetails['bits'] < 2048) {
            throw new Exception('As chaves RSA devem ter pelo menos 2048 bits');
        }
    }
    
    public static function decryptRSA($base64Data) {
        try {
            $base64Data = preg_replace('/\s+/', '', $base64Data);
            
            $data = base64_decode($base64Data, true);
            if ($data === false) {
                throw new Exception("Base64 inválido - dados não podem ser decodificados");
            }

            if (empty($data)) {
                throw new Exception("Dados criptografados estão vazios após decodificação base64");
            }

            $keyDetails = openssl_pkey_get_details(self::$privateKey);
            $maxDecryptLen = $keyDetails['bits'] / 8; 
            
            error_log("Tamanho dos dados criptografados: " . strlen($data) . " bytes");
            error_log("Tamanho máximo permitido: " . $maxDecryptLen . " bytes");
            
            if (strlen($data) > $maxDecryptLen) {
                throw new Exception("Dados criptografados muito grandes para a chave (" . strlen($data) . " > " . $maxDecryptLen . ")");
            }

            while (openssl_error_string()) {}

            $decrypted = null;
            $success = false;

            error_log("Tentando descriptografia RSA-OAEP com SHA-1...");
            $success = openssl_private_decrypt(
                $data, 
                $decrypted, 
                self::$privateKey, 
                OPENSSL_PKCS1_OAEP_PADDING
            );

            if (!$success) {
                $errors = [];
                while ($error = openssl_error_string()) {
                    $errors[] = $error;
                }
                $errorString = 'SHA-1: ' . implode('; ', $errors);
                
                error_log("OAEP com SHA-1 falhou, tentando PKCS1 padding...");
                $success = openssl_private_decrypt(
                    $data, 
                    $decrypted, 
                    self::$privateKey, 
                    OPENSSL_PKCS1_PADDING
                );
                
                if (!$success) {
                    $additionalErrors = [];
                    while ($error = openssl_error_string()) {
                        $additionalErrors[] = $error;
                    }
                    $errorString .= '; PKCS1: ' . implode('; ', $additionalErrors);
                    
                    if (class_exists('phpseclib3\Crypt\RSA')) {
                        error_log("Tentando com phpseclib3...");
                        try {
                            $rsa = \phpseclib3\Crypt\RSA::loadPrivateKey(self::$privateKey);
                            $rsa = $rsa->withPadding(\phpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
                                       ->withHash('sha256')
                                       ->withMGFHash('sha256');
                            $decrypted = $rsa->decrypt($data);
                            $success = true;
                            error_log("Descriptografia bem-sucedida com phpseclib3");
                        } catch (Exception $e) {
                            error_log("phpseclib3 também falhou: " . $e->getMessage());
                        }
                    }
                    
                    if (!$success) {
                        throw new Exception("Erro ao descriptografar com RSA (todos os métodos falharam): " . $errorString);
                    }
                } else {
                    error_log("AVISO: Descriptografia bem-sucedida com PKCS1 padding (menos seguro)");
                }
            }

            if (empty($decrypted)) {
                throw new Exception("Dados descriptografados estão vazios");
            }

            error_log("Descriptografia RSA bem-sucedida. Tamanho da chave AES: " . strlen($decrypted) . " bytes");
            return $decrypted;

        } catch (Exception $e) {
            error_log("Erro RSA decrypt: " . $e->getMessage());
            error_log("Dados base64 recebidos (primeiros 100 chars): " . substr($base64Data, 0, 100));
            error_log("Tamanho base64: " . strlen($base64Data));
            if (isset($data)) {
                error_log("Tamanho após decodificação: " . strlen($data));
                error_log("Hexdump dos primeiros 32 bytes: " . bin2hex(substr($data, 0, 32)));
            }
            throw $e;
        }
    }
    
    public static function encryptAES($data, $key, $iv) {
        if (strlen($key) !== 32) {
            throw new Exception("Chave AES deve ter 32 bytes para AES-256-CBC, recebida: " . strlen($key) . " bytes");
        }
        
        if (strlen($iv) !== 16) {
            throw new Exception("IV deve ter 16 bytes, recebido: " . strlen($iv) . " bytes");
        }
        
        $encrypted = openssl_encrypt(
            $data,
            self::$cipherMethod,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            $error = openssl_error_string();
            throw new Exception("Erro na criptografia AES: " . $error);
        }
        
        return base64_encode($encrypted);
    }
    
    public static function decryptAES($data, $key, $iv) {
        if (strlen($key) !== 32) {
            throw new Exception("Chave AES deve ter 32 bytes para AES-256-CBC, recebida: " . strlen($key) . " bytes");
        }
        
        if (strlen($iv) !== 16) {
            throw new Exception("IV deve ter 16 bytes, recebido: " . strlen($iv) . " bytes");
        }
        
        $data = base64_decode($data, true);
        if ($data === false) {
            throw new Exception("Base64 inválido para AES");
        }
        
        $decrypted = openssl_decrypt(
            $data,
            self::$cipherMethod,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            $error = openssl_error_string();
            throw new Exception("Erro na descriptografia AES: " . $error);
        }
        
        return $decrypted;
    }
    
    public static function processEncryptedRequest() {
        try {
            $input = file_get_contents('php://input');
            if (empty($input)) {
                throw new Exception('Nenhum dado recebido');
            }
            
            error_log("Input recebido (primeiros 200 chars): " . substr($input, 0, 200));
            
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON inválido: ' . json_last_error_msg());
            }

            $requiredFields = ['encryptedKey', 'iv', 'encryptedData'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Campo obrigatório ausente ou vazio: $field");
                }
            }

            error_log("Dados JSON válidos recebidos. Campos: " . implode(', ', array_keys($data)));

            error_log("Iniciando descriptografia da chave RSA...");
            $symmetricKey = self::decryptRSA($data['encryptedKey']);

            $iv = base64_decode($data['iv'], true);
            if ($iv === false) {
                throw new Exception('IV base64 inválido');
            }
            if (strlen($iv) !== 16) {
                throw new Exception('IV inválido - deve ter 16 bytes, recebido: ' . strlen($iv));
            }
            
            error_log("Iniciando descriptografia dos dados AES...");
            $decryptedData = self::decryptAES($data['encryptedData'], $symmetricKey, $iv);

            self::$lastDecryptedKey = $symmetricKey;
            self::$lastIV = $iv;

            $result = json_decode($decryptedData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Dados descriptografados não são JSON válido: ' . json_last_error_msg());
            }

            error_log("Processamento de requisição criptografada concluído com sucesso");
            return $result;
            
        } catch (Exception $e) {
            error_log("Erro processEncryptedRequest: " . $e->getMessage());
            self::$lastDecryptedKey = null;
            self::$lastIV = null;
            throw $e;
        }
    }
    
    public static function prepareEncryptedResponse($responseData, $key = null, $iv = null) {
        try {
            if ($key === null) {
                $key = self::$lastDecryptedKey;
            }
            if ($iv === null) {
                $iv = self::$lastIV;
            }

            if (!$key || !$iv) {
                throw new Exception('Chave ou IV não disponível para criptografia da resposta');
            }

            $jsonData = json_encode($responseData, JSON_UNESCAPED_UNICODE);
            if ($jsonData === false) {
                throw new Exception('Erro ao codificar resposta em JSON');
            }
            
            $encryptedData = self::encryptAES($jsonData, $key, $iv);
            $encryptedKey = base64_encode(self::encryptRSA($key));

            return [
                'encryptedKey' => $encryptedKey,
                'iv' => base64_encode($iv),
                'encryptedData' => $encryptedData
            ];
        } catch (Exception $e) {
            error_log("Erro prepareEncryptedResponse: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function encryptRSA($data) {
        $keyDetails = openssl_pkey_get_details(self::$publicKey);
        $maxEncryptLen = ($keyDetails['bits'] / 8) - 42; 
        
        if (strlen($data) > $maxEncryptLen) {
            throw new Exception("Dados muito grandes para criptografia RSA (" . strlen($data) . " > " . $maxEncryptLen . ")");
        }
        
        if (!openssl_public_encrypt($data, $encrypted, self::$publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            $error = openssl_error_string();
            throw new Exception("Erro ao criptografar com RSA: " . $error);
        }
        return $encrypted;
    }

    public static function canSendEncryptedResponse() {
        return (self::$lastDecryptedKey !== null && self::$lastIV !== null);
    }

    public static function testCompatibility() {
        try {
            $testKey = random_bytes(32);
            $testKeyBase64 = base64_encode($testKey);
            
            error_log("Testando compatibilidade RSA...");
            error_log("Chave de teste (base64): " . $testKeyBase64);
            
            $encryptedKey = self::encryptRSA($testKey);
            $encryptedKeyBase64 = base64_encode($encryptedKey);
            
            error_log("Chave criptografada (base64): " . $encryptedKeyBase64);
            
            $decryptedKey = self::decryptRSA($encryptedKeyBase64);
            
            if ($testKey === $decryptedKey) {
                error_log(" Teste de compatibilidade RSA: SUCESSO");
                return true;
            } else {
                error_log(" Teste de compatibilidade RSA: FALHA - chaves diferentes");
                error_log("Original: " . bin2hex($testKey));
                error_log("Descriptografada: " . bin2hex($decryptedKey));
                return false;
            }
            
        } catch (Exception $e) {
            error_log(" Teste de compatibilidade RSA: ERRO - " . $e->getMessage());
            return false;
        }
    }

    public static function testKeys() {
        try {
            $testData = "Hello World Test 123";
            
            $encryptedRSA = self::encryptRSA($testData);
            $decryptedRSA = self::decryptRSA(base64_encode($encryptedRSA));
            
            if ($testData !== $decryptedRSA) {
                throw new Exception("Teste RSA falhou: dados não coincidem");
            }
            
            $aesKey = random_bytes(32);
            $aesIV = random_bytes(16);
            $encryptedAES = self::encryptAES($testData, $aesKey, $aesIV);
            $decryptedAES = self::decryptAES($encryptedAES, $aesKey, $aesIV);
            
            if ($testData !== $decryptedAES) {
                throw new Exception("Teste AES falhou: dados não coincidem");
            }
            
            $compatibilityTest = self::testCompatibility();
            
            return [
                'success' => true,
                'message' => 'Todas as funções de criptografia funcionando corretamente',
                'compatibility' => $compatibilityTest
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro nos testes: ' . $e->getMessage()
            ];
        }
    }
}

try {
    CryptoUtils::init();
    error_log("CryptoUtils inicializado com sucesso");
    
    $testResult = CryptoUtils::testCompatibility();
    if (!$testResult) {
        error_log("  AVISO: Teste de compatibilidade RSA falhou!");
    }
    
} catch (Exception $e) {
    error_log("Erro ao inicializar CryptoUtils: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Erro de configuração do servidor: ' . $e->getMessage()]));
}
?>