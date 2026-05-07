-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 07/05/2026
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Banco de dados: `sistema_autenticacao`
--

CREATE DATABASE IF NOT EXISTS sistema_autenticacao;
USE sistema_autenticacao;

-- --------------------------------------------------------

--
-- Estrutura para tabela `sessoes`
--

CREATE TABLE IF NOT EXISTS `sessoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token_sessao` varchar(255) NOT NULL,
  `data_expiracao` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_adm`
--

CREATE TABLE IF NOT EXISTS `user_adm` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telegram_id` bigint(20) DEFAULT NULL,
  `telegram_username` varchar(100) DEFAULT NULL,
  `telegram_auth_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL,
  `nome_usuario` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `confirmado` tinyint(1) DEFAULT 0,
  `token_confirmacao` varchar(255) DEFAULT NULL,
  `token_recuperacao` varchar(255) DEFAULT NULL,
  `token_recuperacao_expira` datetime DEFAULT NULL,
  `google_2fa_secret` varchar(32) DEFAULT NULL,
  `google_2fa_ativado` tinyint(1) DEFAULT 0,
  `banido` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `viagens`
--

CREATE TABLE IF NOT EXISTS `viagens` (
  `id` int(11) NOT NULL,
  `motorista_id` int(11) NOT NULL,
  `passageiro_id` int(11) DEFAULT NULL,
  `contato` varchar(20) DEFAULT NULL,
  `origem` varchar(150) NOT NULL,
  `destino` varchar(150) NOT NULL,
  `veiculo` varchar(100) DEFAULT NULL,
  `criada_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_acao`
--

CREATE TABLE IF NOT EXISTS `logs_acao` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` enum('LEITURA', 'ESCRITA', 'ALTERACAO', 'EXCLUSAO') NOT NULL,
  `descricao` text NOT NULL,
  `ip_origem` varchar(45) NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `lgpd_arquivamento`
--

CREATE TABLE IF NOT EXISTS `lgpd_arquivamento` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `dados_json` JSON NOT NULL,
  `data_exclusao` datetime DEFAULT current_timestamp(),
  `ip_origem` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- ÍNDICES (Ignorando erros se os índices já existirem)
-- --------------------------------------------------------

ALTER TABLE `sessoes` ADD PRIMARY KEY IF NOT EXISTS (`id`), ADD KEY IF NOT EXISTS `usuario_id` (`usuario_id`);
ALTER TABLE `user_adm` ADD PRIMARY KEY IF NOT EXISTS (`id`), ADD UNIQUE KEY IF NOT EXISTS `email` (`email`), ADD UNIQUE KEY IF NOT EXISTS `telegram_id` (`telegram_id`);
ALTER TABLE `usuarios` ADD PRIMARY KEY IF NOT EXISTS (`id`), ADD UNIQUE KEY IF NOT EXISTS `email` (`email`), ADD UNIQUE KEY IF NOT EXISTS `telefone` (`telefone`);
ALTER TABLE `viagens` ADD PRIMARY KEY IF NOT EXISTS (`id`), ADD KEY IF NOT EXISTS `motorista_id` (`motorista_id`), ADD KEY IF NOT EXISTS `passageiro_id` (`passageiro_id`), ADD KEY IF NOT EXISTS `contato` (`contato`);
ALTER TABLE `logs_acao` ADD PRIMARY KEY IF NOT EXISTS (`id`), ADD KEY IF NOT EXISTS `usuario_id` (`usuario_id`);
ALTER TABLE `lgpd_arquivamento` ADD PRIMARY KEY IF NOT EXISTS (`id`);

-- --------------------------------------------------------
-- AUTO_INCREMENT
-- --------------------------------------------------------

ALTER TABLE `sessoes` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_adm` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `usuarios` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `viagens` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `logs_acao` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `lgpd_arquivamento` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- RELAÇÕES (FOREIGN KEYS) - Adicionando apenas se não existirem
-- Para evitar erros caso você já tenha essas relações, as restrições normais foram omitidas.
-- Se for recriar o banco do zero, garanta as Foreign Keys.
-- --------------------------------------------------------

COMMIT;

-- =========================
-- USUÁRIOS SEGREGADOS MYSQL
-- =========================

CREATE USER IF NOT EXISTS 'uberv_auth'@'localhost' IDENTIFIED BY 'Auth@2026!segura';
CREATE USER IF NOT EXISTS 'uberv_read'@'localhost' IDENTIFIED BY 'Read@2026!segura';
CREATE USER IF NOT EXISTS 'uberv_write'@'localhost' IDENTIFIED BY 'Write@2026!segura';
CREATE USER IF NOT EXISTS 'uberv_admin'@'localhost' IDENTIFIED BY 'Admin@2026!segura';

-- Atualiza senha caso usuário já exista
ALTER USER 'uberv_auth'@'localhost' IDENTIFIED BY 'Auth@2026!segura';
ALTER USER 'uberv_read'@'localhost' IDENTIFIED BY 'Read@2026!segura';
ALTER USER 'uberv_write'@'localhost' IDENTIFIED BY 'Write@2026!segura';
ALTER USER 'uberv_admin'@'localhost' IDENTIFIED BY 'Admin@2026!segura';

-- =========================
-- LIMPA PERMISSÕES ANTIGAS
-- =========================

REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'uberv_auth'@'localhost';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'uberv_read'@'localhost';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'uberv_write'@'localhost';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'uberv_admin'@'localhost';

-- =========================
-- AUTH
-- =========================
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_autenticacao.usuarios TO 'uberv_auth'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_autenticacao.sessoes TO 'uberv_auth'@'localhost';
-- A permissão que estava faltando para o backup na exclusão:
GRANT INSERT ON sistema_autenticacao.lgpd_arquivamento TO 'uberv_auth'@'localhost';

-- =========================
-- READ
-- =========================
GRANT SELECT ON sistema_autenticacao.usuarios TO 'uberv_read'@'localhost';
GRANT SELECT ON sistema_autenticacao.viagens TO 'uberv_read'@'localhost';

-- =========================
-- WRITE
-- =========================
GRANT SELECT, INSERT, UPDATE ON sistema_autenticacao.usuarios TO 'uberv_write'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_autenticacao.viagens TO 'uberv_write'@'localhost';

-- =========================
-- ADMIN
-- =========================
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_autenticacao.usuarios TO 'uberv_admin'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_autenticacao.viagens TO 'uberv_admin'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_autenticacao.sessoes TO 'uberv_admin'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_autenticacao.user_adm TO 'uberv_admin'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_autenticacao.logs_acao TO 'uberv_admin'@'localhost';
-- Administrador pode gerenciar o cofre
GRANT SELECT, INSERT, UPDATE, DELETE ON sistema_autenticacao.lgpd_arquivamento TO 'uberv_admin'@'localhost';

FLUSH PRIVILEGES;