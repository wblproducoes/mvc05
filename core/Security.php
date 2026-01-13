<?php

namespace Core;

/**
 * Classe para gerenciamento de segurança
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class Security
{
    /**
     * Gera token CSRF
     * 
     * @return string
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
     * 
     * @param string $token
     * @return bool
     */
    public static function validateCsrfToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || self::isTokenExpired()) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Verifica se o token CSRF expirou
     * 
     * @return bool
     */
    private static function isTokenExpired(): bool
    {
        $expireTime = $_ENV['CSRF_TOKEN_EXPIRE'] ?? 3600; // 1 hora por padrão
        
        if (!isset($_SESSION['csrf_token_time'])) {
            return true;
        }

        return (time() - $_SESSION['csrf_token_time']) > $expireTime;
    }

    /**
     * Criptografa senha
     * 
     * @param string $password
     * @return string
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verifica senha
     * 
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Sanitiza entrada de dados
     * 
     * @param string $input
     * @return string
     */
    public static function sanitizeInput(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Gera string aleatória
     * 
     * @param int $length
     * @return string
     */
    public static function generateRandomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Valida email
     * 
     * @param string $email
     * @return bool
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Limita tentativas de login
     * 
     * @param string $identifier
     * @param int $maxAttempts
     * @param int $timeWindow
     * @return bool
     */
    public static function rateLimitCheck(string $identifier, int $maxAttempts = 5, int $timeWindow = 900): bool
    {
        $key = "login_attempts_{$identifier}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        // Remove tentativas antigas
        $currentTime = time();
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });

        return count($_SESSION[$key]) < $maxAttempts;
    }

    /**
     * Registra tentativa de login
     * 
     * @param string $identifier
     * @return void
     */
    public static function recordLoginAttempt(string $identifier): void
    {
        $key = "login_attempts_{$identifier}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        $_SESSION[$key][] = time();
    }

    /**
     * Limpa tentativas de login
     * 
     * @param string $identifier
     * @return void
     */
    public static function clearLoginAttempts(string $identifier): void
    {
        $key = "login_attempts_{$identifier}";
        unset($_SESSION[$key]);
    }

    /**
     * Gera chave de API
     * 
     * @return string
     */
    public static function generateApiKey(): string
    {
        return 'ak_' . self::generateRandomString(40);
    }

    /**
     * Valida força da senha
     * 
     * @param string $password
     * @return array
     */
    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'A senha deve ter pelo menos 8 caracteres';
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

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Escapa saída para HTML
     * 
     * @param string $string
     * @return string
     */
    public static function escapeHtml(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Remove tags HTML perigosas
     * 
     * @param string $input
     * @return string
     */
    public static function stripDangerousTags(string $input): string
    {
        $allowedTags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6>';
        return strip_tags($input, $allowedTags);
    }
}