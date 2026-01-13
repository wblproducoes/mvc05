<?php

namespace App\Models;

use Core\Model;

/**
 * Model de tipos de evento de acesso
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.2.0
 */
class EventType extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'event_types';

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
     * Busca tipos de evento ativos
     * 
     * @return array
     */
    public function getActive(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE deleted_at IS NULL ORDER BY translate";
        return $this->database->select($sql);
    }

    /**
     * Busca tipo de evento por nome
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
        $eventTypes = $this->getActive();
        $options = [];
        
        foreach ($eventTypes as $eventType) {
            $options[$eventType['id']] = $eventType['translate'];
        }
        
        return $options;
    }

    /**
     * Obtém tipos de evento de segurança
     * 
     * @return array
     */
    public function getSecurityEvents(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE name IN ('login', 'logout', 'failed_login', 'password_reset', 'password_changed', 'account_locked') 
                AND deleted_at IS NULL 
                ORDER BY id";
        
        return $this->database->select($sql);
    }

    /**
     * Obtém tipos de evento de usuário
     * 
     * @return array
     */
    public function getUserEvents(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE name IN ('user_created', 'user_updated', 'user_deleted', 'profile_update') 
                AND deleted_at IS NULL 
                ORDER BY id";
        
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
}