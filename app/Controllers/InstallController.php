<?php

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Security;
use App\Models\User;

/**
 * Controller de instalação
 * 
 * @package App\Controllers
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class InstallController extends Controller
{
    /**
     * Exibe página de instalação
     * 
     * @return void
     */
    public function index(): void
    {
        // Verificar se o sistema precisa ser instalado
        $needsInstall = $this->needsInstallation();
        
        // Se não precisa instalar e já está marcado como instalado, redireciona
        if (!$needsInstall && file_exists(ROOT_PATH . '/.installed')) {
            $this->redirect('/');
        }

        // Se não precisa instalar mas não tem o arquivo .installed, cria
        if (!$needsInstall) {
            file_put_contents(ROOT_PATH . '/.installed', date('Y-m-d H:i:s'));
            $this->redirect('/');
        }

        $this->render('install/index.twig', [
            'requirements' => $this->checkRequirements(),
            'database_config' => $this->getDatabaseConfig(),
            'needs_install' => $needsInstall,
            'auto_install' => $needsInstall // Instalação automática se tabelas não existem
        ]);
    }

    /**
     * Processa instalação
     * 
     * @return void
     */
    public function install(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/install');
        }

        // Verificar se o sistema precisa ser instalado
        $needsInstall = $this->needsInstallation();
        
        // Se não precisa instalar, redireciona
        if (!$needsInstall) {
            $this->redirect('/');
        }

        // Se as tabelas não existem, não pede senha (instalação automática)
        // Se as tabelas existem, pede senha (reinstalação manual)
        $requirePassword = !$this->isFirstInstall();
        
        if ($requirePassword) {
            // Validar senha de instalação apenas se não for primeira instalação
            $installPassword = $_ENV['INSTALL_PASSWORD'] ?? 'admin123';
            if ($this->post('install_password') !== $installPassword) {
                $this->flash('error', 'Senha de instalação incorreta');
                $this->redirect('/install');
            }
        }

        // Validar dados do administrador
        $validation = $this->validate($_POST, [
            'admin_name' => 'required|min:2|max:100',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:8',
            'admin_password_confirmation' => 'required|confirmed',
            'system_name' => 'required|min:2|max:100'
        ]);

        if (!$validation['valid']) {
            $this->render('install/index.twig', [
                'errors' => $validation['errors'],
                'old' => $_POST,
                'requirements' => $this->checkRequirements(),
                'database_config' => $this->getDatabaseConfig(),
                'needs_install' => $needsInstall,
                'auto_install' => $this->isFirstInstall()
            ]);
            return;
        }

        // Validar força da senha
        $passwordValidation = Security::validatePasswordStrength($this->post('admin_password'));
        if (!$passwordValidation['valid']) {
            $this->render('install/index.twig', [
                'errors' => ['admin_password' => $passwordValidation['errors']],
                'old' => $_POST,
                'requirements' => $this->checkRequirements(),
                'database_config' => $this->getDatabaseConfig(),
                'needs_install' => $needsInstall,
                'auto_install' => $this->isFirstInstall()
            ]);
            return;
        }

        try {
            // Verificar requisitos
            $requirements = $this->checkRequirements();
            if (!$requirements['all_passed']) {
                throw new \Exception('Nem todos os requisitos foram atendidos');
            }

            // Criar banco de dados e tabelas
            $this->createDatabase();

            // Configurar sistema
            $this->configureSystem();

            // Criar usuário administrador
            $this->createAdminUser();

            // Criar diretórios necessários
            $this->createDirectories();

            // Criar arquivo .env se não existir
            $this->createEnvFile();

            // Marcar como instalado
            file_put_contents(ROOT_PATH . '/.installed', date('Y-m-d H:i:s'));

            $this->flash('success', 'Sistema instalado com sucesso!');
            $this->redirect('/login');

        } catch (\Exception $e) {
            error_log("Erro na instalação: " . $e->getMessage());
            $this->flash('error', 'Erro na instalação: ' . $e->getMessage());
            
            $this->render('install/index.twig', [
                'old' => $_POST,
                'requirements' => $this->checkRequirements(),
                'database_config' => $this->getDatabaseConfig(),
                'needs_install' => $needsInstall,
                'auto_install' => $this->isFirstInstall()
            ]);
        }
    }

    /**
     * Verifica requisitos do sistema
     * 
     * @return array
     */
    private function checkRequirements(): array
    {
        $requirements = [
            'php_version' => [
                'name' => 'PHP 8.4+',
                'required' => '8.4.0',
                'current' => PHP_VERSION,
                'passed' => version_compare(PHP_VERSION, '8.4.0', '>=')
            ],
            'pdo' => [
                'name' => 'PDO Extension',
                'passed' => extension_loaded('pdo')
            ],
            'pdo_mysql' => [
                'name' => 'PDO MySQL Extension',
                'passed' => extension_loaded('pdo_mysql')
            ],
            'mbstring' => [
                'name' => 'Mbstring Extension',
                'passed' => extension_loaded('mbstring')
            ],
            'openssl' => [
                'name' => 'OpenSSL Extension',
                'passed' => extension_loaded('openssl')
            ],
            'curl' => [
                'name' => 'cURL Extension',
                'passed' => extension_loaded('curl')
            ],
            'gd' => [
                'name' => 'GD Extension',
                'passed' => extension_loaded('gd')
            ],
            'writable_storage' => [
                'name' => 'Storage Directory Writable',
                'passed' => is_writable(ROOT_PATH . '/storage') || $this->makeWritable(ROOT_PATH . '/storage')
            ],
            'writable_public' => [
                'name' => 'Public Directory Writable',
                'passed' => is_writable(ROOT_PATH . '/public') || $this->makeWritable(ROOT_PATH . '/public')
            ]
        ];

        $allPassed = true;
        foreach ($requirements as $requirement) {
            if (!$requirement['passed']) {
                $allPassed = false;
                break;
            }
        }

        $requirements['all_passed'] = $allPassed;

        return $requirements;
    }

    /**
     * Obtém configuração do banco de dados
     * 
     * @return array
     */
    private function getDatabaseConfig(): array
    {
        return [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'name' => $_ENV['DB_NAME'] ?? 'admin_system',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4'
        ];
    }

    /**
     * Cria banco de dados e tabelas
     * 
     * @return void
     * @throws \Exception
     */
    private function createDatabase(): void
    {
        try {
            $database = new Database();
            
            // Executar script SQL de criação das tabelas
            $sqlFile = ROOT_PATH . '/database/schema.sql';
            
            if (!file_exists($sqlFile)) {
                throw new \Exception('Arquivo de schema não encontrado');
            }

            if (!$database->executeSqlFile($sqlFile)) {
                throw new \Exception('Erro ao criar tabelas do banco de dados');
            }

        } catch (\Exception $e) {
            throw new \Exception('Erro na criação do banco de dados: ' . $e->getMessage());
        }
    }

    /**
     * Cria usuário administrador
     * 
     * @return void
     * @throws \Exception
     */
    private function createAdminUser(): void
    {
        $userModel = new User();
        
        $adminData = [
            'name' => Security::sanitizeInput($this->post('admin_name')),
            'email' => strtolower(trim($this->post('admin_email'))),
            'password' => $this->post('admin_password'),
            'level_id' => 1, // Master
            'status_id' => 1, // Ativo
            'username' => 'admin'
        ];

        $userId = $userModel->create($adminData);
        
        if (!$userId) {
            throw new \Exception('Erro ao criar usuário administrador');
        }
    }

    /**
     * Configura o sistema com dados iniciais
     * 
     * @return void
     * @throws \Exception
     */
    private function configureSystem(): void
    {
        try {
            $database = new Database();
            
            // Configurar nome do sistema
            $systemName = Security::sanitizeInput($this->post('system_name'));
            
            $sql = "UPDATE `{prefix}settings` SET `value` = :system_name WHERE `key` = 'app_name'";
            $processedSql = $database->processSqlWithPrefix($sql);
            $database->update($processedSql, ['system_name' => $systemName]);
            
        } catch (\Exception $e) {
            // Se falhar, não é crítico - pode ser configurado depois
            error_log("Erro ao configurar sistema: " . $e->getMessage());
        }
    }

    /**
     * Verifica se o sistema precisa ser instalado
     * 
     * @return bool
     */
    private function needsInstallation(): bool
    {
        try {
            $database = new Database();
            
            // Verifica se a tabela principal (users) existe
            $tableName = $database->prefixTable('users');
            
            if (!$database->tableExists($tableName)) {
                return true;
            }
            
            // Verifica se há pelo menos um usuário
            $sql = "SELECT COUNT(*) as count FROM `{$tableName}`";
            $result = $database->selectOne($sql);
            
            return $result['count'] == 0;
            
        } catch (\Exception $e) {
            // Se não conseguir conectar ou verificar, assume que precisa instalar
            return true;
        }
    }

    /**
     * Verifica se é a primeira instalação (tabelas não existem)
     * 
     * @return bool
     */
    private function isFirstInstall(): bool
    {
        try {
            $database = new Database();
            
            // Lista de tabelas essenciais
            $essentialTables = ['users', 'levels', 'status', 'genders'];
            
            foreach ($essentialTables as $table) {
                $tableName = $database->prefixTable($table);
                if (!$database->tableExists($tableName)) {
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
     * Cria diretórios necessários
     * 
     * @return void
     */
    private function createDirectories(): void
    {
        $directories = [
            ROOT_PATH . '/storage',
            ROOT_PATH . '/storage/cache',
            ROOT_PATH . '/storage/logs',
            ROOT_PATH . '/storage/uploads',
            ROOT_PATH . '/public/uploads',
            ROOT_PATH . '/tmp'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Criar arquivo .gitkeep
            $gitkeepFile = $dir . '/.gitkeep';
            if (!file_exists($gitkeepFile)) {
                touch($gitkeepFile);
            }
        }
    }

    /**
     * Cria arquivo .env se não existir
     * 
     * @return void
     */
    private function createEnvFile(): void
    {
        $envFile = ROOT_PATH . '/.env';
        
        if (!file_exists($envFile)) {
            $exampleFile = ROOT_PATH . '/.env.example';
            
            if (file_exists($exampleFile)) {
                copy($exampleFile, $envFile);
            }
        }
    }

    /**
     * Verifica status da instalação via API
     * 
     * @return void
     */
    public function status(): void
    {
        header('Content-Type: application/json');
        
        try {
            $installMiddleware = new \Core\InstallationMiddleware();
            $status = $installMiddleware->getInstallationStatus();
            
            echo json_encode([
                'success' => true,
                'data' => $status
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Tenta tornar diretório gravável
     * 
     * @param string $path
     * @return bool
     */
    private function makeWritable(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        
        return chmod($path, 0755);
    }
}