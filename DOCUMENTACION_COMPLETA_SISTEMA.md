# TutoresOnLine - Memoria Técnica y Documentación Arquitectónica Integral

> **Documento:** Especificación Técnica y Arquitectónica del Sistema  
> **Proyecto:** TutoresOnLine - Plataforma de Tutorías Académicas  
> **Versión:** 2.4.0 (Octubre 2026)  
> **Entorno:** USBWebserver (Apache 2.4 / PHP 7.4.x / MySQL 5.7 InnoDB)  
> **Tecnologías:** PHP 7.4 puro (PDO), MySQL 5.7 (utf8mb4_unicode_ci), Bootstrap 5.3 CDN, Vue.js 3 CDN  

---

## Tabla de Contenidos

1. [Resumen Ejecutivo y Objetivos del Negocio](#1-resumen-ejecutivo-y-objetivos-del-negocio)
2. [Arquitectura Global del Software](#2-arquitectura-global-del-software)
3. [Stack Tecnológico y Justificación](#3-stack-tecnológico-y-justificación)
4. [Diccionario de Datos y Modelo Relacional (14 Tablas)](#4-diccionario-de-datos-y-modelo-relacional-14-tablas)
5. [Arquitectura de Seguridad, Criptografía y Hardening](#5-arquitectura-de-seguridad-criptografía-y-hardening)
6. [Módulos del Sistema y Flujos Funcionales](#6-módulos-del-sistema-y-flujos-funcionales)
7. [Especificación Formal de la API RESTful](#7-especificación-formal-de-la-api-restful)
8. [Estructura Física del Repositorio](#8-estructura-física-del-repositorio)
9. [Guía de Despliegue, Instalación y Operación](#9-guía-de-despliegue-instalación-y-operación)
10. [Matriz de Pruebas y Validaciones de Seguridad](#10-matriz-de-pruebas-y-validaciones-de-seguridad)
11. [Conclusiones y Hoja de Ruta de Escalabilidad](#11-conclusiones-y-hoja-de-ruta-de-escalabilidad)

---

## 1. Resumen Ejecutivo y Objetivos del Negocio

**TutoresOnLine** es una plataforma digital de intermediación académica orientada a optimizar el encuentro pedagógico entre profesores particulares (tutores) y estudiantes de los diferentes ciclos educativos (primaria, secundaria, universidad y educación no formal).

### Problemática que Resuelve
Tradicionalmente, la contratación de un tutor particular adolece de múltiples debilidades:
- Incertidumbre sobre las competencias del docente y ausencia de validación formal de hojas de vida.
- Falta de transparencia en las tarifas por hora.
- Procesos informales de agendamiento sin confirmación mutua de disponibilidad.
- Exposición innecesaria de números personales de teléfono en etapas tempranas.
- Manipulación o ausencia de un sistema de reputación con calificaciones honestas.

### Propuesta de Valor
- **Buscador Multicriterio en Tiempo Real:** Filtros cruzados por especialidad/materia, nivel educativo, ciudad y modalidad (virtual o presencial).
- **Perfiles Docentes Auditados:** Tarifas desglosadas por nivel, franjas semanales de atención y acceso a certificados/hojas de vida en PDF.
- **Sistema de Reputación Objetivo:** Cálculo ponderado de calificaciones de 1 a 5 estrellas con comentarios de estudiantes autenticados, eliminando notas ficticias para profesores sin historial.
- **Mensajería Interna Segura:** Canal de chat directo integrado sin necesidad de revelar canales privados de contacto.
- **Seguridad Criptográfica Grado Empresarial:** Cumplimiento de estándares OWASP Top 10 para mitigación de inyecciones SQL, CSRF, XSS, fuerza bruta y robo de sesión.

---

## 2. Arquitectura Global del Software

La plataforma implementa un patrón **Front Controller Modular Liviano** complementado con una capa de **Servicios Centralizados** (`/includes/`) y **Endpoints API REST** (`/api/`), desacoplada de frameworks pesados para permitir ejecución nativa en hosting compartido y USBWebserver.

### Diagrama de Capas del Sistema

```text
+-----------------------------------------------------------------------------------+
|                            CAPA DE PRESENTACIÓN (CLIENTE)                         |
|   Bootstrap 5 (CSS/Componentes/Grid)  |  Vue.js 3 (Buscador reactivo SPA y Chat)  |
|   Vanilla JS Async Fetch API          |  Bootstrap Icons SVG                      |
+-----------------------------------------------------------------------------------+
                                        | (Peticiones HTTP / HTTPS / JSON Fetch)
                                        v
+-----------------------------------------------------------------------------------+
|                        CAPA DE ENRUTAMIENTO Y VISTAS PÚBLICAS                     |
|   index.php, login.php, registro.php, buscar.php, chat.php                        |
|   panel.php (Dashboard Universal), perfil.php (Perfil Dual Privado/Público)      |
+-----------------------------------------------------------------------------------+
                                        | (Invocación de Servicios y Control de Acceso)
                                        v
+-----------------------------------------------------------------------------------+
|                        CAPA DE CONTROLADORES DE API (REST JSON)                   |
|   /api/buscar_tutores.php  |  /api/reservar.php  |  /api/solicitudes.php          |
|   /api/chat_conversaciones.php  |  /api/chat_mensajes.php  |  /api/chat_enviar.php |
+-----------------------------------------------------------------------------------+
                                        | (Inclusión de Lógica Transversal y Seguridad)
                                        v
+-----------------------------------------------------------------------------------+
|                         CAPA CENTRAL DE SERVICIOS (/includes/)                    |
|   seguridad.php   : Cabeceras HTTP, CSRF, XSS, Bcrypt+Pepper, Rate Limiter        |
|   auth.php        : Control de Roles (RBAC), Sesiones atadas a UA, Cookie Recordar|
|   oauth_google.php: Integración OAuth2 con Google (cURL & Fallback Streams)       |
|   archivos.php    : Inspección MIME binaria (finfo), Hash aleatorio, Uploads      |
|   correo.php      : Despachador de Correos (Modo Log local y SMTP/Mail nativo)    |
|   conexion.php    : Singleton PDO con Prepared Statements nativos                |
+-----------------------------------------------------------------------------------+
                                        | (Mapeo de Datos SQL / Transacciones ACID)
                                        v
+-----------------------------------------------------------------------------------+
|                        CAPA DE PERSISTENCIA (BASE DE DATOS)                       |
|   MySQL 5.7 / MariaDB 10.2+ (Motor InnoDB, Charset utf8mb4_unicode_ci)            |
|   14 Tablas Relacionales | Integridad Referencial con ON DELETE CASCADE / RESTRICT |
+-----------------------------------------------------------------------------------+
```

---

## 3. Stack Tecnológico y Justificación

| Componente | Tecnología | Rol | Justificación |
|---|---|---|---|
| **Lenguaje Backend** | PHP 7.4+ puro | Controlador y Lógica de Negocio | Compatibilidad universal sin dependencias de Composer, tipado estricto en funciones y bajo consumo de memoria. |
| **Acceso a Datos** | PHP Data Objects (PDO) | Capa de Abstracción de Base de Datos | Consultas preparadas forzadas con `ATTR_EMULATE_PREPARES => false`, neutralizando inyecciones SQL. |
| **Base de Datos** | MySQL 5.7 / MariaDB | Motor de Persistencia | Motor InnoDB transaccional con integridad referencial completa y soporte de emojis con `utf8mb4`. |
| **Estilos & Layout** | Bootstrap 5.3 CDN | Diseño UI Responsivo | Grid flexbox moderno, componentes accesibles sin dependencia de jQuery. |
| **Frontend Reactivo** | Vue.js 3 CDN (Global Build) | Interactividad en Buscador y Chat | Reactividad declarativa sin build tools (Node/Webpack), bajo tiempo de carga y sintaxis limpia con `v-model`. |
| **Autenticación Externa** | Google OAuth 2.0 | Proveedor Federado de Identidad | Acceso ágil con cuentas institucionales o personales de Google mediante Authorization Code Flow. |
| **Servidor Web** | Apache 2.4 (USBWebserver) | Servidor HTTP y Middleware | Reglas `.htaccess` que bloquean directorios confidenciales e invalidan ejecución PHP en carpetas públicas. |

---

## 4. Diccionario de Datos y Modelo Relacional (14 Tablas)

El esquema `tutoresonline` cuenta con **14 tablas relacionales** diseñadas bajo Tercera Forma Normal (3FN):

### 4.1. `usuarios` (Entidad Central)
Almacena credenciales, roles y datos de perfil.
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT): Identificador unívoco.
- `nombre` (VARCHAR 100, NOT NULL): Nombre del usuario.
- `correo` (VARCHAR 150, NOT NULL, UNIQUE): Email corporativo o personal.
- `password_hash` (VARCHAR 255, NULL): Hash Bcrypt (cost 12) + Pepper. Permite NULL para usuarios autenticados únicamente vía Google OAuth.
- `rol` (ENUM('estudiante','tutor'), NOT NULL): Rol de autorización.
- `ciudad` (VARCHAR 100, NULL): Ciudad de residencia.
- `foto` (VARCHAR 255, NULL): Nombre del archivo de avatar en disco.
- `correo_verificado` (TINYINT 1, DEFAULT 0): Estado de activación de la cuenta.
- `creado_en` (DATETIME, DEFAULT CURRENT_TIMESTAMP): Fecha de registro.

### 4.2. `materias` (Catálogo de Asignaturas)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `nombre` (VARCHAR 100, NOT NULL, UNIQUE): Asignatura (Álgebra, Cálculo, Programación, etc.).

### 4.3. `niveles` (Catálogo Educativo)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `nombre` (VARCHAR 50, NOT NULL, UNIQUE): Primaria, Secundaria, Universidad, Profesional.

### 4.4. `tutor_materia` (Especialidades y Tarifas por Hora)
Tabla ternaria que define qué imparte el tutor, a qué nivel y a qué precio.
- `tutor_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `materia_id` (INT UNSIGNED, FK -> `materias.id` ON DELETE CASCADE).
- `nivel_id` (INT UNSIGNED, FK -> `niveles.id` ON DELETE CASCADE).
- `tarifa_hora` (DECIMAL 10,2, DEFAULT 0.00): Costo de la hora de clase.
- **Clave Primaria:** Compuesta `(tutor_id, materia_id, nivel_id)`.

### 4.5. `disponibilidad` (Franjas Horarias Semanales)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `tutor_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `dia_semana` (ENUM('lunes','martes','miercoles','jueves','viernes','sabado','domingo')).
- `hora_inicio` (TIME), `hora_fin` (TIME).
- `modalidad` (ENUM('virtual','presencial'), DEFAULT 'virtual').

### 4.6. `sesiones` (Gestión de Reservas de Clases)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `estudiante_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `tutor_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `materia_id` (INT UNSIGNED, FK -> `materias.id` ON DELETE RESTRICT).
- `fecha` (DATE): Fecha de la clase agendada.
- `hora_inicio` (TIME), `hora_fin` (TIME).
- `modalidad` (ENUM('virtual','presencial')).
- `estado` (ENUM('pendiente','aceptada','rechazada','finalizada'), DEFAULT 'pendiente').
- `creado_en` (DATETIME, DEFAULT CURRENT_TIMESTAMP).

### 4.7. `calificaciones` (Reputación y Reseñas)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `sesion_id` (INT UNSIGNED, NULL, FK -> `sesiones.id` ON DELETE CASCADE): Vinculación opcional con clase concluida.
- `estudiante_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `tutor_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `puntaje` (TINYINT UNSIGNED, NOT NULL): Calificación de 1 a 5 estrellas.
- `comentario` (TEXT, NULL): Reseña escrita.
- `creado_en` (DATETIME, DEFAULT CURRENT_TIMESTAMP).
- **Restricción de Unicidad Anti-Spam:** `UNIQUE KEY uq_calif_estudiante_tutor (estudiante_id, tutor_id)`.

### 4.8. `archivos` (Repositorio Documental Auditado)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `usuario_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `tipo` (ENUM('foto','hoja_vida')).
- `nombre_original` (VARCHAR 255).
- `nombre_guardado` (VARCHAR 190, UNIQUE): Nombre aleatorio criptográfico.
- `mime` (VARCHAR 100): MIME real detectado binariamente con `finfo`.
- `tamano` (INT UNSIGNED): Tamaño en bytes.
- `subido_en` (DATETIME, DEFAULT CURRENT_TIMESTAMP).

### 4.9. `tokens_sesion` (Arquitectura "Recordarme" de 30 Días)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `usuario_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `selector` (CHAR 32, UNIQUE): Búsqueda en índice.
- `validador_hash` (CHAR 64): Hash SHA-256 del secreto.
- `user_agent_hash` (CHAR 64): Hash SHA-256 del User-Agent.
- `ip` (VARCHAR 45), `expira_en` (DATETIME), `ultimo_uso` (DATETIME).

### 4.10. `tokens_verificacion` (Activación y Recuperación de Contraseña)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `usuario_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `tipo` (ENUM('correo','recuperacion')).
- `token_hash` (CHAR 64): Hash SHA-256 del token unívoco.
- `expira_en` (DATETIME), `usado` (TINYINT 1, DEFAULT 0).

### 4.11. `cuentas_oauth` (Identidad Federada con Google)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `usuario_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `proveedor` (VARCHAR 50, DEFAULT 'google').
- `proveedor_user_id` (VARCHAR 190): `sub` entregado por Google OAuth.
- **Índice Único:** `UNIQUE (proveedor, proveedor_user_id)`.

### 4.12. `intentos_login` (Mitigación de Fuerza Bruta)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `correo` (VARCHAR 150), `ip` (VARCHAR 45), `exitoso` (TINYINT 1), `creado_en` (DATETIME).

### 4.13. `conversaciones` (Hilos de Mensajería)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `estudiante_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `tutor_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `sesion_id` (INT UNSIGNED, NULL, FK -> `sesiones.id` ON DELETE SET NULL).
- `actualizado_en` (DATETIME), `creado_en` (DATETIME).
- **Índice Único:** `UNIQUE (estudiante_id, tutor_id)`.

### 4.14. `mensajes` (Mensajes de Chat)
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT).
- `conversacion_id` (INT UNSIGNED, FK -> `conversaciones.id` ON DELETE CASCADE).
- `emisor_id` (INT UNSIGNED, FK -> `usuarios.id` ON DELETE CASCADE).
- `texto` (TEXT, NOT NULL), `leido` (TINYINT 1, DEFAULT 0), `creado_en` (DATETIME).

---

## 5. Arquitectura de Seguridad, Criptografía y Hardening

### 5.1. Cabeceras HTTP de Endurecimiento
La función `enviar_cabeceras_seguridad()` inyecta defensas preventivas:
- `X-Content-Type-Options: nosniff`: Previene ataques de confusión de tipo MIME.
- `X-Frame-Options: DENY`: Anula el riesgo de Clickjacking impidiendo el renderizado en iframes.
- `Referrer-Policy: strict-origin-when-cross-origin`: Resguarda URLs sensibles en transiciones de navegación.
- `X-XSS-Protection: 1; mode=block`: Filtro defensivo para agentes de usuario antiguos.

### 5.2. Gestión de Sesiones y Protección contra Session Fixation
- Configuración con `session.use_strict_mode = 1` y `session.use_only_cookies = 1`.
- Cookie configurada con `HttpOnly=true` (inaccesible para JavaScript), `SameSite=Lax` y `Secure` condicional si se detecta HTTPS.
- Atadura al User-Agent: Si el hash SHA-256 del agente del usuario cambia entre peticiones, la sesión se revoca automáticamente.
- Tiempo de inactividad estricto de 30 minutos.
- Regeneración periódica del ID de sesión (`session_regenerate_id(true)`) en el login y cada 15 minutos continuos.

### 5.3. Esquema Selector-Validador para Cookies Persistentes
Sigue el modelo de Charles Miller:
- `selector`: Cadena aleatoria de 16 bytes (32 caracteres hex) usada para indexación rápida.
- `validador`: Secreto de 32 bytes (64 caracteres hex) cuyo hash SHA-256 se almacena en BD.
- **Rotación en cada inicio:** Cada vez que la cookie "Recordarme" es utilizada, el validador se regenera y la cookie se reemite.
- **Detección de clonación:** Si se presenta un selector válido pero con un validador incorrecto, el sistema revoca inmediatamente todos los tokens del usuario en la base de datos por sospecha fundada de robo de cookie.

### 5.4. Almacenamiento Seguro de Contraseñas (Bcrypt + Pepper)
```php
function hashear_password($password) {
    $pepper = env('APP_PEPPER', '');
    $prehash = hash_hmac('sha256', $password, $pepper);
    return password_hash($prehash, PASSWORD_DEFAULT, ['cost' => 12]);
}
```
El uso de un secreto adicional (**Pepper**) ubicado en el archivo `.env` fuera del alcance de la base de datos garantiza que las contraseñas no puedan ser descifradas mediante tablas arcoíris, aun si la base de datos sufre una filtración completa.

### 5.5. Prevención de Ataques de Fuerza Bruta
La tabla `intentos_login` audita cada acceso fallido por IP y correo. La rutina `esta_bloqueado_fuerza_bruta($correo, 5, 15)` comprueba si se han superado los 5 intentos en los últimos 15 minutos, aplicando un bloqueo temporal que frena herramientas automáticas de diccionario (Hydra, Burp Intruder).

### 5.6. Subida Segura de Archivos
- Límites estrictos: 2 MB para imágenes y 5 MB para PDF.
- Whitelist de extensiones: `.jpg`, `.jpeg`, `.png`, `.pdf`.
- Verificación binaria de tipo MIME real con `finfo_file()` inspeccionando la firma mágica del archivo.
- Generación de nombres aleatorios con `bin2hex(random_bytes(16))`.
- Desactivación del motor PHP en `public/uploads/.htaccess` para imposibilitar la ejecución de Web Shells.

---

## 6. Módulos del Sistema y Flujos Funcionales

### 6.1. Autenticación y Registro Dual
- **Registro de Estudiantes y Tutores:** Formularios adaptados que recopilan datos geográficos y roles.
- **Google OAuth 2.0:** Flujo sin librerías externas que procesa el Authorization Code contra `accounts.google.com` y `oauth2.googleapis.com` empleando cURL o streams de PHP, enlazando la cuenta mediante el identificador unívoco `sub`.
- **Verificación de Correo Electrónico:** Tokens con 24 horas de validez; en desarrollo local se escriben en `logs/mail.log` con enlace directo para pruebas sin servidor SMTP.

### 6.2. Dashboard Central Universal (`panel.php`)
- **Estudiantes:**
  - Consulta de tutorías solicitadas con insignias de estado (Pendiente, Aceptada, Rechazada, Finalizada).
  - Enlace directo con un clic para chatear con el tutor asignado.
  - Botón interactivo para calificar clases que han concluido.
- **Tutores:**
  - Bandeja de solicitudes de clase con botones para aceptar o rechazar al instante.
  - Facultad de marcar clases como finalizadas.
  - Administrador de materias y tarifas por nivel, permitiendo crear nuevas asignaturas al vuelo.
  - Planificador de agenda semanal y modalidad (virtual o presencial).

### 6.3. Perfil Universal (`perfil.php`)
- **Modo Privado:** Selector lateral organizado por pestañas:
  - *Datos Personales:* Actualización de nombre y ciudad.
  - *Seguridad:* Cambio de contraseña validando la clave actual.
  - *Especialidades y Tarifas:* Asignación de asignaturas y precios horarios.
  - *Disponibilidad Semanal:* Configuración de horarios por día.
  - *Documentos:* Subida de foto de perfil y hoja de vida en PDF con eliminación segura.
- **Modo Público:** Visualización de la ficha técnica del profesor para los alumnos, incluyendo tarifas, disponibilidad y módulo de opiniones reales.
- **Sistema de Calificación sin Notas Falsas:** Se eliminó el 5.0 por defecto; si el docente no tiene opiniones, muestra la insignia *"Sin calificaciones aún"*. Los estudiantes pueden puntuar de 1 a 5 estrellas con comentarios, impidiendo auto-calificaciones.

### 6.4. Buscador Reactivo de Tutores (`buscar.php` + Vue 3)
- Renderizado reactivo sin recarga de página mediante Vue 3 CDN.
- Filtros simultáneos por texto (nombre de tutor o materia), nivel académico, ciudad y modalidad.
- Cookie `ultima_materia_buscada` para recordar los intereses del estudiante en futuras visitas.

### 6.5. Sistema de Mensajería y Chat en Tiempo Real (`chat.php`)
- Arquitectura de **Polling Incremental**: Consulta el servidor cada 2.5 segundos mediante `api/chat_mensajes.php?desde_id=X`.
- Transmisión mínima de datos: El servidor solo devuelve registros con ID superior al último mensaje recibido por el cliente.
- Validación estricta de pertenencia al hilo para evitar acceso a conversaciones ajenas.

---

## 7. Especificación Formal de la API RESTful

Todas las rutas retornan JSON con cabecera `Content-Type: application/json; charset=UTF-8`.

| Endpoint | Método | Auth | Parámetros Entrada | Respuesta Exitosa |
|---|---|---|---|---|
| `/api/buscar_tutores.php` | `GET` | Pública | `materia`, `nivel_id`, `ciudad`, `modalidad` | `{ exito: true, total: N, tutores: [...] }` |
| `/api/reservar.php` | `POST` | Estudiante | `tutor_id`, `materia_id`, `fecha`, `hora_inicio`, `hora_fin`, `modalidad`, `csrf_token` | `{ exito: true, mensaje: "...", sesion_id: N }` |
| `/api/solicitudes.php` | `POST` | Tutor | `sesion_id`, `accion` ('aceptar'/'rechazar'/'finalizar'), `csrf_token` | `{ exito: true, mensaje: "...", nuevo_estado: "..." }` |
| `/api/disponibilidad.php` | `GET/POST` | Tutor / Pública | GET: `tutor_id` \| POST: `dia_semana`, `hora_inicio`, `hora_fin`, `modalidad` | `{ exito: true, disponibilidad: [...] }` |
| `/api/chat_conversaciones.php` | `GET/POST` | Requerida | GET: Ninguno \| POST: `contacto_id`, `csrf_token` | `{ exito: true, conversaciones: [...] }` |
| `/api/chat_mensajes.php` | `GET` | Participante | `conversacion_id`, `desde_id` (opcional) | `{ exito: true, mensajes: [...], total: N }` |
| `/api/chat_enviar.php` | `POST` | Participante | `conversacion_id`, `texto`, `csrf_token` | `{ exito: true, mensaje: { id: N, ... } }` |

---

## 8. Estructura Física del Repositorio

```text
TutoresOn-Line/
├── .gitignore                      # Excluye .env, uploads/* y logs/*
├── AGENTS.md                       # Directivas y especificaciones técnicas para agentes
├── PLAN.md                         # Plan de trabajo y seguimiento de hitos
├── README.md                       # Documentación inicial y cuentas de prueba
├── DOCUMENTACION_COMPLETA_SISTEMA.md # Memoria técnica detallada en formato Markdown
├── documentacion_sistema.html      # Plantilla HTML maestra lista para exportar a PDF
├── config/
│   ├── .env                        # Configuración sensible local (excluido de Git)
│   ├── .env.example                # Plantilla de variables de entorno
│   ├── .htaccess                   # Deny from all (bloqueo HTTP total)
│   └── config.php                  # Parser nativo de .env, rutas y constantes
├── database/
│   ├── schema.sql                  # DDL de 14 tablas en InnoDB utf8mb4
│   └── datos_prueba.sql            # Datos semilla y cuentas de prueba
├── includes/
│   ├── .htaccess                   # Bloqueo HTTP a lógica de negocio
│   ├── conexion.php                # Conexión Singleton PDO
│   ├── seguridad.php               # Módulo central de cabeceras, CSRF, XSS y rate limit
│   ├── auth.php                    # Control de sesiones, roles y tokens persistentes
│   ├── oauth_google.php            # Integración nativa Google OAuth 2.0
│   ├── archivos.php                # Validación MIME (finfo) y almacenamiento seguro
│   ├── correo.php                  # Despachador de emails (modo log y mail)
│   ├── header.php                  # Barra de navegación adaptable y alertas flash
│   └── footer.php                  # Pie de página y dependencias de Bootstrap 5
├── api/                            # Endpoints REST JSON
│   ├── buscar_tutores.php          # Búsqueda multicriterio con promedios reales
│   ├── reservar.php                # Agendamiento seguro de tutorías
│   ├── solicitudes.php             # Aceptación/rechazo de clases
│   ├── disponibilidad.php          # Horarios semanales
│   ├── chat_conversaciones.php     # Conversaciones de chat
│   ├── chat_mensajes.php           # Polling incremental de mensajes
│   └── chat_enviar.php             # Envío seguro de mensajes
├── logs/                           # Registro de errores y correos locales
│   ├── .htaccess                   # Bloqueo de acceso HTTP
│   └── mail.log                    # Enlaces de activación y recuperación de claves
└── public/                         # Raíz pública web del servidor Apache
    ├── index.php                   # Página de bienvenida
    ├── login.php                   # Inicio de sesión y botón Google
    ├── registro.php                # Registro de estudiantes y profesores
    ├── verificar.php               # Confirmación de token de correo
    ├── recuperar.php               # Restablecimiento de contraseña
    ├── logout.php                  # Cierre de sesión y revocación de tokens
    ├── buscar.php                  # Buscador interactivo SPA con Vue 3
    ├── panel.php                   # Dashboard unificado adaptativo
    ├── perfil.php                  # Perfil universal multifunción
    ├── chat.php                    # Interfaz de mensajería instantánea
    ├── reservar.php                # Formulario de agendamiento
    ├── css/estilos.css             # Estilos y micro-animaciones personalizadas
    └── uploads/                    # Archivos subidos con ejecución PHP deshabilitada
```

---

## 9. Guía de Despliegue, Instalación y Operación

### 9.1. Requisitos
- Servidor Web Apache 2.4 con módulo `mod_rewrite`.
- PHP 7.4 o superior con extensiones `pdo_mysql`, `fileinfo`, `json`, `session` activas.
- MySQL 5.7 o superior / MariaDB 10.2+.

### 9.2. Pasos de Instalación
1. Clonar o descomprimir el proyecto en el directorio raíz de Apache (ej. `C:\USBWebserver\root\tutoresonline`).
2. Copiar `config/.env.example` como `config/.env` y verificar credenciales de base de datos (`root` / `usbw`).
3. Importar en phpMyAdmin (`http://localhost/phpmyadmin`):
   - Primero: `database/schema.sql`
   - Segundo: `database/datos_prueba.sql`
4. Asegurar permisos de escritura en las carpetas `logs/` y `public/uploads/`.
5. Abrir el navegador en `http://localhost/tutoresonline/public/`.

### 9.3. Cuentas de Prueba Preconfiguradas (Clave: `password123`)
- **Estudiante:** `carlos.estudiante@ejemplo.com` (Agendar tutorías y chatear).
- **Estudiante:** `laura.estudiante@ejemplo.com` (Calificar profesores y explorar).
- **Tutor de Matemáticas:** `roberto.tutor@ejemplo.com` (Aprobar solicitudes y fijar tarifas).
- **Tutor de Programación:** `maria.tutor@ejemplo.com` (Subir CV en PDF y gestionar disponibilidad).

---

## 10. Matriz de Pruebas y Validaciones de Seguridad

| Vulnerabilidad Evaluada | Escenario de Ataque Ejecutado | Contramedida Aplicada en el Código | Resultado |
|---|---|---|---|
| **SQL Injection (SQLi)** | Inyección de comillas `' OR '1'='1` en buscador, login y reservas. | PDO con `ATTR_EMULATE_PREPARES => false`. Parámetros tratados como valores escalares. | <span style="color:green;font-weight:bold;">MITIGADO</span> |
| **Cross-Site Scripting (XSS)** | Carga de etiquetas `<script>` en chat, comentarios y nombres. | Sanitización y escape con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` mediante `e()`. | <span style="color:green;font-weight:bold;">MITIGADO</span> |
| **Cross-Site Request Forgery (CSRF)** | Envío de formularios POST omitiendo el token o alterándolo. | Función `verificar_csrf()` compara con `hash_equals()` el token de sesión. | <span style="color:green;font-weight:bold;">MITIGADO</span> |
| **Fuerza Bruta en Autenticación** | Múltiples solicitudes de login con credenciales erradas. | `esta_bloqueado_fuerza_bruta()` bloquea la IP/correo tras 5 intentos en 15 min. | <span style="color:green;font-weight:bold;">MITIGADO</span> |
| **Ejecución de Código en Uploads** | Subida de archivo malicioso `.php` camuflado con extensión `.jpg`. | `finfo` analiza la firma binaria real y `.htaccess` desactiva el motor PHP en `uploads/`. | <span style="color:green;font-weight:bold;">MITIGADO</span> |
| **Secuestro de Sesión (Session Hijacking)** | Clonación de la cookie de sesión entre diferentes navegadores. | Validación de `$_SESSION['ua_hash']` contra el `HTTP_USER_AGENT` actual. | <span style="color:green;font-weight:bold;">MITIGADO</span> |
| **Manipulación de Calificaciones** | Intento de auto-calificación de un profesor sobre su propio perfil. | Validación de roles en backend y bloqueo explícito para el ID del tutor. | <span style="color:green;font-weight:bold;">MITIGADO</span> |

---

## 11. Conclusiones y Hoja de Ruta de Escalabilidad

El proyecto **TutoresOnLine** consolida una arquitectura ligera, modular y de alto rendimiento que prioriza la seguridad sin sacrificar la interactividad del usuario final.

### Próximos Desarrollos Recomendados
1. **Pasarela de Pagos con Depósito en Garantía (Escrow):** Integración con Wompi o PayU para custodiar el pago de la clase hasta que el alumno certifique su realización.
2. **Salas de Videoconferencia Integradas:** Conexión automática con WebRTC o API de Jitsi Meet para clases virtuales con pizarra digital.
3. **Notificaciones Push y SMS:** Alertas vía Twilio o WhatsApp Business API ante nuevas solicitudes de tutorías.
4. **WebSockets con Ratchet PHP:** Migración del polling del chat hacia canales dúplex persistentes cuando se disponga de un servidor VPS o dedicado.
