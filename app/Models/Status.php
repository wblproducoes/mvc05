<?php

namespace App\Models;

use Core\Model;

/**
 * Model de status
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.1.0
 */
class Status extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'status';

    /**
     * @var array Campos que podem ser preenchidos em massa
     */
    protected array $fillable = [
        'name',
        'translate',
        'color',
        'description'
    ];

    /**
     * @var array Campos de data que devem ser convertidos
     */
    protected array $dates = ['dh', 'dh_update', 'deleted_at'];

    /**
     * Busca status ativos
     * 
     * @return array
     */
    public function getActive(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE deleted_at IS NULL ORDER BY translate";
        return $this->database->select($sql);
    }

    /**
     * Busca status por nome
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
        $statuses = $this->getActive();
        $options = [];
        
        foreach ($statuses as $status) {
            $options[$status['id']] = $status['translate'];
        }
        
        return $options;
    }

    /**
     * Obtém status com cores para badges
     * 
     * @return array
     */
    public function getWithColors(): array
    {
        $statuses = $this->getActive();
        $options = [];
        
        foreach ($statuses as $status) {
            $options[$status['id']] = [
                'name' => $status['translate'],
                'color' => $status['color'],
                'description' => $status['description']
            ];
        }
        
        return $options;
    }

    /**
     * Obtém cor do status
     * 
     * @param int $statusId
     * @return string
     */
    public function getColor(int $statusId): string
    {
        $status = $this->find($statusId);
        return $status ? $status['color'] : 'secondary';
    }

    /**
     * Obtém tradução do status
     * 
     * @param int $statusId
     * @return string
     */
    public function getTranslate(int $statusId): string
    {
        $status = $this->find($statusId);
        return $status ? $status['translate'] : 'Desconhecido';
    }

    /**
     * Verifica se status é ativo
     * 
     * @param int $statusId
     * @return bool
     */
    public function isActive(int $statusId): bool
    {
        $status = $this->find($statusId);
        return $status && $status['name'] === 'active';
    }

    /**
     * Verifica se status é inativo
     * 
     * @param int $statusId
     * @return bool
     */
    public function isInactive(int $statusId): bool
    {
        $status = $this->find($statusId);
        return $status && in_array($status['name'], ['inactive', 'blocked', 'suspended']);
    }

    /**
     * Verifica se status é deletado
     * 
     * @param int $statusId
     * @return bool
     */
    public function isDeleted(int $statusId): bool
    {
        $status = $this->find($statusId);
        return $status && $status['name'] === 'deleted';
    }

    /**
     * Obtém status padrão (ativo)
     * 
     * @return int
     */
    public function getDefaultId(): int
    {
        $status = $this->findByName('active');
        return $status ? $status['id'] : 1;
    }

    /**
     * Obtém ID do status por nome
     * 
     * @param string $name
     * @return int|null
     */
    public function getIdByName(string $name): ?int
    {
        $status = $this->findByName($name);
        return $status ? $status['id'] : null;
    }

    /**
     * Obtém status para usuários
     * 
     * @return array
     */
    public function getForUsers(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE name IN ('active', 'inactive', 'blocked', 'suspended') 
                AND deleted_at IS NULL 
                ORDER BY id";
        
        return $this->database->select($sql);
    }

    /**
     * Obtém estatísticas de uso dos status
     * 
     * @param string $table Tabela para verificar
     * @param string $column Coluna do status
     * @return array
     */
    public function getUsageStats(string $table = 'users', string $column = 'status_id'): array
    {
        $sql = "SELECT s.id, s.name, s.translate, s.color, COUNT(t.{$column}) as count
                FROM {$this->table} s
                LEFT JOIN {$table} t ON s.id = t.{$column} AND t.deleted_at IS NULL
                WHERE s.deleted_at IS NULL
                GROUP BY s.id, s.name, s.translate, s.color
                ORDER BY s.id";
        
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