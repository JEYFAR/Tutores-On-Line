-- =============================================================================
-- TutoresOnLine - Datos de Prueba iniciales (Entrega Parcial 2)
-- =============================================================================
-- Contraseña general para TODOS los usuarios de prueba: password123
-- Hash bcrypt (cost 12): $2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC
-- =============================================================================

USE `tutoresonline`;

-- -----------------------------------------------------------------------------
-- 1. Insertar Materias
-- -----------------------------------------------------------------------------
INSERT INTO `materias` (`id`, `nombre`) VALUES
(1, 'Matemáticas'),
(2, 'Física'),
(3, 'Química'),
(4, 'Programación Web'),
(5, 'Inglés'),
(6, 'Cálculo Diferencial'),
(7, 'Álgebra Lineal');

-- -----------------------------------------------------------------------------
-- 2. Insertar Niveles
-- -----------------------------------------------------------------------------
INSERT INTO `niveles` (`id`, `nombre`) VALUES
(1, 'Primaria'),
(2, 'Secundaria'),
(3, 'Bachillerato'),
(4, 'Universidad');

-- -----------------------------------------------------------------------------
-- 3. Insertar Usuarios
-- (3 estudiantes y 3 tutores)
-- -----------------------------------------------------------------------------
INSERT INTO `usuarios` (`id`, `nombre`, `correo`, `password_hash`, `rol`, `ciudad`, `foto`, `correo_verificado`, `creado_en`) VALUES
-- Estudiante 1 (Verificado)
(1, 'Carlos Mendoza', 'carlos.estudiante@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'estudiante', 'Bogotá', NULL, 1, NOW()),
-- Estudiante 2 (Verificado)
(2, 'Laura Gómez', 'laura.estudiante@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'estudiante', 'Medellín', NULL, 1, NOW()),
-- Estudiante 3 (Pendiente de verificar correo)
(3, 'Andrés Ramos', 'andres.pendiente@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'estudiante', 'Cali', NULL, 0, NOW()),

-- Tutor 1: Ing. Roberto Díaz (Matemáticas, Cálculo)
(4, 'Roberto Díaz', 'roberto.tutor@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'tutor', 'Bogotá', NULL, 1, NOW()),
-- Tutor 2: Dra. María Fernanda Peña (Programación, Inglés)
(5, 'María Fernanda Peña', 'maria.tutor@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'tutor', 'Medellín', NULL, 1, NOW()),
-- Tutor 3: Lic. Camilo Torres (Física, Química)
(6, 'Camilo Torres', 'camilo.tutor@ejemplo.com', '$2y$12$YsWGFAzkXWTUOvPBQQ68HeswFyGA2IYrG5eM.hRQX0vdh/v02VIeC', 'tutor', 'Cali', NULL, 1, NOW());

-- -----------------------------------------------------------------------------
-- 4. Asociar Tutores con Materias, Niveles y Tarifas (COP / Hora)
-- -----------------------------------------------------------------------------
INSERT INTO `tutor_materia` (`tutor_id`, `materia_id`, `nivel_id`, `tarifa_hora`) VALUES
-- Roberto Díaz
(4, 1, 4, 45000.00), -- Matemáticas en Universidad
(4, 6, 4, 50000.00), -- Cálculo Diferencial en Universidad
(4, 7, 4, 48000.00), -- Álgebra Lineal en Universidad

-- María Fernanda Peña
(5, 4, 4, 55000.00), -- Programación Web en Universidad
(5, 1, 3, 35000.00), -- Matemáticas en Bachillerato
(5, 5, 3, 30000.00), -- Inglés en Bachillerato

-- Camilo Torres
(6, 2, 2, 30000.00), -- Física en Secundaria
(6, 2, 4, 42000.00), -- Física en Universidad
(6, 3, 3, 32000.00); -- Química en Bachillerato

-- -----------------------------------------------------------------------------
-- 5. Disponibilidad horaria semanal de los tutores
-- -----------------------------------------------------------------------------
INSERT INTO `disponibilidad` (`tutor_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `modalidad`) VALUES
-- Roberto Díaz
(4, 'lunes', '08:00:00', '12:00:00', 'virtual'),
(4, 'miercoles', '14:00:00', '18:00:00', 'presencial'),
(4, 'viernes', '09:00:00', '13:00:00', 'virtual'),

-- María Fernanda Peña
(5, 'martes', '10:00:00', '14:00:00', 'virtual'),
(5, 'jueves', '15:00:00', '19:00:00', 'virtual'),
(5, 'sabado', '08:00:00', '12:00:00', 'presencial'),

-- Camilo Torres
(6, 'lunes', '14:00:00', '18:00:00', 'presencial'),
(6, 'martes', '16:00:00', '20:00:00', 'virtual');

-- -----------------------------------------------------------------------------
-- 6. Sesiones de tutoría de prueba
-- -----------------------------------------------------------------------------
INSERT INTO `sesiones` (`id`, `estudiante_id`, `tutor_id`, `materia_id`, `fecha`, `hora_inicio`, `hora_fin`, `modalidad`, `estado`, `creado_en`) VALUES
-- Sesión 1: Finalizada y calificada
(1, 1, 4, 6, '2026-10-10', '09:00:00', '11:00:00', 'virtual', 'finalizada', '2026-10-08 14:30:00'),
-- Sesión 2: Aceptada (próxima)
(2, 1, 5, 4, '2026-10-22', '10:00:00', '12:00:00', 'virtual', 'aceptada', '2026-10-15 11:00:00'),
-- Sesión 3: Pendiente de aprobación por el tutor
(3, 2, 6, 2, '2026-10-25', '16:00:00', '18:00:00', 'virtual', 'pendiente', '2026-10-16 09:20:00'),
-- Sesión 4: Rechazada
(4, 2, 4, 7, '2026-10-12', '15:00:00', '17:00:00', 'presencial', 'rechazada', '2026-10-09 18:00:00');

-- -----------------------------------------------------------------------------
-- 7. Calificaciones de prueba
-- -----------------------------------------------------------------------------
INSERT INTO `calificaciones` (`sesion_id`, `estudiante_id`, `tutor_id`, `puntaje`, `comentario`, `creado_en`) VALUES
(1, 1, 4, 5, 'Excelente tutoría, explicó los límites y derivadas con mucha claridad y ejemplos prácticos.', '2026-10-10 11:30:00');
