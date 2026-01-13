<?php

namespace Core;

/**
 * Classe utilitária para gerenciamento de prefixos de tabelas
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.4.1
 */
class TablePrefix
{
    /**
     * @var string Prefixo atual das tabelas
     */
    private static string $prefix = '';

    /**
     * @var array Lista de tabelas do sistema
     */
    private static array $systemTables = [
        'users',
        'genders', 
        'levels', 
        'status',
        'activity_logs',
        'settings',
        'password_resets',
        'sessions',
        'notifications',
        'event_types',
        'phone_types',
        'living_with',
        'marital_status',
        'addresses',
        'phones',
        'school_periods',
        'school_subjects',
        'school_schedules',
        'school_teams'
    ];

    /**
     * Inicializa o prefixo a partir das variáveis de ambiente
     * 
     * @return void
     */
    public static function init(): void
    {
        self::$prefix = $_ENV['DB_TABLE_PREFIX'] ?? '';
    }

    /**
     * Obtém o prefixo atual
     * 
     * @return string
     */
    public static function get(): string
    {
        return self::$prefix;
    }

    /**
     * Define o prefixo
     * 
     * @param string $prefix
     * @return void
     */
    public static function set(string $prefix): void
    {
        self::$prefix = $prefix;
    }

    /**
     * Adiciona prefixo a uma tabela
     * 
     * @param string $tableName
     * @return string
     */
    public static function add(string $tableName): string
    {
        if (empty(self::$prefix)) {
            return $tableName;
        }

        // Se a tabela já tem o prefixo, não adiciona novamente
        if (strpos($tableName, self::$prefix) === 0) {
            return $tableName;
        }

        return self::$prefix . $tableName;
    }

    /**
     * Remove prefixo de uma tabela
     * 
     * @param string $tableName
     * @return string
     */
    public static function remove(string $tableName): string
    {
        if (empty(self::$prefix)) {
            return $tableName;
        }

        if (strpos($tableName, self::$prefix) === 0) {
            return substr($tableName, strlen(self::$prefix));
        }

        return $tableName;
    }

    /**
     * Processa SQL substituindo placeholders e nomes de tabelas
     * 
     * @param string $sql
     * @return string
     */
    public static function processSql(string $sql): string
    {
        // Substitui {prefix} pelo prefixo atual
        $sql = str_replace('{prefix}', self::$prefix, $sql);
        
        // Se não há prefixo, retorna o SQL como está
        if (empty(self::$prefix)) {
            return $sql;
        }

        // Substitui nomes de tabelas conhecidas pelo nome com prefixo
        foreach (self::$systemTables as $table) {
            // Padrão para substituir referências à tabela
            $patterns = [
                // FROM tablename
                '/\bFROM\s+`?' . preg_quote($table, '/') . '`?\b/i',
                // JOIN tablename
                '/\bJOIN\s+`?' . preg_quote($table, '/') . '`?\b/i',
                // UPDATE tablename
                '/\bUPDATE\s+`?' . preg_quote($table, '/') . '`?\b/i',
                // INSERT INTO tablename
                '/\bINTO\s+`?' . preg_quote($table, '/') . '`?\b/i',
                // DELETE FROM tablename
                '/\bFROM\s+`?' . preg_quote($table, '/') . '`?\b/i',
                // REFERENCES tablename
                '/\bREFERENCES\s+`?' . preg_quote($table, '/') . '`?\b/i'
            ];

            $replacement = self::add($table);
            
            foreach ($patterns as $pattern) {
                $sql = preg_replace($pattern, function($matches) use ($replacement) {
                    return str_replace($matches[1] ?? $matches[0], $replacement, $matches[0]);
                }, $sql);
            }
        }

        return $sql;
    }

    /**
     * Verifica se uma tabela é do sistema
     * 
     * @param string $tableName
     * @return bool
     */
    public static function isSystemTable(string $tableName): bool
    {
        $cleanName = self::remove($tableName);
        return in_array($cleanName, self::$systemTables);
    }

    /**
     * Obtém lista de todas as tabelas do sistema
     * 
     * @param bool $withPrefix
     * @return array
     */
    public static function getSystemTables(bool $withPrefix = false): array
    {
        if (!$withPrefix) {
            return self::$systemTables;
        }

        return array_map([self::class, 'add'], self::$systemTables);
    }

    /**
     * Valida se o prefixo é válido
     * 
     * @param string $prefix
     * @return bool
     */
    public static function isValidPrefix(string $prefix): bool
    {
        // Prefixo vazio é válido
        if (empty($prefix)) {
            return true;
        }

        // Deve conter apenas letras, números e underscore
        // Deve começar com letra ou underscore
        // Não deve terminar com underscore (será adicionado automaticamente)
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $prefix);
    }

    /**
     * Normaliza o prefixo (adiciona underscore no final se necessário)
     * 
     * @param string $prefix
     * @return string
     */
    public static function normalize(string $prefix): string
    {
        if (empty($prefix)) {
            return '';
        }

        // Remove underscores do final
        $prefix = rtrim($prefix, '_');
        
        // Adiciona um underscore no final
        return $prefix . '_';
    }

    /**
     * Gera exemplo de nome de tabela com prefixo
     * 
     * @param string $prefix
     * @param string $tableName
     * @return string
     */
    public static function example(string $prefix, string $tableName = 'users'): string
    {
        $normalizedPrefix = self::normalize($prefix);
        return $normalizedPrefix . $tableName;
    }
}