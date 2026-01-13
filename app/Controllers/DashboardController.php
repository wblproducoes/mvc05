<?php

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use App\Models\User;

/**
 * Controller do dashboard
 * 
 * @package App\Controllers
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class DashboardController extends Controller
{
    /**
     * Exibe o dashboard principal
     * 
     * @return void
     */
    public function index(): void
    {
        $userModel = new User();
        
        // Obter estatísticas
        $stats = $this->getDashboardStats($userModel);
        
        // Obter usuários recentes
        $recentUsers = $this->getRecentUsers($userModel);
        
        // Obter atividades recentes (implementar conforme necessário)
        $recentActivities = $this->getRecentActivities();

        $this->render('dashboard/index.twig', [
            'stats' => $stats,
            'recent_users' => $recentUsers,
            'recent_activities' => $recentActivities,
            'user' => Auth::user()
        ]);
    }

    /**
     * Obtém estatísticas do dashboard
     * 
     * @param User $userModel
     * @return array
     */
    private function getDashboardStats(User $userModel): array
    {
        $userStats = $userModel->getStats();
        
        // Estatísticas básicas
        $stats = [
            'users' => [
                'total' => $userStats['total'],
                'active' => $userStats['active'],
                'inactive' => $userStats['inactive'],
                'growth' => $this->calculateUserGrowth($userModel)
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                'disk_space' => $this->getDiskSpace(),
                'uptime' => $this->getSystemUptime()
            ]
        ];

        // Adicionar estatísticas específicas por papel
        $stats['roles'] = [
            'admins' => $userStats['admins'],
            'users' => $userStats['users']
        ];

        return $stats;
    }

    /**
     * Obtém usuários recentes
     * 
     * @param User $userModel
     * @return array
     */
    private function getRecentUsers(User $userModel): array
    {
        $sql = "SELECT id, name, email, role, created_at 
                FROM users 
                ORDER BY created_at DESC 
                LIMIT 5";
        
        return $this->database->select($sql);
    }

    /**
     * Obtém atividades recentes
     * 
     * @return array
     */
    private function getRecentActivities(): array
    {
        // Implementar sistema de logs de atividades conforme necessário
        return [
            [
                'user' => 'Sistema',
                'action' => 'Backup automático realizado',
                'time' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'type' => 'system'
            ],
            [
                'user' => Auth::user()['name'] ?? 'Usuário',
                'action' => 'Login realizado',
                'time' => date('Y-m-d H:i:s'),
                'type' => 'auth'
            ]
        ];
    }

    /**
     * Calcula crescimento de usuários
     * 
     * @param User $userModel
     * @return array
     */
    private function calculateUserGrowth(User $userModel): array
    {
        // Usuários do mês atual
        $currentMonth = date('Y-m');
        $sql = "SELECT COUNT(*) as count FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = :month";
        $currentMonthUsers = $this->database->selectOne($sql, ['month' => $currentMonth]);
        
        // Usuários do mês anterior
        $lastMonth = date('Y-m', strtotime('-1 month'));
        $lastMonthUsers = $this->database->selectOne($sql, ['month' => $lastMonth]);
        
        $current = (int) $currentMonthUsers['count'];
        $previous = (int) $lastMonthUsers['count'];
        
        $percentage = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
        
        return [
            'current_month' => $current,
            'last_month' => $previous,
            'percentage' => round($percentage, 1),
            'trend' => $percentage >= 0 ? 'up' : 'down'
        ];
    }

    /**
     * Formata bytes em formato legível
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
     * Obtém espaço em disco
     * 
     * @return array
     */
    private function getDiskSpace(): array
    {
        $totalBytes = disk_total_space('.');
        $freeBytes = disk_free_space('.');
        $usedBytes = $totalBytes - $freeBytes;
        
        return [
            'total' => $this->formatBytes($totalBytes),
            'used' => $this->formatBytes($usedBytes),
            'free' => $this->formatBytes($freeBytes),
            'percentage' => round(($usedBytes / $totalBytes) * 100, 1)
        ];
    }

    /**
     * Obtém uptime do sistema (simulado)
     * 
     * @return string
     */
    private function getSystemUptime(): string
    {
        // Em um ambiente real, você obteria o uptime do sistema
        // Por enquanto, retornar um valor simulado
        return '2 dias, 14 horas';
    }

    /**
     * API para obter dados do dashboard
     * 
     * @return void
     */
    public function apiStats(): void
    {
        $userModel = new User();
        $stats = $this->getDashboardStats($userModel);
        
        $this->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * API para obter gráfico de usuários por mês
     * 
     * @return void
     */
    public function apiUserChart(): void
    {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM users 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month";
        
        $data = $this->database->select($sql);
        
        $months = [];
        $counts = [];
        
        foreach ($data as $row) {
            $months[] = date('M/Y', strtotime($row['month'] . '-01'));
            $counts[] = (int) $row['count'];
        }
        
        $this->json([
            'success' => true,
            'data' => [
                'labels' => $months,
                'datasets' => [
                    [
                        'label' => 'Novos Usuários',
                        'data' => $counts,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'borderWidth' => 2
                    ]
                ]
            ]
        ]);
    }
}