<?php

namespace App\Models;

use Core\Model;
use Core\Security;

/**
 * Model de usuário
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.1.0
 */
class User extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'users';

    /**
     * @var array Campos que podem ser preenchidos em massa
     */
    protected array $fillable = [
        'name',
        'email',
        'password',
        'level_id',
        'gender_id',
        'status_id',
        'avatar',
        'phone',
        'birth_date',
        'document',
        'address',
        'city',
        'state',
        'zip_code',
        'last_login',
        'login_count',
        'notes'
    ];

    /**
     * @var array Campos que devem ser ocultados
     */
    protected array $hidden = [
        'password',
        'remember_token'
    ];

    /**
     * Cria um novo usuário
     * 
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        // Criptografar senha
        if (isset($data['password'])) {
            $data['password'] = Security::hashPassword($data['password']);
        }

        // Definir valores padrão
        $data['status_id'] = $data['status_id'] ?? 1; // Ativo
        $data['level_id'] = $data['level_id'] ?? 11; // Usuário comum
        $data['login_count'] = 0;

        return parent::create($data);
    }

    /**
     * Atualiza um usuário
     * 
     * @param int $id
     * @param array $data
     * @return int
     */
    public function update(int $id, array $data): int
    {
        // Criptografar senha se fornecida
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Security::hashPassword($data['password']);
        } else {
            // Remove senha vazia para não sobrescrever
            unset($data['password']);
        }

        return parent::update($id, $data);
    }

    /**
     * Busca usuário por email
     * 
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->whereFirst(['email' => $email, 'deleted_at' => null]);
    }

    /**
     * Busca usuários ativos
     * 
     * @return array
     */
    public function getActive(): array
    {
        $sql = "SELECT u.*, l.translate as level_name, s.translate as status_name, s.color as status_color,
                       g.translate as gender_name
                FROM {$this->table} u
                LEFT JOIN levels l ON u.level_id = l.id
                LEFT JOIN status s ON u.status_id = s.id
                LEFT JOIN genders g ON u.gender_id = g.id
                WHERE u.deleted_at IS NULL AND s.name = 'active'
                ORDER BY u.name";
        
        return $this->database->select($sql);
    }

    /**
     * Busca usuários por nível
     * 
     * @param int $levelId
     * @return array
     */
    public function getByLevel(int $levelId): array
    {
        $sql = "SELECT u.*, l.translate as level_name, s.translate as status_name, s.color as status_color
                FROM {$this->table} u
                LEFT JOIN levels l ON u.level_id = l.id
                LEFT JOIN status s ON u.status_id = s.id
                WHERE u.level_id = :level_id AND u.deleted_at IS NULL
                ORDER BY u.name";
        
        return $this->database->select($sql, ['level_id' => $levelId]);
    }

    /**
     * Busca usuários por status
     * 
     * @param int $statusId
     * @return array
     */
    public function getByStatus(int $statusId): array
    {
        $sql = "SELECT u.*, l.translate as level_name, s.translate as status_name, s.color as status_color
                FROM {$this->table} u
                LEFT JOIN levels l ON u.level_id = l.id
                LEFT JOIN status s ON u.status_id = s.id
                WHERE u.status_id = :status_id AND u.deleted_at IS NULL
                ORDER BY u.name";
        
        return $this->database->select($sql, ['status_id' => $statusId]);
    }

    /**
     * Busca usuário com relacionamentos
     * 
     * @param int $id
     * @return array|null
     */
    public function findWithRelations(int $id): ?array
    {
        $sql = "SELECT u.*, l.translate as level_name, l.name as level_code,
                       s.translate as status_name, s.color as status_color, s.name as status_code,
                       g.translate as gender_name, g.name as gender_code
                FROM {$this->table} u
                LEFT JOIN levels l ON u.level_id = l.id
                LEFT JOIN status s ON u.status_id = s.id
                LEFT JOIN genders g ON u.gender_id = g.id
                WHERE u.id = :id AND u.deleted_at IS NULL";
        
        return $this->database->selectOne($sql, ['id' => $id]);
    }

    /**
     * Conta usuários por nível
     * 
     * @param int $levelId
     * @return int
     */
    public function countByLevel(int $levelId): int
    {
        return $this->count(['level_id' => $levelId, 'deleted_at' => null]);
    }

    /**
     * Conta usuários por status
     * 
     * @param int $statusId
     * @return int
     */
    public function countByStatus(int $statusId): int
    {
        return $this->count(['status_id' => $statusId, 'deleted_at' => null]);
    }

    /**
     * Obtém estatísticas de usuários
     * 
     * @return array
     */
    public function getStats(): array
    {
        // Total de usuários
        $total = $this->count(['deleted_at' => null]);
        
        // Por status
        $statusStats = $this->database->select("
            SELECT s.name, s.translate, COUNT(u.id) as count
            FROM status s
            LEFT JOIN users u ON s.id = u.status_id AND u.deleted_at IS NULL
            WHERE s.deleted_at IS NULL
            GROUP BY s.id, s.name, s.translate
            ORDER BY s.id
        ");

        // Por nível
        $levelStats = $this->database->select("
            SELECT l.name, l.translate, COUNT(u.id) as count
            FROM levels l
            LEFT JOIN users u ON l.id = u.level_id AND u.deleted_at IS NULL
            WHERE l.deleted_at IS NULL
            GROUP BY l.id, l.name, l.translate
            ORDER BY l.id
        ");

        // Estatísticas básicas
        $active = $this->database->selectOne("
            SELECT COUNT(u.id) as count
            FROM users u
            JOIN status s ON u.status_id = s.id
            WHERE s.name = 'active' AND u.deleted_at IS NULL
        ")['count'];

        $inactive = $total - $active;

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'by_status' => $statusStats,
            'by_level' => $levelStats
        ];
    }

    /**
     * Busca usuários com paginação e filtros
     * 
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public function paginateWithFilters(int $page = 1, int $perPage = 15, array $filters = []): array
    {
        $conditions = ['u.deleted_at IS NULL'];
        $params = [];
        
        // Filtro por nome
        if (!empty($filters['name'])) {
            $conditions[] = "u.name LIKE :name";
            $params['name'] = '%' . $filters['name'] . '%';
        }
        
        // Filtro por email
        if (!empty($filters['email'])) {
            $conditions[] = "u.email LIKE :email";
            $params['email'] = '%' . $filters['email'] . '%';
        }
        
        // Filtro por nível
        if (!empty($filters['level_id'])) {
            $conditions[] = "u.level_id = :level_id";
            $params['level_id'] = $filters['level_id'];
        }
        
        // Filtro por status
        if (!empty($filters['status_id'])) {
            $conditions[] = "u.status_id = :status_id";
            $params['status_id'] = $filters['status_id'];
        }

        // Filtro por gênero
        if (!empty($filters['gender_id'])) {
            $conditions[] = "u.gender_id = :gender_id";
            $params['gender_id'] = $filters['gender_id'];
        }

        $offset = ($page - 1) * $perPage;
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        
        // Buscar dados
        $sql = "SELECT u.id, u.name, u.email, u.phone, u.created_at, u.last_login, u.login_count,
                       l.translate as level_name, l.name as level_code,
                       s.translate as status_name, s.color as status_color, s.name as status_code,
                       g.translate as gender_name
                FROM {$this->table} u
                LEFT JOIN levels l ON u.level_id = l.id
                LEFT JOIN status s ON u.status_id = s.id
                LEFT JOIN genders g ON u.gender_id = g.id
                {$whereClause} 
                ORDER BY u.created_at DESC 
                LIMIT {$perPage} OFFSET {$offset}";
        
        $data = $this->database->select($sql, $params);
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} u
                     LEFT JOIN levels l ON u.level_id = l.id
                     LEFT JOIN status s ON u.status_id = s.id
                     LEFT JOIN genders g ON u.gender_id = g.id
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
     * Altera status do usuário
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
     * Atualiza último login
     * 
     * @param int $id
     * @return int
     */
    public function updateLastLogin(int $id): int
    {
        $user = $this->find($id);
        
        return $this->update($id, [
            'last_login' => date('Y-m-d H:i:s'),
            'login_count' => ($user['login_count'] ?? 0) + 1
        ]);
    }

    /**
     * Remove dados sensíveis do usuário
     * 
     * @param array $user
     * @return array
     */
    public function sanitizeUser(array $user): array
    {
        return $this->hideFields($user);
    }

    /**
     * Verifica se email já existe
     * 
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE email = :email AND deleted_at IS NULL AND id != :id";
            $result = $this->database->selectOne($sql, ['email' => $email, 'id' => $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE email = :email AND deleted_at IS NULL";
            $result = $this->database->selectOne($sql, ['email' => $email]);
        }
        
        return $result['count'] > 0;
    }

    /**
     * Verifica se documento já existe
     * 
     * @param string $document
     * @param int|null $excludeId
     * @return bool
     */
    public function documentExists(string $document, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE document = :document AND deleted_at IS NULL AND id != :id";
            $result = $this->database->selectOne($sql, ['document' => $document, 'id' => $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE document = :document AND deleted_at IS NULL";
            $result = $this->database->selectOne($sql, ['document' => $document]);
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
     * Restaurar usuário
     * 
     * @param int $id
     * @return int
     */
    public function restore(int $id): int
    {
        return $this->update($id, ['deleted_at' => null]);
    }

    /**
     * Busca usuários deletados
     * 
     * @return array
     */
    public function getDeleted(): array
    {
        $sql = "SELECT u.*, l.translate as level_name, s.translate as status_name
                FROM {$this->table} u
                LEFT JOIN levels l ON u.level_id = l.id
                LEFT JOIN status s ON u.status_id = s.id
                WHERE u.deleted_at IS NOT NULL
                ORDER BY u.deleted_at DESC";
        
        return $this->database->select($sql);
    }
}