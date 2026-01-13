<?php

namespace App\Models;

use Core\Model;

/**
 * Model de nível de acesso
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.1.0
 */
class Level extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'levels';

    /**
     * @var array Campos que podem ser preenchidos em massa
     */
    protected array $fillable = [
        'name',
        'translate',
        'description'
    ];

    /**
     * @var array Campos de data que devem ser convertidos
     */
    protected array $dates = ['dh', 'dh_update', 'deleted_at'];

    /**
     * Busca níveis ativos
     * 
     * @return array
     */
    public function getActive(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE deleted_at IS NULL ORDER BY id";
        return $this->database->select($sql);
    }

    /**
     * Busca nível por nome
     * 
     * @param string $name
     * @return array|null
     */
    public function findByName(string $name): ?array
    {
        return $this->whereFirst(['name' => $name, 'deleted_at' => null]);
    }

    /**
     * Obtém lista para select
     * 
     * @return array
     */
    public function getForSelect(): array
    {
        $levels = $this->getActive();
        $options = [];
        
        foreach ($levels as $level) {
            $options[$level['id']] = $level['translate'];
        }
        
        return $options;
    }

    /**
     * Obtém níveis administrativos
     * 
     * @return array
     */
    public function getAdministrative(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE name IN ('master', 'admin', 'direction', 'financial', 'coordination', 'secretary') 
                AND deleted_at IS NULL 
                ORDER BY id";
        
        return $this->database->select($sql);
    }

    /**
     * Obtém níveis educacionais
     * 
     * @return array
     */
    public function getEducational(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE name IN ('teacher', 'student', 'guardian') 
                AND deleted_at IS NULL 
                ORDER BY id";
        
        return $this->database->select($sql);
    }

    /**
     * Verifica se é nível administrativo
     * 
     * @param int $levelId
     * @return bool
     */
    public function isAdministrative(int $levelId): bool
    {
        $level = $this->find($levelId);
        
        if (!$level) {
            return false;
        }
        
        $adminLevels = ['master', 'admin', 'direction', 'financial', 'coordination', 'secretary'];
        
        return in_array($level['name'], $adminLevels);
    }

    /**
     * Verifica se é master
     * 
     * @param int $levelId
     * @return bool
     */
    public function isMaster(int $levelId): bool
    {
        $level = $this->find($levelId);
        return $level && $level['name'] === 'master';
    }

    /**
     * Verifica se é admin ou superior
     * 
     * @param int $levelId
     * @return bool
     */
    public function isAdminOrAbove(int $levelId): bool
    {
        $level = $this->find($levelId);
        
        if (!$level) {
            return false;
        }
        
        return in_array($level['name'], ['master', 'admin']);
    }

    /**
     * Obtém hierarquia de níveis
     * 
     * @return array
     */
    public function getHierarchy(): array
    {
        return [
            1 => 'master',
            2 => 'admin',
            3 => 'direction',
            4 => 'financial',
            5 => 'coordination',
            6 => 'secretary',
            7 => 'teacher',
            8 => 'employee',
            9 => 'student',
            10 => 'guardian',
            11 => 'user'
        ];
    }

    /**
     * Verifica se nível tem permissão superior a outro
     * 
     * @param int $levelId1
     * @param int $levelId2
     * @return bool
     */
    public function hasHigherPermission(int $levelId1, int $levelId2): bool
    {
        return $levelId1 < $levelId2; // Menor ID = maior permissão
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
     * Restaurar registro
     * 
     * @param int $id
     * @return int
     */
    public function restore(int $id): int
    {
        return $this->update($id, ['deleted_at' => null]);
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
}