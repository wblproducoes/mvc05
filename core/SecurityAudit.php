<?php

namespace Core;

/**
 * Sistema de auditoria e monitoramento de segurança
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.4.3
 */
class SecurityAudit
{
    /**
     * @var Database Instância do banco de dados
     */
    private Database $database;

    /**
     * @var array Configurações de auditoria
     */
    private array $config;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->database = new Database();
        $this->config = [
            'log_retention_days' => (int)($_ENV['AUDIT_LOG_RETENTION_DAYS'] ?? 90),
            'alert_threshold_failed_logins' => (int)($_ENV['ALERT_THRESHOLD_FAILED_LOGINS'] ?? 10),
            'alert_threshold_blocked_ips' => (int)($_ENV['ALERT_THRESHOLD_BLOCKED_IPS'] ?? 5),
            'monitor_file_changes' => filter_var($_ENV['MONITOR_FILE_CHANGES'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'email_alerts' => filter_var($_ENV['SECURITY_EMAIL_ALERTS'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'alert_email' => $_ENV['SECURITY_ALERT_EMAIL'] ?? 'admin@localhost'
        ];
    }

    /**
     * Analisa logs de segurança
     */
    public function analyzeLogs(int $hours = 24): array
    {
        $logFiles = $this->getLogFiles($hours);
        $analysis = [
            'total_events' => 0,
            'failed_logins' => 0,
            'blocked_ips' => [],
            'csrf_violations' => 0,
            'suspicious_activities' => [],
            'top_ips' => [],
            'attack_patterns' => [],
            'recommendations' => []
        ];

        foreach ($logFiles as $file) {
            $events = $this->parseLogFile($file);
            $analysis = $this->analyzeEvents($events, $analysis);
        }

        // Gerar recomendações
        $analysis['recommendations'] = $this->generateRecommendations($analysis);

        // Verificar se precisa enviar alertas
        $this->checkAlerts($analysis);

        return $analysis;
    }

    /**
     * Obtém arquivos de log do período
     */
    private function getLogFiles(int $hours): array
    {
        $files = [];
        $logDir = STORAGE_PATH . '/logs';
        
        if (!is_dir($logDir)) {
            return $files;
        }

        $startDate = new \DateTime("-{$hours} hours");
        
        for ($i = 0; $i <= $hours / 24; $i++) {
            $date = clone $startDate;
            $date->add(new \DateInterval("P{$i}D"));
            $filename = $logDir . '/security_' . $date->format('Y-m-d') . '.log';
            
            if (file_exists($filename)) {
                $files[] = $filename;
            }
        }

        return $files;
    }

    /**
     * Analisa arquivo de log
     */
    private function parseLogFile(string $filename): array
    {
        $events = [];
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $event = json_decode($line, true);
            if ($event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Analisa eventos de segurança
     */
    private function analyzeEvents(array $events, array $analysis): array
    {
        foreach ($events as $event) {
            $analysis['total_events']++;

            switch ($event['event']) {
                case 'login_attempt':
                    if (isset($event['data']['success']) && !$event['data']['success']) {
                        $analysis['failed_logins']++;
                        $this->trackSuspiciousIp($event['ip'], $analysis);
                    }
                    break;

                case 'ip_blocked_rate_limit':
                    if (!in_array($event['ip'], $analysis['blocked_ips'])) {
                        $analysis['blocked_ips'][] = $event['ip'];
                    }
                    break;

                case 'csrf_violation':
                    $analysis['csrf_violations']++;
                    $this->trackSuspiciousActivity($event, $analysis);
                    break;

                case 'session_hijack_attempt':
                    $this->trackSuspiciousActivity($event, $analysis);
                    break;

                case 'invalid_file_upload':
                    $this->trackSuspiciousActivity($event, $analysis);
                    break;

                case 'sql_injection_attempt':
                    $this->trackSuspiciousActivity($event, $analysis);
                    break;
            }

            // Contar IPs mais ativos
            $ip = $event['ip'];
            if (!isset($analysis['top_ips'][$ip])) {
                $analysis['top_ips'][$ip] = 0;
            }
            $analysis['top_ips'][$ip]++;
        }

        // Ordenar IPs por atividade
        arsort($analysis['top_ips']);
        $analysis['top_ips'] = array_slice($analysis['top_ips'], 0, 10, true);

        return $analysis;
    }

    /**
     * Rastreia IP suspeito
     */
    private function trackSuspiciousIp(string $ip, array &$analysis): void
    {
        if (!isset($analysis['suspicious_ips'][$ip])) {
            $analysis['suspicious_ips'][$ip] = 0;
        }
        $analysis['suspicious_ips'][$ip]++;
    }

    /**
     * Rastreia atividade suspeita
     */
    private function trackSuspiciousActivity(array $event, array &$analysis): void
    {
        $activity = [
            'timestamp' => $event['timestamp'],
            'event' => $event['event'],
            'ip' => $event['ip'],
            'user_agent' => $event['user_agent'] ?? '',
            'data' => $event['data'] ?? []
        ];

        $analysis['suspicious_activities'][] = $activity;
    }

    /**
     * Gera recomendações de segurança
     */
    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];

        // Muitas tentativas de login falharam
        if ($analysis['failed_logins'] > $this->config['alert_threshold_failed_logins']) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Muitas tentativas de login falharam',
                'description' => "Foram detectadas {$analysis['failed_logins']} tentativas de login falharam nas últimas 24 horas.",
                'action' => 'Considere implementar CAPTCHA ou aumentar o tempo de bloqueio.'
            ];
        }

        // Muitos IPs bloqueados
        if (count($analysis['blocked_ips']) > $this->config['alert_threshold_blocked_ips']) {
            $recommendations[] = [
                'type' => 'danger',
                'title' => 'Muitos IPs bloqueados',
                'description' => count($analysis['blocked_ips']) . ' IPs foram bloqueados por atividade suspeita.',
                'action' => 'Verifique se há um ataque coordenado em andamento.'
            ];
        }

        // Violações CSRF
        if ($analysis['csrf_violations'] > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Violações de token CSRF detectadas',
                'description' => "{$analysis['csrf_violations']} tentativas de violação CSRF foram detectadas.",
                'action' => 'Verifique se há tentativas de ataques CSRF.'
            ];
        }

        // Atividades suspeitas
        if (count($analysis['suspicious_activities']) > 5) {
            $recommendations[] = [
                'type' => 'danger',
                'title' => 'Múltiplas atividades suspeitas',
                'description' => count($analysis['suspicious_activities']) . ' atividades suspeitas foram detectadas.',
                'action' => 'Revise os logs detalhadamente e considere medidas adicionais de segurança.'
            ];
        }

        // Verificar senhas fracas
        $weakPasswords = $this->checkWeakPasswords();
        if ($weakPasswords > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Usuários com senhas fracas',
                'description' => "{$weakPasswords} usuários possuem senhas que não atendem aos critérios de segurança.",
                'action' => 'Force a alteração de senhas fracas.'
            ];
        }

        // Verificar usuários inativos
        $inactiveUsers = $this->checkInactiveUsers();
        if ($inactiveUsers > 0) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'Usuários inativos',
                'description' => "{$inactiveUsers} usuários não fazem login há mais de 90 dias.",
                'action' => 'Considere desativar contas inativas.'
            ];
        }

        return $recommendations;
    }

    /**
     * Verifica senhas fracas
     */
    private function checkWeakPasswords(): int
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {prefix}users 
                    WHERE deleted_at IS NULL 
                    AND (LENGTH(password) < 60 OR password LIKE '%password%')";
            
            $processedSql = $this->database->processSqlWithPrefix($sql);
            $result = $this->database->selectOne($processedSql);
            
            return $result['count'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Verifica usuários inativos
     */
    private function checkInactiveUsers(): int
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {prefix}users 
                    WHERE deleted_at IS NULL 
                    AND (last_access IS NULL OR last_access < DATE_SUB(NOW(), INTERVAL 90 DAY))";
            
            $processedSql = $this->database->processSqlWithPrefix($sql);
            $result = $this->database->selectOne($processedSql);
            
            return $result['count'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Verifica alertas e envia notificações
     */
    private function checkAlerts(array $analysis): void
    {
        $alerts = [];

        // Alerta de muitas tentativas de login
        if ($analysis['failed_logins'] > $this->config['alert_threshold_failed_logins']) {
            $alerts[] = [
                'level' => 'high',
                'title' => 'Muitas tentativas de login falharam',
                'message' => "Detectadas {$analysis['failed_logins']} tentativas de login falharam nas últimas 24 horas."
            ];
        }

        // Alerta de IPs bloqueados
        if (count($analysis['blocked_ips']) > $this->config['alert_threshold_blocked_ips']) {
            $alerts[] = [
                'level' => 'critical',
                'title' => 'Múltiplos IPs bloqueados',
                'message' => count($analysis['blocked_ips']) . ' IPs foram bloqueados. Possível ataque em andamento.'
            ];
        }

        // Enviar alertas por email se configurado
        if (!empty($alerts) && $this->config['email_alerts']) {
            $this->sendSecurityAlert($alerts);
        }

        // Salvar alertas no banco
        foreach ($alerts as $alert) {
            $this->saveAlert($alert);
        }
    }

    /**
     * Envia alerta de segurança por email
     */
    private function sendSecurityAlert(array $alerts): void
    {
        $subject = 'Alerta de Segurança - Sistema Administrativo';
        $message = "Os seguintes alertas de segurança foram detectados:\n\n";

        foreach ($alerts as $alert) {
            $message .= "NÍVEL: " . strtoupper($alert['level']) . "\n";
            $message .= "TÍTULO: {$alert['title']}\n";
            $message .= "MENSAGEM: {$alert['message']}\n";
            $message .= "TIMESTAMP: " . date('Y-m-d H:i:s') . "\n\n";
        }

        $message .= "Verifique o painel de segurança para mais detalhes.";

        // Usar PHPMailer ou função mail() do PHP
        mail($this->config['alert_email'], $subject, $message);
    }

    /**
     * Salva alerta no banco de dados
     */
    private function saveAlert(array $alert): void
    {
        try {
            $sql = "INSERT INTO {prefix}security_alerts 
                    (level, title, message, created_at) 
                    VALUES (:level, :title, :message, NOW())";
            
            $processedSql = $this->database->processSqlWithPrefix($sql);
            $this->database->insert($processedSql, [
                'level' => $alert['level'],
                'title' => $alert['title'],
                'message' => $alert['message']
            ]);
        } catch (\Exception $e) {
            // Se a tabela não existir, criar
            $this->createAlertsTable();
        }
    }

    /**
     * Cria tabela de alertas de segurança
     */
    private function createAlertsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{prefix}security_alerts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `level` enum('low','medium','high','critical') NOT NULL,
            `title` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `acknowledged` tinyint(1) DEFAULT 0,
            `acknowledged_by` int(11) DEFAULT NULL,
            `acknowledged_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_level` (`level`),
            KEY `idx_acknowledged` (`acknowledged`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $processedSql = $this->database->processSqlWithPrefix($sql);
        $this->database->getConnection()->exec($processedSql);
    }

    /**
     * Limpa logs antigos
     */
    public function cleanupOldLogs(): int
    {
        $logDir = STORAGE_PATH . '/logs';
        $retentionDate = new \DateTime("-{$this->config['log_retention_days']} days");
        $deletedFiles = 0;

        if (!is_dir($logDir)) {
            return 0;
        }

        $files = glob($logDir . '/security_*.log');
        
        foreach ($files as $file) {
            $fileDate = \DateTime::createFromFormat('Y-m-d', basename($file, '.log'));
            
            if ($fileDate && $fileDate < $retentionDate) {
                if (unlink($file)) {
                    $deletedFiles++;
                }
            }
        }

        return $deletedFiles;
    }

    /**
     * Gera relatório de segurança
     */
    public function generateSecurityReport(int $days = 7): array
    {
        $analysis = $this->analyzeLogs($days * 24);
        
        return [
            'period' => "{$days} dias",
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_events' => $analysis['total_events'],
                'failed_logins' => $analysis['failed_logins'],
                'blocked_ips_count' => count($analysis['blocked_ips']),
                'csrf_violations' => $analysis['csrf_violations'],
                'suspicious_activities_count' => count($analysis['suspicious_activities'])
            ],
            'details' => $analysis,
            'system_health' => $this->getSystemHealth()
        ];
    }

    /**
     * Obtém saúde do sistema
     */
    private function getSystemHealth(): array
    {
        return [
            'active_users' => $this->getActiveUsersCount(),
            'inactive_users' => $this->checkInactiveUsers(),
            'weak_passwords' => $this->checkWeakPasswords(),
            'disk_usage' => $this->getDiskUsage(),
            'log_files_count' => $this->getLogFilesCount(),
            'last_backup' => $this->getLastBackupDate()
        ];
    }

    /**
     * Obtém contagem de usuários ativos
     */
    private function getActiveUsersCount(): int
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {prefix}users u
                    JOIN {prefix}status s ON u.status_id = s.id
                    WHERE u.deleted_at IS NULL AND s.name = 'active'";
            
            $processedSql = $this->database->processSqlWithPrefix($sql);
            $result = $this->database->selectOne($processedSql);
            
            return $result['count'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Obtém uso do disco
     */
    private function getDiskUsage(): array
    {
        $bytes = disk_free_space(ROOT_PATH);
        $total = disk_total_space(ROOT_PATH);
        
        return [
            'free_bytes' => $bytes,
            'total_bytes' => $total,
            'used_bytes' => $total - $bytes,
            'usage_percent' => round((($total - $bytes) / $total) * 100, 2)
        ];
    }

    /**
     * Obtém contagem de arquivos de log
     */
    private function getLogFilesCount(): int
    {
        $logDir = STORAGE_PATH . '/logs';
        
        if (!is_dir($logDir)) {
            return 0;
        }

        return count(glob($logDir . '/*.log'));
    }

    /**
     * Obtém data do último backup
     */
    private function getLastBackupDate(): ?string
    {
        $backupDir = STORAGE_PATH . '/backups';
        
        if (!is_dir($backupDir)) {
            return null;
        }

        $files = glob($backupDir . '/*.sql');
        
        if (empty($files)) {
            return null;
        }

        $latestFile = max($files);
        return date('Y-m-d H:i:s', filemtime($latestFile));
    }
}