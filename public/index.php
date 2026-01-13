<?php
/**
 * Sistema Administrativo MVC
 * Entry point da aplicação
 * 
 * @author Sistema Administrativo
 * @version 1.4.2
 */

// Definir constantes do sistema
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CORE_PATH', ROOT_PATH . '/core');
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Autoload do Composer
require_once ROOT_PATH . '/vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv->load();
}

// Configurar timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');

// Configurar exibição de erros baseado no ambiente
if ($_ENV['APP_DEBUG'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Iniciar sessão
session_start();

// Aplicar middleware de segurança
try {
    $securityMiddleware = new Core\SecurityMiddleware();
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    if (!$securityMiddleware->handle($currentPath, $method)) {
        // Middleware bloqueou a requisição
        exit;
    }
} catch (Exception $e) {
    error_log("Erro no middleware de segurança: " . $e->getMessage());
    
    // Em caso de erro no middleware, continua mas loga o problema
    if ($_ENV['APP_DEBUG'] ?? false) {
        echo "Erro de segurança: " . $e->getMessage();
        exit;
    }
}

// Verificar se o sistema precisa ser instalado
try {
    $installMiddleware = new Core\InstallationMiddleware();
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    if ($installMiddleware->handle($currentPath)) {
        header('Location: ' . $installMiddleware->getInstallUrl());
        exit;
    }
} catch (Exception $e) {
    // Se houver erro na verificação, redireciona para instalação
    if (!str_contains($_SERVER['REQUEST_URI'], '/install')) {
        header('Location: /install');
        exit;
    }
}

try {
    // Inicializar a aplicação
    $app = new Core\Application();
    $app->run();
} catch (Exception $e) {
    // Log do erro
    error_log($e->getMessage());
    
    // Exibir página de erro amigável
    if ($_ENV['APP_DEBUG'] ?? false) {
        echo '<h1>Erro na Aplicação</h1>';
        echo '<p>' . $e->getMessage() . '</p>';
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
    } else {
        echo '<h1>Erro Interno do Servidor</h1>';
        echo '<p>Ocorreu um erro inesperado. Tente novamente mais tarde.</p>';
    }
}