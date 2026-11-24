-- ============================================================================
--  database/agenda.sql
--  Base de datos de la Agenda de Contactos
--  Mini Proyecto 2 · Unidad 4 · Programación Web
--
--  Uso local (XAMPP / Laragon / MySQL):
--      mysql -u root -p < database/agenda.sql
--  Uso en hosting (phpMyAdmin de InfinityFree / AeonFree / ByetHost):
--      1. Crear la base de datos desde el panel.
--      2. Abrir phpMyAdmin, seleccionar la base y ejecutar este archivo
--         (sin la línea CREATE DATABASE / USE si el hosting ya la creó).
-- ============================================================================

-- CREATE DATABASE IF NOT EXISTS agenda_contactos
--   CHARACTER SET utf8mb4
--   COLLATE utf8mb4_unicode_ci;

-- USE agenda_contactos;

DROP TABLE IF EXISTS contactos;

CREATE TABLE contactos (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(100)  NOT NULL,
    apellido       VARCHAR(100)  NOT NULL,
    telefono       VARCHAR(30)   NOT NULL,
    foto           VARCHAR(255)  NOT NULL,
    email          VARCHAR(150)  DEFAULT NULL,
    direccion      VARCHAR(255)  DEFAULT NULL,
    notas          TEXT          DEFAULT NULL,
    creado_en      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nombre_apellido (nombre, apellido),
    KEY idx_telefono (telefono),
    KEY idx_email (email)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Datos de ejemplo (las imágenes viven en /archivos)
-- ----------------------------------------------------------------------------
INSERT INTO contactos (nombre, apellido, telefono, email, direccion, notas, foto) VALUES
('Ana Lucía', 'Martínez', '+503 7771-2345', 'ana.martinez@example.com',
 'Col. Escalón, San Salvador', 'Contacto de ejemplo. Cliente frecuente de la empresa.', 'demo-ana.png'),

('Bruno', 'Castro', '7772-8899', 'bruno.castro@example.com',
 'Av. Independencia #125, Santa Ana', 'Compañero de la universidad. Prefiere comunicación por correo.', 'demo-bruno.png'),

('Carla', 'Rodríguez', '(+503) 7001-4433', NULL,
 'Barrio El Centro, La Libertad', 'Referencia para el proyecto de desarrollo web.', 'demo-carla.png'),

('Diego', 'Fernández', '2555-1020', 'diego.fernandez@example.com',
 'Calle Arce #410, San Salvador', 'Proveedor de equipos de cómputo.', 'demo-diego.png'),

('Elena', 'Guzmán', '7888-2200', 'elena.guzman@example.com',
 'Res. Los Héroes, Soyapango', 'Contacto de emergencia familiar.', 'demo-elena.png'),

('Francisco', 'Alvarado', '2222-0700', NULL,
 'Paseo General Escalón #3315', 'Consultor externo de redes y servidores.', 'demo-francisco.png');
