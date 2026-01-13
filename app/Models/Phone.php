<?php

namespace App\Models;

use Core\Model;

/**
 * Model de telefones (genérico)
 * 
 * @package App\Models
 * @author Sistema Administrativo
 * @version 1.2.0
 */
class Phone extends Model
{
    /**
     * @var string Nome da tabela
     */
    protected string $table = 'phones';

    /**
     * @var array Campos que podem ser preenchidos em massa
     */
    protected array $fillable = [
        'entity_type',
        'entity_id',
        'phone_type_id',
        'numero',
        'obs',
        'is_primary'
    ];

    /**
     * Busca telefones de uma entidade
     * 
     * @param string $entityType
     * @param int $entityId
     * @return array
     */
    public function getByEntity(string $entityType, int $entityId): array
    {
        $sql = "SELECT p.*, pt.translate as phone_type_name 
                FROM {$this->table} p
                LEFT JOIN phone_types pt ON p.phone_type_id = pt.id
                WHERE p.entity_type = :entity_type AND p.entity_id = :entity_id 
                AND p.deleted_at IS NULL 
                ORDER BY p.is_primary DESC, pt.sort_order, p.created_at";
        
        return $this->database->select($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
    }

    /**
     * Busca telefone principal de uma entidade
     * 
     * @param string $entityType
     * @param int $entityId
     * @return array|null
     */
    public function getPrimaryByEntity(string $entityType, int $entityId): ?array
    {
        $sql = "SELECT p.*, pt.translate as phone_type_name 
                FROM {$this->table} p
                LEFT JOIN phone_types pt ON p.phone_type_id = pt.id
                WHERE p.entity_type = :entity_type AND p.entity_id = :entity_id 
                AND p.is_primary = 1 AND p.deleted_at IS NULL 
                LIMIT 1";
        
        return $this->database->selectOne($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
    }

    /**
     * Busca telefones por tipo
     * 
     * @param string $entityType
     * @param int $entityId
     * @param int $phoneTypeId
     * @return array
     */
    public function getByType(string $entityType, int $entityId, int $phoneTypeId): array
    {
        $sql = "SELECT p.*, pt.translate as phone_type_name 
                FROM {$this->table} p
                LEFT JOIN phone_types pt ON p.phone_type_id = pt.id
                WHERE p.entity_type = :entity_type AND p.entity_id = :entity_id 
                AND p.phone_type_id = :phone_type_id AND p.deleted_at IS NULL 
                ORDER BY p.is_primary DESC, p.created_at";
        
        return $this->database->select($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'phone_type_id' => $phoneTypeId
        ]);
    }

    /**
     * Cria telefone para uma entidade
     * 
     * @param string $entityType
     * @param int $entityId
     * @param array $phoneData
     * @param bool $isPrimary
     * @return int
     */
    public function createForEntity(string $entityType, int $entityId, array $phoneData, bool $isPrimary = false): int
    {
        // Se for telefone principal, remover flag de outros telefones
        if ($isPrimary) {
            $this->removePrimaryFlag($entityType, $entityId);
        }

        $data = array_merge($phoneData, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_primary' => $isPrimary,
            'numero' => $this->formatPhone($phoneData['numero'])
        ]);

        return $this->create($data);
    }

    /**
     * Define telefone como principal
     * 
     * @param int $phoneId
     * @param string $entityType
     * @param int $entityId
     * @return int
     */
    public function setPrimary(int $phoneId, string $entityType, int $entityId): int
    {
        // Remover flag primary de outros telefones
        $this->removePrimaryFlag($entityType, $entityId);
        
        // Definir como principal
        return $this->update($phoneId, ['is_primary' => 1]);
    }

    /**
     * Remove flag de telefone principal
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
     * Busca telefones por número
     * 
     * @param string $numero
     * @return array
     */
    public function getByNumber(string $numero): array
    {
        $numero = $this->cleanPhone($numero);
        
        $sql = "SELECT p.*, pt.translate as phone_type_name 
                FROM {$this->table} p
                LEFT JOIN phone_types pt ON p.phone_type_id = pt.id
                WHERE p.numero LIKE :numero AND p.deleted_at IS NULL 
                ORDER BY p.created_at DESC";
        
        return $this->database->select($sql, ['numero' => "%{$numero}%"]);
    }

    /**
     * Obtém estatísticas de telefones por tipo
     * 
     * @return array
     */
    public function getStatsByType(): array
    {
        $sql = "SELECT pt.translate as type_name, COUNT(p.id) as count 
                FROM phone_types pt
                LEFT JOIN {$this->table} p ON pt.id = p.phone_type_id AND p.deleted_at IS NULL
                WHERE pt.deleted_at IS NULL
                GROUP BY pt.id, pt.translate
                ORDER BY count DESC, pt.sort_order";
        
        return $this->database->select($sql);
    }

    /**
     * Formata número de telefone
     * 
     * @param string $phone
     * @return string
     */
    public function formatPhone(string $phone): string
    {
        // Remove caracteres não numéricos
        $phone = preg_replace('/\D/', '', $phone);
        
        // Formatar baseado no tamanho
        $length = strlen($phone);
        
        if ($length === 11) {
            // Celular com 9 dígitos: (11) 99999-9999
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
        } elseif ($length === 10) {
            // Fixo: (11) 9999-9999
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
        } elseif ($length === 9) {
            // Celular sem DDD: 99999-9999
            return substr($phone, 0, 5) . '-' . substr($phone, 5);
        } elseif ($length === 8) {
            // Fixo sem DDD: 9999-9999
            return substr($phone, 0, 4) . '-' . substr($phone, 4);
        }
        
        return $phone;
    }

    /**
     * Limpa número de telefone (apenas números)
     * 
     * @param string $phone
     * @return string
     */
    public function cleanPhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * Verifica se é número de celular
     * 
     * @param string $phone
     * @return bool
     */
    public function isMobile(string $phone): bool
    {
        $clean = $this->cleanPhone($phone);
        
        // Celular tem 11 dígitos (com DDD) ou 9 dígitos (sem DDD)
        // E o primeiro dígito após o DDD deve ser 9
        if (strlen($clean) === 11) {
            return $clean[2] === '9';
        } elseif (strlen($clean) === 9) {
            return $clean[0] === '9';
        }
        
        return false;
    }

    /**
     * Verifica se é WhatsApp (baseado no tipo)
     * 
     * @param int $phoneId
     * @return bool
     */
    public function isWhatsApp(int $phoneId): bool
    {
        $phone = $this->find($phoneId);
        
        if (!$phone) {
            return false;
        }
        
        $phoneTypeModel = new PhoneType();
        $phoneType = $phoneTypeModel->find($phone['phone_type_id']);
        
        return $phoneType && $phoneType['name'] === 'whatsapp';
    }

    /**
     * Gera link do WhatsApp
     * 
     * @param string $phone
     * @param string|null $message
     * @return string
     */
    public function generateWhatsAppLink(string $phone, ?string $message = null): string
    {
        $cleanPhone = $this->cleanPhone($phone);
        
        // Adicionar código do país se não tiver
        if (strlen($cleanPhone) === 11 || strlen($cleanPhone) === 10) {
            $cleanPhone = '55' . $cleanPhone;
        }
        
        $url = "https://wa.me/{$cleanPhone}";
        
        if ($message) {
            $url .= '?text=' . urlencode($message);
        }
        
        return $url;
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