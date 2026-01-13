# Sistema de Prefixos de Tabelas

O Sistema Administrativo MVC suporta prefixos de tabelas configuráveis, permitindo múltiplas instalações no mesmo banco de dados ou organização personalizada das tabelas.

## Configuração

### Arquivo .env

Adicione a variável `DB_TABLE_PREFIX` no seu arquivo `.env`:

```env
# Sem prefixo (padrão)
DB_TABLE_PREFIX=

# Com prefixo simples
DB_TABLE_PREFIX=escola

# Com prefixo de versão
DB_TABLE_PREFIX=v2

# Com prefixo de ambiente
DB_TABLE_PREFIX=prod
```

### Normalização Automática

O sistema automaticamente normaliza o prefixo:
- Adiciona underscore (`_`) no final se não existir
- Remove underscores extras do final
- Valida caracteres permitidos

**Exemplos:**
- `escola` → `escola_`
- `v2_` → `v2_`
- `prod__` → `prod_`

## Como Funciona

### Tabelas Resultantes

Com `DB_TABLE_PREFIX=escola`, as tabelas ficam:

| Tabela Original | Com Prefixo |
|----------------|-------------|
| `users` | `escola_users` |
| `levels` | `escola_levels` |
| `status` | `escola_status` |
| `school_teams` | `escola_school_teams` |
| `school_subjects` | `escola_school_subjects` |

### Processamento Automático

1. **Models**: Aplicam prefixo automaticamente no construtor
2. **Database**: Processa SQL com placeholders `{prefix}`
3. **Schema**: Usa `{prefix}` como placeholder
4. **Foreign Keys**: Mantém integridade referencial

## Uso nos Models

Os models funcionam transparentemente:

```php
// Funciona normalmente, independente do prefixo
$user = new User();
$users = $user->all(); // Busca em escola_users (se prefixo = escola)

// Métodos utilitários
$tableName = $user->getTable(); // escola_users
$cleanName = $user->getTableWithoutPrefix(); // users
```

## Uso no Database

### Placeholders no SQL

```sql
-- No schema.sql
CREATE TABLE `{prefix}users` (
    `id` int NOT NULL,
    -- ...
);

-- Processado automaticamente para:
CREATE TABLE `escola_users` (
    `id` int NOT NULL,
    -- ...
);
```

### Métodos Utilitários

```php
$db = new Database();

// Adicionar prefixo
$prefixed = $db->prefixTable('users'); // escola_users

// Remover prefixo
$clean = $db->unprefixTable('escola_users'); // users

// Processar SQL
$sql = "SELECT * FROM {prefix}users WHERE status = 'active'";
$processed = $db->processSqlWithPrefix($sql);
// Resultado: SELECT * FROM escola_users WHERE status = 'active'
```

## Classe TablePrefix

### Métodos Principais

```php
use Core\TablePrefix;

// Inicializar (feito automaticamente)
TablePrefix::init();

// Obter prefixo atual
$prefix = TablePrefix::get(); // escola_

// Adicionar prefixo
$table = TablePrefix::add('users'); // escola_users

// Remover prefixo
$clean = TablePrefix::remove('escola_users'); // users

// Processar SQL
$sql = TablePrefix::processSql("SELECT * FROM {prefix}users");

// Validar prefixo
$valid = TablePrefix::isValidPrefix('escola'); // true
$invalid = TablePrefix::isValidPrefix('123invalid'); // false

// Normalizar
$normalized = TablePrefix::normalize('escola'); // escola_
```

### Validação de Prefixos

Prefixos válidos devem:
- Começar com letra ou underscore
- Conter apenas letras, números e underscores
- Não ser muito longos (recomendado até 10 caracteres)

**Válidos:**
- `escola`
- `v2`
- `prod`
- `_test`
- `app2024`

**Inválidos:**
- `123abc` (começa com número)
- `app-test` (contém hífen)
- `app.test` (contém ponto)

## Casos de Uso

### 1. Múltiplas Escolas

```env
# Escola A
DB_TABLE_PREFIX=escolaa

# Escola B  
DB_TABLE_PREFIX=escolab
```

### 2. Ambientes Diferentes

```env
# Produção
DB_TABLE_PREFIX=prod

# Desenvolvimento
DB_TABLE_PREFIX=dev

# Testes
DB_TABLE_PREFIX=test
```

### 3. Versões do Sistema

```env
# Versão atual
DB_TABLE_PREFIX=v2

# Versão anterior (mantida)
DB_TABLE_PREFIX=v1
```

## Migração

### De Sistema Sem Prefixo

1. **Backup**: Faça backup completo do banco
2. **Configure**: Adicione `DB_TABLE_PREFIX=` (vazio) no `.env`
3. **Teste**: Verifique se tudo funciona normalmente
4. **Migre**: Quando necessário, altere o prefixo e execute o schema

### Renomear Tabelas Existentes

```sql
-- Exemplo: adicionar prefixo 'escola_' a tabelas existentes
RENAME TABLE 
    users TO escola_users,
    levels TO escola_levels,
    status TO escola_status,
    genders TO escola_genders;
    -- ... continue para todas as tabelas
```

## Troubleshooting

### Erro de Tabela Não Encontrada

```
Table 'database.escola_users' doesn't exist
```

**Soluções:**
1. Verifique se o prefixo está correto no `.env`
2. Execute o schema com o prefixo configurado
3. Verifique se as tabelas foram criadas com o prefixo

### Foreign Keys com Erro

```
Cannot add foreign key constraint
```

**Soluções:**
1. Certifique-se que todas as tabelas têm o mesmo prefixo
2. Execute o schema completo de uma vez
3. Verifique a ordem de criação das tabelas

### Prefixo Não Aplicado

**Verificações:**
1. Arquivo `.env` carregado corretamente
2. Variável `DB_TABLE_PREFIX` definida
3. Cache limpo (se aplicável)
4. Reinicialização da aplicação

## Boas Práticas

### Nomenclatura

- Use prefixos curtos e descritivos
- Evite caracteres especiais
- Seja consistente entre ambientes
- Documente o padrão usado

### Organização

```env
# ✅ Bom
DB_TABLE_PREFIX=escola
DB_TABLE_PREFIX=v2
DB_TABLE_PREFIX=prod

# ❌ Evitar
DB_TABLE_PREFIX=sistema_escolar_municipal_2024
DB_TABLE_PREFIX=123
DB_TABLE_PREFIX=app-test
```

### Segurança

- Não use prefixos previsíveis em produção
- Considere prefixos aleatórios para maior segurança
- Mantenha configurações de prefixo em local seguro

## Limitações

1. **Tamanho**: MySQL tem limite de 64 caracteres para nomes de tabelas
2. **Caracteres**: Apenas letras, números e underscores
3. **Compatibilidade**: Alguns tools externos podem não reconhecer prefixos
4. **Performance**: Prefixos muito longos podem impactar performance marginalmente

## Suporte

Para dúvidas ou problemas com prefixos de tabelas:

1. Verifique este documento
2. Consulte os logs de erro
3. Teste em ambiente de desenvolvimento
4. Faça backup antes de mudanças em produção