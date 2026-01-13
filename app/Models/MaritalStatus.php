<?php

namespace App\Models;

use Core\Model;

/**
 * Model de estado civil
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.2.0
 */
class MaritalStatus extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'marital_status';

    /**
     * @var array Campos que podem ser preenchidos em massa
     */
    protected array $fillable = [
        'name',
        'translate',
        'sort_order',
        'ativo'
    ];

    /**
     * Busca estados civis ativos
     * 
     * @return array
     */
    public function getActive(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE ativo = 1 AND deleted_at IS NULL 
                ORDER BY sort_order, translate";
        return $this->database->select($sql);
    }

    /**
     * Busca estado civil por nome
     * 
     * @param string $name
     * @return array|null
     */
    public function findByName(string $name): ?array
    {
        return $this->whereFirst(['name' => $name, 'ativo' => 1, 'deleted_at' => null]);
    }

    /**
     * Obtém lista para select
     * 
     * @return array
     */
    public function getForSelect(): array
    {
        $maritalStatuses = $this->getActive();
        $options = [];
        
        foreach ($maritalStatuses as $status) {
            $options[$status['id']] = $status['translate'];
        }
        
        return $options;
    }

    /**
     * Obtém estados civis que indicam relacionamento
     * 
     * @return array
     */
    public function getInRelationship(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE name IN ('married', 'common_law', 'stable_union') 
                AND ativo = 1 AND deleted_at IS NULL 
                ORDER BY sort_order";
        
        return $this->database->select($sql);
    }

    /**
     * Obtém estados civis que indicam solteiro
     * 
     * @return array
     */
    public function getSingle(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE name IN ('single', 'separated', 'legally_separated', 'divorced', 'widowed') 
                AND ativo = 1 AND deleted_at IS NULL 
                ORDER BY sort_order";
        
        return $this->database->select($sql);
    }

    /**
     * Verifica se estado civil indica relacionamento
     * 
     * @param int $maritalStatusId
     * @return bool
     */
    public function isInRelationship(int $maritalStatusId): bool
    {
        $status = $this->find($maritalStatusId);
        
        if (!$status) {
            return false;
        }
        
        $relationshipStatuses = ['married', 'common_law', 'stable_union'];
        
        return in_array($status['name'], $relationshipStatuses);
    }

    /**
     * Ativa/desativa estado civil
     * 
     * @param int $id
     * @param bool $ativo
     * @return int
     */
    public function toggleActive(int $id, bool $ativo): int
    {
        return $this->update($id, ['ativo' => $ativo]);
    }

    /**
     * Atualiza ordem de exibição
     * 
     * @param int $id
     * @param int $sortOrder
     * @return int
     */
    public function updateSortOrder(int $id, int $sortOrder): int
    {
        return $this->update($id, ['sort_order' => $sortOrder]);
    }

    /**
     * Obtém próxima ordem disponível
     * 
     * @return int
     */
    public function getNextSortOrder(): int
    {
        $sql = "SELECT MAX(sort_order) as max_order FROM {$this->table} WHERE deleted_at IS NULL";
        $result = $this->database->selectOne($sql);
        
        return ((int) $result['max_order']) + 1;
    }

    /**
     * Obtém estatísticas de uso
     * 
     * @param string $entityTable Tabela para verificar (ex: users, students)
     * @param string $column Coluna do estado civil
     * @return array
     */
    public function getUsageStats(string $entityTable = 'users', string $column = 'marital_status_id'): array
    {
        $sql = "SELECT ms.id, ms.name, ms.translate, COUNT(e.{$column}) as count
                FROM {$this->table} ms
                LEFT JOIN {$entityTable} e ON ms.id = e.{$column} AND e.deleted_at IS NULL
                WHERE ms.deleted_at IS NULL
                GROUP BY ms.id, ms.name, ms.translate
                ORDER BY ms.sort_order";
        
        return $this->database->select($sql);
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

    /**
     * Reordena todos os estados civis
     * 
     * @param array $order Array com IDs na ordem desejada
     * @return bool
     */
    public function reorder(array $order): bool
    {
        try {
            $this->database->beginTransaction();
            
            foreach ($order as $index => $id) {
                $this->updateSortOrder($id, $index + 1);
            }
            
            $this->database->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->database->rollback();
            return false;
        }
    }
}