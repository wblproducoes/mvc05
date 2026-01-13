-- Sistema Administrativo MVC - Schema do Banco de Dados
-- Versão: 1.1.0
-- Compatível com MySQL 5.7+ e MariaDB 10.2+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

-- Tabela de Gêneros
CREATE TABLE IF NOT EXISTS `genders` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `translate` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `dh` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `dh_update` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_genders_name` (`name`),
    KEY `idx_genders_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabela de Níveis de Acesso
CREATE TABLE IF NOT EXISTS `levels` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `translate` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `dh` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `dh_update` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_levels_name` (`name`),
    KEY `idx_levels_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabela de Status
CREATE TABLE IF NOT EXISTS `status` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `translate` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `color` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'secondary',
    `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `dh` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `dh_update` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status_name` (`name`),
    KEY `idx_status_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabela de usuários (atualizada para usar as tabelas de referência)
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `level_id` int(11) NOT NULL DEFAULT 11,
    `gender_id` int(11) DEFAULT NULL,
    `status_id` int(11) NOT NULL DEFAULT 1,
    `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `birth_date` date DEFAULT NULL,
    `document` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `state` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `zip_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `remember_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `last_login` datetime DEFAULT NULL,
    `login_count` int(11) NOT NULL DEFAULT 0,
    `email_verified_at` datetime DEFAULT NULL,
    `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `idx_users_level` (`level_id`),
    KEY `idx_users_gender` (`gender_id`),
    KEY `idx_users_status` (`status_id`),
    KEY `idx_users_created_at` (`created_at`),
    KEY `idx_users_deleted_at` (`deleted_at`),
    KEY `idx_users_document` (`document`),
    CONSTRAINT `fk_users_level` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_users_gender` FOREIGN KEY (`gender_id`) REFERENCES `genders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_users_status` FOREIGN KEY (`status_id`) REFERENCES `status` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabela de logs de atividades
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `model_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `model_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `properties` json DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_logs_user_id` (`user_id`),
    KEY `idx_activity_logs_action` (`action`),
    KEY `idx_activity_logs_model` (`model_type`, `model_id`),
    KEY `idx_activity_logs_created_at` (`created_at`),
    CONSTRAINT `fk_activity_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `value` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `type` enum('string','integer','boolean','json','array') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
    `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `group` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'general',
    `is_public` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `settings_key_unique` (`key`),
    KEY `idx_settings_group` (`group`),
    KEY `idx_settings_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabela de tokens de redefinição de senha
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `expires_at` datetime NOT NULL,
    `used_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_password_resets_email` (`email`),
    KEY `idx_password_resets_token` (`token`),
    KEY `idx_password_resets_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabela de sessões (opcional, para armazenar sessões no banco)
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `last_activity` int(11) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_user_id` (`user_id`),
    KEY `idx_sessions_last_activity` (`last_activity`),
    CONSTRAINT `fk_sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabela de notificações
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
    `type` enum('info','success','warning','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
    `read_at` datetime DEFAULT NULL,
    `action_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `data` json DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_id` (`user_id`),
    KEY `idx_notifications_read_at` (`read_at`),
    KEY `idx_notifications_created_at` (`created_at`),
    CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Inserir dados padrão nas tabelas de referência

-- Gêneros
INSERT INTO `genders` (`id`, `name`, `translate`, `description`) VALUES
(1, 'male', 'Masculino', 'Gênero masculino'),
(2, 'female', 'Feminino', 'Gênero feminino'),
(3, 'other', 'Outro', 'Outros gêneros'),
(4, 'not_informed', 'Não informado', 'Prefere não informar')
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `translate` = VALUES(`translate`),
    `description` = VALUES(`description`);

-- Níveis de Acesso
INSERT INTO `levels` (`id`, `name`, `translate`, `description`) VALUES
(1, 'master', 'Master', 'Acesso total ao sistema'),
(2, 'admin', 'Administrador', 'Administrador do sistema'),
(3, 'direction', 'Direção', 'Direção escolar'),
(4, 'financial', 'Financeiro', 'Setor financeiro'),
(5, 'coordination', 'Coordenação', 'Coordenação pedagógica'),
(6, 'secretary', 'Secretaria', 'Secretaria escolar'),
(7, 'teacher', 'Professor', 'Professor'),
(8, 'employee', 'Funcionário', 'Funcionário geral'),
(9, 'student', 'Aluno', 'Aluno da escola'),
(10, 'guardian', 'Responsável', 'Responsável pelo aluno'),
(11, 'user', 'Usuário', 'Usuário comum')
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `translate` = VALUES(`translate`),
    `description` = VALUES(`description`);

-- Status
INSERT INTO `status` (`id`, `name`, `translate`, `color`, `description`) VALUES
(1, 'active', 'Ativo', 'success', 'Registro ativo'),
(2, 'inactive', 'Inativo', 'warning', 'Registro inativo'),
(3, 'blocked', 'Bloqueado', 'danger', 'Registro bloqueado'),
(4, 'deleted', 'Excluído', 'dark', 'Registro excluído'),
(5, 'completed', 'Concluído', 'info', 'Registro concluído'),
(6, 'overdue', 'Vencido', 'danger', 'Registro vencido'),
(7, 'pending', 'Pendente', 'secondary', 'Aguardando aprovação'),
(8, 'suspended', 'Suspenso', 'warning', 'Temporariamente suspenso')
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `translate` = VALUES(`translate`),
    `color` = VALUES(`color`),
    `description` = VALUES(`description`);
INSERT INTO `settings` (`key`, `value`, `type`, `description`, `group`, `is_public`) VALUES
('app_name', 'Sistema Administrativo MVC', 'string', 'Nome da aplicação', 'general', 1),
('app_version', '1.0.0', 'string', 'Versão da aplicação', 'general', 1),
('app_description', 'Sistema administrativo completo desenvolvido em PHP com arquitetura MVC', 'string', 'Descrição da aplicação', 'general', 1),
('maintenance_mode', '0', 'boolean', 'Modo de manutenção ativo', 'general', 0),
('user_registration', '0', 'boolean', 'Permitir registro de novos usuários', 'users', 0),
('email_verification', '0', 'boolean', 'Verificação de email obrigatória', 'users', 0),
('max_login_attempts', '5', 'integer', 'Máximo de tentativas de login', 'security', 0),
('session_lifetime', '7200', 'integer', 'Tempo de vida da sessão em segundos', 'security', 0),
('password_min_length', '8', 'integer', 'Tamanho mínimo da senha', 'security', 0),
('backup_enabled', '1', 'boolean', 'Backup automático habilitado', 'system', 0),
('backup_frequency', 'daily', 'string', 'Frequência do backup (daily, weekly, monthly)', 'system', 0),
('timezone', 'America/Sao_Paulo', 'string', 'Fuso horário do sistema', 'general', 1),
('date_format', 'd/m/Y', 'string', 'Formato de data padrão', 'general', 1),
('datetime_format', 'd/m/Y H:i:s', 'string', 'Formato de data e hora padrão', 'general', 1);

COMMIT;