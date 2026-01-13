# Sistema de Instalação Inteligente

O Sistema Administrativo MVC possui um sistema de instalação inteligente que detecta automaticamente se o sistema precisa ser instalado e guia o usuário através do processo.

## Como Funciona

### Detecção Automática

O sistema verifica automaticamente:
1. **Existência de tabelas**: Verifica se as tabelas essenciais existem no banco
2. **Presença de usuários**: Verifica se há pelo menos um usuário ativo
3. **Integridade do banco**: Testa a conexão e estrutura

### Fluxos de Instalação

#### 1. Primeira Instalação (Recomendado)
**Quando**: Tabelas não existem no banco de dados

**Processo**:
1. Sistema detecta ausência de tabelas
2. Redireciona automaticamente para `/install`
3. **Não solicita senha** (instalação automática)
4. Apresenta formulário com:
   - Nome do administrador
   - Email do administrador  
   - Senha do administrador
   - Nome do sistema
5. Cria todas as tabelas e dados iniciais
6. Configura o primeiro usuário como Master
7. Redireciona para tela de login

#### 2. Reinstalação
**Quando**: Tabelas existem mas não há usuários ativos

**Processo**:
1. Sistema detecta tabelas sem usuários
2. Redireciona para `/install`
3. **Solicita senha de instalação** (segurança)
4. Permite reconfiguração do sistema
5. Mantém estrutura existente

#### 3. Sistema Instalado
**Quando**: Tabelas e usuários existem

**Processo**:
1. Sistema funciona normalmente
2. Não há redirecionamentos
3. Acesso normal às funcionalidades

## Configuração

### Variáveis de Ambiente

```env
# Banco de dados
DB_HOST=localhost
DB_NAME=admin_system
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
DB_TABLE_PREFIX=

# Aplicação
APP_NAME="Sistema Administrativo MVC"
APP_TIMEZONE=America/Sao_Paulo

# Instalação (apenas para reinstalações)
INSTALL_PASSWORD=admin123
```

### Requisitos do Sistema

O instalador verifica automaticamente:

- **PHP 8.4+**: Versão mínima suportada
- **Extensões PHP**:
  - PDO (conexão com banco)
  - PDO MySQL (driver MySQL)
  - Mbstring (strings multibyte)
  - OpenSSL (criptografia)
  - cURL (requisições HTTP)
  - GD (manipulação de imagens)
- **Permissões de Escrita**:
  - Diretório `storage/`
  - Diretório `public/`

## Interface de Instalação

### Campos do Formulário

#### Dados do Administrador
- **Nome**: Nome completo do administrador
- **Email**: Email válido (será usado para login)
- **Senha**: Mínimo 8 caracteres com validação de força
- **Confirmar Senha**: Confirmação da senha

#### Configuração do Sistema
- **Nome do Sistema**: Nome que aparecerá na interface
- **Senha de Instalação**: Apenas em reinstalações

### Validações

- **Email único**: Verifica se não existe outro usuário com o mesmo email
- **Força da senha**: Valida complexidade da senha
- **Campos obrigatórios**: Todos os campos são validados
- **Requisitos do sistema**: Verifica se todos os requisitos são atendidos

## API de Status

### Endpoint: `GET /install/status`

Retorna o status atual da instalação:

```json
{
  "success": true,
  "data": {
    "needs_install": false,
    "is_first_install": false,
    "tables_exist": true,
    "has_users": true,
    "database_connected": true
  }
}
```

### Campos de Resposta

- `needs_install`: Se o sistema precisa ser instalado
- `is_first_install`: Se é a primeira instalação (tabelas não existem)
- `tables_exist`: Se as tabelas essenciais existem
- `has_users`: Se há usuários ativos no sistema
- `database_connected`: Se a conexão com banco está funcionando

## Middleware de Instalação

### InstallationMiddleware

Classe responsável por:
- Verificar necessidade de instalação
- Redirecionar automaticamente
- Ignorar arquivos estáticos
- Fornecer status detalhado

### Integração Automática

O middleware é executado automaticamente em todas as requisições:

```php
// Em public/index.php
$installMiddleware = new Core\InstallationMiddleware();
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($installMiddleware->handle($currentPath)) {
    header('Location: ' . $installMiddleware->getInstallUrl());
    exit;
}
```

## Estrutura Criada

### Tabelas Essenciais

A instalação cria as seguintes tabelas (com prefixo se configurado):

- `users`: Usuários do sistema
- `levels`: Níveis de acesso
- `status`: Status dos registros
- `genders`: Gêneros
- `settings`: Configurações do sistema
- `school_*`: Tabelas do sistema escolar
- Outras tabelas auxiliares

### Dados Iniciais

- **Gêneros**: Masculino, Feminino, Outro, Não informado
- **Níveis**: Master, Admin, Direção, etc.
- **Status**: Ativo, Inativo, Bloqueado, etc.
- **Matérias**: 20 disciplinas escolares
- **Períodos**: Matutino, Vespertino, Noturno, Integral
- **Configurações**: Nome do sistema, versão, etc.

### Primeiro Usuário

O usuário criado na instalação tem:
- **Level**: Master (level_id = 1)
- **Status**: Ativo (status_id = 1)
- **Username**: admin
- **Unique Code**: Gerado automaticamente

## Troubleshooting

### Problemas Comuns

#### 1. Erro de Conexão com Banco
```
Erro na criação do banco de dados: Connection refused
```

**Soluções**:
- Verificar se MySQL/MariaDB está rodando
- Conferir credenciais no `.env`
- Testar conexão manualmente

#### 2. Permissões de Diretório
```
Storage Directory not writable
```

**Soluções**:
```bash
chmod 755 storage/
chmod 755 public/
```

#### 3. Extensões PHP Faltando
```
PDO MySQL Extension: Failed
```

**Soluções**:
- Instalar extensões necessárias
- Verificar configuração do PHP
- Reiniciar servidor web

#### 4. Tabelas Já Existem
```
Table 'users' already exists
```

**Soluções**:
- Usar reinstalação com senha
- Fazer backup e limpar banco
- Usar prefixo diferente

### Logs de Erro

Erros são registrados em:
- Log do PHP (error_log)
- Console do navegador (se APP_DEBUG=true)
- Arquivo de log da aplicação

### Verificação Manual

Para verificar manualmente o status:

```php
$middleware = new Core\InstallationMiddleware();
$status = $middleware->getInstallationStatus();
var_dump($status);
```

## Segurança

### Proteções Implementadas

1. **Senha de Reinstalação**: Evita reinstalações não autorizadas
2. **Validação de Entrada**: Sanitização de todos os dados
3. **Verificação de Integridade**: Confirma estrutura do banco
4. **Logs de Auditoria**: Registra tentativas de instalação

### Boas Práticas

1. **Altere a senha de instalação** no `.env`
2. **Remova o arquivo `.installed`** apenas se necessário
3. **Faça backup** antes de reinstalações
4. **Use HTTPS** em produção
5. **Configure permissões** adequadamente

## Personalização

### Modificar Dados Iniciais

Para personalizar dados criados na instalação:

1. Edite o arquivo `database/schema.sql`
2. Modifique os INSERTs das tabelas
3. Adicione novos dados conforme necessário

### Customizar Interface

Para personalizar a tela de instalação:

1. Edite `app/Views/install/index.twig`
2. Modifique CSS em `public/assets/css/`
3. Ajuste validações no controller

### Adicionar Verificações

Para adicionar novas verificações:

1. Modifique `InstallationMiddleware`
2. Adicione novos requisitos em `checkRequirements()`
3. Implemente validações customizadas

## Migração

### De Versões Anteriores

Se você tem uma versão anterior sem o sistema inteligente:

1. **Backup completo** do banco e arquivos
2. **Atualize os arquivos** do sistema
3. **Configure o `.env`** com as novas variáveis
4. **Acesse o sistema** - será redirecionado automaticamente se necessário

### Para Nova Instalação

1. **Configure o banco** de dados
2. **Configure o `.env`** com as credenciais
3. **Acesse a URL** do sistema
4. **Siga o assistente** de instalação

O sistema detectará automaticamente que é uma nova instalação e não pedirá senha.