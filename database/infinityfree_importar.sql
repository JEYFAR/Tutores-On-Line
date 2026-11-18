-- =============================================================================
-- TutoresOnLine - Script Unificado para Importar en InfinityFree (phpMyAdmin)
-- NO incluye CREATE DATABASE ni USE para evitar el error #1044 en InfinityFree.
-- =============================================================================

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

CREATE TABLE `usuarios` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `correo` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NULL,
  `rol` ENUM('estudiante', 'tutor') NOT NULL,
  `ciudad` VARCHAR(100) NULL,
  `foto` VARCHAR(255) NULL,
  `descripcion` TEXT NULL DEFAULT NULL,
  `correo_verificado` TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_usuarios_correo` (`correo`),
  INDEX `idx_usuarios_rol` (`rol`),
  INDEX `idx_usuarios_ciudad` (`ciudad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `materias` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  UNIQUE KEY `uq_materias_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `niveles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(50) NOT NULL,
  UNIQUE KEY `uq_niveles_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tutor_materia` (
  `tutor_id` INT UNSIGNED NOT NULL,
  `materia_id` INT UNSIGNED NOT NULL,
  `nivel_id` INT UNSIGNED NOT NULL,
  `tarifa_hora` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`tutor_id`, `materia_id`, `nivel_id`),
  CONSTRAINT `fk_tutor_mat_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tutor_mat_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tutor_mat_nivel` FOREIGN KEY (`nivel_id`) REFERENCES `niveles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `disponibilidad` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tutor_id` INT UNSIGNED NOT NULL,
  `dia_semana` ENUM('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo') NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  `modalidad` ENUM('virtual', 'presencial') NOT NULL DEFAULT 'virtual',
  CONSTRAINT `fk_disp_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_disp_tutor_dia` (`tutor_id`, `dia_semana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `enlace_videollamada` VARCHAR(255) NULL DEFAULT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sesiones_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sesiones_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sesiones_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX `idx_sesiones_estudiante` (`estudiante_id`, `estado`),
  INDEX `idx_sesiones_tutor` (`tutor_id`, `estado`),
  INDEX `idx_sesiones_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `calificaciones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sesion_id` INT UNSIGNED NULL,
  `estudiante_id` INT UNSIGNED NOT NULL,
  `tutor_id` INT UNSIGNED NOT NULL,
  `puntaje` TINYINT UNSIGNED NOT NULL,
  `comentario` TEXT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_calif_estudiante_tutor` (`estudiante_id`, `tutor_id`),
  CONSTRAINT `fk_calif_sesion` FOREIGN KEY (`sesion_id`) REFERENCES `sesiones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calif_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calif_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_calif_tutor` (`tutor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `archivos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('foto', 'hoja_vida') NOT NULL,
  `nombre_original` VARCHAR(255) NOT NULL,
  `nombre_guardado` VARCHAR(190) NOT NULL,
  `mime` VARCHAR(100) NOT NULL,
  `tamano` INT UNSIGNED NOT NULL,
  `subido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_archivos_guardado` (`nombre_guardado`),
  CONSTRAINT `fk_archivos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_archivos_usuario_tipo` (`usuario_id`, `tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tokens_sesion` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `selector` CHAR(32) NOT NULL,
  `validador_hash` CHAR(64) NOT NULL,
  `user_agent_hash` CHAR(64) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `expira_en` DATETIME NOT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_uso` DATETIME NULL,
  UNIQUE KEY `uq_tokens_selector` (`selector`),
  CONSTRAINT `fk_tokens_sesion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_tokens_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tokens_verificacion` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('correo', 'recuperacion') NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expira_en` DATETIME NOT NULL,
  `usado` TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_tokens_verif_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_tokens_verif_hash` (`token_hash`),
  INDEX `idx_tokens_verif_usuario` (`usuario_id`, `tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cuentas_oauth` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `proveedor` VARCHAR(50) NOT NULL DEFAULT 'google',
  `proveedor_user_id` VARCHAR(190) NOT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_oauth_proveedor_id` (`proveedor`, `proveedor_user_id`),
  CONSTRAINT `fk_oauth_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_oauth_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `intentos_login` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `correo` VARCHAR(150) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `exitoso` TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_intentos_correo_ip_fecha` (`correo`, `ip`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversaciones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `estudiante_id` INT UNSIGNED NOT NULL,
  `tutor_id` INT UNSIGNED NOT NULL,
  `sesion_id` INT UNSIGNED NULL,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_conversacion_usuarios` (`estudiante_id`, `tutor_id`),
  INDEX `idx_conv_actualizado` (`actualizado_en`),
  CONSTRAINT `fk_conv_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conv_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conv_sesion` FOREIGN KEY (`sesion_id`) REFERENCES `sesiones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mensajes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `conversacion_id` INT UNSIGNED NOT NULL,
  `emisor_id` INT UNSIGNED NOT NULL,
  `texto` TEXT NOT NULL,
  `leido` TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_msg_conv_id` (`conversacion_id`, `id`),
  INDEX `idx_msg_leido` (`conversacion_id`, `leido`),
  CONSTRAINT `fk_msg_conversacion` FOREIGN KEY (`conversacion_id`) REFERENCES `conversaciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_msg_emisor` FOREIGN KEY (`emisor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- DATOS SEMILLA INICIALES (Contraseña de todos: password123)
-- =============================================================================

INSERT INTO `materias` (`id`, `nombre`) VALUES
(1, 'Matemáticas'),
(2, 'Física'),
(3, 'Química'),
(4, 'Programación Web'),
(5, 'Inglés'),
(6, 'Cálculo Diferencial'),
(7, 'Álgebra Lineal');

INSERT INTO `niveles` (`id`, `nombre`) VALUES
(1, 'Primaria'),
(2, 'Secundaria'),
(3, 'Bachillerato'),
(4, 'Universidad');

INSERT INTO `usuarios` (`id`, `nombre`, `correo`, `password_hash`, `rol`, `ciudad`, `foto`, `descripcion`, `correo_verificado`, `creado_en`) VALUES
(1, 'Carlos Mendoza', 'carlos.estudiante@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'estudiante', 'Bogotá', NULL, NULL, 1, NOW()),
(2, 'Laura Gómez', 'laura.estudiante@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'estudiante', 'Medellín', NULL, NULL, 1, NOW()),
(3, 'Andrés Ramos', 'andres.pendiente@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'estudiante', 'Cali', NULL, NULL, 0, NOW()),
(4, 'Roberto Díaz', 'roberto.tutor@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'tutor', 'Bogotá', NULL, 'Ingeniero Civil con más de 6 años de experiencia dictando tutorías universitarias en Matemáticas, Cálculo Diferencial y Álgebra Lineal. Metodología práctica enfocada en resolver ejercicios paso a paso y preparar parciales.', 1, NOW()),
(5, 'María Fernanda Peña', 'maria.tutor@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'tutor', 'Medellín', NULL, 'Doctora en Ciencias de la Computación y docente universitaria. Especialista en Programación Web, desarrollo de software, Matemáticas e Inglés técnico con clases personalizadas orientadas a proyectos reales.', 1, NOW()),
(6, 'Camilo Torres', 'camilo.tutor@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'tutor', 'Cali', NULL, 'Licenciado en Física y Química con amplia trayectoria en bachillerato y primeros semestres universitarios. Clases dinámicas con ejemplos de la vida cotidiana, refuerzo teórico y guías de estudio.', 1, NOW());

INSERT INTO `tutor_materia` (`tutor_id`, `materia_id`, `nivel_id`, `tarifa_hora`) VALUES
(4, 1, 4, 45000.00),
(4, 6, 4, 50000.00),
(4, 7, 4, 48000.00),
(5, 4, 4, 55000.00),
(5, 1, 3, 35000.00),
(5, 5, 3, 30000.00),
(6, 2, 2, 30000.00),
(6, 2, 4, 42000.00),
(6, 3, 3, 32000.00);

INSERT INTO `disponibilidad` (`tutor_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `modalidad`) VALUES
(4, 'lunes', '08:00:00', '12:00:00', 'virtual'),
(4, 'miercoles', '14:00:00', '18:00:00', 'presencial'),
(4, 'viernes', '09:00:00', '13:00:00', 'virtual'),
(5, 'martes', '10:00:00', '14:00:00', 'virtual'),
(5, 'jueves', '15:00:00', '19:00:00', 'virtual'),
(5, 'sabado', '08:00:00', '12:00:00', 'presencial'),
(6, 'lunes', '14:00:00', '18:00:00', 'presencial'),
(6, 'martes', '16:00:00', '20:00:00', 'virtual');

INSERT INTO `sesiones` (`id`, `estudiante_id`, `tutor_id`, `materia_id`, `fecha`, `hora_inicio`, `hora_fin`, `modalidad`, `estado`, `enlace_videollamada`, `creado_en`) VALUES
(1, 1, 4, 6, '2026-10-10', '09:00:00', '11:00:00', 'virtual', 'finalizada', 'TutoresOnLine_Sala_1_9f8a7c4e2b1d6a5f3e0c9b8a', '2026-10-08 14:30:00'),
(2, 1, 5, 4, '2026-10-22', '10:00:00', '12:00:00', 'virtual', 'aceptada', 'TutoresOnLine_Sala_2_4c7b1e9a3d5f8c2e6b0a1d4f', '2026-10-15 11:00:00'),
(3, 2, 6, 2, '2026-10-25', '16:00:00', '18:00:00', 'virtual', 'pendiente', NULL, '2026-10-16 09:20:00'),
(4, 2, 4, 7, '2026-10-12', '15:00:00', '17:00:00', 'presencial', 'rechazada', NULL, '2026-10-09 18:00:00');

INSERT INTO `calificaciones` (`sesion_id`, `estudiante_id`, `tutor_id`, `puntaje`, `comentario`, `creado_en`) VALUES
(1, 1, 4, 5, 'Excelente tutoría, explicó los límites y derivadas con mucha claridad y ejemplos prácticos.', '2026-10-10 11:30:00');
