<?php

namespace Core;

/**
 * Middleware para verificar se o sistema precisa ser instalado
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.4.2
 */
class InstallationMiddleware
{
    /**
     * @var Database Instância do banco de dados
     */
    private Database $database;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->database = new Database();
    }

    /**
     * Verifica se o sistema precisa ser instalado
     * 
     * @param string $currentPath
     * @return bool True se deve redirecionar para instalação
     */
    public function handle(string $currentPath): bool
    {
        // Se já está na página de instalação, não redireciona
        if (strpos($currentPath, '/install') === 0) {
            return false;
        }

        // Se é um arquivo estático, não verifica
        if ($this->isStaticFile($currentPath)) {
            return false;
        }

        // Verifica se precisa instalar
        return $this->needsInstallation();
    }

    /**
     * Verifica se o sistema precisa ser instalado
     * 
     * @return bool
     */
    private function needsInstallation(): bool
    {
        try {
            // Verifica se a tabela principal (users) existe
            $tableName = $this->database->prefixTable('users');
            
            if (!$this->database->tableExists($tableName)) {
                return true;
            }
            
            // Verifica se há pelo menos um usuário ativo
            $sql = "SELECT COUNT(*) as count FROM `{$tableName}` WHERE deleted_at IS NULL";
            $result = $this->database->selectOne($sql);
            
            return $result['count'] == 0;
            
        } catch (\Exception $e) {
            // Se não conseguir conectar ou verificar, assume que precisa instalar
            error_log("Erro ao verificar instalação: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Verifica se é um arquivo estático
     * 
     * @param string $path
     * @return bool
     */
    private function isStaticFile(string $path): bool
    {
        $staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf'];
        
        foreach ($staticExtensions as $ext) {
            if (str_ends_with(strtolower($path), $ext)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Obtém URL de redirecionamento para instalação
     * 
     * @return string
     */
    public function getInstallUrl(): string
    {
        return '/install';
    }

    /**
     * Verifica se é a primeira instalação
     * 
     * @return bool
     */
    public function isFirstInstall(): bool
    {
        try {
            // Lista de tabelas essenciais
            $essentialTables = ['users', 'levels', 'status', 'genders'];
            
            foreach ($essentialTables as $table) {
                $tableName = $this->database->prefixTable($table);
                if (!$this->database->tableExists($tableName)) {
                    return true;
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            // Se não conseguir verificar, assume primeira instalação
            return true;
        }
    }

    /**
     * Obtém status da instalação
     * 
     * @return array
     */
    public function getInstallationStatus(): array
    {
        try {
            $status = [
                'needs_install' => $this->needsInstallation(),
                'is_first_install' => $this->isFirstInstall(),
                'tables_exist' => false,
                'has_users' => false,
                'database_connected' => false
            ];

            // Testa conexão com banco
            $this->database->getConnection();
            $status['database_connected'] = true;

            // Verifica se tabelas existem
            $tableName = $this->database->prefixTable('users');
            if ($this->database->tableExists($tableName)) {
                $status['tables_exist'] = true;

                // Verifica se há usuários
                $sql = "SELECT COUNT(*) as count FROM `{$tableName}` WHERE deleted_at IS NULL";
                $result = $this->database->selectOne($sql);
                $status['has_users'] = $result['count'] > 0;
            }

            return $status;

        } catch (\Exception $e) {
            return [
                'needs_install' => true,
                'is_first_install' => true,
                'tables_exist' => false,
                'has_users' => false,
                'database_connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}