<?php

namespace Core;

/**
 * Sistema de logs avançado
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.5.0
 */
class Logger
{
    /**
     * Níveis de log (PSR-3 compliant)
     */
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    /**
     * @var array Configurações do logger
     */
    private array $config;

    /**
     * @var array Handlers de log
     */
    private array $handlers = [];

    /**
     * @var array Context global
     */
    private array $globalContext = [];

    /**
     * @var string Diretório de logs
     */
    private string $logDirectory;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->logDirectory = STORAGE_PATH . '/logs';
        $this->ensureLogDirectory();
        
        $this->config = [
            'default_level' => $_ENV['LOG_LEVEL'] ?? self::INFO,
            'max_file_size' => (int)($_ENV['LOG_MAX_FILE_SIZE'] ?? 10485760),
            'max_files' => (int)($_ENV['LOG_MAX_FILES'] ?? 30),
            'date_format' => $_ENV['LOG_DATE_FORMAT'] ?? 'Y-m-d H:i:s',
            'include_context' => filter_var($_ENV['LOG_INCLUDE_CONTEXT'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'include_trace' => filter_var($_ENV['LOG_INCLUDE_TRACE'] ?? 'false', FILTER_VALIDATE_BOOLEAN)
        ];

        $this->initializeHandlers();
        $this->setGlobalContext();
    }

    /**
     * Inicializa handlers de log
     */
    private function initializeHandlers(): void
    {
        // Handler para arquivo
        $this->handlers['file'] = new FileLogHandler($this->logDirectory, $this->config);
        
        // Handler para banco de dados
        $this->handlers['database'] = new DatabaseLogHandler($this->config);
        
        // Handler para email (alertas críticos)
        if (!empty($_ENV['LOG_EMAIL_ALERTS'])) {
            $this->handlers['email'] = new EmailLogHandler($_ENV['LOG_EMAIL_ALERTS'], $this->config);
        }
    }

    /**
     * Define contexto global
     */
    private function setGlobalContext(): void
    {
        $this->globalContext = [
            'server' => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'php_version' => PHP_VERSION,
            'user_id' => $_SESSION['user_id'] ?? null,
            'session_id' => session_id(),
            'ip' => Security::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI'
        ];
    }

    /**
     * Log de emergência
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Log de alerta
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * Log crítico
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Log de erro
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log de aviso
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log de notificação
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Log informativo
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log de debug
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log principal
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $logEntry = $this->createLogEntry($level, $message, $context);
        $this->writeLog($logEntry);
    }

    /**
     * Verifica se deve fazer log do nível
     */
    private function shouldLog(string $level): bool
    {
        $levels = [
            self::DEBUG => 0,
            self::INFO => 1,
            self::NOTICE => 2,
            self::WARNING => 3,
            self::ERROR => 4,
            self::CRITICAL => 5,
            self::ALERT => 6,
            self::EMERGENCY => 7
        ];

        $currentLevel = $levels[$this->config['default_level']] ?? 1;
        $messageLevel = $levels[$level] ?? 0;

        return $messageLevel >= $currentLevel;
    }

    /**
     * Cria entrada de log
     */
    private function createLogEntry(string $level, string $message, array $context): array
    {
        $entry = [
            'timestamp' => date($this->config['date_format']),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => array_merge($this->globalContext, $context),
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        ];

        // Adicionar informações de exceção se presente no contexto
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $entry['exception'] = [
                'class' => get_class($context['exception']),
                'message' => $context['exception']->getMessage(),
                'file' => $context['exception']->getFile(),
                'line' => $context['exception']->getLine(),
                'trace' => $context['exception']->getTraceAsString()
            ];
        }

        return $entry;
    }

    /**
     * Escreve log usando handlers
     */
    private function writeLog(array $logEntry): void
    {
        foreach ($this->handlers as $handler) {
            try {
                $handler->handle($logEntry);
            } catch (\Throwable $e) {
                error_log("Logger handler failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Garante que diretório de logs existe
     */
    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }

        $htaccessFile = $this->logDirectory . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Deny from all\n");
        }
    }

    /**
     * Instância singleton
     */
    private static ?Logger $instance = null;

    /**
     * Obtém instância singleton
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Métodos estáticos para conveniência
     */
    public static function logEmergency(string $message, array $context = []): void
    {
        self::getInstance()->emergency($message, $context);
    }

    public static function logAlert(string $message, array $context = []): void
    {
        self::getInstance()->alert($message, $context);
    }

    public static function logCritical(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }

    public static function logError(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    public static function logWarning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    public static function logNotice(string $message, array $context = []): void
    {
        self::getInstance()->notice($message, $context);
    }

    public static function logInfo(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    public static function logDebug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }
}