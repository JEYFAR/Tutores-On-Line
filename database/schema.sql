-- =============================================================================
-- TutoresOnLine - Esquema de Base de Datos (Entrega Parcial 2)
-- Compatible con: MySQL 5.7+ / MariaDB 10.2+
-- Motor: InnoDB | Cotejamiento: utf8mb4_unicode_ci
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `tutoresonline`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `tutoresonline`;

-- Desactivar temporalmente revisión de llaves foráneas para permitir reinicio limpio
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `mensajes`;
DROP TABLE IF EXISTS `conversaciones`;
DROP TABLE IF EXISTS `calificaciones`;
DROP TABLE IF EXISTS `sesiones`;
DROP TABLE IF EXISTS `disponibilidad`;
DROP TABLE IF EXISTS `tutor_materia`;
DROP TABLE IF EXISTS `archivos`;
DROP TABLE IF EXISTS `tokens_sesion`;
DROP TABLE IF EXISTS `tokens_verificacion`;
DROP TABLE IF EXISTS `cuentas_oauth`;
DROP TABLE IF EXISTS `intentos_login`;
DROP TABLE IF EXISTS `niveles`;
DROP TABLE IF EXISTS `materias`;
DROP TABLE IF EXISTS `usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1. Tabla: usuarios
-- Almacena estudiantes y tutores. password_hash permite NULL para logins con Google.
-- -----------------------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `correo` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NULL COMMENT 'Hash bcrypt con cost 12. NULL si solo usa OAuth Google',
  `rol` ENUM('estudiante', 'tutor') NOT NULL,
  `ciudad` VARCHAR(100) NULL,
  `foto` VARCHAR(255) NULL COMMENT 'Nombre o ruta del archivo de imagen de perfil',
  `correo_verificado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0: pendiente, 1: verificado',
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_usuarios_correo` (`correo`),
  INDEX `idx_usuarios_rol` (`rol`),
  INDEX `idx_usuarios_ciudad` (`ciudad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Tabla: materias
-- Catálogo de materias ofertadas (Matemáticas, Física, Programación, etc.)
-- -----------------------------------------------------------------------------
CREATE TABLE `materias` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  UNIQUE KEY `uq_materias_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Tabla: niveles
-- Catálogo de niveles educativos (Primaria, Secundaria, Universidad, etc.)
-- -----------------------------------------------------------------------------
CREATE TABLE `niveles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(50) NOT NULL,
  UNIQUE KEY `uq_niveles_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Tabla: tutor_materia
-- Relación de tutores con las materias y niveles que enseñan, con su tarifa/hora.
-- -----------------------------------------------------------------------------
CREATE TABLE `tutor_materia` (
  `tutor_id` INT UNSIGNED NOT NULL,
  `materia_id` INT UNSIGNED NOT NULL,
  `nivel_id` INT UNSIGNED NOT NULL,
  `tarifa_hora` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`tutor_id`, `materia_id`, `nivel_id`),
  CONSTRAINT `fk_tutor_mat_tutor`
    FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tutor_mat_materia`
    FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tutor_mat_nivel`
    FOREIGN KEY (`nivel_id`) REFERENCES `niveles` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Tabla: disponibilidad
-- Horarios semanales configurados por los tutores y modalidad de atención.
-- -----------------------------------------------------------------------------
CREATE TABLE `disponibilidad` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tutor_id` INT UNSIGNED NOT NULL,
  `dia_semana` ENUM('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo') NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  `modalidad` ENUM('virtual', 'presencial') NOT NULL DEFAULT 'virtual',
  CONSTRAINT `fk_disp_tutor`
    FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_disp_tutor_dia` (`tutor_id`, `dia_semana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Tabla: sesiones
-- Tutorías solicitadas por estudiantes a los tutores.
-- -----------------------------------------------------------------------------
CREATE TABLE `sesiones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `estudiante_id` INT UNSIGNED NOT NULL,
  `tutor_id` INT UNSIGNED NOT NULL,
  `materia_id` INT UNSIGNED NOT NULL,
  `fecha` DATE NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  `modalidad` ENUM('virtual', 'presencial') NOT NULL DEFAULT 'virtual',
  `estado` ENUM('pendiente', 'aceptada', 'rechazada', 'finalizada') NOT NULL DEFAULT 'pendiente',
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sesiones_estudiante`
    FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sesiones_tutor`
    FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sesiones_materia`
    FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX `idx_sesiones_estudiante` (`estudiante_id`, `estado`),
  INDEX `idx_sesiones_tutor` (`tutor_id`, `estado`),
  INDEX `idx_sesiones_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Tabla: calificaciones
-- Reseñas y puntuación (1 a 5 estrellas) dejadas por los estudiantes tras una tutoría.
-- -----------------------------------------------------------------------------
CREATE TABLE `calificaciones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sesion_id` INT UNSIGNED NOT NULL,
  `estudiante_id` INT UNSIGNED NOT NULL,
  `tutor_id` INT UNSIGNED NOT NULL,
  `puntaje` TINYINT UNSIGNED NOT NULL,
  `comentario` TEXT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_calificaciones_sesion` (`sesion_id`),
  CONSTRAINT `fk_calif_sesion`
    FOREIGN KEY (`sesion_id`) REFERENCES `sesiones` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calif_estudiante`
    FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calif_tutor`
    FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_calif_tutor` (`tutor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Tabla: archivos
-- Registro de archivos subidos al servidor (fotos de perfil, hojas de vida de tutores).
-- -----------------------------------------------------------------------------
CREATE TABLE `archivos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('foto', 'hoja_vida') NOT NULL,
  `nombre_original` VARCHAR(255) NOT NULL,
  `nombre_guardado` VARCHAR(190) NOT NULL,
  `mime` VARCHAR(100) NOT NULL,
  `tamano` INT UNSIGNED NOT NULL COMMENT 'Tamaño en bytes',
  `subido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_archivos_guardado` (`nombre_guardado`),
  CONSTRAINT `fk_archivos_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_archivos_usuario_tipo` (`usuario_id`, `tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Tabla: tokens_sesion
-- Implementa la sesión persistente ("Recordarme") con selector y validador hasheado.
-- -----------------------------------------------------------------------------
CREATE TABLE `tokens_sesion` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `selector` CHAR(32) NOT NULL,
  `validador_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del validador',
  `user_agent_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del User-Agent para atar la sesión',
  `ip` VARCHAR(45) NULL,
  `expira_en` DATETIME NOT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_uso` DATETIME NULL,
  UNIQUE KEY `uq_tokens_selector` (`selector`),
  CONSTRAINT `fk_tokens_sesion_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_tokens_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Tabla: tokens_verificacion
-- Tokens de un solo uso para verificar correo electrónico y recuperar contraseña.
-- -----------------------------------------------------------------------------
CREATE TABLE `tokens_verificacion` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('correo', 'recuperacion') NOT NULL,
  `token_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del token aleatorio',
  `expira_en` DATETIME NOT NULL,
  `usado` TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_tokens_verif_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_tokens_verif_hash` (`token_hash`),
  INDEX `idx_tokens_verif_usuario` (`usuario_id`, `tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. Tabla: cuentas_oauth
-- Vincula cuentas locales con proveedores externos (Google).
-- -----------------------------------------------------------------------------
CREATE TABLE `cuentas_oauth` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `proveedor` VARCHAR(50) NOT NULL DEFAULT 'google',
  `proveedor_user_id` VARCHAR(190) NOT NULL COMMENT 'ID único que entrega Google (sub)',
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_oauth_proveedor_id` (`proveedor`, `proveedor_user_id`),
  CONSTRAINT `fk_oauth_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_oauth_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. Tabla: intentos_login
-- Registro de intentos para mitigar ataques de fuerza bruta (rate limiting).
-- -----------------------------------------------------------------------------
CREATE TABLE `intentos_login` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `correo` VARCHAR(150) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `exitoso` TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_intentos_correo_ip_fecha` (`correo`, `ip`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 13. Tabla: conversaciones
-- Hilo de chat directo entre un estudiante y un tutor (opcionalmente atado a sesión).
-- -----------------------------------------------------------------------------
CREATE TABLE `conversaciones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `estudiante_id` INT UNSIGNED NOT NULL,
  `tutor_id` INT UNSIGNED NOT NULL,
  `sesion_id` INT UNSIGNED NULL,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_conversacion_usuarios` (`estudiante_id`, `tutor_id`),
  INDEX `idx_conv_actualizado` (`actualizado_en`),
  CONSTRAINT `fk_conv_estudiante`
    FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conv_tutor`
    FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conv_sesion`
    FOREIGN KEY (`sesion_id`) REFERENCES `sesiones` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 14. Tabla: mensajes
-- Mensajes intercambiados en cada conversación con control de lectura y timestamp.
-- -----------------------------------------------------------------------------
CREATE TABLE `mensajes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `conversacion_id` INT UNSIGNED NOT NULL,
  `emisor_id` INT UNSIGNED NOT NULL,
  `texto` TEXT NOT NULL,
  `leido` TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_msg_conv_id` (`conversacion_id`, `id`),
  INDEX `idx_msg_leido` (`conversacion_id`, `leido`),
  CONSTRAINT `fk_msg_conversacion`
    FOREIGN KEY (`conversacion_id`) REFERENCES `conversaciones` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_msg_emisor`
    FOREIGN KEY (`emisor_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

