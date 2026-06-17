<?php
/**
 * cripto.php  —  Criptografia híbrida do lado do servidor (Uberversitarios)
 * --------------------------------------------------------------------------
 * Esquema: chave de sessão (AES-256-CBC) protegida por chave pública (RSA-OAEP).
 *
 * O front (Web Crypto API) faz:
 *   1) pega a chave pública  ->  api/chave-publica.php
 *   2) gera uma chave AES-256 de sessão
 *   3) cifra essa chave AES com a pública (RSA-OAEP)
 *   4) cifra os dados do formulário com a chave AES (AES-256-CBC)
 *   5) envia { encryptedKey, iv, encryptedData } (tudo base64)
 *
 * Este arquivo desfaz o passo 5 -> 1 no servidor.
 *
 * IMPORTANTE (compatibilidade com o navegador):
 *   - RSA usa OAEP com hash SHA-1, que é o que o PHP aplica em
 *     OPENSSL_PKCS1_OAEP_PADDING. No front, a chave é importada com
 *     { name:'RSA-OAEP', hash:'SHA-1' }.
 *   - AES é AES-256-CBC com IV de 16 bytes e padding PKCS7 (o padrão tanto
 *     do Web Crypto quanto do openssl_decrypt sem OPENSSL_ZERO_PADDING).
 *
 * Sem dependências externas: usa só a extensão openssl do PHP.
 */

class Cripto
{
    /** @var \OpenSSLAsymmetricKey|resource */
    private static $chavePrivada;
    /** @var \OpenSSLAsymmetricKey|resource */
    private static $chavePublica;

    private static $metodoAes = 'AES-256-CBC';
    private static $iniciado  = false;

    /**
     * Carrega o par de chaves de /keys. Chame antes de usar o resto.
     */
    public static function init(): void
    {
        if (self::$iniciado) {
            return;
        }

        $caminhoPriv = __DIR__ . '/keys/private.key';
        $caminhoPub  = __DIR__ . '/keys/public.key';

        if (!is_readable($caminhoPriv) || !is_readable($caminhoPub)) {
            throw new Exception('Chaves RSA não encontradas em ' . __DIR__ . '/keys');
        }

        $privPem = file_get_contents($caminhoPriv);
        $pubPem  = file_get_contents($caminhoPub);

        self::$chavePrivada = openssl_pkey_get_private($privPem);
        if (!self::$chavePrivada) {
            throw new Exception('Falha ao carregar a chave privada: ' . self::erroOpenssl());
        }

        self::$chavePublica = openssl_pkey_get_public($pubPem);
        if (!self::$chavePublica) {
            throw new Exception('Falha ao carregar a chave pública: ' . self::erroOpenssl());
        }

        $det = openssl_pkey_get_details(self::$chavePrivada);
        if (!$det || ($det['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || ($det['bits'] ?? 0) < 2048) {
            throw new Exception('A chave deve ser RSA de pelo menos 2048 bits.');
        }

        self::$iniciado = true;
    }

    /**
     * Recebe o envelope { encryptedKey, iv, encryptedData } e devolve o
     * conteúdo já decifrado como array associativo.
     */
    public static function descriptografarEnvelope(array $envelope): array
    {
        self::init();

        foreach (['encryptedKey', 'iv', 'encryptedData'] as $campo) {
            if (empty($envelope[$campo])) {
                throw new Exception("Campo obrigatório ausente no envelope: {$campo}");
            }
        }

        // 1) decifra a chave de sessão (AES) usando a chave privada (RSA-OAEP)
        $chaveAes = self::decifrarRsa($envelope['encryptedKey']);
        if (strlen($chaveAes) !== 32) {
            throw new Exception('Chave de sessão inválida: esperados 32 bytes, vieram ' . strlen($chaveAes));
        }

        // 2) decifra os dados com AES-256-CBC
        $iv = base64_decode($envelope['iv'], true);
        if ($iv === false || strlen($iv) !== 16) {
            throw new Exception('IV inválido (precisa de 16 bytes em base64).');
        }

        $jsonClaro = self::decifrarAes($envelope['encryptedData'], $chaveAes, $iv);

        $dados = json_decode($jsonClaro, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Conteúdo decifrado não é um JSON válido: ' . json_last_error_msg());
        }

        return $dados;
    }

    /**
     * Descriptografia RSA (chave privada) com padding OAEP (SHA-1),
     * equivalente ao que o navegador usa com RSA-OAEP/SHA-1.
     */
    private static function decifrarRsa(string $base64): string
    {
        $bin = base64_decode(preg_replace('/\s+/', '', $base64), true);
        if ($bin === false || $bin === '') {
            throw new Exception('encryptedKey em base64 inválido.');
        }

        while (openssl_error_string() !== false) { /* limpa fila de erros */ }

        $aberto = '';
        $ok = openssl_private_decrypt($bin, $aberto, self::$chavePrivada, OPENSSL_PKCS1_OAEP_PADDING);
        if (!$ok) {
            throw new Exception('Falha na descriptografia RSA-OAEP: ' . self::erroOpenssl());
        }

        return $aberto;
    }

    /**
     * Descriptografia AES-256-CBC (remove o padding PKCS7 automaticamente).
     */
    private static function decifrarAes(string $base64, string $chave, string $iv): string
    {
        $bin = base64_decode($base64, true);
        if ($bin === false) {
            throw new Exception('encryptedData em base64 inválido.');
        }

        $claro = openssl_decrypt($bin, self::$metodoAes, $chave, OPENSSL_RAW_DATA, $iv);
        if ($claro === false) {
            throw new Exception('Falha na descriptografia AES: ' . self::erroOpenssl());
        }

        return $claro;
    }

    /* ---------------------------------------------------------------------
     *  S.3.1.f  —  Log no "console" do back
     *  Imprime no formato  usuario:hostname>mensagem
     *
     *  Para VER no terminal, rode o back com o servidor embutido:
     *      php -S localhost:8000
     *  (assim o error_log cai direto na janela do terminal).
     *  No Apache/XAMPP a mesma linha aparece no error.log do servidor.
     * ------------------------------------------------------------------- */
    public static function logConsole(string $mensagem): void
    {
        $linha = self::prefixoConsole() . '>' . $mensagem;

        // stderr -> aparece direto no terminal do `php -S`
        @file_put_contents('php://stderr', $linha . PHP_EOL);
        // error_log -> aparece no log do Apache/XAMPP (e também no `php -S`)
        error_log($linha);
    }

    /**
     * Monta "usuario:hostname" do servidor (funciona em Linux e Windows/XAMPP).
     */
    private static function prefixoConsole(): string
    {
        $usuario = '';

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid(@posix_geteuid());
            if (!empty($info['name'])) {
                $usuario = $info['name'];
            }
        }
        if ($usuario === '') {
            $usuario = getenv('USER') ?: getenv('USERNAME') ?: get_current_user() ?: 'desconhecido';
        }

        $hostname = gethostname();
        if ($hostname === false || $hostname === '') {
            $hostname = getenv('COMPUTERNAME') ?: php_uname('n');
        }

        return $usuario . ':' . $hostname;
    }

    private static function erroOpenssl(): string
    {
        $msgs = [];
        while (($e = openssl_error_string()) !== false) {
            $msgs[] = $e;
        }
        return $msgs ? implode('; ', $msgs) : 'erro desconhecido';
    }

    /* =====================================================================
     *  S.3.2  —  Criptografia de campos do BD com chave simétrica
     *  A chave (AES-256) fica protegida por gestão de segredos: gravada
     *  mascarada (XOR com o poema) no arquivo chave_bd.enc, gerada pelo
     *  script gerar_chave_bd.php. Aqui só a lemos e usamos.
     * ================================================================== */

    private static $chaveBD = null;

    /**
     * Lê a chave simétrica do BD, desmascarando com o poema (mesma gestão de
     * segredos usada no config.php). Requer que o config.php já tenha sido
     * carregado (ele define carregarChaveMascara() e aplicarMascaraXor()).
     */
    public static function carregarChaveBD(): string
    {
        if (self::$chaveBD !== null) {
            return self::$chaveBD;
        }

        if (!function_exists('carregarChaveMascara') || !function_exists('aplicarMascaraXor')) {
            throw new Exception('config.php precisa ser carregado antes de usar a cripto do BD.');
        }

        $arquivo = __DIR__ . '/chave_bd.enc';
        if (!is_readable($arquivo)) {
            throw new Exception('Chave do BD não encontrada (chave_bd.enc). Rode gerar_chave_bd.php.');
        }

        $mascarado = base64_decode(trim(file_get_contents($arquivo)), true);
        if ($mascarado === false || $mascarado === '') {
            throw new Exception('chave_bd.enc inválido.');
        }

        $poema = carregarChaveMascara();
        $raw   = aplicarMascaraXor($mascarado, $poema);

        if (strlen($raw) !== 32) {
            throw new Exception('Chave do BD inválida: esperados 32 bytes, vieram ' . strlen($raw));
        }

        self::$chaveBD = $raw;
        return $raw;
    }

    /**
     * Cifra um campo para guardar no BD (AES-256-CBC). Guarda IV + ciphertext
     * em base64, então cada valor cifrado é independente.
     */
    public static function cifrarBD(string $plain): string
    {
        $key = self::carregarChaveBD();
        $iv  = openssl_random_pseudo_bytes(16);
        $ct  = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($ct === false) {
            throw new Exception('Falha ao cifrar campo do BD: ' . self::erroOpenssl());
        }

        return base64_encode($iv . $ct);
    }

    /**
     * Desfaz cifrarBD: separa o IV (16 bytes) do ciphertext e decifra.
     */
    public static function decifrarBD(string $blob): string
    {
        $key = self::carregarChaveBD();
        $bin = base64_decode($blob, true);

        if ($bin === false || strlen($bin) < 17) {
            throw new Exception('Valor cifrado do BD inválido.');
        }

        $iv = substr($bin, 0, 16);
        $ct = substr($bin, 16);

        $plain = openssl_decrypt($ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new Exception('Falha ao decifrar campo do BD: ' . self::erroOpenssl());
        }

        return $plain;
    }
}