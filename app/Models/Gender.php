<?php

namespace App\Models;

use Core\Model;

/**
 * Model de gênero
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.1.0
 */
class Gender extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'genders';

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
     * Busca gêneros ativos
     * 
     * @return array
     */
    public function getActive(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE deleted_at IS NULL ORDER BY translate";
        return $this->database->select($sql);
    }

    /**
     * Busca gênero por nome
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
        $genders = $this->getActive();
        $options = [];
        
        foreach ($genders as $gender) {
            $options[$gender['id']] = $gender['translate'];
        }
        
        return $options;
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
        $conditions = ['name' => $name, 'deleted_at' => null];
        
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