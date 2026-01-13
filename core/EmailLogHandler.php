<?php

namespace Core;

/**
 * Handler de logs para email
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.5.0
 */
class EmailLogHandler implements LogHandlerInterface
{
    /**
     * @var string Email de destino
     */
    private string $emailTo;

    /**
     * @var array Configurações
     */
    private array $config;

    /**
     * @var array Níveis que devem ser enviados por email
     */
    private array $alertLevels = [
        Logger::EMERGENCY,
        Logger::ALERT,
        Logger::CRITICAL,
        Logger::ERROR
    ];

    /**
     * Construtor
     */
    public function __construct(string $emailTo, array $config = [])
    {
        $this->emailTo = $emailTo;
        $this->config = array_merge([
            'subject_prefix' => '[SISTEMA] ',
            'max_emails_per_hour' => 10
        ], $config);
    }

    /**
     * Processa entrada de log
     */
    public function handle(array $logEntry): void
    {
        // Só enviar email para níveis críticos
        if (!in_array(strtolower($logEntry['level']), $this->alertLevels)) {
            return;
        }

        // Verificar rate limiting
        if (!$this->canSendEmail()) {
            return;
        }

        $subject = $this->config['subject_prefix'] . 'Alerta de Log - ' . $logEntry['level'];
        $message = $this->formatEmailMessage($logEntry);
        
        mail($this->emailTo, $subject, $message);
        $this->recordEmailSent();
    }

    /**
     * Formata mensagem de email
     */
    private function formatEmailMessage(array $logEntry): string
    {
        $message = "ALERTA DO SISTEMA DE LOGS\n";
        $message .= str_repeat("=", 40) . "\n\n";
        $message .= "Nível: " . $logEntry['level'] . "\n";
        $message .= "Timestamp: " . $logEntry['timestamp'] . "\n";
        $message .= "Mensagem: " . $logEntry['message'] . "\n\n";
        
        if (!empty($logEntry['context'])) {
            $message .= "Contexto:\n";
            foreach ($logEntry['context'] as $key => $value) {
                $message .= "  {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
        }
        
        if (!empty($logEntry['exception'])) {
            $message .= "\nExceção:\n";
            $message .= "  Classe: " . $logEntry['exception']['class'] . "\n";
            $message .= "  Mensagem: " . $logEntry['exception']['message'] . "\n";
            $message .= "  Arquivo: " . $logEntry['exception']['file'] . ":" . $logEntry['exception']['line'] . "\n";
        }
        
        $message .= "\n---\n";
        $message .= "Sistema Administrativo MVC\n";
        
        return $message;
    }

    /**
     * Verifica se pode enviar email (rate limiting)
     */
    private function canSendEmail(): bool
    {
        $cacheFile = STORAGE_PATH . '/logs/email_rate_limit.json';
        
        if (!file_exists($cacheFile)) {
            return true;
        }
        
        $data = json_decode(file_get_contents($cacheFile), true);
        $currentHour = date('Y-m-d H');
        
        if (!isset($data[$currentHour])) {
            return true;
        }
        
        return $data[$currentHour] < $this->config['max_emails_per_hour'];
    }

    /**
     * Registra email enviado
     */
    private function recordEmailSent(): void
    {
        $cacheFile = STORAGE_PATH . '/logs/email_rate_limit.json';
        $currentHour = date('Y-m-d H');
        
        $data = [];
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        
        $data[$currentHour] = ($data[$currentHour] ?? 0) + 1;
        
        // Limpar dados antigos (mais de 24 horas)
        $cutoff = date('Y-m-d H', strtotime('-24 hours'));
        foreach ($data as $hour => $count) {
            if ($hour < $cutoff) {
                unset($data[$hour]);
            }
        }
        
        file_put_contents($cacheFile, json_encode($data));
    }
}