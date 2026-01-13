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

## [1.3.2] - 2025-01-13

### Corrigido
- **Estrutura da Tabela Levels**
  - Ajustado campo `dh` para permitir NULL (timestamp NULL DEFAULT CURRENT_TIMESTAMP)
  - Estrutura alinhada com especificação original
  - Model Level já estava correto, sem necessidade de ajustes

### Técnico
- Tabela levels com estrutura final correta
- Campos de timestamp ajustados para permitir NULL
- Consistência mantida com outras tabelas do sistema

---

## [1.3.1] - 2025-01-13

### Corrigido
- **Estrutura da Tabela Status**
  - Ajustado campo `dh` para permitir NULL (timestamp NULL DEFAULT CURRENT_TIMESTAMP)
  - Removido campo `ativo` que não existe na estrutura final
  - Mantidos métodos úteis do model Status sem referência ao campo inexistente
  - Estrutura alinhada com especificação original

### Técnico
- Model Status atualizado para refletir estrutura real da tabela
- Métodos de verificação de status mantidos funcionais
- Queries otimizadas sem campos inexistentes

---

## [1.3.0] - 2025-01-13

### Alterado - BREAKING CHANGES
- **Estrutura Completa da Tabela Users**
  - Reestruturação total da tabela `users` com campos profissionais
  - Adição de campos: `alias`, `cpf`, `phone_home`, `phone_mobile`, `phone_message`
  - Campos de integração: `username`, `unique_code`, `photo`
  - Integração Google: `google_access_token`, `google_refresh_token`, `google_token_expires`, `google_calendar_id`
  - Assinatura de mensagens: `message_signature`, `signature_include_logo`
  - Controle de acesso: `session_token`, `last_access`, `permissions_updated_at`
  - Reset de senha: `password_reset_token`, `password_reset_expires`
  - Auditoria: `register_id` (quem cadastrou), `dh`, `dh_update`

### Adicionado
- **Funcionalidades de CPF**
  - Validação completa de CPF brasileiro
  - Formatação automática (000.000.000-00)
  - Verificação de duplicatas
  - Índice único para performance

- **Sistema de Username**
  - Geração automática baseada no nome
  - Verificação de unicidade
  - Remoção de acentos e caracteres especiais
  - Login por email OU username

- **Código Único do Usuário**
  - Geração automática de código alfanumérico
  - Identificação única para cada usuário
  - Útil para integrações e referências

- **Múltiplos Telefones**
  - `phone_home` - Telefone residencial
  - `phone_mobile` - Telefone celular
  - `phone_message` - Telefone para recados
  - Formatação automática brasileira

- **Integração com Google**
  - Tokens de acesso e refresh
  - Controle de expiração
  - ID do calendário Google
  - Base para sincronização futura

- **Sistema de Assinatura**
  - Assinatura HTML personalizada
  - Opção de incluir logo
  - Para emails e mensagens

- **Controle de Sessão Avançado**
  - Token de sessão único
  - Registro de último acesso
  - Logout forçado de todas as sessões

- **Reset de Senha Seguro**
  - Tokens com expiração
  - Validação temporal
  - Limpeza automática

### Melhorado
- **Sistema de Autenticação**
  - Login por email OU username
  - Atualização automática de último acesso
  - Tokens de sessão mais seguros
  - Remember me com session_token

- **Validações Robustas**
  - CPF com algoritmo oficial brasileiro
  - Username único e limpo
  - Telefones formatados automaticamente
  - Email e username como identificadores

- **Auditoria Completa**
  - Registro de quem cadastrou (`register_id`)
  - Timestamps de criação e atualização
  - Soft delete mantido
  - Rastreabilidade total

### Técnico
- **Índices Otimizados**
  - Índices únicos: email, username, unique_code, cpf
  - Índices de performance: level_id, status_id, last_access
  - Foreign keys com CASCADE apropriado
  - Self-reference para register_id

- **Métodos Utilitários**
  - Formatação de CPF e telefones brasileiros
  - Geração de username limpo
  - Validação de CPF com algoritmo oficial
  - Remoção de acentos automática

---

## [1.2.0] - 2025-01-13

### Adicionado
- **Novas Tabelas de Referência Especializadas**
  - `event_types` - Tipos de eventos de acesso (login, logout, etc.)
  - `phone_types` - Tipos de telefone (celular, residencial, comercial, WhatsApp, etc.)
  - `living_with` - Tipos de parentesco/"mora com" (pais, sozinho, cônjuge, etc.)
  - `marital_status` - Estados civis (solteiro, casado, divorciado, etc.)

- **Tabelas Genéricas para Relacionamentos**
  - `addresses` - Endereços genéricos para qualquer entidade
  - `phones` - Telefones genéricos para qualquer entidade
  - Sistema de entidades polimórficas (entity_type + entity_id)

- **Models Especializados**
  - EventType.php - Gerenciamento de tipos de eventos
  - PhoneType.php - Tipos de telefone com ordenação
  - LivingWith.php - Tipos de parentesco/convivência
  - MaritalStatus.php - Estados civis
  - Address.php - Endereços com formatação e validação de CEP
  - Phone.php - Telefones com formatação e integração WhatsApp

- **Funcionalidades Avançadas**
  - Sistema de ordenação (sort_order) para listas
  - Flags de registro principal (is_primary)
  - Formatação automática de telefones brasileiros
  - Formatação de CEP e endereços completos
  - Geração de links do WhatsApp
  - Validação de números de celular
  - Estatísticas de uso por tipo

### Melhorado
- **Flexibilidade do Sistema**
  - Tabelas genéricas reutilizáveis
  - Sistema polimórfico para relacionamentos
  - Configurações ordenáveis e ativáveis

- **Usabilidade**
  - Formatação automática de dados
  - Links diretos para WhatsApp
  - Endereços formatados automaticamente
  - Ordenação customizável de listas

- **Organização de Dados**
  - Separação clara entre dados pessoais e de contato
  - Relacionamentos bem definidos
  - Integridade referencial mantida

### Técnico
- **Estrutura de Dados Normalizada**
  - Foreign keys com CASCADE apropriado
  - Índices otimizados para performance
  - Soft delete em todas as tabelas
  - Timestamps automáticos

- **Métodos Utilitários**
  - Formatação de telefones brasileiros
  - Validação de CEP
  - Detecção de números móveis
  - Estatísticas de uso automáticas

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