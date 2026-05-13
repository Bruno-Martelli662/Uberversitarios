-- phpMyAdmin SQL Dump
-- versão compatível com MariaDB 10.4+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =========================
-- CRIAÇÃO DO BANCO
-- =========================

CREATE DATABASE IF NOT EXISTS sistema_autenticacao
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE sistema_autenticacao;

-- =====================================================
-- TABELA: usuarios
-- =====================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id INT NOT NULL AUTO_INCREMENT,
    nome_usuario VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,

    confirmado TINYINT(1) DEFAULT 0,

    token_confirmacao VARCHAR(255) DEFAULT NULL,

    token_recuperacao VARCHAR(255) DEFAULT NULL,
    token_recuperacao_expira DATETIME DEFAULT NULL,

    google_2fa_secret VARCHAR(32) DEFAULT NULL,
    google_2fa_ativado TINYINT(1) DEFAULT 0,

    banido TINYINT(1) DEFAULT 0,

    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_usuarios_email (email),
    UNIQUE KEY uk_usuarios_telefone (telefone),
    UNIQUE KEY uk_usuarios_nome (nome_usuario)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: sessoes
-- =====================================================

CREATE TABLE IF NOT EXISTS sessoes (
    id INT NOT NULL AUTO_INCREMENT,

    usuario_id INT NOT NULL,

    token_sessao VARCHAR(255) NOT NULL,

    data_expiracao DATETIME NOT NULL,

    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_token_sessao (token_sessao),

    KEY idx_sessoes_usuario (usuario_id),

    CONSTRAINT fk_sessoes_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: user_adm
-- =====================================================

CREATE TABLE IF NOT EXISTS user_adm (
    id INT NOT NULL AUTO_INCREMENT,

    email VARCHAR(255) NOT NULL,

    telegram_id BIGINT(20) DEFAULT NULL,
    telegram_username VARCHAR(100) DEFAULT NULL,
    telegram_auth_token VARCHAR(255) DEFAULT NULL,

    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_user_adm_email (email),
    UNIQUE KEY uk_user_adm_telegram (telegram_id)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: viagens
-- =====================================================

CREATE TABLE IF NOT EXISTS viagens (
    id INT NOT NULL AUTO_INCREMENT,

    motorista_id INT NOT NULL,
    passageiro_id INT DEFAULT NULL,

    contato VARCHAR(20) DEFAULT NULL,

    origem VARCHAR(150) NOT NULL,
    destino VARCHAR(150) NOT NULL,

    veiculo VARCHAR(100) DEFAULT NULL,

    criada_em DATETIME DEFAULT CURRENT_TIMESTAMP,

    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_viagens_motorista (motorista_id),
    KEY idx_viagens_passageiro (passageiro_id),

    CONSTRAINT fk_viagem_motorista
        FOREIGN KEY (motorista_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_viagem_passageiro
        FOREIGN KEY (passageiro_id)
        REFERENCES usuarios(id)
        ON DELETE SET NULL

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: logs_acao
-- =====================================================

CREATE TABLE IF NOT EXISTS logs_acao (
    id INT NOT NULL AUTO_INCREMENT,

    usuario_id INT DEFAULT NULL,

    acao ENUM(
        'LEITURA',
        'ESCRITA',
        'ALTERACAO',
        'EXCLUSAO'
    ) NOT NULL,

    descricao TEXT NOT NULL,

    ip_origem VARCHAR(45) NOT NULL,

    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_logs_usuario (usuario_id),

    CONSTRAINT fk_logs_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE SET NULL

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: lgpd_arquivamento
-- =====================================================

CREATE TABLE IF NOT EXISTS lgpd_arquivamento (
    id INT NOT NULL AUTO_INCREMENT,

    usuario_id INT NOT NULL,

    dados_json LONGTEXT NOT NULL,

    motivo_exclusao VARCHAR(255) DEFAULT NULL,

    operador_id INT DEFAULT NULL,

    data_exclusao DATETIME DEFAULT CURRENT_TIMESTAMP,

    ip_origem VARCHAR(45) NOT NULL,

    PRIMARY KEY (id),

    KEY idx_lgpd_usuario (usuario_id),

    CONSTRAINT fk_lgpd_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

COMMIT;

-- =====================================================
-- USUÁRIOS MYSQL
-- =====================================================

CREATE USER IF NOT EXISTS 'uberv_auth'@'localhost'
IDENTIFIED BY 'Auth@2026!segura';

CREATE USER IF NOT EXISTS 'uberv_read'@'localhost'
IDENTIFIED BY 'Read@2026!segura';

CREATE USER IF NOT EXISTS 'uberv_write'@'localhost'
IDENTIFIED BY 'Write@2026!segura';

CREATE USER IF NOT EXISTS 'uberv_admin'@'localhost'
IDENTIFIED BY 'Admin@2026!segura';

-- =====================================================
-- ATUALIZA SENHAS
-- =====================================================

ALTER USER 'uberv_auth'@'localhost'
IDENTIFIED BY 'Auth@2026!segura';

ALTER USER 'uberv_read'@'localhost'
IDENTIFIED BY 'Read@2026!segura';

ALTER USER 'uberv_write'@'localhost'
IDENTIFIED BY 'Write@2026!segura';

ALTER USER 'uberv_admin'@'localhost'
IDENTIFIED BY 'Admin@2026!segura';

-- =====================================================
-- REMOVE PRIVILÉGIOS ANTIGOS
-- =====================================================

REVOKE ALL PRIVILEGES, GRANT OPTION
FROM 'uberv_auth'@'localhost';

REVOKE ALL PRIVILEGES, GRANT OPTION
FROM 'uberv_read'@'localhost';

REVOKE ALL PRIVILEGES, GRANT OPTION
FROM 'uberv_write'@'localhost';

REVOKE ALL PRIVILEGES, GRANT OPTION
FROM 'uberv_admin'@'localhost';

-- =====================================================
-- AUTH
-- =====================================================

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.usuarios
TO 'uberv_auth'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.sessoes
TO 'uberv_auth'@'localhost';

GRANT INSERT
ON sistema_autenticacao.lgpd_arquivamento
TO 'uberv_auth'@'localhost';

-- =====================================================
-- READ
-- =====================================================

GRANT SELECT
ON sistema_autenticacao.usuarios
TO 'uberv_read'@'localhost';

GRANT SELECT
ON sistema_autenticacao.viagens
TO 'uberv_read'@'localhost';

-- =====================================================
-- WRITE
-- =====================================================

GRANT SELECT, INSERT, UPDATE
ON sistema_autenticacao.usuarios
TO 'uberv_write'@'localhost';

GRANT SELECT, INSERT, UPDATE
ON sistema_autenticacao.viagens
TO 'uberv_write'@'localhost';

-- =====================================================
-- ADMIN
-- =====================================================

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

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.lgpd_arquivamento
TO 'uberv_admin'@'localhost';

-- =========================
-- ADMIN PASSWORDLESS
-- E-MAIL + TELEGRAM
-- =========================

USE sistema_autenticacao;

ALTER TABLE user_adm
  ADD COLUMN IF NOT EXISTS criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS email_login_token VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS email_login_expira DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS email_login_confirmado TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS codigo_login_hash VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS codigo_login_expira DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS admin_sessao_token VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS admin_sessao_expira DATETIME DEFAULT NULL;

-- Índices úteis para login admin
CREATE INDEX IF NOT EXISTS idx_user_adm_email_login_token
ON user_adm (email_login_token);

CREATE INDEX IF NOT EXISTS idx_user_adm_admin_sessao_token
ON user_adm (admin_sessao_token);


FLUSH PRIVILEGES;