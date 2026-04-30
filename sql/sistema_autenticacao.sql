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
-- IMPORTANTE
-- =========================
-- usuarios.banido -> bloqueio de usuários no painel admin
-- user_adm -> controle de administradores via Telegram