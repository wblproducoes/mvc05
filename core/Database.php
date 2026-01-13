<?php

namespace Core;

use PDO;
use PDOException;

/**
 * Classe para gerenciamento do banco de dados
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class Database
{
    /**
     * @var PDO|null Instância da conexão PDO
     */
    private static ?PDO $instance = null;

    /**
     * @var array Configurações do banco de dados
     */
    private array $config;

    /**
     * @var string Prefixo das tabelas
     */
    private string $tablePrefix;

    /**
     * Construtor da classe Database
     */
    public function __construct()
    {
        $this->config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'dbname' => $_ENV['DB_NAME'] ?? 'admin_system',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4'
        ];

        // Inicializa o prefixo e normaliza
        $prefix = $_ENV['DB_TABLE_PREFIX'] ?? '';
        $this->tablePrefix = TablePrefix::normalize($prefix);
        TablePrefix::set($this->tablePrefix);
    }

    /**
     * Obtém a instância da conexão PDO (Singleton)
     * 
     * @return PDO
     * @throws PDOException
     */
    public function getConnection(): PDO
    {
        if (self::$instance === null) {
            $this->connect();
        }

        return self::$instance;
    }

    /**
     * Estabelece a conexão com o banco de dados
     * 
     * @return void
     * @throws PDOException
     */
    private function connect(): void
    {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['dbname']};charset={$this->config['charset']}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']}"
            ];

            self::$instance = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
            
        } catch (PDOException $e) {
            throw new PDOException("Erro na conexão com o banco de dados: " . $e->getMessage());
        }
    }

    /**
     * Executa uma query SELECT
     * 
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->prepare($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Executa uma query SELECT e retorna apenas um registro
     * 
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->prepare($sql, $params);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    /**
     * Executa uma query INSERT
     * 
     * @param string $sql
     * @param array $params
     * @return int ID do registro inserido
     */
    public function insert(string $sql, array $params = []): int
    {
        $this->prepare($sql, $params);
        return (int) $this->getConnection()->lastInsertId();
    }

    /**
     * Executa uma query UPDATE
     * 
     * @param string $sql
     * @param array $params
     * @return int Número de linhas afetadas
     */
    public function update(string $sql, array $params = []): int
    {
        $stmt = $this->prepare($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Executa uma query DELETE
     * 
     * @param string $sql
     * @param array $params
     * @return int Número de linhas afetadas
     */
    public function delete(string $sql, array $params = []): int
    {
        $stmt = $this->prepare($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Prepara e executa uma query
     * 
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     */
    private function prepare(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt;
    }

    /**
     * Inicia uma transação
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Confirma uma transação
     * 
     * @return bool
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Desfaz uma transação
     * 
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollback();
    }

    /**
     * Verifica se existe uma tabela no banco de dados
     * 
     * @param string $tableName
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        $sql = "SHOW TABLES LIKE :table";
        $result = $this->selectOne($sql, ['table' => $tableName]);
        
        return !empty($result);
    }

    /**
     * Executa um arquivo SQL
     * 
     * @param string $filePath
     * @return bool
     */
    public function executeSqlFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $sql = file_get_contents($filePath);
        
        // Processa o SQL com prefixos
        $sql = $this->processSqlWithPrefix($sql);
        
        try {
            $this->getConnection()->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao executar arquivo SQL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém o prefixo das tabelas
     * 
     * @return string
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Define o prefixo das tabelas
     * 
     * @param string $prefix
     * @return void
     */
    public function setTablePrefix(string $prefix): void
    {
        $this->tablePrefix = $prefix;
    }

    /**
     * Adiciona o prefixo ao nome da tabela
     * 
     * @param string $tableName
     * @return string
     */
    public function prefixTable(string $tableName): string
    {
        return TablePrefix::add($tableName);
    }

    /**
     * Remove o prefixo do nome da tabela
     * 
     * @param string $tableName
     * @return string
     */
    public function unprefixTable(string $tableName): string
    {
        return TablePrefix::remove($tableName);
    }

    /**
     * Processa SQL substituindo placeholders de prefixo
     * 
     * @param string $sql
     * @return string
     */
    public function processSqlWithPrefix(string $sql): string
    {
        return TablePrefix::processSql($sql);
    }
}