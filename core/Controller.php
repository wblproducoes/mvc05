<?php

namespace Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Classe base para todos os controllers
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.0.0
 */
abstract class Controller
{
    /**
     * @var Environment Instância do Twig
     */
    protected Environment $twig;

    /**
     * @var Database Instância do banco de dados
     */
    protected Database $database;

    /**
     * Construtor do controller base
     */
    public function __construct()
    {
        $this->initializeTwig();
        $this->initializeDatabase();
    }

    /**
     * Inicializa o Twig
     * 
     * @return void
     */
    private function initializeTwig(): void
    {
        $loader = new FilesystemLoader(ROOT_PATH . '/app/Views');
        
        $this->twig = new Environment($loader, [
            'cache' => ($_ENV['APP_ENV'] === 'production') ? ROOT_PATH . '/storage/cache' : false,
            'debug' => $_ENV['APP_DEBUG'] ?? false,
            'auto_reload' => true
        ]);

        // Adicionar variáveis globais
        $this->twig->addGlobal('app_name', $_ENV['APP_NAME'] ?? 'Sistema Administrativo');
        $this->twig->addGlobal('app_url', $_ENV['APP_URL'] ?? 'http://localhost');
        $this->twig->addGlobal('csrf_token', Security::generateCsrfToken());
        $this->twig->addGlobal('user', Auth::user());
        $this->twig->addGlobal('flash_messages', $this->getFlashMessages());
    }

    /**
     * Inicializa o banco de dados
     * 
     * @return void
     */
    private function initializeDatabase(): void
    {
        $this->database = new Database();
    }

    /**
     * Renderiza uma view
     * 
     * @param string $template
     * @param array $data
     * @return void
     */
    protected function render(string $template, array $data = []): void
    {
        try {
            echo $this->twig->render($template, $data);
        } catch (\Exception $e) {
            $this->handleViewError($e);
        }
    }

    /**
     * Redireciona para uma URL
     * 
     * @param string $url
     * @param int $statusCode
     * @return void
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    /**
     * Retorna resposta JSON
     * 
     * @param array $data
     * @param int $statusCode
     * @return void
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Valida dados de entrada
     * 
     * @param array $data
     * @param array $rules
     * @return array
     */
    protected function validate(array $data, array $rules): array
    {
        $validator = new Validator();
        return $validator->validate($data, $rules);
    }

    /**
     * Adiciona mensagem flash
     * 
     * @param string $type
     * @param string $message
     * @return void
     */
    protected function flash(string $type, string $message): void
    {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }

        $_SESSION['flash_messages'][] = [
            'type' => $type,
            'message' => $message
        ];
    }

    /**
     * Obtém mensagens flash
     * 
     * @return array
     */
    private function getFlashMessages(): array
    {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        
        return $messages;
    }

    /**
     * Manipula erros de view
     * 
     * @param \Exception $e
     * @return void
     */
    private function handleViewError(\Exception $e): void
    {
        error_log("Erro na view: " . $e->getMessage());
        
        if ($_ENV['APP_DEBUG'] ?? false) {
            echo "<h1>Erro na View</h1>";
            echo "<p>" . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        } else {
            echo "<h1>Erro na Página</h1>";
            echo "<p>Ocorreu um erro ao carregar a página.</p>";
        }
    }

    /**
     * Verifica se a requisição é POST
     * 
     * @return bool
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Verifica se a requisição é GET
     * 
     * @return bool
     */
    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    /**
     * Obtém dados POST
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }

        return $_POST[$key] ?? $default;
    }

    /**
     * Obtém dados GET
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }

        return $_GET[$key] ?? $default;
    }
}