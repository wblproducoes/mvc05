<?php

namespace App\Controllers;

use Core\Controller;
use Core\Security;
use Core\SecurityAudit;

/**
 * Controller de segurança
 * 
 * @package App\Controllers
 * @author Sistema Administrativo
 * @version 1.4.3
 */
class SecurityController extends Controller
{
    /**
     * @var SecurityAudit Instância do auditor de segurança
     */
    private SecurityAudit $audit;

    /**
     * Construtor
     */
    public function __construct()
    {
        parent::__construct();
        $this->audit = new SecurityAudit();
        
        // Verificar se usuário tem permissão (apenas Master e Admin)
        if (!$this->isAuthenticated() || ($_SESSION['user_level'] ?? 11) > 2) {
            $this->redirect('/');
        }
    }

    /**
     * Dashboard de segurança
     */
    public function index(): void
    {
        $analysis = $this->audit->analyzeLogs(24);
        $systemHealth = $this->getSystemHealth();
        
        $this->render('security/dashboard.twig', [
            'title' => 'Dashboard de Segurança',
            'analysis' => $analysis,
            'system_health' => $systemHealth,
            'csrf_token' => Security::generateCsrfToken()
        ]);
    }

    /**
     * Relatório de segurança
     */
    public function report(): void
    {
        $days = (int)($this->get('days') ?? 7);
        $report = $this->audit->generateSecurityReport($days);
        
        $this->render('security/report.twig', [
            'title' => 'Relatório de Segurança',
            'report' => $report,
            'days' => $days,
            'csrf_token' => Security::generateCsrfToken()
        ]);
    }

    /**
     * Logs de segurança
     */
    public function logs(): void
    {
        $page = (int)($this->get('page') ?? 1);
        $filter = $this->get('filter') ?? '';
        
        $logs = $this->getSecurityLogs($page, $filter);
        
        $this->render('security/logs.twig', [
            'title' => 'Logs de Segurança',
            'logs' => $logs,
            'page' => $page,
            'filter' => $filter,
            'csrf_token' => Security::generateCsrfToken()
        ]);
    }

    /**
     * IPs bloqueados
     */
    public function blockedIps(): void
    {
        $blockedIps = $this->getBlockedIps();
        
        $this->render('security/blocked_ips.twig', [
            'title' => 'IPs Bloqueados',
            'blocked_ips' => $blockedIps,
            'csrf_token' => Security::generateCsrfToken()
        ]);
    }

    /**
     * Desbloquear IP
     */
    public function unblockIp(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/security/blocked-ips');
        }

        if (!Security::validateCsrfToken($this->post('csrf_token'))) {
            $this->flash('error', 'Token de segurança inválido');
            $this->redirect('/security/blocked-ips');
        }

        $ip = $this->post('ip');
        
        if (empty($ip)) {
            $this->flash('error', 'IP não informado');
            $this->redirect('/security/blocked-ips');
        }

        try {
            $this->unblockIpAddress($ip);
            
            Security::logSecurityEvent('ip_unblocked_manually', [
                'ip' => $ip,
                'admin_user_id' => $_SESSION['user_id']
            ]);
            
            $this->flash('success', "IP {$ip} desbloqueado com sucesso");
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao desbloquear IP: ' . $e->getMessage());
        }

        $this->redirect('/security/blocked-ips');
    }

    /**
     * Configurações de segurança
     */
    public function settings(): void
    {
        if ($this->isPost()) {
            $this->updateSecuritySettings();
            return;
        }

        $settings = $this->getSecuritySettings();
        
        $this->render('security/settings.twig', [
            'title' => 'Configurações de Segurança',
            'settings' => $settings,
            'csrf_token' => Security::generateCsrfToken()
        ]);
    }

    /**
     * Atualiza configurações de segurança
     */
    private function updateSecuritySettings(): void
    {
        if (!Security::validateCsrfToken($this->post('csrf_token'))) {
            $this->flash('error', 'Token de segurança inválido');
            $this->redirect('/security/settings');
        }

        $settings = [
            'max_login_attempts' => (int)$this->post('max_login_attempts'),
            'lockout_duration' => (int)$this->post('lockout_duration'),
            'password_min_length' => (int)$this->post('password_min_length'),
            'session_timeout' => (int)$this->post('session_timeout'),
            'audit_log_enabled' => (bool)$this->post('audit_log_enabled'),
            'email_alerts' => (bool)$this->post('email_alerts'),
            'alert_email' => $this->post('alert_email')
        ];

        try {
            $this->saveSecuritySettings($settings);
            
            Security::logSecurityEvent('security_settings_updated', [
                'admin_user_id' => $_SESSION['user_id'],
                'settings' => $settings
            ]);
            
            $this->flash('success', 'Configurações de segurança atualizadas com sucesso');
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao salvar configurações: ' . $e->getMessage());
        }

        $this->redirect('/security/settings');
    }

    /**
     * Força logout de todos os usuários
     */
    public function forceLogoutAll(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/security');
        }

        if (!Security::validateCsrfToken($this->post('csrf_token'))) {
            $this->flash('error', 'Token de segurança inválido');
            $this->redirect('/security');
        }

        try {
            // Limpar todas as sessões ativas
            $this->clearAllSessions();
            
            Security::logSecurityEvent('force_logout_all', [
                'admin_user_id' => $_SESSION['user_id']
            ]);
            
            $this->flash('success', 'Todos os usuários foram desconectados');
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao desconectar usuários: ' . $e->getMessage());
        }

        $this->redirect('/security');
    }

    /**
     * Limpa logs antigos
     */
    public function cleanupLogs(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/security');
        }

        if (!Security::validateCsrfToken($this->post('csrf_token'))) {
            $this->flash('error', 'Token de segurança inválido');
            $this->redirect('/security');
        }

        try {
            $deletedFiles = $this->audit->cleanupOldLogs();
            
            Security::logSecurityEvent('logs_cleanup', [
                'admin_user_id' => $_SESSION['user_id'],
                'deleted_files' => $deletedFiles
            ]);
            
            $this->flash('success', "{$deletedFiles} arquivos de log antigos foram removidos");
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao limpar logs: ' . $e->getMessage());
        }

        $this->redirect('/security');
    }

    /**
     * API: Status de segurança
     */
    public function apiStatus(): void
    {
        header('Content-Type: application/json');
        
        try {
            $analysis = $this->audit->analyzeLogs(1); // Última hora
            $systemHealth = $this->getSystemHealth();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'analysis' => $analysis,
                    'system_health' => $systemHealth,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtém saúde do sistema
     */
    private function getSystemHealth(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'disk_free' => disk_free_space(ROOT_PATH),
            'uptime' => $this->getSystemUptime(),
            'security_headers' => $this->checkSecurityHeaders(),
            'ssl_enabled' => isset($_SERVER['HTTPS']),
            'session_secure' => ini_get('session.cookie_secure'),
            'last_security_scan' => $this->getLastSecurityScan()
        ];
    }

    /**
     * Obtém logs de segurança paginados
     */
    private function getSecurityLogs(int $page, string $filter): array
    {
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        
        // Implementar leitura de logs com filtro e paginação
        $logFiles = glob(STORAGE_PATH . '/logs/security_*.log');
        $logs = [];
        
        foreach (array_reverse($logFiles) as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach (array_reverse($lines) as $line) {
                $event = json_decode($line, true);
                
                if ($event && (empty($filter) || strpos($event['event'], $filter) !== false)) {
                    $logs[] = $event;
                    
                    if (count($logs) >= $offset + $perPage) {
                        break 2;
                    }
                }
            }
        }
        
        return array_slice($logs, $offset, $perPage);
    }

    /**
     * Obtém IPs bloqueados
     */
    private function getBlockedIps(): array
    {
        $blockFile = STORAGE_PATH . '/security/blocked_ips.json';
        
        if (!file_exists($blockFile)) {
            return [];
        }
        
        $blocked = json_decode(file_get_contents($blockFile), true) ?? [];
        $result = [];
        
        foreach ($blocked as $ip => $expiry) {
            if ($expiry > time()) {
                $result[] = [
                    'ip' => $ip,
                    'expires_at' => date('Y-m-d H:i:s', $expiry),
                    'remaining' => $expiry - time()
                ];
            }
        }
        
        return $result;
    }

    /**
     * Desbloqueia IP
     */
    private function unblockIpAddress(string $ip): void
    {
        $blockFile = STORAGE_PATH . '/security/blocked_ips.json';
        
        if (!file_exists($blockFile)) {
            return;
        }
        
        $blocked = json_decode(file_get_contents($blockFile), true) ?? [];
        
        if (isset($blocked[$ip])) {
            unset($blocked[$ip]);
            file_put_contents($blockFile, json_encode($blocked));
        }
    }

    /**
     * Obtém configurações de segurança
     */
    private function getSecuritySettings(): array
    {
        return Security::getConfig();
    }

    /**
     * Salva configurações de segurança
     */
    private function saveSecuritySettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Security::setConfig($key, $value);
        }
        
        // Salvar no banco de dados ou arquivo de configuração
        // Implementar persistência das configurações
    }

    /**
     * Limpa todas as sessões ativas
     */
    private function clearAllSessions(): void
    {
        // Implementar limpeza de sessões no banco ou arquivos
        $sessionPath = session_save_path();
        
        if (is_dir($sessionPath)) {
            $files = glob($sessionPath . '/sess_*');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Obtém uptime do sistema
     */
    private function getSystemUptime(): ?string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = (int)explode(' ', $uptime)[0];
                return gmdate('H:i:s', $seconds);
            }
        }
        
        return null;
    }

    /**
     * Verifica headers de segurança
     */
    private function checkSecurityHeaders(): array
    {
        $headers = getallheaders();
        
        return [
            'x_frame_options' => isset($headers['X-Frame-Options']),
            'x_content_type_options' => isset($headers['X-Content-Type-Options']),
            'x_xss_protection' => isset($headers['X-XSS-Protection']),
            'strict_transport_security' => isset($headers['Strict-Transport-Security']),
            'content_security_policy' => isset($headers['Content-Security-Policy'])
        ];
    }

    /**
     * Obtém data do último scan de segurança
     */
    private function getLastSecurityScan(): ?string
    {
        $scanFile = STORAGE_PATH . '/security/last_scan.txt';
        
        if (file_exists($scanFile)) {
            return file_get_contents($scanFile);
        }
        
        return null;
    }
}