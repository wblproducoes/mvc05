<?php

namespace Core;

/**
 * Handler de logs para banco de dados
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.5.0
 */
class DatabaseLogHandler implements LogHandlerInterface
{
    /**
     * @var Database Instância do banco
     */
    private Database $database;

    /**
     * @var array Configurações
     */
    private array $config;

    /**
     * Construtor
     */
    public function __construct(array $config = [])
    {
        $this->database = new Database();
        $this->config = $config;
    }

    /**
     * Processa entrada de log
     */
    public function handle(array $logEntry): void
    {
        try {
            $sql = "INSERT INTO {prefix}system_logs 
                    (level, message, context, created_at) 
                    VALUES (:level, :message, :context, :created_at)";

            $processedSql = $this->database->processSqlWithPrefix($sql);
            
            $this->database->insert($processedSql, [
                'level' => $logEntry['level'],
                'message' => $logEntry['message'],
                'context' => json_encode($logEntry['context']),
                'created_at' => $logEntry['timestamp']
            ]);

        } catch (\Exception $e) {
            // Falha silenciosa para evitar loops
        }
    }
}