# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [Não Lançado]

### Planejado
- Sistema de notificações em tempo real
- API REST completa
- Sistema de backup automático
- Autenticação de dois fatores (2FA)
- Sistema de permissões granulares
- Dashboard customizável
- Integração com serviços de email (SendGrid, Mailgun)
- Sistema de logs avançado
- Modo escuro na interface
- Exportação de dados em Excel

---

## [1.1.0] - 2025-01-13

### Adicionado
- **Tabelas de Referência**
  - Tabela `genders` para gerenciamento de gêneros
  - Tabela `levels` para níveis de acesso hierárquicos
  - Tabela `status` para estados dos registros
  - Models correspondentes (Gender, Level, Status)

- **Sistema de Usuários Aprimorado**
  - Campos adicionais: gênero, data de nascimento, documento, endereço
  - Relacionamentos com tabelas de referência
  - Soft delete para usuários
  - Sistema de níveis hierárquicos (Master, Admin, Direção, etc.)
  - Status coloridos para melhor visualização

- **Níveis de Acesso Expandidos**
  - 11 níveis diferentes: Master, Admin, Direção, Financeiro, Coordenação, Secretaria, Professor, Funcionário, Aluno, Responsável, Usuário
  - Sistema de permissões baseado em níveis
  - Hierarquia de permissões

- **Sistema de Status Flexível**
  - Status com cores personalizáveis
  - Estados: Ativo, Inativo, Bloqueado, Excluído, Concluído, Vencido, Pendente, Suspenso
  - Badges coloridos na interface

### Alterado
- **Estrutura do Banco de Dados**
  - Tabela `users` reformulada para usar foreign keys
  - Remoção do campo `role` em favor de `level_id`
  - Remoção do campo `active` em favor de `status_id`
  - Adição de campos para informações pessoais completas

- **Sistema de Autenticação**
  - Atualizado para usar novos campos de status e nível
  - Verificação de status ativo (status_id = 1)
  - Permissões baseadas em níveis hierárquicos

- **Models Atualizados**
  - User model completamente reformulado
  - Métodos para trabalhar com relacionamentos
  - Suporte a soft delete
  - Queries otimizadas com JOINs

### Melhorado
- **Performance do Banco**
  - Índices otimizados nas novas tabelas
  - Foreign keys para integridade referencial
  - Queries mais eficientes com relacionamentos

- **Flexibilidade do Sistema**
  - Configuração de gêneros personalizáveis
  - Níveis de acesso extensíveis
  - Status customizáveis com cores

- **Manutenibilidade**
  - Código mais organizado com separação de responsabilidades
  - Models específicos para cada entidade
  - Métodos utilitários para operações comuns

---

## [1.0.0] - 2025-01-13

### Adicionado
- **Arquitetura MVC Completa**
  - Sistema de roteamento avançado com parâmetros
  - Controllers base com funcionalidades comuns
  - Models com CRUD automático e paginação
  - Views com Twig 3.0 e Bootstrap 5.3

- **Sistema de Autenticação Seguro**
  - Login com email e senha
  - Criptografia bcrypt para senhas
  - Sistema "Lembrar de mim" seguro
  - Rate limiting para tentativas de login
  - Proteção CSRF em todos os formulários
  - Middleware de autenticação

- **Gerenciamento de Usuários**
  - CRUD completo de usuários
  - Sistema de papéis (admin, usuário, moderador)
  - Ativação/desativação de contas
  - Filtros e busca avançada
  - Paginação de resultados
  - Validação de força de senha

- **Dashboard Administrativo**
  - Estatísticas em tempo real
  - Gráficos de crescimento de usuários
  - Informações do sistema
  - Usuários recentes
  - Timeline de atividades
  - Cards informativos responsivos

- **Sistema de Relatórios**
  - Relatório de usuários (HTML/PDF)
  - Relatório de atividades do sistema
  - Relatório de informações técnicas
  - Geração de PDF com DomPDF
  - Filtros personalizáveis
  - Exportação com dados em tempo real

- **Sistema de Instalação**
  - Assistente de instalação protegido por senha
  - Verificação automática de requisitos
  - Criação automática do banco de dados
  - Configuração do usuário administrador
  - Validação de configurações

- **Interface Moderna**
  - Design responsivo com Bootstrap 5.3
  - Sidebar colapsável
  - Tema administrativo profissional
  - Ícones Bootstrap Icons
  - Animações CSS suaves
  - Componentes interativos

- **Segurança Avançada**
  - Proteção contra SQL Injection (PDO Prepared Statements)
  - Proteção XSS (sanitização de dados)
  - Tokens CSRF em formulários
  - Headers de segurança HTTP
  - Validação de entrada robusta
  - Controle de sessões seguro

- **Banco de Dados**
  - Schema MySQL/MariaDB otimizado
  - Índices para performance
  - Relacionamentos com integridade referencial
  - Tabelas para logs de atividade
  - Sistema de configurações
  - Suporte a notificações

- **Validação de Dados**
  - Sistema de validação extensível
  - Regras customizáveis
  - Mensagens de erro em português
  - Validação client-side e server-side
  - Suporte a arquivos e imagens

- **Funcionalidades JavaScript**
  - Componentes interativos
  - Busca em tempo real
  - Confirmações de ação
  - Upload de arquivos com preview
  - Máscaras de input
  - Notificações toast

### Recursos Técnicos
- **PHP 8.4+** - Compatibilidade garantida com PHP 8.4 e 8.5
- **Composer** - Gerenciamento de dependências
- **Twig 3.0** - Template engine moderna
- **Bootstrap 5.3** - Framework CSS responsivo
- **DomPDF 3.1.4** - Geração de PDFs
- **PHPMailer 7.0.3** - Envio de emails
- **Autoload PSR-4** - Carregamento automático de classes
- **Variáveis de Ambiente** - Configuração via .env

### Estrutura do Projeto
```
├── app/
│   ├── Controllers/     # AuthController, DashboardController, UserController, InstallController, ReportController
│   ├── Models/         # User
│   └── Views/          # Templates Twig organizados por funcionalidade
├── core/               # Application, Router, Database, Controller, Model, Auth, Security, Validator
├── database/           # schema.sql
├── public/             # index.php, .htaccess, assets (CSS/JS)
├── storage/            # cache, logs, uploads
└── vendor/             # Dependências do Composer
```

### Configuração e Instalação
- Sistema de instalação web com verificação de requisitos
- Configuração via arquivo .env
- Suporte a múltiplos ambientes (development, production)
- Documentação completa de instalação

### Documentação
- README.md completo com instruções
- Comentários PHPDocs em todo o código
- Exemplos de uso e desenvolvimento
- Guia de contribuição

---

## Tipos de Mudanças

- `Adicionado` para novas funcionalidades
- `Alterado` para mudanças em funcionalidades existentes
- `Descontinuado` para funcionalidades que serão removidas
- `Removido` para funcionalidades removidas
- `Corrigido` para correções de bugs
- `Segurança` para vulnerabilidades corrigidas

## Versionamento

Este projeto usa [Versionamento Semântico](https://semver.org/lang/pt-BR/):

- **MAJOR** (X.0.0): Mudanças incompatíveis na API
- **MINOR** (0.X.0): Funcionalidades adicionadas de forma compatível
- **PATCH** (0.0.X): Correções de bugs compatíveis

## Links

- [Repositório](https://github.com/seu-usuario/sistema-administrativo-mvc)
- [Issues](https://github.com/seu-usuario/sistema-administrativo-mvc/issues)
- [Releases](https://github.com/seu-usuario/sistema-administrativo-mvc/releases)