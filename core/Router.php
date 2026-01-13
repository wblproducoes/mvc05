<?php

namespace Core;

/**
 * Sistema de roteamento da aplicação
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class Router
{
    /**
     * @var array Rotas registradas
     */
    private array $routes = [];

    /**
     * @var string URI atual
     */
    private string $currentUri;

    /**
     * @var string Método HTTP atual
     */
    private string $currentMethod;

    /**
     * Construtor do roteador
     */
    public function __construct()
    {
        $this->currentUri = $this->getCurrentUri();
        $this->currentMethod = $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Registra uma rota GET
     * 
     * @param string $uri
     * @param string $action
     * @param array $middleware
     * @return void
     */
    public function get(string $uri, string $action, array $middleware = []): void
    {
        $this->addRoute('GET', $uri, $action, $middleware);
    }

    /**
     * Registra uma rota POST
     * 
     * @param string $uri
     * @param string $action
     * @param array $middleware
     * @return void
     */
    public function post(string $uri, string $action, array $middleware = []): void
    {
        $this->addRoute('POST', $uri, $action, $middleware);
    }

    /**
     * Adiciona uma rota ao sistema
     * 
     * @param string $method
     * @param string $uri
     * @param string $action
     * @param array $middleware
     * @return void
     */
    private function addRoute(string $method, string $uri, string $action, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => $middleware
        ];
    }

    /**
     * Processa a rota atual
     * 
     * @return void
     * @throws \Exception
     */
    public function dispatch(): void
    {
        $route = $this->findRoute();
        
        if (!$route) {
            $this->handleNotFound();
            return;
        }

        // Processar middleware
        $this->processMiddleware($route['middleware']);

        // Executar controller
        $this->executeController($route['action'], $route['params'] ?? []);
    }

    /**
     * Encontra a rota correspondente
     * 
     * @return array|null
     */
    private function findRoute(): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $this->currentMethod) {
                continue;
            }

            $params = $this->matchUri($route['uri'], $this->currentUri);
            
            if ($params !== false) {
                $route['params'] = $params;
                return $route;
            }
        }

        return null;
    }

    /**
     * Verifica se a URI corresponde ao padrão
     * 
     * @param string $pattern
     * @param string $uri
     * @return array|false
     */
    private function matchUri(string $pattern, string $uri): array|false
    {
        // Converter padrão para regex
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches); // Remove o match completo
            return $matches;
        }

        return false;
    }

    /**
     * Processa middleware da rota
     * 
     * @param array $middleware
     * @return void
     * @throws \Exception
     */
    private function processMiddleware(array $middleware): void
    {
        foreach ($middleware as $middlewareName) {
            switch ($middlewareName) {
                case 'auth':
                    if (!Auth::check()) {
                        header('Location: /login');
                        exit;
                    }
                    break;
            }
        }
    }

    /**
     * Executa o controller
     * 
     * @param string $action
     * @param array $params
     * @return void
     * @throws \Exception
     */
    private function executeController(string $action, array $params = []): void
    {
        [$controllerName, $methodName] = explode('@', $action);
        
        $controllerClass = "App\\Controllers\\{$controllerName}";
        
        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller {$controllerClass} não encontrado");
        }

        $controller = new $controllerClass();
        
        if (!method_exists($controller, $methodName)) {
            throw new \Exception("Método {$methodName} não encontrado no controller {$controllerClass}");
        }

        call_user_func_array([$controller, $methodName], $params);
    }

    /**
     * Manipula rota não encontrada
     * 
     * @return void
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        echo "Página não encontrada";
    }

    /**
     * Obtém a URI atual
     * 
     * @return string
     */
    private function getCurrentUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'];
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        return $uri;
    }
}