<?php

namespace App\Models;

use Core\Model;

/**
 * Model de endereços (genérico)
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.2.0
 */
class Address extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'addresses';

    /**
     * @var array Campos que podem ser preenchidos em massa
     */
    protected array $fillable = [
        'entity_type',
        'entity_id',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'is_primary'
    ];

    /**
     * Busca endereços de uma entidade
     * 
     * @param string $entityType
     * @param int $entityId
     * @return array
     */
    public function getByEntity(string $entityType, int $entityId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE entity_type = :entity_type AND entity_id = :entity_id 
                AND deleted_at IS NULL 
                ORDER BY is_primary DESC, created_at";
        
        return $this->database->select($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
    }

    /**
     * Busca endereço principal de uma entidade
     * 
     * @param string $entityType
     * @param int $entityId
     * @return array|null
     */
    public function getPrimaryByEntity(string $entityType, int $entityId): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE entity_type = :entity_type AND entity_id = :entity_id 
                AND is_primary = 1 AND deleted_at IS NULL 
                LIMIT 1";
        
        return $this->database->selectOne($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
    }

    /**
     * Cria endereço para uma entidade
     * 
     * @param string $entityType
     * @param int $entityId
     * @param array $addressData
     * @param bool $isPrimary
     * @return int
     */
    public function createForEntity(string $entityType, int $entityId, array $addressData, bool $isPrimary = false): int
    {
        // Se for endereço principal, remover flag de outros endereços
        if ($isPrimary) {
            $this->removePrimaryFlag($entityType, $entityId);
        }

        $data = array_merge($addressData, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_primary' => $isPrimary
        ]);

        return $this->create($data);
    }

    /**
     * Define endereço como principal
     * 
     * @param int $addressId
     * @param string $entityType
     * @param int $entityId
     * @return int
     */
    public function setPrimary(int $addressId, string $entityType, int $entityId): int
    {
        // Remover flag primary de outros endereços
        $this->removePrimaryFlag($entityType, $entityId);
        
        // Definir como principal
        return $this->update($addressId, ['is_primary' => 1]);
    }

    /**
     * Remove flag de endereço principal
     * 
     * @param string $entityType
     * @param int $entityId
     * @return void
     */
    private function removePrimaryFlag(string $entityType, int $entityId): void
    {
        $sql = "UPDATE {$this->table} 
                SET is_primary = 0 
                WHERE entity_type = :entity_type AND entity_id = :entity_id 
                AND deleted_at IS NULL";
        
        $this->database->update($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
    }

    /**
     * Busca endereços por CEP
     * 
     * @param string $cep
     * @return array
     */
    public function getByCep(string $cep): array
    {
        $cep = preg_replace('/\D/', '', $cep); // Remove caracteres não numéricos
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE cep = :cep AND deleted_at IS NULL 
                ORDER BY created_at DESC";
        
        return $this->database->select($sql, ['cep' => $cep]);
    }

    /**
     * Busca endereços por cidade
     * 
     * @param string $cidade
     * @param string|null $uf
     * @return array
     */
    public function getByCidade(string $cidade, ?string $uf = null): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE cidade LIKE :cidade";
        
        $params = ['cidade' => "%{$cidade}%"];
        
        if ($uf) {
            $sql .= " AND uf = :uf";
            $params['uf'] = $uf;
        }
        
        $sql .= " AND deleted_at IS NULL ORDER BY cidade, bairro";
        
        return $this->database->select($sql, $params);
    }

    /**
     * Obtém estatísticas de endereços por estado
     * 
     * @return array
     */
    public function getStatsByUf(): array
    {
        $sql = "SELECT uf, COUNT(*) as count 
                FROM {$this->table} 
                WHERE uf IS NOT NULL AND deleted_at IS NULL 
                GROUP BY uf 
                ORDER BY count DESC, uf";
        
        return $this->database->select($sql);
    }

    /**
     * Obtém estatísticas de endereços por cidade
     * 
     * @param string|null $uf
     * @param int $limit
     * @return array
     */
    public function getStatsByCidade(?string $uf = null, int $limit = 10): array
    {
        $sql = "SELECT cidade, uf, COUNT(*) as count 
                FROM {$this->table} 
                WHERE cidade IS NOT NULL AND deleted_at IS NULL";
        
        $params = [];
        
        if ($uf) {
            $sql .= " AND uf = :uf";
            $params['uf'] = $uf;
        }
        
        $sql .= " GROUP BY cidade, uf 
                  ORDER BY count DESC, cidade 
                  LIMIT {$limit}";
        
        return $this->database->select($sql, $params);
    }

    /**
     * Formata endereço completo
     * 
     * @param array $address
     * @return string
     */
    public function formatAddress(array $address): string
    {
        $parts = [];
        
        if (!empty($address['logradouro'])) {
            $logradouro = $address['logradouro'];
            
            if (!empty($address['numero'])) {
                $logradouro .= ', ' . $address['numero'];
            }
            
            if (!empty($address['complemento'])) {
                $logradouro .= ' - ' . $address['complemento'];
            }
            
            $parts[] = $logradouro;
        }
        
        if (!empty($address['bairro'])) {
            $parts[] = $address['bairro'];
        }
        
        if (!empty($address['cidade'])) {
            $cidade = $address['cidade'];
            
            if (!empty($address['uf'])) {
                $cidade .= '/' . $address['uf'];
            }
            
            $parts[] = $cidade;
        }
        
        if (!empty($address['cep'])) {
            $parts[] = 'CEP: ' . $this->formatCep($address['cep']);
        }
        
        return implode(' - ', $parts);
    }

    /**
     * Formata CEP
     * 
     * @param string $cep
     * @return string
     */
    public function formatCep(string $cep): string
    {
        $cep = preg_replace('/\D/', '', $cep);
        
        if (strlen($cep) === 8) {
            return substr($cep, 0, 5) . '-' . substr($cep, 5);
        }
        
        return $cep;
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
}