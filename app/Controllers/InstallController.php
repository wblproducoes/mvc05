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
        // Verificar se já está instalado
        if (file_exists(ROOT_PATH . '/.installed')) {
            $this->redirect('/');
        }

        $this->render('install/index.twig', [
            'requirements' => $this->checkRequirements(),
            'database_config' => $this->getDatabaseConfig()
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

        // Verificar se já está instalado
        if (file_exists(ROOT_PATH . '/.installed')) {
            $this->redirect('/');
        }

        // Validar senha de instalação
        $installPassword = $_ENV['INSTALL_PASSWORD'] ?? 'admin123';
        if ($this->post('install_password') !== $installPassword) {
            $this->flash('error', 'Senha de instalação incorreta');
            $this->redirect('/install');
        }

        // Validar dados
        $validation = $this->validate($_POST, [
            'admin_name' => 'required|min:2|max:100',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:8',
            'admin_password_confirmation' => 'required|confirmed'
        ]);

        if (!$validation['valid']) {
            $this->render('install/index.twig', [
                'errors' => $validation['errors'],
                'old' => $_POST,
                'requirements' => $this->checkRequirements(),
                'database_config' => $this->getDatabaseConfig()
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
                'database_config' => $this->getDatabaseConfig()
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
                'database_config' => $this->getDatabaseConfig()
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
                $this->createSchemaFile();
            }

            if (!$database->executeSqlFile($sqlFile)) {
                throw new \Exception('Erro ao criar tabelas do banco de dados');
            }

        } catch (\Exception $e) {
            throw new \Exception('Erro na conexão com o banco de dados: ' . $e->getMessage());
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
            'role' => 'admin',
            'active' => true
        ];

        $userId = $userModel->create($adminData);
        
        if (!$userId) {
            throw new \Exception('Erro ao criar usuário administrador');
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

    /**
     * Cria arquivo de schema SQL
     * 
     * @return void
     */
    private function createSchemaFile(): void
    {
        $schemaDir = ROOT_PATH . '/database';
        
        if (!is_dir($schemaDir)) {
            mkdir($schemaDir, 0755, true);
        }

        $sql = $this->getSchemaSql();
        file_put_contents($schemaDir . '/schema.sql', $sql);
    }

    /**
     * Obtém SQL do schema
     * 
     * @return string
     */
    private function getSchemaSql(): string
    {
        return "
-- Tabela de usuários
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL UNIQUE,
    `password` varchar(255) NOT NULL,
    `role` enum('admin','user') NOT NULL DEFAULT 'user',
    `active` tinyint(1) NOT NULL DEFAULT 1,
    `avatar` varchar(255) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `remember_token` varchar(255) DEFAULT NULL,
    `last_login` datetime DEFAULT NULL,
    `login_count` int(11) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_role` (`role`),
    KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de atividades (opcional)
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de configurações do sistema (opcional)
CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `key` varchar(100) NOT NULL UNIQUE,
    `value` text DEFAULT NULL,
    `type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
    `description` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
    }
}