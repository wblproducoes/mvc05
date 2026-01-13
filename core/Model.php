<?php

namespace Core;

/**
 * Classe base para todos os models
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.0.0
 */
abstract class Model
{
    /**
     * @var Database Instância do banco de dados
     */
    protected Database $database;

    /**
     * @var string Nome da tabela
     */
    protected string $table;

    /**
     * @var string Chave primária
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Campos que podem ser preenchidos em massa
     */
    protected array $fillable = [];

    /**
     * @var array Campos que devem ser ocultados
     */
    protected array $hidden = [];

    /**
     * @var array Campos de data que devem ser convertidos
     */
    protected array $dates = ['created_at', 'updated_at'];

    /**
     * Construtor do model
     */
    public function __construct()
    {
        $this->database = new Database();
        
        // Aplica o prefixo ao nome da tabela se definido
        if (isset($this->table)) {
            $this->table = $this->database->prefixTable($this->table);
        }
    }

    /**
     * Busca todos os registros
     * 
     * @param array $columns
     * @return array
     */
    public function all(array $columns = ['*']): array
    {
        $columnsStr = implode(', ', $columns);
        $sql = "SELECT {$columnsStr} FROM {$this->table}";
        
        return $this->database->select($sql);
    }

    /**
     * Busca um registro por ID
     * 
     * @param int $id
     * @param array $columns
     * @return array|null
     */
    public function find(int $id, array $columns = ['*']): ?array
    {
        $columnsStr = implode(', ', $columns);
        $sql = "SELECT {$columnsStr} FROM {$this->table} WHERE {$this->primaryKey} = :id";
        
        return $this->database->selectOne($sql, ['id' => $id]);
    }

    /**
     * Busca registros com condições
     * 
     * @param array $conditions
     * @param array $columns
     * @return array
     */
    public function where(array $conditions, array $columns = ['*']): array
    {
        $columnsStr = implode(', ', $columns);
        $whereClause = $this->buildWhereClause($conditions);
        
        $sql = "SELECT {$columnsStr} FROM {$this->table} WHERE {$whereClause['sql']}";
        
        return $this->database->select($sql, $whereClause['params']);
    }

    /**
     * Busca um registro com condições
     * 
     * @param array $conditions
     * @param array $columns
     * @return array|null
     */
    public function whereFirst(array $conditions, array $columns = ['*']): ?array
    {
        $columnsStr = implode(', ', $columns);
        $whereClause = $this->buildWhereClause($conditions);
        
        $sql = "SELECT {$columnsStr} FROM {$this->table} WHERE {$whereClause['sql']} LIMIT 1";
        
        return $this->database->selectOne($sql, $whereClause['params']);
    }

    /**
     * Cria um novo registro
     * 
     * @param array $data
     * @return int ID do registro criado
     */
    public function create(array $data): int
    {
        $data = $this->filterFillable($data);
        $data = $this->addTimestamps($data);
        
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        
        return $this->database->insert($sql, $data);
    }

    /**
     * Atualiza um registro
     * 
     * @param int $id
     * @param array $data
     * @return int Número de linhas afetadas
     */
    public function update(int $id, array $data): int
    {
        $data = $this->filterFillable($data);
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClause) . " WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;
        
        return $this->database->update($sql, $data);
    }

    /**
     * Deleta um registro
     * 
     * @param int $id
     * @return int Número de linhas afetadas
     */
    public function delete(int $id): int
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        
        return $this->database->delete($sql, ['id' => $id]);
    }

    /**
     * Conta registros
     * 
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        
        if (!empty($conditions)) {
            $whereClause = $this->buildWhereClause($conditions);
            $sql .= " WHERE {$whereClause['sql']}";
            $result = $this->database->selectOne($sql, $whereClause['params']);
        } else {
            $result = $this->database->selectOne($sql);
        }
        
        return (int) $result['total'];
    }

    /**
     * Paginação
     * 
     * @param int $page
     * @param int $perPage
     * @param array $conditions
     * @return array
     */
    public function paginate(int $page = 1, int $perPage = 15, array $conditions = []): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = $this->buildWhereClause($conditions);
            $sql .= " WHERE {$whereClause['sql']}";
            $params = $whereClause['params'];
        }
        
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        
        $data = $this->database->select($sql, $params);
        $total = $this->count($conditions);
        
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    /**
     * Constrói cláusula WHERE
     * 
     * @param array $conditions
     * @return array
     */
    private function buildWhereClause(array $conditions): array
    {
        $sql = [];
        $params = [];
        
        foreach ($conditions as $column => $value) {
            $sql[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }
        
        return [
            'sql' => implode(' AND ', $sql),
            'params' => $params
        ];
    }

    /**
     * Filtra campos permitidos
     * 
     * @param array $data
     * @return array
     */
    private function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Adiciona timestamps
     * 
     * @param array $data
     * @return array
     */
    private function addTimestamps(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        
        if (in_array('created_at', $this->dates) && !isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
        
        if (in_array('updated_at', $this->dates) && !isset($data['updated_at'])) {
            $data['updated_at'] = $now;
        }
        
        return $data;
    }

    /**
     * Remove campos ocultos
     * 
     * @param array $data
     * @return array
     */
    protected function hideFields(array $data): array
    {
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        
        return $data;
    }

    /**
     * Obtém o nome da tabela com prefixo
     * 
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Obtém o nome da tabela sem prefixo
     * 
     * @return string
     */
    public function getTableWithoutPrefix(): string
    {
        return $this->database->unprefixTable($this->table);
    }

    /**
     * Define o nome da tabela (será automaticamente prefixada)
     * 
     * @param string $tableName
     * @return void
     */
    public function setTable(string $tableName): void
    {
        $this->table = $this->database->prefixTable($tableName);
    }
}