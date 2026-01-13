<?php

namespace App\Models;

use Core\Model;

/**
 * Model de matérias escolares
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.4.0
 */
class SchoolSubject extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'school_subjects';

    /**
     * @var array Campos que podem ser preenchidos em massa
     */
    protected array $fillable = [
        'name',
        'translate',
        'description',
        'status_id'
    ];

    /**
     * @var array Campos de data que devem ser convertidos
     */
    protected array $dates = ['dh', 'dh_update', 'deleted_at'];

    /**
     * Busca matérias ativas
     * 
     * @return array
     */
    public function getActive(): array
    {
        $sql = "SELECT ss.*, s.translate as status_name, s.color as status_color
                FROM {$this->table} ss
                LEFT JOIN status s ON ss.status_id = s.id
                WHERE ss.deleted_at IS NULL AND s.name = 'active'
                ORDER BY ss.name";
        
        return $this->database->select($sql);
    }

    /**
     * Busca matéria com relacionamentos
     * 
     * @param int $id
     * @return array|null
     */
    public function findWithRelations(int $id): ?array
    {
        $sql = "SELECT ss.*, s.translate as status_name, s.color as status_color, s.name as status_code
                FROM {$this->table} ss
                LEFT JOIN status s ON ss.status_id = s.id
                WHERE ss.id = :id AND ss.deleted_at IS NULL";
        
        return $this->database->selectOne($sql, ['id' => $id]);
    }

    /**
     * Busca matérias por status
     * 
     * @param int $statusId
     * @return array
     */
    public function getByStatus(int $statusId): array
    {
        $sql = "SELECT ss.*, s.translate as status_name, s.color as status_color
                FROM {$this->table} ss
                LEFT JOIN status s ON ss.status_id = s.id
                WHERE ss.status_id = :status_id AND ss.deleted_at IS NULL
                ORDER BY ss.name";
        
        return $this->database->select($sql, ['status_id' => $statusId]);
    }

    /**
     * Conta matérias por status
     * 
     * @param int $statusId
     * @return int
     */
    public function countByStatus(int $statusId): int
    {
        return $this->count(['status_id' => $statusId, 'deleted_at' => null]);
    }

    /**
     * Obtém estatísticas de matérias
     * 
     * @return array
     */
    public function getStats(): array
    {
        // Total de matérias
        $total = $this->count(['deleted_at' => null]);
        
        // Por status
        $statusStats = $this->database->select("
            SELECT s.name, s.translate, COUNT(ss.id) as count
            FROM status s
            LEFT JOIN school_subjects ss ON s.id = ss.status_id AND ss.deleted_at IS NULL
            WHERE s.deleted_at IS NULL
            GROUP BY s.id, s.name, s.translate
            ORDER BY s.id
        ");

        // Estatísticas básicas
        $active = $this->database->selectOne("
            SELECT COUNT(ss.id) as count
            FROM school_subjects ss
            JOIN status s ON ss.status_id = s.id
            WHERE s.name = 'active' AND ss.deleted_at IS NULL
        ")['count'];

        $inactive = $total - $active;

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'by_status' => $statusStats
        ];
    }

    /**
     * Busca matérias com paginação e filtros
     * 
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public function paginateWithFilters(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $conditions = ['ss.deleted_at IS NULL'];
        $params = [];
        
        // Filtro por nome
        if (!empty($filters['name'])) {
            $conditions[] = "(ss.name LIKE :name OR ss.translate LIKE :name)";
            $params['name'] = '%' . $filters['name'] . '%';
        }
        
        // Filtro por status
        if (!empty($filters['status_id'])) {
            $conditions[] = "ss.status_id = :status_id";
            $params['status_id'] = $filters['status_id'];
        }

        $offset = ($page - 1) * $perPage;
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        
        // Buscar dados
        $sql = "SELECT ss.id, ss.name, ss.translate, ss.description, ss.dh,
                       s.translate as status_name, s.color as status_color, s.name as status_code
                FROM {$this->table} ss
                LEFT JOIN status s ON ss.status_id = s.id
                {$whereClause} 
                ORDER BY ss.name 
                LIMIT {$perPage} OFFSET {$offset}";
        
        $data = $this->database->select($sql, $params);
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} ss
                     LEFT JOIN status s ON ss.status_id = s.id
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
     * Altera status da matéria
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
     * Verifica se nome já existe
     * 
     * @param string $name
     * @param int|null $excludeId
     * @return bool
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE name = :name AND deleted_at IS NULL AND id != :id";
            $result = $this->database->selectOne($sql, ['name' => $name, 'id' => $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE name = :name AND deleted_at IS NULL";
            $result = $this->database->selectOne($sql, ['name' => $name]);
        }
        
        return $result['count'] > 0;
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
     * Restaurar matéria
     * 
     * @param int $id
     * @return int
     */
    public function restore(int $id): int
    {
        return $this->update($id, ['deleted_at' => null]);
    }

    /**
     * Busca matérias deletadas
     * 
     * @return array
     */
    public function getDeleted(): array
    {
        $sql = "SELECT ss.*, s.translate as status_name
                FROM {$this->table} ss
                LEFT JOIN status s ON ss.status_id = s.id
                WHERE ss.deleted_at IS NOT NULL
                ORDER BY ss.deleted_at DESC";
        
        return $this->database->select($sql);
    }

    /**
     * Busca matéria por nome
     * 
     * @param string $name
     * @return array|null
     */
    public function findByName(string $name): ?array
    {
        return $this->whereFirst(['name' => $name, 'deleted_at' => null]);
    }

    /**
     * Cria uma nova matéria
     * 
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        // Definir valores padrão
        $data['status_id'] = $data['status_id'] ?? 1; // Ativo

        return parent::create($data);
    }

    /**
     * Busca matérias para horários
     * 
     * @return array
     */
    public function getForSchedules(): array
    {
        $sql = "SELECT ss.id, ss.name, ss.translate
                FROM {$this->table} ss
                JOIN status s ON ss.status_id = s.id
                WHERE ss.deleted_at IS NULL AND s.name = 'active'
                ORDER BY ss.name";
        
        return $this->database->select($sql);
    }

    /**
     * Conta horários por matéria
     * 
     * @param int $subjectId
     * @return int
     */
    public function countSchedules(int $subjectId): int
    {
        $sql = "SELECT COUNT(*) as count 
                FROM school_schedules 
                WHERE subject_id = :subject_id AND deleted_at IS NULL";
        
        $result = $this->database->selectOne($sql, ['subject_id' => $subjectId]);
        return $result['count'];
    }

    /**
     * Busca professores que lecionam a matéria
     * 
     * @param int $subjectId
     * @return array
     */
    public function getTeachers(int $subjectId): array
    {
        $sql = "SELECT DISTINCT u.id, u.name, u.email
                FROM users u
                JOIN school_schedules ss ON u.id = ss.teacher_id
                WHERE ss.subject_id = :subject_id AND ss.deleted_at IS NULL
                ORDER BY u.name";
        
        return $this->database->select($sql, ['subject_id' => $subjectId]);
    }
}