<?php

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Controller de relatórios
 * 
 * @package App\Controllers
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class ReportController extends Controller
{
    /**
     * Lista relatórios disponíveis
     * 
     * @return void
     */
    public function index(): void
    {
        $reports = [
            [
                'name' => 'Relatório de Usuários',
                'description' => 'Lista completa de usuários do sistema',
                'url' => '/reports/users',
                'pdf_url' => '/reports/users/pdf',
                'icon' => 'bi-people'
            ],
            [
                'name' => 'Relatório de Atividades',
                'description' => 'Log de atividades dos usuários',
                'url' => '/reports/activities',
                'pdf_url' => '/reports/activities/pdf',
                'icon' => 'bi-activity'
            ],
            [
                'name' => 'Relatório do Sistema',
                'description' => 'Informações gerais do sistema',
                'url' => '/reports/system',
                'pdf_url' => '/reports/system/pdf',
                'icon' => 'bi-gear'
            ]
        ];

        $this->render('reports/index.twig', [
            'reports' => $reports
        ]);
    }

    /**
     * Relatório de usuários
     * 
     * @return void
     */
    public function users(): void
    {
        $userModel = new User();
        
        // Filtros
        $filters = [
            'role' => $this->get('role'),
            'active' => $this->get('active'),
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to')
        ];
        
        // Remover filtros vazios
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
        
        // Buscar usuários
        $users = $this->getUsersForReport($filters);
        $stats = $userModel->getStats();

        $this->render('reports/users.twig', [
            'users' => $users,
            'stats' => $stats,
            'filters' => $filters,
            'roles' => $this->getRoles()
        ]);
    }

    /**
     * Gera PDF do relatório de usuários
     * 
     * @return void
     */
    public function usersPdf(): void
    {
        $userModel = new User();
        
        // Filtros
        $filters = [
            'role' => $this->get('role'),
            'active' => $this->get('active'),
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to')
        ];
        
        // Remover filtros vazios
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
        
        // Buscar usuários
        $users = $this->getUsersForReport($filters);
        $stats = $userModel->getStats();

        // Renderizar HTML
        $html = $this->twig->render('reports/users-pdf.twig', [
            'users' => $users,
            'stats' => $stats,
            'filters' => $filters,
            'generated_at' => date('d/m/Y H:i:s'),
            'generated_by' => Auth::user()['name']
        ]);

        // Gerar PDF
        $this->generatePdf($html, 'relatorio-usuarios-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Relatório de atividades
     * 
     * @return void
     */
    public function activities(): void
    {
        $page = (int) ($this->get('page') ?? 1);
        $perPage = 50;
        
        // Filtros
        $filters = [
            'user_id' => $this->get('user_id'),
            'action' => $this->get('action'),
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to')
        ];
        
        // Remover filtros vazios
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
        
        // Buscar atividades
        $activities = $this->getActivitiesForReport($page, $perPage, $filters);

        $this->render('reports/activities.twig', [
            'activities' => $activities,
            'filters' => $filters
        ]);
    }

    /**
     * Relatório do sistema
     * 
     * @return void
     */
    public function system(): void
    {
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'disk_total_space' => $this->formatBytes(disk_total_space('.')),
            'disk_free_space' => $this->formatBytes(disk_free_space('.')),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak_usage' => $this->formatBytes(memory_get_peak_usage(true))
        ];

        // Extensões PHP importantes
        $extensions = [
            'PDO' => extension_loaded('pdo'),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'OpenSSL' => extension_loaded('openssl'),
            'cURL' => extension_loaded('curl'),
            'GD' => extension_loaded('gd'),
            'Mbstring' => extension_loaded('mbstring'),
            'JSON' => extension_loaded('json'),
            'XML' => extension_loaded('xml'),
            'Zip' => extension_loaded('zip')
        ];

        $this->render('reports/system.twig', [
            'system_info' => $systemInfo,
            'extensions' => $extensions
        ]);
    }

    /**
     * Busca usuários para relatório
     * 
     * @param array $filters
     * @return array
     */
    private function getUsersForReport(array $filters): array
    {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['role'])) {
            $conditions[] = "role = :role";
            $params['role'] = $filters['role'];
        }
        
        if (isset($filters['active'])) {
            $conditions[] = "active = :active";
            $params['active'] = $filters['active'];
        }
        
        if (!empty($filters['date_from'])) {
            $conditions[] = "created_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT id, name, email, role, active, created_at, last_login, login_count 
                FROM users 
                {$whereClause} 
                ORDER BY created_at DESC";
        
        return $this->database->select($sql, $params);
    }

    /**
     * Busca atividades para relatório
     * 
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    private function getActivitiesForReport(int $page, int $perPage, array $filters): array
    {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $conditions[] = "al.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $conditions[] = "al.action LIKE :action";
            $params['action'] = '%' . $filters['action'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $conditions[] = "al.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "al.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $offset = ($page - 1) * $perPage;
        
        // Buscar dados
        $sql = "SELECT al.*, u.name as user_name 
                FROM activity_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                {$whereClause} 
                ORDER BY al.created_at DESC 
                LIMIT {$perPage} OFFSET {$offset}";
        
        $data = $this->database->select($sql, $params);
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total 
                     FROM activity_logs al 
                     LEFT JOIN users u ON al.user_id = u.id 
                     {$whereClause}";
        
        $totalResult = $this->database->selectOne($countSql, $params);
        $total = $totalResult['total'];
        
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    /**
     * Gera PDF
     * 
     * @param string $html
     * @param string $filename
     * @return void
     */
    private function generatePdf(string $html, string $filename): void
    {
        // Configurar DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Enviar PDF para o navegador
        $dompdf->stream($filename, [
            'Attachment' => false // true para forçar download
        ]);
    }

    /**
     * Formata bytes
     * 
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Obtém lista de papéis
     * 
     * @return array
     */
    private function getRoles(): array
    {
        return [
            'admin' => 'Administrador',
            'user' => 'Usuário',
            'moderator' => 'Moderador'
        ];
    }
}