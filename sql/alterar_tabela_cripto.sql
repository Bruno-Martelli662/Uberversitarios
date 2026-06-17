-- =====================================================================
--  S.3.2  —  Ajuste de colunas para guardar dados cifrados
-- ---------------------------------------------------------------------
--  Os campos cifrados são gravados como base64(IV + ciphertext), que é
--  bem maior que o texto original:
--    - telefone (11 dígitos)        -> ~44 caracteres  (não cabe em VARCHAR(20))
--    - nome (até 100 caracteres)    -> ~172 caracteres (não cabe em VARCHAR(100))
--
--  email NÃO é alterado: continua em texto porque é usado em WHERE no login.
--  senha_hash NÃO é alterado: já é um hash de tamanho fixo.
--
--  Rode isto ANTES de cadastrar usuários com a criptografia do BD ligada.
--  Banco: sistema_autenticacao
-- =====================================================================

ALTER TABLE `usuarios`
    MODIFY `nome_usuario` VARCHAR(255) NOT NULL,
    MODIFY `telefone`     VARCHAR(255) NOT NULL;