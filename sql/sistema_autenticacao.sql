-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 14/05/2026 às 22:50
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `sistema_autenticacao`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `lgpd_arquivamento`
--

CREATE TABLE `lgpd_arquivamento` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `dados_json` longtext NOT NULL,
  `motivo_exclusao` varchar(255) DEFAULT NULL,
  `operador_id` int(11) DEFAULT NULL,
  `data_exclusao` datetime DEFAULT current_timestamp(),
  `ip_origem` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_acao`
--

CREATE TABLE `logs_acao` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` enum('LEITURA','ESCRITA','ALTERACAO','EXCLUSAO') NOT NULL,
  `descricao` text NOT NULL,
  `ip_origem` varchar(45) NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `logs_acao`
--

INSERT INTO `logs_acao` (`id`, `usuario_id`, `acao`, `descricao`, `ip_origem`, `criado_em`) VALUES
(1, 1, 'ESCRITA', 'Novo usuário cadastrado com e-mail: elgsongabriel@gmail.com', '127.0.0.1', '2026-05-14 16:03:29');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sessoes`
--

CREATE TABLE `sessoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token_sessao` varchar(255) NOT NULL,
  `data_expiracao` datetime NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `sessoes`
--

INSERT INTO `sessoes` (`id`, `usuario_id`, `token_sessao`, `data_expiracao`, `criado_em`) VALUES
(1, 1, 'ac618298b1cd34e945586eef96957416194c901585ea07ee9f3d7cb10ac46fcb', '2026-05-15 16:04:46', '2026-05-14 16:04:46'),
(2, 1, '09f665ac033d37fcd08ca397db3c1f5c39b66ee0c4ea2b81e2ba9852e408b54e', '2026-05-15 16:05:28', '2026-05-14 16:05:28'),
(3, 1, '2250445fcabf65305a2c5f65560ae27fa1ccebdf53c7add821ea0034ade07db1', '2026-05-15 16:05:37', '2026-05-14 16:05:37'),
(4, 1, '49e4b3ca74952c85ea12ceeee84946b6220b3a7d6de9d96490c95efe9aaaa4c7', '2026-05-15 16:06:31', '2026-05-14 16:06:31'),
(5, 1, '17bc43c984d89176d409412ba85be2f888bb1bd218a8014158ca1aabae366a98', '2026-05-15 17:09:33', '2026-05-14 17:09:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_adm`
--

CREATE TABLE `user_adm` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telegram_id` bigint(20) DEFAULT NULL,
  `telegram_username` varchar(100) DEFAULT NULL,
  `telegram_auth_token` varchar(255) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  `email_login_token` varchar(255) DEFAULT NULL,
  `email_login_expira` datetime DEFAULT NULL,
  `email_login_confirmado` tinyint(1) DEFAULT 0,
  `codigo_login_hash` varchar(255) DEFAULT NULL,
  `codigo_login_expira` datetime DEFAULT NULL,
  `admin_sessao_token` varchar(255) DEFAULT NULL,
  `admin_sessao_expira` datetime DEFAULT NULL,
  `resposta_seguranca_1_hash` varchar(64) DEFAULT NULL,
  `resposta_seguranca_2_hash` varchar(64) DEFAULT NULL,
  `perguntas_configuradas` tinyint(1) NOT NULL DEFAULT 0,
  `tentativas_pergunta` int(11) NOT NULL DEFAULT 0,
  `bloqueado_pergunta_ate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `user_adm`
--

INSERT INTO `user_adm` (`id`, `email`, `telegram_id`, `telegram_username`, `telegram_auth_token`, `criado_em`, `email_login_token`, `email_login_expira`, `email_login_confirmado`, `codigo_login_hash`, `codigo_login_expira`, `admin_sessao_token`, `admin_sessao_expira`, `resposta_seguranca_1_hash`, `resposta_seguranca_2_hash`, `perguntas_configuradas`, `tentativas_pergunta`, `bloqueado_pergunta_ate`) VALUES
(2, 'elgsonascimento@gmail.com', 99999999, 'Dkkkkkkz', NULL, '2026-05-14 17:40:19', '4cb10ca1d2d447dc147b8294e3f4d11c0f121f88812d24fee94c6bd317fd6fab', '2026-05-14 17:58:07', 1, '0317d1bfab65d1b1ba982e27cc2a97427a58b329483d93cc92c523252c3e6718', '2026-05-14 17:54:42', NULL, NULL, 'f2c736a5f4ea6b533410cc4a8d90b6c57dcc283f6f64ff3f230ec228d38b2a68', 'ce88052e6b1d97e5fc4638cbc687202c2b75507921292084be279aaaf3c850b6', 1, 0, '2026-05-14 19:49:58');

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
  `token_recuperacao` varchar(255) DEFAULT NULL,
  `token_recuperacao_expira` datetime DEFAULT NULL,
  `google_2fa_secret` varchar(32) DEFAULT NULL,
  `google_2fa_ativado` tinyint(1) DEFAULT 0,
  `banido` tinyint(1) DEFAULT 0,
  `criado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome_usuario`, `email`, `telefone`, `senha_hash`, `confirmado`, `token_confirmacao`, `token_recuperacao`, `token_recuperacao_expira`, `google_2fa_secret`, `google_2fa_ativado`, `banido`, `criado_em`, `atualizado_em`) VALUES
(1, 'elgson', 'elgsongabriel@gmail.com', '41987987979', 'e5ce28a079332466d623dc837ee6156bf4a0fb85857763138d6ea08898f1865a', 1, NULL, NULL, NULL, NULL, 0, 0, '2026-05-14 16:03:29', '2026-05-14 16:04:28');

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
  `criada_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `lgpd_arquivamento`
--
ALTER TABLE `lgpd_arquivamento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lgpd_usuario` (`usuario_id`);

--
-- Índices de tabela `logs_acao`
--
ALTER TABLE `logs_acao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_usuario` (`usuario_id`);

--
-- Índices de tabela `sessoes`
--
ALTER TABLE `sessoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_token_sessao` (`token_sessao`),
  ADD KEY `idx_sessoes_usuario` (`usuario_id`);

--
-- Índices de tabela `user_adm`
--
ALTER TABLE `user_adm`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_adm_email` (`email`),
  ADD UNIQUE KEY `uk_user_adm_telegram` (`telegram_id`),
  ADD KEY `idx_user_adm_email_login_token` (`email_login_token`),
  ADD KEY `idx_user_adm_admin_sessao_token` (`admin_sessao_token`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_usuarios_email` (`email`),
  ADD UNIQUE KEY `uk_usuarios_telefone` (`telefone`),
  ADD UNIQUE KEY `uk_usuarios_nome` (`nome_usuario`);

--
-- Índices de tabela `viagens`
--
ALTER TABLE `viagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_viagens_motorista` (`motorista_id`),
  ADD KEY `idx_viagens_passageiro` (`passageiro_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `lgpd_arquivamento`
--
ALTER TABLE `lgpd_arquivamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs_acao`
--
ALTER TABLE `logs_acao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `sessoes`
--
ALTER TABLE `sessoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `user_adm`
--
ALTER TABLE `user_adm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `viagens`
--
ALTER TABLE `viagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `logs_acao`
--
ALTER TABLE `logs_acao`
  ADD CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `sessoes`
--
ALTER TABLE `sessoes`
  ADD CONSTRAINT `fk_sessoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `viagens`
--
ALTER TABLE `viagens`
  ADD CONSTRAINT `fk_viagem_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_viagem_passageiro` FOREIGN KEY (`passageiro_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
