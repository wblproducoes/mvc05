<?php

namespace Core;

use App\Models\User;

/**
 * Classe para gerenciamento de autenticação
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class Auth
{
    /**
     * @var string Chave da sessão do usuário
     */
    private const SESSION_KEY = 'user_id';

    /**
     * @var string Chave do remember token
     */
    private const REMEMBER_KEY = 'remember_token';

    /**
     * Realiza login do usuário
     * 
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return bool
     */
    public static function attempt(string $email, string $password, bool $remember = false): bool
    {
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user || !Security::verifyPassword($password, $user['password'])) {
            return false;
        }

        // Verificar se o usuário está ativo (status_id = 1)
        if ($user['status_id'] != 1) {
            return false;
        }

        // Fazer login
        self::login($user, $remember);

        // Atualizar último login
        $userModel->update($user['id'], [
            'last_login' => date('Y-m-d H:i:s'),
            'login_count' => ($user['login_count'] ?? 0) + 1
        ]);

        return true;
    }

    /**
     * Faz login do usuário
     * 
     * @param array $user
     * @param bool $remember
     * @return void
     */
    public static function login(array $user, bool $remember = false): void
    {
        $_SESSION[self::SESSION_KEY] = $user['id'];
        
        if ($remember) {
            self::setRememberToken($user['id']);
        }

        // Regenerar ID da sessão por segurança
        session_regenerate_id(true);
    }

    /**
     * Faz logout do usuário
     * 
     * @return void
     */
    public static function logout(): void
    {
        // Remover remember token se existir
        if (isset($_COOKIE[self::REMEMBER_KEY])) {
            self::clearRememberToken();
        }

        // Limpar sessão
        unset($_SESSION[self::SESSION_KEY]);
        
        // Destruir sessão completamente
        session_destroy();
        
        // Iniciar nova sessão
        session_start();
        session_regenerate_id(true);
    }

    /**
     * Verifica se o usuário está autenticado
     * 
     * @return bool
     */
    public static function check(): bool
    {
        // Verificar sessão
        if (isset($_SESSION[self::SESSION_KEY])) {
            return true;
        }

        // Verificar remember token
        if (isset($_COOKIE[self::REMEMBER_KEY])) {
            return self::loginByRememberToken($_COOKIE[self::REMEMBER_KEY]);
        }

        return false;
    }

    /**
     * Verifica se o usuário é convidado (não autenticado)
     * 
     * @return bool
     */
    public static function guest(): bool
    {
        return !self::check();
    }

    /**
     * Obtém o usuário autenticado
     * 
     * @return array|null
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $userModel = new User();
        $user = $userModel->findWithRelations($_SESSION[self::SESSION_KEY]);

        if (!$user || $user['status_id'] != 1) {
            self::logout();
            return null;
        }

        // Remover senha dos dados retornados
        unset($user['password']);
        
        return $user;
    }

    /**
     * Obtém o ID do usuário autenticado
     * 
     * @return int|null
     */
    public static function id(): ?int
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Define remember token
     * 
     * @param int $userId
     * @return void
     */
    private static function setRememberToken(int $userId): void
    {
        $token = Security::generateRandomString(64);
        $hashedToken = hash('sha256', $token);

        // Salvar token no banco
        $userModel = new User();
        $userModel->update($userId, ['remember_token' => $hashedToken]);

        // Definir cookie (30 dias)
        $expiry = time() + (30 * 24 * 60 * 60);
        setcookie(self::REMEMBER_KEY, $token, $expiry, '/', '', true, true);
    }

    /**
     * Faz login usando remember token
     * 
     * @param string $token
     * @return bool
     */
    private static function loginByRememberToken(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        
        $userModel = new User();
        $user = $userModel->whereFirst(['remember_token' => $hashedToken, 'deleted_at' => null]);

        if (!$user || $user['status_id'] != 1) {
            self::clearRememberToken();
            return false;
        }

        // Fazer login
        $_SESSION[self::SESSION_KEY] = $user['id'];
        
        // Regenerar token por segurança
        self::setRememberToken($user['id']);

        return true;
    }

    /**
     * Remove remember token
     * 
     * @return void
     */
    private static function clearRememberToken(): void
    {
        if (isset($_COOKIE[self::REMEMBER_KEY])) {
            $token = $_COOKIE[self::REMEMBER_KEY];
            $hashedToken = hash('sha256', $token);

            // Remover do banco
            $userModel = new User();
            $user = $userModel->whereFirst(['remember_token' => $hashedToken]);
            
            if ($user) {
                $userModel->update($user['id'], ['remember_token' => null]);
            }

            // Remover cookie
            setcookie(self::REMEMBER_KEY, '', time() - 3600, '/', '', true, true);
        }
    }

    /**
     * Verifica se o usuário tem uma permissão específica
     * 
     * @param string $permission
     * @return bool
     */
    public static function can(string $permission): bool
    {
        $user = self::user();
        
        if (!$user) {
            return false;
        }

        $levelModel = new \App\Models\Level();
        
        // Master e Admin têm todas as permissões
        if (in_array($user['level_code'], ['master', 'admin'])) {
            return true;
        }

        // Verificar permissões específicas baseadas no nível
        return self::checkLevelPermission($user['level_code'], $permission);
    }

    /**
     * Verifica se o usuário tem um nível específico
     * 
     * @param string $level
     * @return bool
     */
    public static function hasLevel(string $level): bool
    {
        $user = self::user();
        
        return $user && $user['level_code'] === $level;
    }

    /**
     * Verifica se o usuário tem nível administrativo
     * 
     * @return bool
     */
    public static function isAdmin(): bool
    {
        $user = self::user();
        
        if (!$user) {
            return false;
        }

        $adminLevels = ['master', 'admin', 'direction', 'financial', 'coordination', 'secretary'];
        
        return in_array($user['level_code'], $adminLevels);
    }

    /**
     * Verifica permissões específicas por nível
     * 
     * @param string $levelCode
     * @param string $permission
     * @return bool
     */
    private static function checkLevelPermission(string $levelCode, string $permission): bool
    {
        $permissions = [
            'direction' => ['users.view', 'users.create', 'users.edit', 'reports.view', 'settings.view'],
            'financial' => ['users.view', 'reports.view', 'financial.manage'],
            'coordination' => ['users.view', 'students.manage', 'teachers.manage'],
            'secretary' => ['users.view', 'students.manage'],
            'teacher' => ['students.view', 'classes.manage'],
            'employee' => ['basic.access'],
            'student' => ['profile.view'],
            'guardian' => ['student.view'],
            'user' => ['profile.view']
        ];

        $userPermissions = $permissions[$levelCode] ?? [];
        
        return in_array($permission, $userPermissions);
    }

    /**
     * Força logout de todas as sessões do usuário
     * 
     * @param int $userId
     * @return void
     */
    public static function logoutEverywhere(int $userId): void
    {
        $userModel = new User();
        
        // Remover remember token
        $userModel->update($userId, ['remember_token' => null]);
        
        // Se for o usuário atual, fazer logout
        if (self::id() === $userId) {
            self::logout();
        }
    }
}