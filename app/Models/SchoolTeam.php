<?php

namespace App\Models;

use Core\Model;
use Core\Security;

/**
 * Model de turmas escolares
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.4.0
 */
class SchoolTeam extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'school_teams';

    /**
     * @var array Campos que podem ser preenchidos em massa
     */
    protected array $fillable = [
        'serie_id',
        'period_id',
        'education_id',
        'status_id',
        'public_link_token',
        'public_link_enabled',
        'public_link_expires_at'
    ];

    /**
     * @var array Campos que devem ser ocultados
     */
    protected array $hidden = [
        'public_link_token'
    ];

    /**
     * @var array Campos de data que devem ser convertidos
     */
    protected array $dates = ['dh', 'dh_update', 'deleted_at', 'public_link_expires_at'];

    /**
     * Busca turmas ativas
     * 
     * @return array
     */
    public function getActive(): array
    {
        $sql = "SELECT st.*, 
                       sp.translate as period_name,
                       s.translate as status_name, s.color as status_color
                FROM {$this->table} st
                LEFT JOIN school_periods sp ON st.period_id = sp.id
                LEFT JOIN status s ON st.status_id = s.id
                WHERE st.deleted_at IS NULL AND s.name = 'active'
                ORDER BY st.id";
        
        return $this->database->select($sql);
    }

    /**
     * Busca turma com relacionamentos
     * 
     * @param int $id
     * @return array|null
     */
    public function findWithRelations(int $id): ?array
    {
        $sql = "SELECT st.*, 
                       sp.translate as period_name, sp.name as period_code,
                       s.translate as status_name, s.color as status_color, s.name as status_code
                FROM {$this->table} st
                LEFT JOIN school_periods sp ON st.period_id = sp.id
                LEFT JOIN status s ON st.status_id = s.id
                WHERE st.id = :id AND st.deleted_at IS NULL";
        
        return $this->database->selectOne($sql, ['id' => $id]);
    }

    /**
     * Busca turmas por período
     * 
     * @param int $periodId
     * @return array
     */
    public function getByPeriod(int $periodId): array
    {
        $sql = "SELECT st.*, 
                       sp.translate as period_name,
                       s.translate as status_name, s.color as status_color
                FROM {$this->table} st
                LEFT JOIN school_periods sp ON st.period_id = sp.id
                LEFT JOIN status s ON st.status_id = s.id
                WHERE st.period_id = :period_id AND st.deleted_at IS NULL
                ORDER BY st.id";
        
        return $this->database->select($sql, ['period_id' => $periodId]);
    }

    /**
     * Busca turmas por status
     * 
     * @param int $statusId
     * @return array
     */
    public function getByStatus(int $statusId): array
    {
        $sql = "SELECT st.*, 
                       sp.translate as period_name,
                       s.translate as status_name, s.color as status_color
                FROM {$this->table} st
                LEFT JOIN school_periods sp ON st.period_id = sp.id
                LEFT JOIN status s ON st.status_id = s.id
                WHERE st.status_id = :status_id AND st.deleted_at IS NULL
                ORDER BY st.id";
        
        return $this->database->select($sql, ['status_id' => $statusId]);
    }

    /**
     * Conta turmas por período
     * 
     * @param int $periodId
     * @return int
     */
    public function countByPeriod(int $periodId): int
    {
        return $this->count(['period_id' => $periodId, 'deleted_at' => null]);
    }

    /**
     * Conta turmas por status
     * 
     * @param int $statusId
     * @return int
     */
    public function countByStatus(int $statusId): int
    {
        return $this->count(['status_id' => $statusId, 'deleted_at' => null]);
    }

    /**
     * Obtém estatísticas de turmas
     * 
     * @return array
     */
    public function getStats(): array
    {
        // Total de turmas
        $total = $this->count(['deleted_at' => null]);
        
        // Por status
        $statusStats = $this->database->select("
            SELECT s.name, s.translate, COUNT(st.id) as count
            FROM status s
            LEFT JOIN school_teams st ON s.id = st.status_id AND st.deleted_at IS NULL
            WHERE s.deleted_at IS NULL
            GROUP BY s.id, s.name, s.translate
            ORDER BY s.id
        ");

        // Por período
        $periodStats = $this->database->select("
            SELECT sp.name, sp.translate, COUNT(st.id) as count
            FROM school_periods sp
            LEFT JOIN school_teams st ON sp.id = st.period_id AND st.deleted_at IS NULL
            WHERE sp.deleted_at IS NULL
            GROUP BY sp.id, sp.name, sp.translate
            ORDER BY sp.id
        ");

        // Estatísticas básicas
        $active = $this->database->selectOne("
            SELECT COUNT(st.id) as count
            FROM school_teams st
            JOIN status s ON st.status_id = s.id
            WHERE s.name = 'active' AND st.deleted_at IS NULL
        ")['count'];

        $inactive = $total - $active;

        // Turmas com link público ativo
        $publicLinks = $this->database->selectOne("
            SELECT COUNT(*) as count
            FROM {$this->table}
            WHERE public_link_enabled = 1 
            AND (public_link_expires_at IS NULL OR public_link_expires_at > CURDATE())
            AND deleted_at IS NULL
        ")['count'];

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'public_links' => $publicLinks,
            'by_status' => $statusStats,
            'by_period' => $periodStats
        ];
    }

    /**
     * Busca turmas com paginação e filtros
     * 
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public function paginateWithFilters(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $conditions = ['st.deleted_at IS NULL'];
        $params = [];
        
        // Filtro por período
        if (!empty($filters['period_id'])) {
            $conditions[] = "st.period_id = :period_id";
            $params['period_id'] = $filters['period_id'];
        }
        
        // Filtro por status
        if (!empty($filters['status_id'])) {
            $conditions[] = "st.status_id = :status_id";
            $params['status_id'] = $filters['status_id'];
        }

        // Filtro por série
        if (!empty($filters['serie_id'])) {
            $conditions[] = "st.serie_id = :serie_id";
            $params['serie_id'] = $filters['serie_id'];
        }

        // Filtro por educação
        if (!empty($filters['education_id'])) {
            $conditions[] = "st.education_id = :education_id";
            $params['education_id'] = $filters['education_id'];
        }

        // Filtro por link público
        if (isset($filters['public_link_enabled'])) {
            $conditions[] = "st.public_link_enabled = :public_link_enabled";
            $params['public_link_enabled'] = $filters['public_link_enabled'];
        }

        $offset = ($page - 1) * $perPage;
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        
        // Buscar dados
        $sql = "SELECT st.id, st.serie_id, st.period_id, st.education_id, 
                       st.public_link_enabled, st.public_link_expires_at, st.dh,
                       sp.translate as period_name,
                       s.translate as status_name, s.color as status_color, s.name as status_code
                FROM {$this->table} st
                LEFT JOIN school_periods sp ON st.period_id = sp.id
                LEFT JOIN status s ON st.status_id = s.id
                {$whereClause} 
                ORDER BY st.id 
                LIMIT {$perPage} OFFSET {$offset}";
        
        $data = $this->database->select($sql, $params);
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} st
                     LEFT JOIN school_periods sp ON st.period_id = sp.id
                     LEFT JOIN status s ON st.status_id = s.id
                     {$whereClause}";
        
        $totalResult = $this->database->selectOne($countSql, $params);
        $total = $totalResult['total'];
        
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /**
     * Altera status da turma
     * 
     * @param int $id
     * @param int $statusId
     * @return int
     */
    public function changeStatus(int $id, int $statusId): int
    {
        return $this->update($id, ['status_id' => $statusId]);
    }

    /**
     * Soft delete
     * 
     * @param int $id
     * @return int
     */
    public function softDelete(int $id): int
    {
        return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Restaurar turma
     * 
     * @param int $id
     * @return int
     */
    public function restore(int $id): int
    {
        return $this->update($id, ['deleted_at' => null]);
    }

    /**
     * Busca turmas deletadas
     * 
     * @return array
     */
    public function getDeleted(): array
    {
        $sql = "SELECT st.*, 
                       sp.translate as period_name,
                       s.translate as status_name
                FROM {$this->table} st
                LEFT JOIN school_periods sp ON st.period_id = sp.id
                LEFT JOIN status s ON st.status_id = s.id
                WHERE st.deleted_at IS NOT NULL
                ORDER BY st.deleted_at DESC";
        
        return $this->database->select($sql);
    }

    /**
     * Cria uma nova turma
     * 
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        // Definir valores padrão
        $data['status_id'] = $data['status_id'] ?? 1; // Ativo
        $data['public_link_enabled'] = $data['public_link_enabled'] ?? false;

        return parent::create($data);
    }

    /**
     * Gera token único para link público
     * 
     * @return string
     */
    public function generatePublicLinkToken(): string
    {
        do {
            $token = strtoupper(Security::generateRandomString(10));
        } while ($this->tokenExists($token));
        
        return $token;
    }

    /**
     * Verifica se token já existe
     * 
     * @param string $token
     * @return bool
     */
    public function tokenExists(string $token): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE public_link_token = :token AND deleted_at IS NULL";
        $result = $this->database->selectOne($sql, ['token' => $token]);
        
        return $result['count'] > 0;
    }

    /**
     * Busca turma por token público
     * 
     * @param string $token
     * @return array|null
     */
    public function findByPublicToken(string $token): ?array
    {
        $sql = "SELECT st.*, 
                       sp.translate as period_name,
                       s.translate as status_name, s.color as status_color
                FROM {$this->table} st
                LEFT JOIN school_periods sp ON st.period_id = sp.id
                LEFT JOIN status s ON st.status_id = s.id
                WHERE st.public_link_token = :token 
                AND st.public_link_enabled = 1
                AND (st.public_link_expires_at IS NULL OR st.public_link_expires_at > CURDATE())
                AND st.deleted_at IS NULL";
        
        return $this->database->selectOne($sql, ['token' => $token]);
    }

    /**
     * Ativa link público
     * 
     * @param int $id
     * @param string|null $expiresAt
     * @return int
     */
    public function enablePublicLink(int $id, ?string $expiresAt = null): int
    {
        $data = [
            'public_link_enabled' => true,
            'public_link_expires_at' => $expiresAt
        ];

        // Gerar token se não existir
        $team = $this->find($id);
        if (!$team['public_link_token']) {
            $data['public_link_token'] = $this->generatePublicLinkToken();
        }

        return $this->update($id, $data);
    }

    /**
     * Desativa link público
     * 
     * @param int $id
     * @return int
     */
    public function disablePublicLink(int $id): int
    {
        return $this->update($id, ['public_link_enabled' => false]);
    }

    /**
     * Renova token do link público
     * 
     * @param int $id
     * @return string
     */
    public function renewPublicLinkToken(int $id): string
    {
        $token = $this->generatePublicLinkToken();
        $this->update($id, ['public_link_token' => $token]);
        
        return $token;
    }

    /**
     * Busca turmas com link público ativo
     * 
     * @return array
     */
    public function getWithActivePublicLink(): array
    {
        $sql = "SELECT st.*, 
                       sp.translate as period_name,
                       s.translate as status_name, s.color as status_color
                FROM {$this->table} st
                LEFT JOIN school_periods sp ON st.period_id = sp.id
                LEFT JOIN status s ON st.status_id = s.id
                WHERE st.public_link_enabled = 1 
                AND (st.public_link_expires_at IS NULL OR st.public_link_expires_at > CURDATE())
                AND st.deleted_at IS NULL
                ORDER BY st.id";
        
        return $this->database->select($sql);
    }

    /**
     * Busca turmas com link público expirado
     * 
     * @return array
     */
    public function getWithExpiredPublicLink(): array
    {
        $sql = "SELECT st.*, 
                       sp.translate as period_name,
                       s.translate as status_name, s.color as status_color
                FROM {$this->table} st
                LEFT JOIN school_periods sp ON st.period_id = sp.id
                LEFT JOIN status s ON st.status_id = s.id
                WHERE st.public_link_enabled = 1 
                AND st.public_link_expires_at IS NOT NULL 
                AND st.public_link_expires_at <= CURDATE()
                AND st.deleted_at IS NULL
                ORDER BY st.public_link_expires_at DESC";
        
        return $this->database->select($sql);
    }

    /**
     * Obtém horários da turma
     * 
     * @param int $teamId
     * @return array
     */
    public function getSchedules(int $teamId): array
    {
        $sql = "SELECT sch.*, 
                       u.name as teacher_name,
                       ss.name as subject_name, ss.translate as subject_translate
                FROM school_schedules sch
                LEFT JOIN users u ON sch.teacher_id = u.id
                LEFT JOIN school_subjects ss ON sch.subject_id = ss.id
                WHERE sch.team_id = :team_id AND sch.deleted_at IS NULL
                ORDER BY sch.day_of_week, sch.class_number";
        
        return $this->database->select($sql, ['team_id' => $teamId]);
    }

    /**
     * Conta horários da turma
     * 
     * @param int $teamId
     * @return int
     */
    public function countSchedules(int $teamId): int
    {
        $sql = "SELECT COUNT(*) as count 
                FROM school_schedules 
                WHERE team_id = :team_id AND deleted_at IS NULL";
        
        $result = $this->database->selectOne($sql, ['team_id' => $teamId]);
        return $result['count'];
    }

    /**
     * Verifica se link público está válido
     * 
     * @param array $team
     * @return bool
     */
    public function isPublicLinkValid(array $team): bool
    {
        if (!$team['public_link_enabled'] || !$team['public_link_token']) {
            return false;
        }

        if ($team['public_link_expires_at']) {
            return strtotime($team['public_link_expires_at']) > time();
        }

        return true;
    }

    /**
     * Gera URL do link público
     * 
     * @param string $token
     * @param string $baseUrl
     * @return string
     */
    public function generatePublicLinkUrl(string $token, string $baseUrl = ''): string
    {
        return rtrim($baseUrl, '/') . '/public/team/' . $token;
    }
}