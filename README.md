# TutoresOnLine - Plataforma Integral de Tutorías Académicas

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479a1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952b3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Vue.js](https://img.shields.io/badge/Vue.js-3%20CDN-4fc08d?logo=vuedotjs&logoColor=white)](https://vuejs.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Plataforma web completa para conectar estudiantes de todos los niveles educativos con profesores tutores particulares para clases virtuales o presenciales, con gestión de agendas, mensajería en tiempo real, sistema de calificaciones auditado y seguridad de grado empresarial.

Desarrollada en **PHP 7.4 puro (PDO)**, **MySQL 5.7 (InnoDB con utf8mb4)**, **Bootstrap 5.3** y reactividad frontend con **Vue 3 por CDN** (sin necesidad de Node.js, Webpack ni herramientas de compilación).

---

## 🚀 Características Principales

### 1. 🛡️ Autenticación, Roles y Seguridad Robusta
- **Registro y Acceso Diferenciado:** Flujos específicos para **Estudiantes** y **Tutores**.
- **Autenticación Federada con Google OAuth 2.0:** Integración nativa (cURL y fallback de streams) mediante el flujo *Authorization Code*, con validación de `state` anti-CSRF y auto-vinculación de cuentas.
- **Hashing Criptográfico Avanzado:** Contraseñas protegidas con `password_hash()` (Bcrypt, costo 12) precedidas de un **Pepper secreto en HMAC-SHA256**.
- **Mitigación de Fuerza Bruta:** Bloqueo temporal de 15 minutos tras 5 intentos fallidos consecutivos auditados en la tabla `intentos_login`.
- **Sesiones Blindadas:** Directivas estrictas (`HttpOnly`, `SameSite=Lax`, `Secure` condicional), atadura al hash SHA-256 del `HTTP_USER_AGENT`, expiración por inactividad tras 30 minutos y regeneración periódica contra *Session Fixation*.
- **Tokenización CSRF Universal:** Tokens de 32 bytes validados en tiempo constante mediante `hash_equals()` en todos los formularios y llamadas API.
- **Prevención de Inyección SQL y XSS:** Consultas preparadas PDO forzadas (`ATTR_EMULATE_PREPARES => false`) y escape contextual sistemático con `htmlspecialchars()` (helper `e()`).
- **Activación y Recuperación:** Tokens de un solo uso con caducidad temporal para confirmación de correos y reseteo de claves.

### 2. 🍪 Gestión Avanzada de Cookies
- **Sesión Persistente ("Recordarme" de 30 días):** Arquitectura *Selector:Validador* hasheado en SHA-256 (estándar de Charles Miller) con **rotación automática del validador en cada uso** y **revocación masiva** inmediata ante sospechas de clonación o manipulación.
- **Preferencia de Búsqueda:** Cookie `ultima_materia_buscada` para precargar automáticamente la última consulta académica realizada.

### 3. 📂 Almacenamiento Seguro de Documentos
- Subida de fotografías de perfil (JPG/PNG, máx. 2 MB) y hojas de vida de tutores (PDF, máx. 5 MB).
- Validación de códigos de error nativos y verificación de extensiones por lista blanca.
- **Inspección Binaria de Tipo MIME:** Detección de la firma mágica del archivo en disco con `finfo_file()`, ignorando cabeceras manipuladas por el cliente.
- Nombres aleatorios e impredecibles generados con `bin2hex(random_bytes(16))`.
- Directorio `public/uploads/` protegido con `.htaccess` que deshabilita el motor de PHP (`php_flag engine off`) para imposibilitar la ejecución de Web Shells.

### 4. 🔍 Buscador Reactivo de Tutores (Vue 3 SPA)
- Catálogo interactivo consumiendo la API JSON (`/api/buscar_tutores.php`).
- Búsqueda predictiva y en vivo por nombre de docente o por materia impartida.
- Filtros simultáneos por nivel académico (Primaria, Secundaria, Universidad, Profesional), ciudad de residencia y modalidad (virtual o presencial).
- Visualización de tarifas por hora reales y estado de reputación.

### 5. ⭐ Perfil Universal y Sistema de Reputación Objetivo
- **Perfil Dual:**
  - *Modo Privado:* Administrador personal dividido en apartados organizados mediante selector lateral (Datos Personales, Seguridad y Claves, Especialidades y Tarifas, Disponibilidad Semanal, Documentos y Foto).
  - *Modo Público:* Tarjeta de presentación del docente, especialidades, tarifas por nivel, horarios de atención, botón de agendamiento y sección de opiniones.
- **Calificaciones Honestizadas:** Se eliminó la asignación ficticia de 5.0 para profesores sin historial; ahora indica limpiamente *"Sin calificaciones aún"*.
- **Módulo de Puntuación (1 a 5 estrellas):** Permite a los estudiantes autenticados calificar y redactar reseñas desde el perfil público del profesor o desde el panel de clases finalizadas, con restricción `UNIQUE` en base de datos para impedir spam y bloqueo de auto-calificaciones para tutores.

### 6. 💬 Mensajería y Chat en Tiempo Real
- Interfaz reactiva en `public/chat.php` impulsada por Vue 3.
- **Arquitectura de Polling Incremental:** Consulta periódica eficiente cada 2.5 segundos mediante parámetro `desde_id`, descargando únicamente los mensajes nuevos.
- Marcas de lectura automáticas, ordenamiento dinámico de conversaciones por actividad y validación de seguridad de pertenencia al hilo (`HTTP 403`).

### 7. 📅 Agendamiento y Ciclo de Vida de Tutorías
- Solicitud guiada de clases con validación de horarios y modalidad.
- Dashboard unificado (`panel.php`) adaptativo por rol:
  - *Estudiantes:* Seguimiento de solicitudes (Pendiente, Aceptada, Rechazada, Finalizada), acceso al chat del tutor y modal de calificación.
  - *Tutores:* Aprobación o rechazo de solicitudes entrantes, marcado de finalización, configuración de franjas horarias y alta al vuelo de nuevas materias.

---

## 📁 Estructura del Repositorio

```text
TutoresOn-Line/
├── .gitignore                      # Excluye .env, uploads/*, logs/* y artefactos temporales
├── LICENSE                         # Licencia MIT (Copyright 2026 Jeyfar)
├── README.md                       # Documentación principal del proyecto
├── DOCUMENTACION_COMPLETA_SISTEMA.md # Memoria técnica y arquitectónica exhaustiva
├── config/
│   ├── .env                        # Variables locales y llaves criptográficas (no se commitea)
│   ├── .env.example                # Plantilla de configuración de entorno
│   ├── .htaccess                   # Bloqueo total de acceso HTTP externo
│   └── config.php                  # Parser nativo de .env, rutas y manejo de errores
├── database/
│   ├── schema.sql                  # DDL de 14 tablas en InnoDB (utf8mb4_unicode_ci)
│   └── datos_prueba.sql            # Datos semilla (materias, niveles y usuarios de prueba)
├── includes/
│   ├── .htaccess                   # Bloqueo directo de acceso web
│   ├── conexion.php                # Singleton de conexión PDO
│   ├── seguridad.php               # Módulo central de cabeceras, CSRF, XSS y rate limit
│   ├── auth.php                    # Control de sesiones, roles RBAC y tokens persistentes
│   ├── oauth_google.php            # Integración nativa Google OAuth 2.0 (cURL y Streams)
│   ├── archivos.php                # Validación MIME (finfo) y subida segura
│   ├── correo.php                  # Despachador de emails (modo log y SMTP)
│   ├── header.php                  # Barra de navegación responsiva y mensajes flash
│   └── footer.php                  # Cierre semántico y scripts de Bootstrap 5
├── api/                            # Endpoints REST JSON
│   ├── buscar_tutores.php          # Búsqueda multicriterio con promedios reales
│   ├── reservar.php                # Agendamiento seguro de sesiones
│   ├── solicitudes.php             # Gestión de estados (aceptar / rechazar / finalizar)
│   ├── disponibilidad.php          # Horarios semanales del tutor
│   ├── chat_conversaciones.php     # Gestión de conversaciones de chat
│   ├── chat_mensajes.php           # Polling incremental de mensajes
│   └── chat_enviar.php             # Envío seguro y sanitizado de mensajes
├── logs/                           # Auditoría y simulación de correo
│   ├── .htaccess                   # Bloqueo de acceso HTTP
│   └── mail.log                    # Enlaces de activación y recuperación de claves
└── public/                         # Raíz pública web del servidor Apache
    ├── index.php                   # Página de bienvenida
    ├── login.php                   # Inicio de sesión seguro y botón de Google
    ├── registro.php                # Registro diferenciado de usuarios
    ├── verificar.php               # Activación de cuentas por token
    ├── recuperar.php               # Restablecimiento de contraseña
    ├── logout.php                  # Cierre de sesión y revocación de cookies
    ├── buscar.php                  # Buscador interactivo SPA con Vue 3
    ├── panel.php                   # Dashboard unificado adaptativo por rol
    ├── perfil.php                  # Perfil universal privado y público
    ├── chat.php                    # Interfaz de mensajería interactiva
    ├── reservar.php                # Formulario guiado de reserva de clases
    ├── css/estilos.css             # Estilos y micro-animaciones personalizadas
    └── uploads/                    # Archivos subidos con ejecución PHP deshabilitada
```

---

## 🛠️ Instalación y Puesta en Marcha

### 1. Requisitos Previos
- Servidor Web **Apache 2.4+** con módulo `mod_rewrite` habilitado.
- **PHP 7.4 o superior** con extensiones `pdo_mysql`, `fileinfo`, `json` y `session`.
- **MySQL 5.7+** o MariaDB 10.2+.
- Compatible con **USBWebserver**, XAMPP, Laragon, WampServer o servidores Linux LAMP.

### 2. Configuración en Servidor Local (Ej. USBWebserver)
1. Coloca la carpeta del proyecto dentro de la raíz de Apache (ej. `C:\USBWebserver\root\tutoresonline`).
2. Copia el archivo `config/.env.example` y renómbralo como `config/.env`:
   ```bash
   cp config/.env.example config/.env
   ```
3. Verifica los valores de conexión a la base de datos en `config/.env`:
   ```ini
   APP_NAME=TutoresOnLine
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost/tutoresonline/public
   APP_PEPPER=clave_secreta_pepper_criptografica_2026

   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=tutoresonline
   DB_USER=root
   DB_PASS=usbw

   MAIL_DRIVER=log
   ```

### 3. Importación de la Base de Datos
Abre phpMyAdmin (`http://localhost/phpmyadmin`) o tu cliente MySQL e importa en este orden estricto:
1. `database/schema.sql` (crea la base de datos `tutoresonline` y sus 14 tablas relacionales).
2. `database/datos_prueba.sql` (inserta el catálogo de materias, niveles y 6 usuarios listos para pruebas).

### 4. Permisos de Directorios
Asegúrate de que los directorios `logs/` y `public/uploads/` tengan permisos de lectura y escritura para el servidor web.

---

## 👤 Cuentas de Prueba Preconfiguradas

La contraseña universal para todas las cuentas de prueba iniciales es: **`password123`**

| Rol | Correo Electrónico | Especialidad / Propósito de Prueba |
|---|---|---|
| **Estudiante** | `carlos.estudiante@ejemplo.com` | Agendar tutorías, explorar perfiles y chatear |
| **Estudiante** | `laura.estudiante@ejemplo.com` | Probar calificaciones con estrellas y reseñas |
| **Estudiante (Pendiente)** | `andres.pendiente@ejemplo.com` | Validar banner de activación por correo |
| **Tutor** | `roberto.tutor@ejemplo.com` | Matemáticas &bull; Aceptar/rechazar solicitudes de clase |
| **Tutor** | `maria.tutor@ejemplo.com` | Programación &bull; Subida de CV en PDF y foto de perfil |
| **Tutor** | `camilo.tutor@ejemplo.com` | Física &bull; Configuración de horarios y tarifas |

---

## 📧 Pruebas de Correo en Desarrollo Local

Para facilitar pruebas sin requerir un servidor SMTP externo con internet, el sistema utiliza el driver `MAIL_DRIVER=log`.

Al registrar una cuenta nueva o solicitar restablecer la contraseña, el sistema escribe el enlace de activación o reseteo en:
```text
logs/mail.log
```
Basta con abrir ese archivo, copiar la URL generada y pegarla en el navegador para activar la cuenta o cambiar la contraseña inmediatamente.

---

## 📄 Licencia

Este proyecto está bajo la **Licencia MIT**. Consulta el archivo [LICENSE](LICENSE) para más detalles.

Copyright (c) 2026 **Jeyfar**.
