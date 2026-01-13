<?php

namespace Core;

/**
 * Classe principal da aplicação
 * Responsável por inicializar e gerenciar o sistema MVC
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class Application
{
    /**
     * @var Router Instância do roteador
     */
    private Router $router;

    /**
     * @var Database Instância do banco de dados
     */
    private Database $database;

    /**
     * Construtor da aplicação
     */
    public function __construct()
    {
        $this->initializeDatabase();
        $this->initializeRouter();
    }

    /**
     * Executa a aplicação
     * 
     * @return void
     */
    public function run(): void
    {
        try {
            // Processar middleware de autenticação
            $this->processMiddleware();
            
            // Processar rota
            $this->router->dispatch();
            
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Inicializa a conexão com o banco de dados
     * 
     * @return void
     */
    private function initializeDatabase(): void
    {
        $this->database = new Database();
    }

    /**
     * Inicializa o roteador
     * 
     * @return void
     */
    private function initializeRouter(): void
    {
        $this->router = new Router();
        $this->loadRoutes();
    }

    /**
     * Carrega as rotas da aplicação
     * 
     * @return void
     */
    private function loadRoutes(): void
    {
        // Rotas de autenticação
        $this->router->get('/login', 'AuthController@showLogin');
        $this->router->post('/login', 'AuthController@login');
        $this->router->get('/logout', 'AuthController@logout');
        
        // Rotas do dashboard (protegidas)
        $this->router->get('/', 'DashboardController@index', ['auth']);
        $this->router->get('/dashboard', 'DashboardController@index', ['auth']);
        
        // Rotas de usuários (protegidas)
        $this->router->get('/users', 'UserController@index', ['auth']);
        $this->router->get('/users/create', 'UserController@create', ['auth']);
        $this->router->post('/users/store', 'UserController@store', ['auth']);
        $this->router->get('/users/{id}/edit', 'UserController@edit', ['auth']);
        $this->router->post('/users/{id}/update', 'UserController@update', ['auth']);
        $this->router->post('/users/{id}/delete', 'UserController@delete', ['auth']);
        
        // Rotas de relatórios (protegidas)
        $this->router->get('/reports', 'ReportController@index', ['auth']);
        $this->router->get('/reports/pdf', 'ReportController@generatePdf', ['auth']);
        
        // Rotas de instalação
        $this->router->get('/install', 'InstallController@index');
        $this->router->post('/install', 'InstallController@install');
    }

    /**
     * Processa middleware da aplicação
     * 
     * @return void
     */
    private function processMiddleware(): void
    {
        // Middleware CSRF
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrfToken();
        }
    }

    /**
     * Valida token CSRF
     * 
     * @return void
     * @throws \Exception
     */
    private function validateCsrfToken(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        
        if (!Security::validateCsrfToken($token)) {
            throw new \Exception('Token CSRF inválido');
        }
    }

    /**
     * Manipula exceções da aplicação
     * 
     * @param \Exception $e
     * @return void
     */
    private function handleException(\Exception $e): void
    {
        // Log do erro
        error_log($e->getMessage());
        
        // Redirecionar para página de erro
        http_response_code(500);
        
        if ($_ENV['APP_DEBUG'] ?? false) {
            echo $e->getMessage();
        } else {
            echo 'Erro interno do servidor';
        }
    }

    /**
     * Obtém instância do banco de dados
     * 
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }
}