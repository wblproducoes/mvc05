<?php

namespace Core;

/**
 * Handler de logs para arquivos
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.5.0
 */
class FileLogHandler implements LogHandlerInterface
{
    /**
     * @var string Diretório de logs
     */
    private string $logDirectory;

    /**
     * @var array Configurações
     */
    private array $config;

    /**
     * Construtor
     */
    public function __construct(string $logDirectory, array $config = [])
    {
        $this->logDirectory = $logDirectory;
        $this->config = $config;
    }

    /**
     * Processa entrada de log
     */
    public function handle(array $logEntry): void
    {
        $logFile = $this->getLogFile($logEntry['level']);
        $logLine = json_encode($logEntry) . "\n";
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Obtém arquivo de log baseado no nível
     */
    private function getLogFile(string $level): string
    {
        $date = date('Y-m-d');
        return $this->logDirectory . '/' . strtolower($level) . '_' . $date . '.log';
    }
}