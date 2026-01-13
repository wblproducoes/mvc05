<?php

namespace Core;

/**
 * Classe para validação de dados
 * 
 * @package Core
 * @author Sistema Administrativo
 * @version 1.0.0
 */
class Validator
{
    /**
     * @var array Mensagens de erro
     */
    private array $errors = [];

    /**
     * @var array Dados a serem validados
     */
    private array $data = [];

    /**
     * Valida dados com base nas regras
     * 
     * @param array $data
     * @param array $rules
     * @return array
     */
    public function validate(array $data, array $rules): array
    {
        $this->data = $data;
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $this->validateField($field, $fieldRules);
        }

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'data' => $this->data
        ];
    }

    /**
     * Valida um campo específico
     * 
     * @param string $field
     * @param string|array $rules
     * @return void
     */
    private function validateField(string $field, string|array $rules): void
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $value = $this->data[$field] ?? null;

        foreach ($rules as $rule) {
            $this->applyRule($field, $value, $rule);
        }
    }

    /**
     * Aplica uma regra de validação
     * 
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @return void
     */
    private function applyRule(string $field, mixed $value, string $rule): void
    {
        // Separar regra e parâmetros
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

        switch ($ruleName) {
            case 'required':
                $this->validateRequired($field, $value);
                break;
                
            case 'email':
                $this->validateEmail($field, $value);
                break;
                
            case 'min':
                $this->validateMin($field, $value, (int) $parameters[0]);
                break;
                
            case 'max':
                $this->validateMax($field, $value, (int) $parameters[0]);
                break;
                
            case 'numeric':
                $this->validateNumeric($field, $value);
                break;
                
            case 'alpha':
                $this->validateAlpha($field, $value);
                break;
                
            case 'alpha_num':
                $this->validateAlphaNum($field, $value);
                break;
                
            case 'confirmed':
                $this->validateConfirmed($field, $value);
                break;
                
            case 'unique':
                $this->validateUnique($field, $value, $parameters[0], $parameters[1] ?? null);
                break;
                
            case 'exists':
                $this->validateExists($field, $value, $parameters[0], $parameters[1] ?? null);
                break;
                
            case 'in':
                $this->validateIn($field, $value, $parameters);
                break;
                
            case 'regex':
                $this->validateRegex($field, $value, $parameters[0]);
                break;
                
            case 'date':
                $this->validateDate($field, $value);
                break;
                
            case 'url':
                $this->validateUrl($field, $value);
                break;
                
            case 'file':
                $this->validateFile($field, $value);
                break;
                
            case 'image':
                $this->validateImage($field, $value);
                break;
        }
    }

    /**
     * Valida campo obrigatório
     */
    private function validateRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, 'O campo é obrigatório');
        }
    }

    /**
     * Valida email
     */
    private function validateEmail(string $field, mixed $value): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'O campo deve ser um email válido');
        }
    }

    /**
     * Valida tamanho mínimo
     */
    private function validateMin(string $field, mixed $value, int $min): void
    {
        if ($value && strlen((string) $value) < $min) {
            $this->addError($field, "O campo deve ter pelo menos {$min} caracteres");
        }
    }

    /**
     * Valida tamanho máximo
     */
    private function validateMax(string $field, mixed $value, int $max): void
    {
        if ($value && strlen((string) $value) > $max) {
            $this->addError($field, "O campo deve ter no máximo {$max} caracteres");
        }
    }

    /**
     * Valida se é numérico
     */
    private function validateNumeric(string $field, mixed $value): void
    {
        if ($value && !is_numeric($value)) {
            $this->addError($field, 'O campo deve ser numérico');
        }
    }

    /**
     * Valida se contém apenas letras
     */
    private function validateAlpha(string $field, mixed $value): void
    {
        if ($value && !preg_match('/^[a-zA-ZÀ-ÿ\s]+$/', $value)) {
            $this->addError($field, 'O campo deve conter apenas letras');
        }
    }

    /**
     * Valida se contém apenas letras e números
     */
    private function validateAlphaNum(string $field, mixed $value): void
    {
        if ($value && !preg_match('/^[a-zA-Z0-9À-ÿ\s]+$/', $value)) {
            $this->addError($field, 'O campo deve conter apenas letras e números');
        }
    }

    /**
     * Valida confirmação de campo
     */
    private function validateConfirmed(string $field, mixed $value): void
    {
        $confirmField = $field . '_confirmation';
        $confirmValue = $this->data[$confirmField] ?? null;
        
        if ($value !== $confirmValue) {
            $this->addError($field, 'A confirmação não confere');
        }
    }

    /**
     * Valida se o valor é único no banco
     */
    private function validateUnique(string $field, mixed $value, string $table, ?string $column = null): void
    {
        if (!$value) return;
        
        $column = $column ?? $field;
        $database = new Database();
        
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = :value";
        $result = $database->selectOne($sql, ['value' => $value]);
        
        if ($result['count'] > 0) {
            $this->addError($field, 'Este valor já está em uso');
        }
    }

    /**
     * Valida se o valor existe no banco
     */
    private function validateExists(string $field, mixed $value, string $table, ?string $column = null): void
    {
        if (!$value) return;
        
        $column = $column ?? $field;
        $database = new Database();
        
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = :value";
        $result = $database->selectOne($sql, ['value' => $value]);
        
        if ($result['count'] === 0) {
            $this->addError($field, 'O valor selecionado é inválido');
        }
    }

    /**
     * Valida se o valor está na lista
     */
    private function validateIn(string $field, mixed $value, array $options): void
    {
        if ($value && !in_array($value, $options)) {
            $this->addError($field, 'O valor selecionado é inválido');
        }
    }

    /**
     * Valida expressão regular
     */
    private function validateRegex(string $field, mixed $value, string $pattern): void
    {
        if ($value && !preg_match($pattern, $value)) {
            $this->addError($field, 'O formato do campo é inválido');
        }
    }

    /**
     * Valida data
     */
    private function validateDate(string $field, mixed $value): void
    {
        if ($value && !strtotime($value)) {
            $this->addError($field, 'O campo deve ser uma data válida');
        }
    }

    /**
     * Valida URL
     */
    private function validateUrl(string $field, mixed $value): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'O campo deve ser uma URL válida');
        }
    }

    /**
     * Valida arquivo
     */
    private function validateFile(string $field, mixed $value): void
    {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, 'Erro no upload do arquivo');
        }
    }

    /**
     * Valida imagem
     */
    private function validateImage(string $field, mixed $value): void
    {
        if (isset($_FILES[$field])) {
            $file = $_FILES[$field];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->addError($field, 'Erro no upload da imagem');
                return;
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!in_array($file['type'], $allowedTypes)) {
                $this->addError($field, 'O arquivo deve ser uma imagem válida (JPEG, PNG, GIF, WebP)');
            }
        }
    }

    /**
     * Adiciona erro de validação
     */
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }

    /**
     * Obtém todos os erros
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Verifica se há erros
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}