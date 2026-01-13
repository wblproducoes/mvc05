<?php

namespace Core;

/**
 * Classe avançada para gerenciamento de segurança
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.4.3
 */
class Security
{
    /**
     * @var array Configurações de segurança
     */
    private static array $config = [
        'csrf_token_expire' => 3600,
        'session_timeout' => 7200,
        'max_login_attempts' => 5,
        'lockout_duration' => 900,
        'password_min_length' => 12,
        'password_max_age_days' => 90,
        'session_regenerate_interval' => 300,
        'ip_whitelist_enabled' => false,
        'two_factor_enabled' => false,
        'audit_log_enabled' => true
    ];

    /**
     * @var array IPs bloqueados temporariamente
     */
    private static array $blockedIps = [];

    /**
     * @var array Tentativas de login por IP
     */
    private static array $ipAttempts = [];

    /**
     * Inicializa configurações de segurança
     */
    public static function init(): void
    {
        // Configurar headers de segurança
        self::setSecurityHeaders();
        
        // Configurar sessão segura
        self::configureSecureSession();
        
        // Verificar integridade da sessão
        self::validateSession();
        
        // Carregar configurações do ambiente
        self::loadConfig();
    }

    /**
     * Define headers de segurança HTTP
     */
    public static function setSecurityHeaders(): void
    {
        // Previne clickjacking
        header('X-Frame-Options: DENY');
        
        // Previne MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Ativa proteção XSS do navegador
        header('X-XSS-Protection: 1; mode=block');
        
        // Força HTTPS (se em produção)
        if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https://cdn.jsdelivr.net; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none';";
        header("Content-Security-Policy: {$csp}");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    /**
     * Configura sessão segura
     */
    public static function configureSecureSession(): void
    {
        // Configurações de sessão segura
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', self::$config['session_timeout']);
        
        // Nome da sessão personalizado
        session_name('SECURE_ADMIN_SESSION');
    }

    /**
     * Valida integridade da sessão
     */
    public static function validateSession(): void
    {
        if (!isset($_SESSION)) {
            return;
        }

        // Verificar IP da sessão (se habilitado)
        if (isset($_SESSION['ip_address'])) {
            if ($_SESSION['ip_address'] !== self::getClientIp()) {
                self::destroySession();
                self::logSecurityEvent('session_hijack_attempt', [
                    'original_ip' => $_SESSION['ip_address'],
                    'current_ip' => self::getClientIp()
                ]);
                return;
            }
        }

        // Verificar User-Agent da sessão
        if (isset($_SESSION['user_agent'])) {
            if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
                self::destroySession();
                self::logSecurityEvent('session_hijack_attempt', [
                    'user_agent_mismatch' => true
                ]);
                return;
            }
        }

        // Verificar timeout da sessão
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > self::$config['session_timeout']) {
                self::destroySession();
                return;
            }
        }

        // Regenerar ID da sessão periodicamente
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > self::$config['session_regenerate_interval']) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }

        // Atualizar última atividade
        $_SESSION['last_activity'] = time();
    }

    /**
     * Inicia sessão segura
     */
    public static function startSecureSession(): void
    {
        $_SESSION['ip_address'] = self::getClientIp();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['last_regeneration'] = time();
        $_SESSION['csrf_token'] = self::generateCsrfToken();
    }

    /**
     * Destrói sessão de forma segura
     */
    public static function destroySession(): void
    {
        if (isset($_SESSION)) {
            $_SESSION = [];
            
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }
            
            session_destroy();
        }
    }

    /**
     * Gera token CSRF seguro
     */
    public static function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || self::isTokenExpired()) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Valida token CSRF
     */
    public static function validateCsrfToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || self::isTokenExpired()) {
            self::logSecurityEvent('csrf_token_invalid', ['token' => substr($token, 0, 8) . '...']);
            return false;
        }

        $valid = hash_equals($_SESSION['csrf_token'], $token);
        
        if (!$valid) {
            self::logSecurityEvent('csrf_token_mismatch', ['token' => substr($token, 0, 8) . '...']);
        }

        return $valid;
    }

    /**
     * Verifica se o token CSRF expirou
     */
    private static function isTokenExpired(): bool
    {
        if (!isset($_SESSION['csrf_token_time'])) {
            return true;
        }

        return (time() - $_SESSION['csrf_token_time']) > self::$config['csrf_token_expire'];
    }

    /**
     * Criptografa senha com salt forte
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterações
            'threads' => 3          // 3 threads
        ]);
    }

    /**
     * Verifica senha
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Verifica se senha precisa ser rehashed
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Sanitiza entrada de dados de forma robusta
     */
    public static function sanitizeInput(string $input): string
    {
        // Remove caracteres de controle
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // Normaliza espaços em branco
        $input = preg_replace('/\s+/', ' ', trim($input));
        
        // Escapa HTML
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitiza dados para SQL (além do PDO)
     */
    public static function sanitizeSql(string $input): string
    {
        // Remove caracteres perigosos para SQL
        $dangerous = ['--', '/*', '*/', 'xp_', 'sp_', 'EXEC', 'EXECUTE', 'UNION', 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER'];
        
        foreach ($dangerous as $pattern) {
            $input = str_ireplace($pattern, '', $input);
        }
        
        return $input;
    }

    /**
     * Valida e sanitiza upload de arquivo
     */
    public static function validateFileUpload(array $file, array $allowedTypes = [], int $maxSize = 5242880): array
    {
        $errors = [];
        
        // Verificar se arquivo foi enviado
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Erro no upload do arquivo';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Verificar tamanho
        if ($file['size'] > $maxSize) {
            $errors[] = 'Arquivo muito grande. Máximo: ' . number_format($maxSize / 1024 / 1024, 2) . 'MB';
        }
        
        // Verificar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            $errors[] = 'Tipo de arquivo não permitido';
        }
        
        // Verificar extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'pht', 'phar', 'exe', 'bat', 'cmd', 'scr', 'js', 'vbs'];
        
        if (in_array($extension, $dangerousExtensions)) {
            $errors[] = 'Extensão de arquivo não permitida';
        }
        
        // Verificar conteúdo do arquivo
        $content = file_get_contents($file['tmp_name']);
        if (preg_match('/<\?php|<script|javascript:|vbscript:/i', $content)) {
            $errors[] = 'Conteúdo de arquivo suspeito detectado';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'safe_name' => self::generateSafeFilename($file['name'])
        ];
    }

    /**
     * Gera nome de arquivo seguro
     */
    public static function generateSafeFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        // Remove caracteres perigosos
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        
        // Adiciona timestamp para unicidade
        $timestamp = date('YmdHis');
        $random = substr(md5(uniqid()), 0, 8);
        
        return "{$name}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Rate limiting avançado por IP
     */
    public static function rateLimitCheck(string $identifier, int $maxAttempts = null, int $timeWindow = null): bool
    {
        $maxAttempts = $maxAttempts ?? self::$config['max_login_attempts'];
        $timeWindow = $timeWindow ?? self::$config['lockout_duration'];
        
        $ip = self::getClientIp();
        
        // Verificar se IP está bloqueado
        if (self::isIpBlocked($ip)) {
            self::logSecurityEvent('blocked_ip_attempt', ['ip' => $ip, 'identifier' => $identifier]);
            return false;
        }
        
        $key = "attempts_{$identifier}_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        // Remove tentativas antigas
        $currentTime = time();
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });

        $attemptCount = count($_SESSION[$key]);
        
        // Bloquear IP se muitas tentativas
        if ($attemptCount >= $maxAttempts) {
            self::blockIp($ip, $timeWindow);
            self::logSecurityEvent('ip_blocked_rate_limit', [
                'ip' => $ip,
                'attempts' => $attemptCount,
                'identifier' => $identifier
            ]);
            return false;
        }

        return true;
    }

    /**
     * Registra tentativa de login
     */
    public static function recordLoginAttempt(string $identifier): void
    {
        $ip = self::getClientIp();
        $key = "attempts_{$identifier}_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        $_SESSION[$key][] = time();
        
        self::logSecurityEvent('login_attempt', [
            'identifier' => $identifier,
            'ip' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * Limpa tentativas de login
     */
    public static function clearLoginAttempts(string $identifier): void
    {
        $ip = self::getClientIp();
        $key = "attempts_{$identifier}_{$ip}";
        unset($_SESSION[$key]);
    }

    /**
     * Bloqueia IP temporariamente
     */
    public static function blockIp(string $ip, int $duration = null): void
    {
        $duration = $duration ?? self::$config['lockout_duration'];
        self::$blockedIps[$ip] = time() + $duration;
        
        // Salvar em arquivo para persistência
        $blockFile = STORAGE_PATH . '/security/blocked_ips.json';
        if (!is_dir(dirname($blockFile))) {
            mkdir(dirname($blockFile), 0755, true);
        }
        file_put_contents($blockFile, json_encode(self::$blockedIps));
    }

    /**
     * Verifica se IP está bloqueado
     */
    public static function isIpBlocked(string $ip): bool
    {
        // Carregar IPs bloqueados do arquivo
        $blockFile = STORAGE_PATH . '/security/blocked_ips.json';
        if (file_exists($blockFile)) {
            $blocked = json_decode(file_get_contents($blockFile), true) ?? [];
            
            // Remove bloqueios expirados
            $currentTime = time();
            $blocked = array_filter($blocked, function($expiry) use ($currentTime) {
                return $expiry > $currentTime;
            });
            
            // Salva lista atualizada
            file_put_contents($blockFile, json_encode($blocked));
            
            return isset($blocked[$ip]);
        }
        
        return false;
    }

    /**
     * Obtém IP real do cliente
     */
    public static function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Valida força da senha de forma rigorosa
     */
    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];
        $minLength = self::$config['password_min_length'];
        
        if (strlen($password) < $minLength) {
            $errors[] = "A senha deve ter pelo menos {$minLength} caracteres";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'A senha deve conter pelo menos uma letra maiúscula';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'A senha deve conter pelo menos uma letra minúscula';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'A senha deve conter pelo menos um número';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'A senha deve conter pelo menos um caractere especial';
        }
        
        // Verificar senhas comuns
        if (self::isCommonPassword($password)) {
            $errors[] = 'Esta senha é muito comum. Escolha uma senha mais segura';
        }
        
        // Verificar padrões sequenciais
        if (self::hasSequentialPattern($password)) {
            $errors[] = 'A senha não pode conter sequências óbvias (123, abc, etc.)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => self::calculatePasswordStrength($password)
        ];
    }

    /**
     * Verifica se é uma senha comum
     */
    private static function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123', 'password123',
            'admin', 'letmein', 'welcome', 'monkey', '1234567890', 'senha123',
            'admin123', 'root', 'toor', 'pass', '12345678', 'qwerty123'
        ];
        
        return in_array(strtolower($password), $commonPasswords);
    }

    /**
     * Verifica padrões sequenciais
     */
    private static function hasSequentialPattern(string $password): bool
    {
        $sequences = ['123', '234', '345', '456', '567', '678', '789', '890',
                     'abc', 'bcd', 'cde', 'def', 'efg', 'fgh', 'ghi', 'hij'];
        
        $lowerPassword = strtolower($password);
        
        foreach ($sequences as $seq) {
            if (strpos($lowerPassword, $seq) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Calcula força da senha
     */
    private static function calculatePasswordStrength(string $password): int
    {
        $score = 0;
        
        // Comprimento
        $score += min(strlen($password) * 2, 50);
        
        // Variedade de caracteres
        if (preg_match('/[a-z]/', $password)) $score += 5;
        if (preg_match('/[A-Z]/', $password)) $score += 5;
        if (preg_match('/[0-9]/', $password)) $score += 5;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 10;
        
        // Penalidades
        if (self::isCommonPassword($password)) $score -= 30;
        if (self::hasSequentialPattern($password)) $score -= 20;
        
        return max(0, min(100, $score));
    }

    /**
     * Gera string aleatória criptograficamente segura
     */
    public static function generateRandomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Gera token seguro para API
     */
    public static function generateApiKey(): string
    {
        return 'ak_' . self::generateRandomString(64);
    }

    /**
     * Criptografia simétrica segura
     */
    public static function encrypt(string $data, string $key = null): string
    {
        $key = $key ?? ($_ENV['APP_KEY'] ?? 'default-key-change-me');
        $key = hash('sha256', $key, true);
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Descriptografia simétrica segura
     */
    public static function decrypt(string $encryptedData, string $key = null): string
    {
        $key = $key ?? ($_ENV['APP_KEY'] ?? 'default-key-change-me');
        $key = hash('sha256', $key, true);
        
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Log de eventos de segurança
     */
    public static function logSecurityEvent(string $event, array $data = []): void
    {
        if (!self::$config['audit_log_enabled']) {
            return;
        }
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null,
            'session_id' => session_id(),
            'data' => $data
        ];
        
        $logFile = STORAGE_PATH . '/logs/security_' . date('Y-m-d') . '.log';
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Carrega configurações do ambiente
     */
    private static function loadConfig(): void
    {
        self::$config = array_merge(self::$config, [
            'csrf_token_expire' => (int)($_ENV['CSRF_TOKEN_EXPIRE'] ?? 3600),
            'session_timeout' => (int)($_ENV['SESSION_TIMEOUT'] ?? 7200),
            'max_login_attempts' => (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5),
            'lockout_duration' => (int)($_ENV['LOCKOUT_DURATION'] ?? 900),
            'password_min_length' => (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 12),
            'audit_log_enabled' => filter_var($_ENV['AUDIT_LOG_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN)
        ]);
    }

    /**
     * Escapa saída para HTML
     */
    public static function escapeHtml(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Remove tags HTML perigosas
     */
    public static function stripDangerousTags(string $input): string
    {
        $allowedTags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6><blockquote><code>';
        return strip_tags($input, $allowedTags);
    }

    /**
     * Valida email de forma rigorosa
     */
    public static function validateEmail(string $email): bool
    {
        // Validação básica
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Verificar domínios suspeitos
        $suspiciousDomains = ['tempmail.org', '10minutemail.com', 'guerrillamail.com'];
        $domain = substr(strrchr($email, "@"), 1);
        
        if (in_array(strtolower($domain), $suspiciousDomains)) {
            return false;
        }
        
        return true;
    }

    /**
     * Gera hash de integridade para arquivos
     */
    public static function generateFileHash(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('Arquivo não encontrado');
        }
        
        return hash_file('sha256', $filePath);
    }

    /**
     * Verifica integridade de arquivo
     */
    public static function verifyFileIntegrity(string $filePath, string $expectedHash): bool
    {
        return hash_equals($expectedHash, self::generateFileHash($filePath));
    }

    /**
     * Obtém configuração de segurança
     */
    public static function getConfig(string $key = null)
    {
        if ($key === null) {
            return self::$config;
        }
        
        return self::$config[$key] ?? null;
    }

    /**
     * Define configuração de segurança
     */
    public static function setConfig(string $key, $value): void
    {
        self::$config[$key] = $value;
    }
}