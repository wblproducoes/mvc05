<?php

namespace App\Models;

use Core\Model;

/**
 * Model de tipos de telefone
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.2.0
 */
class PhoneType extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'phone_types';

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
     * Busca tipos de telefone ativos
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
     * Busca tipo de telefone por nome
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
        $phoneTypes = $this->getActive();
        $options = [];
        
        foreach ($phoneTypes as $phoneType) {
            $options[$phoneType['id']] = $phoneType['translate'];
        }
        
        return $options;
    }

    /**
     * Ativa/desativa tipo de telefone
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
     * Reordena todos os tipos de telefone
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