-- =========================
-- USUÁRIOS MYSQL SEGREGADOS
-- =========================

CREATE USER IF NOT EXISTS 'uberv_auth'@'localhost'
IDENTIFIED BY 'Auth@2026!segura';

CREATE USER IF NOT EXISTS 'uberv_read'@'localhost'
IDENTIFIED BY 'Read@2026!segura';

CREATE USER IF NOT EXISTS 'uberv_write'@'localhost'
IDENTIFIED BY 'Write@2026!segura';

CREATE USER IF NOT EXISTS 'uberv_admin'@'localhost'
IDENTIFIED BY 'Admin@2026!segura';

-- =========================
-- ATUALIZA SENHAS
-- =========================

ALTER USER 'uberv_auth'@'localhost'
IDENTIFIED BY 'Auth@2026!segura';

ALTER USER 'uberv_read'@'localhost'
IDENTIFIED BY 'Read@2026!segura';

ALTER USER 'uberv_write'@'localhost'
IDENTIFIED BY 'Write@2026!segura';

ALTER USER 'uberv_admin'@'localhost'
IDENTIFIED BY 'Admin@2026!segura';

-- =========================
-- REMOVE PRIVILÉGIOS ANTIGOS
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
-- login, cadastro, recuperação,
-- confirmação, sessões e 2FA
-- =========================

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.usuarios
TO 'uberv_auth'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.sessoes
TO 'uberv_auth'@'localhost';

GRANT INSERT
ON sistema_autenticacao.logs_acao
TO 'uberv_auth'@'localhost';

GRANT INSERT
ON sistema_autenticacao.lgpd_arquivamento
TO 'uberv_auth'@'localhost';

-- =========================
-- READ
-- leitura pública
-- =========================

GRANT SELECT
ON sistema_autenticacao.usuarios
TO 'uberv_read'@'localhost';

GRANT SELECT
ON sistema_autenticacao.viagens
TO 'uberv_read'@'localhost';

GRANT SELECT
ON sistema_autenticacao.denuncias
TO 'uberv_read'@'localhost';

-- =========================
-- WRITE
-- cadastro/edição de viagens
-- =========================

GRANT SELECT, INSERT, UPDATE
ON sistema_autenticacao.viagens
TO 'uberv_write'@'localhost';

GRANT SELECT, INSERT, UPDATE
ON sistema_autenticacao.denuncias
TO 'uberv_write'@'localhost';

GRANT SELECT
ON sistema_autenticacao.usuarios
TO 'uberv_write'@'localhost';

GRANT INSERT
ON sistema_autenticacao.logs_acao
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
ON sistema_autenticacao.logs_acao
TO 'uberv_admin'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.user_adm
TO 'uberv_admin'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.lgpd_arquivamento
TO 'uberv_admin'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON sistema_autenticacao.denuncias
TO 'uberv_admin'@'localhost';

-- =========================
-- APLICA ALTERAÇÕES
-- =========================

FLUSH PRIVILEGES;

-- =========================
-- VERIFICAÇÃO
-- =========================

SHOW GRANTS FOR 'uberv_auth'@'localhost';
SHOW GRANTS FOR 'uberv_read'@'localhost';
SHOW GRANTS FOR 'uberv_write'@'localhost';
SHOW GRANTS FOR 'uberv_admin'@'localhost';
