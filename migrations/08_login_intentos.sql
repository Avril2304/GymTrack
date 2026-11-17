-- 08_login_intentos.sql
-- Tabla para registrar intentos fallidos de login por mail e IP (prevención de fuerza bruta)
CREATE TABLE IF NOT EXISTS login_intentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mail VARCHAR(255) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    fecha_intento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mail (mail),
    INDEX idx_ip (ip),
    INDEX idx_fecha (fecha_intento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
