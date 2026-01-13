<?php

namespace Core;

/**
 * Middleware de segurança avançado
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.4.3
 */
class SecurityMiddleware
{
    /**
     * @var array Rotas que não precisam de autenticação
     */
    private array $publicRoutes = [
        '/login',
        '/install',
        '/install/status',
        '/forgot-password',
        '/reset-password',
        '/api/public'
    ];

    /**
     * @var array Extensões de arquivos permitidas para upload
     */
    private array $allowedUploadExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'
    ];

    /**
     * Executa middleware de segurança
     */
    public function handle(string $path, string $method): bool
    {
        // Inicializar segurança
        Security::init();

        // Verificar IP bloqueado
        if ($this->isIpBlocked()) {
            $this->handleBlockedIp();
            return false;
        }

        // Verificar rate limiting global
        if (!$this->checkGlobalRateLimit()) {
            $this->handleRateLimitExceeded();
            return false;
        }

        // Verificar CSRF para métodos que modificam dados
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            if (!$this->validateCsrfToken()) {
                $this->handleCsrfViolation();
                return false;
            }
        }

        // Verificar autenticação para rotas protegidas
        if (!$this->isPublicRoute($path)) {
            if (!$this->isAuthenticated()) {
                $this->handleUnauthenticated();
                return false;
            }

            // Verificar autorização
            if (!$this->isAuthorized($path, $method)) {
                $this->handleUnauthorized();
                return false;
            }
        }

        // Validar uploads se houver
        if (!empty($_FILES)) {
            if (!$this->validateUploads()) {
                $this->handleInvalidUpload();
                return false;
            }
        }

        // Sanitizar dados de entrada
        $this->sanitizeInputData();

        // Log da requisição
        $this->logRequest($path, $method);

        return true;
    }

    /**
     * Verifica se IP está bloqueado
     */
    private function isIpBlocked(): bool
    {
        return Security::isIpBlocked(Security::getClientIp());
    }

    /**
     * Verifica rate limiting global
     */
    private function checkGlobalRateLimit(): bool
    {
        $ip = Security::getClientIp();
        return Security::rateLimitCheck("global_{$ip}", 100, 300); // 100 requests per 5 minutes
    }

    /**
     * Valida token CSRF
     */
    private function validateCsrfToken(): bool
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (empty($token)) {
            Security::logSecurityEvent('csrf_token_missing');
            return false;
        }

        return Security::validateCsrfToken($token);
    }

    /**
     * Verifica se é rota pública
     */
    private function isPublicRoute(string $path): bool
    {
        foreach ($this->publicRoutes as $route) {
            if (strpos($path, $route) === 0) {
                return true;
            }
        }

        // Verificar arquivos estáticos
        $staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf'];
        foreach ($staticExtensions as $ext) {
            if (str_ends_with(strtolower($path), $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se usuário está autenticado
     */
    private function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Verifica autorização do usuário
     */
    private function isAuthorized(string $path, string $method): bool
    {
        // Implementar lógica de autorização baseada em roles/permissions
        $userLevel = $_SESSION['user_level'] ?? 11; // Default: user
        
        // Rotas administrativas
        $adminRoutes = ['/admin', '/users', '/settings', '/logs'];
        foreach ($adminRoutes as $route) {
            if (strpos($path, $route) === 0) {
                return $userLevel <= 2; // Master ou Admin
            }
        }

        // Rotas de direção
        $directionRoutes = ['/reports', '/school'];
        foreach ($directionRoutes as $route) {
            if (strpos($path, $route) === 0) {
                return $userLevel <= 3; // Master, Admin ou Direção
            }
        }

        return true; // Outras rotas são permitidas para usuários autenticados
    }

    /**
     * Valida uploads de arquivos
     */
    private function validateUploads(): bool
    {
        foreach ($_FILES as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $validation = Security::validateFileUpload($file, [], 10485760); // 10MB max
            if (!$validation['valid']) {
                Security::logSecurityEvent('invalid_file_upload', [
                    'filename' => $file['name'],
                    'errors' => $validation['errors']
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitiza dados de entrada
     */
    private function sanitizeInputData(): void
    {
        // Sanitizar $_POST
        if (!empty($_POST)) {
            array_walk_recursive($_POST, function(&$value) {
                if (is_string($value)) {
                    $value = Security::sanitizeInput($value);
                }
            });
        }

        // Sanitizar $_GET
        if (!empty($_GET)) {
            array_walk_recursive($_GET, function(&$value) {
                if (is_string($value)) {
                    $value = Security::sanitizeInput($value);
                }
            });
        }
    }

    /**
     * Log da requisição
     */
    private function logRequest(string $path, string $method): void
    {
        Security::logSecurityEvent('request', [
            'path' => $path,
            'method' => $method,
            'user_id' => $_SESSION['user_id'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null
        ]);
    }

    /**
     * Manipula IP bloqueado
     */
    private function handleBlockedIp(): void
    {
        http_response_code(429);
        Security::logSecurityEvent('blocked_ip_access_attempt');
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'IP temporariamente bloqueado']);
        } else {
            echo '<h1>Acesso Negado</h1><p>Seu IP foi temporariamente bloqueado devido a atividade suspeita.</p>';
        }
        exit;
    }

    /**
     * Manipula rate limit excedido
     */
    private function handleRateLimitExceeded(): void
    {
        http_response_code(429);
        Security::logSecurityEvent('rate_limit_exceeded');
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Muitas requisições. Tente novamente em alguns minutos.']);
        } else {
            echo '<h1>Muitas Requisições</h1><p>Você fez muitas requisições. Aguarde alguns minutos.</p>';
        }
        exit;
    }

    /**
     * Manipula violação CSRF
     */
    private function handleCsrfViolation(): void
    {
        http_response_code(403);
        Security::logSecurityEvent('csrf_violation');
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Token de segurança inválido']);
        } else {
            echo '<h1>Erro de Segurança</h1><p>Token de segurança inválido. Recarregue a página.</p>';
        }
        exit;
    }

    /**
     * Manipula usuário não autenticado
     */
    private function handleUnauthenticated(): void
    {
        Security::logSecurityEvent('unauthenticated_access_attempt');
        
        if ($this->isAjaxRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Não autenticado', 'redirect' => '/login']);
        } else {
            header('Location: /login');
        }
        exit;
    }

    /**
     * Manipula usuário não autorizado
     */
    private function handleUnauthorized(): void
    {
        http_response_code(403);
        Security::logSecurityEvent('unauthorized_access_attempt');
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Acesso negado']);
        } else {
            echo '<h1>Acesso Negado</h1><p>Você não tem permissão para acessar esta página.</p>';
        }
        exit;
    }

    /**
     * Manipula upload inválido
     */
    private function handleInvalidUpload(): void
    {
        http_response_code(400);
        Security::logSecurityEvent('invalid_upload_attempt');
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Arquivo inválido']);
        } else {
            echo '<h1>Arquivo Inválido</h1><p>O arquivo enviado não é permitido.</p>';
        }
        exit;
    }

    /**
     * Verifica se é requisição AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Adiciona rota pública
     */
    public function addPublicRoute(string $route): void
    {
        $this->publicRoutes[] = $route;
    }

    /**
     * Remove rota pública
     */
    public function removePublicRoute(string $route): void
    {
        $key = array_search($route, $this->publicRoutes);
        if ($key !== false) {
            unset($this->publicRoutes[$key]);
        }
    }

    /**
     * Obtém rotas públicas
     */
    public function getPublicRoutes(): array
    {
        return $this->publicRoutes;
    }
}