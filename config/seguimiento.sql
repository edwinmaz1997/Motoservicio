-- Token público para que el cliente consulte su orden sin iniciar sesión
ALTER TABLE ordenes
    ADD COLUMN token_publico VARCHAR(40) DEFAULT NULL,
    ADD UNIQUE KEY uk_token_publico (token_publico);

-- Generar token para las órdenes que ya existen
UPDATE ordenes
SET token_publico = LOWER(CONCAT(
        SUBSTRING(MD5(CONCAT(id, numero_orden, RAND(), UUID())), 1, 10),
        SUBSTRING(MD5(CONCAT(RAND(), id, NOW(6))), 1, 10)
    ))
WHERE token_publico IS NULL;
