<?php
/**
 * Sistema Administrativo MVC
 * Entry point da aplicação
 * 
 * @author Sistema Administrativo
 * @version 1.0.0
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
date_default_timezone_set('America/Sao_Paulo');

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

// Verificar se o sistema está instalado
if (!file_exists(ROOT_PATH . '/.installed') && !str_contains($_SERVER['REQUEST_URI'], '/install')) {
    header('Location: /install');
    exit;
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