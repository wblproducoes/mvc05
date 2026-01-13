<?php

namespace App\Models;

use Core\Model;

/**
 * Model de períodos escolares
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.4.0
 */
class SchoolPeriod extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'school_periods';

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
     * Busca períodos ativos
     * 
     * @return array
     */
    public function getActive(): array
    {
        $sql = "SELECT sp.*, s.translate as status_name, s.color as status_color
                FROM {$this->table} sp
                LEFT JOIN status s ON sp.status_id = s.id
                WHERE sp.deleted_at IS NULL AND s.name = 'active'
                ORDER BY sp.name";
        
        return $this->database->select($sql);
    }

    /**
     * Busca período com relacionamentos
     * 
     * @param int $id
     * @return array|null
     */
    public function findWithRelations(int $id): ?array
    {
        $sql = "SELECT sp.*, s.translate as status_name, s.color as status_color, s.name as status_code
                FROM {$this->table} sp
                LEFT JOIN status s ON sp.status_id = s.id
                WHERE sp.id = :id AND sp.deleted_at IS NULL";
        
        return $this->database->selectOne($sql, ['id' => $id]);
    }

    /**
     * Busca períodos por status
     * 
     * @param int $statusId
     * @return array
     */
    public function getByStatus(int $statusId): array
    {
        $sql = "SELECT sp.*, s.translate as status_name, s.color as status_color
                FROM {$this->table} sp
                LEFT JOIN status s ON sp.status_id = s.id
                WHERE sp.status_id = :status_id AND sp.deleted_at IS NULL
                ORDER BY sp.name";
        
        return $this->database->select($sql, ['status_id' => $statusId]);
    }

    /**
     * Conta períodos por status
     * 
     * @param int $statusId
     * @return int
     */
    public function countByStatus(int $statusId): int
    {
        return $this->count(['status_id' => $statusId, 'deleted_at' => null]);
    }

    /**
     * Obtém estatísticas de períodos
     * 
     * @return array
     */
    public function getStats(): array
    {
        // Total de períodos
        $total = $this->count(['deleted_at' => null]);
        
        // Por status
        $statusStats = $this->database->select("
            SELECT s.name, s.translate, COUNT(sp.id) as count
            FROM status s
            LEFT JOIN school_periods sp ON s.id = sp.status_id AND sp.deleted_at IS NULL
            WHERE s.deleted_at IS NULL
            GROUP BY s.id, s.name, s.translate
            ORDER BY s.id
        ");

        // Estatísticas básicas
        $active = $this->database->selectOne("
            SELECT COUNT(sp.id) as count
            FROM school_periods sp
            JOIN status s ON sp.status_id = s.id
            WHERE s.name = 'active' AND sp.deleted_at IS NULL
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
     * Busca períodos com paginação e filtros
     * 
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public function paginateWithFilters(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $conditions = ['sp.deleted_at IS NULL'];
        $params = [];
        
        // Filtro por nome
        if (!empty($filters['name'])) {
            $conditions[] = "(sp.name LIKE :name OR sp.translate LIKE :name)";
            $params['name'] = '%' . $filters['name'] . '%';
        }
        
        // Filtro por status
        if (!empty($filters['status_id'])) {
            $conditions[] = "sp.status_id = :status_id";
            $params['status_id'] = $filters['status_id'];
        }

        $offset = ($page - 1) * $perPage;
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        
        // Buscar dados
        $sql = "SELECT sp.id, sp.name, sp.translate, sp.description, sp.dh,
                       s.translate as status_name, s.color as status_color, s.name as status_code
                FROM {$this->table} sp
                LEFT JOIN status s ON sp.status_id = s.id
                {$whereClause} 
                ORDER BY sp.name 
                LIMIT {$perPage} OFFSET {$offset}";
        
        $data = $this->database->select($sql, $params);
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} sp
                     LEFT JOIN status s ON sp.status_id = s.id
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
     * Altera status do período
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
     * Restaurar período
     * 
     * @param int $id
     * @return int
     */
    public function restore(int $id): int
    {
        return $this->update($id, ['deleted_at' => null]);
    }

    /**
     * Busca períodos deletados
     * 
     * @return array
     */
    public function getDeleted(): array
    {
        $sql = "SELECT sp.*, s.translate as status_name
                FROM {$this->table} sp
                LEFT JOIN status s ON sp.status_id = s.id
                WHERE sp.deleted_at IS NOT NULL
                ORDER BY sp.deleted_at DESC";
        
        return $this->database->select($sql);
    }

    /**
     * Busca período por nome
     * 
     * @param string $name
     * @return array|null
     */
    public function findByName(string $name): ?array
    {
        return $this->whereFirst(['name' => $name, 'deleted_at' => null]);
    }

    /**
     * Cria um novo período
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
}