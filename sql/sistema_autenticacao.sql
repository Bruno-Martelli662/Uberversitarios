-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 24/04/2026 às 00:53
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

CREATE TABLE `sessoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token_sessao` varchar(255) NOT NULL,
  `data_expiracao` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_adm`
--

CREATE TABLE `user_adm` (
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

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome_usuario` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `confirmado` tinyint(1) DEFAULT 0,
  `token_confirmacao` varchar(255) DEFAULT NULL,
  `google_2fa_secret` varchar(32) DEFAULT NULL,
  `google_2fa_ativado` tinyint(1) DEFAULT 0,
  `banido` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `viagens`
--

CREATE TABLE `viagens` (
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

CREATE TABLE `logs_acao` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` enum('LEITURA', 'ESCRITA', 'ALTERACAO', 'EXCLUSAO') NOT NULL,
  `descricao` text NOT NULL,
  `ip_origem` varchar(45) NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- ÍNDICES
-- --------------------------------------------------------

ALTER TABLE `sessoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

ALTER TABLE `user_adm`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `telegram_id` (`telegram_id`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `telefone` (`telefone`);

ALTER TABLE `viagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `motorista_id` (`motorista_id`),
  ADD KEY `passageiro_id` (`passageiro_id`),
  ADD KEY `contato` (`contato`);

ALTER TABLE `logs_acao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

-- --------------------------------------------------------

-- AUTO_INCREMENT
-- --------------------------------------------------------

ALTER TABLE `sessoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_adm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `viagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `logs_acao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

-- RELAÇÕES (FOREIGN KEYS)
-- --------------------------------------------------------

ALTER TABLE `sessoes`
  ADD CONSTRAINT `sessoes_ibfk_1`
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
  ON DELETE CASCADE;

ALTER TABLE `viagens`
  ADD CONSTRAINT `viagens_ibfk_1`
  FOREIGN KEY (`motorista_id`) REFERENCES `usuarios` (`id`)
  ON DELETE CASCADE,

  ADD CONSTRAINT `viagens_ibfk_2`
  FOREIGN KEY (`passageiro_id`) REFERENCES `usuarios` (`id`)
  ON DELETE SET NULL,

  ADD CONSTRAINT `viagens_ibfk_3`
  FOREIGN KEY (`contato`) REFERENCES `usuarios` (`telefone`)
  ON DELETE SET NULL;

ALTER TABLE `logs_acao`
  ADD CONSTRAINT `logs_acao_ibfk_1`
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
  ON DELETE SET NULL;

COMMIT;

-- =========================
-- USUÁRIOS SEGREGADOS MYSQL
-- =========================

CREATE USER IF NOT EXISTS 'uberv_auth'@'localhost'
IDENTIFIED BY 'Auth@2026!segura';

CREATE USER IF NOT EXISTS 'uberv_read'@'localhost'
IDENTIFIED BY 'Read@2026!segura';

CREATE USER IF NOT EXISTS 'uberv_write'@'localhost'
IDENTIFIED BY 'Write@2026!segura';

CREATE USER IF NOT EXISTS 'uberv_admin'@'localhost'
IDENTIFIED BY 'Admin@2026!segura';

-- Atualiza senha caso usuário já exista
ALTER USER 'uberv_auth'@'localhost'
IDENTIFIED BY 'Auth@2026!segura';

ALTER USER 'uberv_read'@'localhost'
IDENTIFIED BY 'Read@2026!segura';

ALTER USER 'uberv_write'@'localhost'
IDENTIFIED BY 'Write@2026!segura';

ALTER USER 'uberv_admin'@'localhost'
IDENTIFIED BY 'Admin@2026!segura';

-- =========================
-- LIMPA PERMISSÕES ANTIGAS
-- =========================

REVOKE ALL PRIVILEGES, GRANT OPTION
FROM 'uberv_auth'@'localhost';

REVOKE ALL PRIVILEGES, GRANT OPTION
FROM 'uberv_read'@'localhost';

REVOKE ALL PRIVILEGES, GRANT OPTION
FROM 'uberv_write'@'localhost';

REVOKE ALL PRIVILEGES, GRANT OPTION
FROM 'uberv_admin'@'localhost';

-- =========================
-- AUTH
-- login, cadastro, sessões,
-- recuperação de senha, 2FA
-- =========================

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.usuarios
TO 'uberv_auth'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.sessoes
TO 'uberv_auth'@'localhost';

-- =========================
-- READ
-- leitura pública/listagens
-- =========================

GRANT SELECT
ON sistema_autenticacao.usuarios
TO 'uberv_read'@'localhost';

GRANT SELECT
ON sistema_autenticacao.viagens
TO 'uberv_read'@'localhost';

-- =========================
-- WRITE
-- cadastro/alteração viagens
-- =========================

GRANT SELECT, INSERT, UPDATE
ON sistema_autenticacao.usuarios
TO 'uberv_write'@'localhost';

GRANT SELECT, INSERT, UPDATE
ON sistema_autenticacao.viagens
TO 'uberv_write'@'localhost';

-- =========================
-- ADMIN
-- painel administrativo
-- =========================

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.usuarios
TO 'uberv_admin'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.viagens
TO 'uberv_admin'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.sessoes
TO 'uberv_admin'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.user_adm
TO 'uberv_admin'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.logs_acao
TO 'uberv_admin'@'localhost';

FLUSH PRIVILEGES;

-- =========================
-- VERIFICAÇÃO
-- =========================

SHOW GRANTS FOR 'uberv_auth'@'localhost';
SHOW GRANTS FOR 'uberv_read'@'localhost';
SHOW GRANTS FOR 'uberv_write'@'localhost';
SHOW GRANTS FOR 'uberv_admin'@'localhost';

-- =========================
-- IMPORTANTE
-- =========================
-- usuarios.banido -> bloqueio de usuários no painel admin
-- user_adm -> controle de administradores via Telegram