# TutoresOnLine - Plataforma de Tutorías Académicas

Plataforma web para conectar estudiantes universitarios y de colegio con profesores tutores para clases particulares, virtuales o presenciales.

Desarrollada en **PHP 7.4 puro (PDO)**, **MySQL 5.7 (USBWebserver)**, **Bootstrap 5** y reactividad con **Vue 3 por CDN** (sin build tools pesadas).

---

## 🚀 Características Principales

1. **Autenticación y Seguridad Robusta:**
   - Registro diferenciado para **Estudiantes** y **Tutores**.
   - Contraseñas hasheadas con `password_hash()` (bcrypt, cost 12) + pepper criptográfico en `hash_hmac()`.
   - Protección contra inyección SQL con **PDO** (`ATTR_EMULATE_PREPARES => false`).
   - Tokens **CSRF** en todos los formularios y endpoints API.
   - Mitigación de ataques de fuerza bruta (bloqueo temporal tras 5 intentos fallidos en 15 min).
   - Sesiones seguras atadas al **User-Agent** (`X-Content-Type-Options`, `X-Frame-Options`, `SameSite=Lax`, `HttpOnly`).
   - Verificación de correo electrónico y recuperación de contraseña con tokens criptográficos de un solo uso.

2. **Gestión de Cookies Avanzada:**
   - **Sesión persistente ("Recordarme"):** Token de 30 días con arquitectura selector:validador hasheado (SHA-256) y rotación automática en cada inicio para mitigar robos de sesión.
   - **Cookie de preferencias:** Guarda la última materia buscada (`ultima_materia_buscada`) para prellenar los filtros en visitas posteriores.

3. **Gestión Segura de Archivos:**
   - Subida de fotos de perfil (JPG/PNG, máx. 2 MB) y hojas de vida de tutores (PDF, máx. 5 MB).
   - Validación de extensiones, límites de tamaño e inspección binaria del tipo MIME real con `finfo`.
   - Nombres aleatorios con `bin2hex(random_bytes(16))` para evitar sobreescritura o ejecución maliciosa.
   - Directorio `uploads/` protegido con `.htaccess` que bloquea la ejecución de scripts PHP.

4. **Búsqueda y Reserva Interactiva:**
   - Catálogo de materias, niveles académicos y tutores con Vue 3 CDN consumiendo la API JSON.
   - Perfil público de profesores con disponibilidad semanal, tarifas por hora y reseñas con estrellas.
   - Agendamiento de clases por parte del estudiante y panel del tutor para aceptar o rechazar solicitudes.
   - Calificación de 1 a 5 estrellas y comentarios tras finalizar la tutoría.

---

## 📁 Estructura del Proyecto

```text
tutoresonline/
├── .gitignore              # Excluye .env, uploads/* y logs/*
├── README.md               # Documentación general del sistema
├── database/
│   ├── schema.sql          # Creación de 12 tablas con InnoDB y llaves foráneas
│   └── datos_prueba.sql    # Datos iniciales para pruebas locales
├── config/
│   ├── .env                # Variables de entorno locales (NO se sube a Git)
│   ├── .env.example        # Plantilla pública de variables de entorno
│   ├── .htaccess           # Bloqueo de acceso HTTP directo
│   └── config.php          # Parser nativo de .env, rutas y manejo de errores
├── includes/
│   ├── .htaccess           # Bloqueo de acceso HTTP directo
│   ├── conexion.php        # Conexión PDO Singleton
│   ├── seguridad.php       # Sesiones, CSRF, XSS, rate limiting y cabeceras
│   ├── auth.php            # Control de roles, login, tokens y cookie persistente
│   ├── correo.php          # Envío de emails (modo log para pruebas locales)
│   ├── archivos.php        # Subida, validación finfo y borrado de archivos
│   ├── header.php          # Barra de navegación responsiva y mensajes flash
│   └── footer.php          # Cierre semántico y scripts de Bootstrap 5
├── api/                    # Endpoints que retornan JSON
│   ├── buscar_tutores.php  # Filtros de profesores
│   ├── reservar.php        # Creación de sesiones de tutoría
│   ├── solicitudes.php     # Aceptación/rechazo de clases
│   └── disponibilidad.php  # Horarios semanales del tutor
├── logs/                   # Registro de errores y correos simulados
│   ├── .htaccess           # Bloqueo de acceso HTTP
│   └── mail.log            # Enlaces de activación y recuperación de contraseña
└── public/                 # Directorio público accesible por Apache
    ├── index.php           # Página de bienvenida
    ├── login.php           # Inicio de sesión seguro
    ├── registro.php        # Creación de cuenta
    ├── verificar.php       # Validación de token de correo
    ├── recuperar.php       # Restablecimiento de contraseña
    ├── logout.php          # Cierre de sesión y limpieza de cookies
    ├── buscar.php          # Buscador reactivo de tutores con Vue 3
    ├── panel.php           # Dashboard universal adaptativo (estudiante y tutor)
    ├── perfil.php          # Perfil universal (gestión personal, archivos y vista pública)
    ├── reservar.php        # Formulario de agendamiento
    ├── css/                # Estilos personalizados
    └── uploads/            # Archivos subidos protegidos contra ejecución PHP
```

---

## 🛠️ Instalación y Puesta en Marcha

1. **Servidor Local:**
   - Inicia **USBWebserver** (Apache en puerto 80 y MySQL 5.7 en puerto 3306).
   - Asegúrate de que la carpeta del proyecto esté ubicada en el directorio `root` de Apache (ej. `http://localhost/tutoresonline/public/`).

2. **Base de Datos:**
   - Abre phpMyAdmin en `http://localhost/phpmyadmin` (usuario `root`, contraseña `usbw`).
   - Importa en orden:
     1. `database/schema.sql` (crea la base `tutoresonline` y sus 12 tablas).
     2. `database/datos_prueba.sql` (inserta materias, niveles y 6 usuarios listos).

3. **Variables de Entorno:**
   - Copia `config/.env.example` como `config/.env`.
   - Los valores por defecto vienen configurados para USBWebserver (`root` / `usbw`).

---

## 👤 Cuentas de Prueba Preconfiguradas

La contraseña para todas las cuentas de prueba es: **`password123`**

| Rol | Correo | Función Principal |
|---|---|---|
| **Estudiante** | `carlos.estudiante@ejemplo.com` | Agendar tutorías, calificar clases |
| **Estudiante** | `laura.estudiante@ejemplo.com` | Agendar tutorías y explorar perfiles |
| **Estudiante (Pendiente)** | `andres.pendiente@ejemplo.com` | Probar activación por enlace |
| **Tutor (Matemáticas)** | `roberto.tutor@ejemplo.com` | Gestionar horarios y solicitudes |
| **Tutor (Programación)** | `maria.tutor@ejemplo.com` | Ver solicitudes entrantes y subir CV |
| **Tutor (Física)** | `camilo.tutor@ejemplo.com` | Configurar materias y tarifas |

---

## 📧 Pruebas de Correo Local
Al crear una cuenta nueva o solicitar restablecer contraseña en desarrollo local, el sistema escribe el enlace directo de verificación en el archivo:
`logs/mail.log`
Basta con abrir ese archivo, copiar el enlace y abrirlo en el navegador para activar la cuenta.
