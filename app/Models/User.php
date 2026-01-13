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
        'alias',
        'email',
        'cpf',
        'birth_date',
        'gender_id',
        'phone_home',
        'phone_mobile',
        'phone_message',
        'photo',
        'username',
        'password',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires',
        'google_calendar_id',
        'message_signature',
        'signature_include_logo',
        'permissions_updated_at',
        'unique_code',
        'session_token',
        'last_access',
        'password_reset_token',
        'password_reset_expires',
        'level_id',
        'status_id',
        'register_id'
    ];

    /**
     * @var array Campos que devem ser ocultados
     */
    protected array $hidden = [
        'password',
        'google_access_token',
        'google_refresh_token',
        'session_token',
        'password_reset_token'
    ];

    /**
     * @var array Campos de data que devem ser convertidos
     */
    protected array $dates = ['dh', 'dh_update', 'deleted_at', 'birth_date', 'last_access', 'google_token_expires', 'password_reset_expires', 'permissions_updated_at'];

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

        // Gerar código único se não fornecido
        if (!isset($data['unique_code'])) {
            $data['unique_code'] = $this->generateUniqueCode();
        }

        // Gerar username se não fornecido
        if (!isset($data['username'])) {
            $data['username'] = $this->generateUsername($data['name']);
        }

        // Definir valores padrão
        $data['status_id'] = $data['status_id'] ?? 1; // Ativo
        $data['level_id'] = $data['level_id'] ?? 11; // Usuário comum
        $data['signature_include_logo'] = $data['signature_include_logo'] ?? false;

        // Formatar CPF
        if (isset($data['cpf'])) {
            $data['cpf'] = $this->formatCpf($data['cpf']);
        }

        // Formatar telefones
        if (isset($data['phone_home'])) {
            $data['phone_home'] = $this->formatPhone($data['phone_home']);
        }
        if (isset($data['phone_mobile'])) {
            $data['phone_mobile'] = $this->formatPhone($data['phone_mobile']);
        }
        if (isset($data['phone_message'])) {
            $data['phone_message'] = $this->formatPhone($data['phone_message']);
        }

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

        // Formatar CPF se fornecido
        if (isset($data['cpf'])) {
            $data['cpf'] = $this->formatCpf($data['cpf']);
        }

        // Formatar telefones se fornecidos
        if (isset($data['phone_home'])) {
            $data['phone_home'] = $this->formatPhone($data['phone_home']);
        }
        if (isset($data['phone_mobile'])) {
            $data['phone_mobile'] = $this->formatPhone($data['phone_mobile']);
        }
        if (isset($data['phone_message'])) {
            $data['phone_message'] = $this->formatPhone($data['phone_message']);
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

    /**
     * Busca usuário por username
     * 
     * @param string $username
     * @return array|null
     */
    public function findByUsername(string $username): ?array
    {
        return $this->whereFirst(['username' => $username, 'deleted_at' => null]);
    }

    /**
     * Busca usuário por CPF
     * 
     * @param string $cpf
     * @return array|null
     */
    public function findByCpf(string $cpf): ?array
    {
        $cpf = $this->formatCpf($cpf);
        return $this->whereFirst(['cpf' => $cpf, 'deleted_at' => null]);
    }

    /**
     * Busca usuário por código único
     * 
     * @param string $uniqueCode
     * @return array|null
     */
    public function findByUniqueCode(string $uniqueCode): ?array
    {
        return $this->whereFirst(['unique_code' => $uniqueCode, 'deleted_at' => null]);
    }

    /**
     * Gera código único
     * 
     * @return string
     */
    public function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Security::generateRandomString(8));
        } while ($this->findByUniqueCode($code));
        
        return $code;
    }

    /**
     * Gera username único baseado no nome
     * 
     * @param string $name
     * @return string
     */
    public function generateUsername(string $name): string
    {
        // Remover acentos e caracteres especiais
        $username = $this->removeAccents(strtolower($name));
        $username = preg_replace('/[^a-z0-9]/', '', $username);
        $username = substr($username, 0, 15);
        
        // Verificar se já existe
        $originalUsername = $username;
        $counter = 1;
        
        while ($this->findByUsername($username)) {
            $username = $originalUsername . $counter;
            $counter++;
            
            // Limitar tamanho
            if (strlen($username) > 20) {
                $username = substr($originalUsername, 0, 17) . $counter;
            }
        }
        
        return $username;
    }

    /**
     * Remove acentos de uma string
     * 
     * @param string $string
     * @return string
     */
    private function removeAccents(string $string): string
    {
        $accents = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n'
        ];
        
        return strtr($string, $accents);
    }

    /**
     * Formata CPF
     * 
     * @param string $cpf
     * @return string
     */
    public function formatCpf(string $cpf): string
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        
        if (strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        }
        
        return $cpf;
    }

    /**
     * Formata telefone
     * 
     * @param string $phone
     * @return string
     */
    public function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        
        if (strlen($phone) === 11) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
        } elseif (strlen($phone) === 10) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
        }
        
        return $phone;
    }

    /**
     * Valida CPF
     * 
     * @param string $cpf
     * @return bool
     */
    public function validateCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        
        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Verifica se CPF já existe
     * 
     * @param string $cpf
     * @param int|null $excludeId
     * @return bool
     */
    public function cpfExists(string $cpf, ?int $excludeId = null): bool
    {
        $cpf = $this->formatCpf($cpf);
        
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE cpf = :cpf AND deleted_at IS NULL AND id != :id";
            $result = $this->database->selectOne($sql, ['cpf' => $cpf, 'id' => $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE cpf = :cpf AND deleted_at IS NULL";
            $result = $this->database->selectOne($sql, ['cpf' => $cpf]);
        }
        
        return $result['count'] > 0;
    }

    /**
     * Verifica se username já existe
     * 
     * @param string $username
     * @param int|null $excludeId
     * @return bool
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE username = :username AND deleted_at IS NULL AND id != :id";
            $result = $this->database->selectOne($sql, ['username' => $username, 'id' => $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE username = :username AND deleted_at IS NULL";
            $result = $this->database->selectOne($sql, ['username' => $username]);
        }
        
        return $result['count'] > 0;
    }

    /**
     * Atualiza último acesso
     * 
     * @param int $id
     * @return int
     */
    public function updateLastAccess(int $id): int
    {
        return $this->update($id, [
            'last_access' => date('Y-m-d H:i:s'),
            'session_token' => Security::generateRandomString(64)
        ]);
    }

    /**
     * Gera token de reset de senha
     * 
     * @param int $id
     * @return string
     */
    public function generatePasswordResetToken(int $id): string
    {
        $token = Security::generateRandomString(64);
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $this->update($id, [
            'password_reset_token' => $token,
            'password_reset_expires' => $expires
        ]);
        
        return $token;
    }

    /**
     * Valida token de reset de senha
     * 
     * @param string $token
     * @return array|null
     */
    public function validatePasswordResetToken(string $token): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE password_reset_token = :token 
                AND password_reset_expires > NOW() 
                AND deleted_at IS NULL";
        
        return $this->database->selectOne($sql, ['token' => $token]);
    }

    /**
     * Limpa token de reset de senha
     * 
     * @param int $id
     * @return int
     */
    public function clearPasswordResetToken(int $id): int
    {
        return $this->update($id, [
            'password_reset_token' => null,
            'password_reset_expires' => null
        ]);
    }

    /**
     * Atualiza integração com Google
     * 
     * @param int $id
     * @param array $googleData
     * @return int
     */
    public function updateGoogleIntegration(int $id, array $googleData): int
    {
        return $this->update($id, [
            'google_access_token' => $googleData['access_token'] ?? null,
            'google_refresh_token' => $googleData['refresh_token'] ?? null,
            'google_token_expires' => $googleData['expires_at'] ?? null,
            'google_calendar_id' => $googleData['calendar_id'] ?? null
        ]);
    }

    /**
     * Verifica se usuário tem integração com Google ativa
     * 
     * @param int $id
     * @return bool
     */
    public function hasActiveGoogleIntegration(int $id): bool
    {
        $user = $this->find($id);
        
        if (!$user || !$user['google_access_token']) {
            return false;
        }
        
        if ($user['google_token_expires']) {
            return strtotime($user['google_token_expires']) > time();
        }
        
        return true;
    }
}