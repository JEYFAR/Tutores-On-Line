# Propuesta Comercial y Estimación de Tiempo y Costos — Proyecto "TutoresOnLine"

> **Documento:** Propuesta Comercial, Planificación Ágil (SCRUM) y Presupuesto Financiero  
> **Product Owner (Dueño del Producto):** Profesor Edgar Morillo  
> **Proyecto:** Plataforma Web y Móvil de Tutorías Académicas "TutoresOnLine"  
> **Fecha:** Noviembre de 2026  

---

## 1. Resumen Ejecutivo de la Propuesta

La presente propuesta comercial establece el alcance técnico, la metodología de trabajo ágil (**SCRUM**), el cronograma de ejecución por Sprints y la estimación detallada de tiempos y costos para el desarrollo, aseguramiento de calidad y puesta en producción de **TutoresOnLine**, una plataforma integral que conecta estudiantes con profesores calificados para clases virtuales (con sala de videollamada integrada) y presenciales, respaldada por un asistente académico con Inteligencia Artificial.

---

## 2. Conformación del Equipo de Desarrollo y Roles SCRUM

Siguiendo los lineamientos del proyecto, la célula ágil está conformada por **4 desarrolladores** y el acompañamiento del **Product Owner**:

| Integrante | Rol en el Proyecto | Responsabilidades Principales | Tarifa Hora Comercial (COP) |
|---|---|---|---|
| **Profesor Edgar Morillo** | **Product Owner (Dueño del Producto)** | Definición de visión del producto, validación de reglas de negocio y aprobación de entregables en cada Sprint Review. | *(Cliente / Evaluador)* |
| **Jesús David Ayala Noche (Jeyfar)** | **Líder Técnico / Scrum Master & Full-Stack Dev** | Facilitación de Dailys, Refinamientos, Sprint Planning y Retrospectivas; arquitectura general, seguridad (PDO, CSRF, OAuth2, SMTP), IA y videollamadas. | \$85.000 COP / h |
| **Ricardo Jesús Cudriz González** | **Desarrollador Backend & API RESTful** | Diseño de base de datos relacional (14 tablas en 3FN), endpoints JSON (`/api/*`) y lógica de agendamiento. | \$75.000 COP / h |
| **Steven Manuel Yépez Navarro** | **Desarrollador Frontend & UI/UX (Web/Móvil)** | Diseño de interfaces responsivas con Bootstrap 5.3, componentes reactivos en Vue.js 3 (buscador en vivo y chat en tiempo real) y experiencia móvil. | \$70.000 COP / h |
| **Yojaner Sanjuan** | **Ingeniero QA, Seguridad & Documentación** | Pruebas contra inyección SQL, XSS, CSRF, fuerza bruta, elaboración del Manual del Programador, Manual de Usuario y despliegue. | \$65.000 COP / h |

---

## 3. Metodología de Trabajo Ágil (SCRUM)

El desarrollo se ejecuta bajo un marco de trabajo **SCRUM adaptable semana a semana** durante **4 Sprints (4 semanas / 1 mes)**:

- **Sprint Planning y Refinamiento:** Al inicio de cada semana (1 hora) para priorizar las historias de usuario del Product Backlog según el avance real del equipo.
- **Daily Scrum:** Reuniones diarias breves de seguimiento (15 minutos) para sincronizar avances y resolver bloqueos técnicos.
- **Sprint Review (Entregas Parciales):** Presentación funcional al final de cada Sprint ante el Product Owner (**Profesor Edgar Morillo**) mostrando software funcionando.
- **Sprint Retrospective (Semanal):** Sesión semanal (45 minutos) al cierre de cada Sprint para evaluar qué funcionó bien y ajustar la velocidad del siguiente Sprint.

---

## 4. Cronograma y Estimación de Tiempo por Sprints

Cada miembro del equipo dedica **20 horas semanales** (80 horas por semana entre los 4 integrantes, para un total de **320 horas-hombre** en las 4 semanas):

| Sprint | Semana | Entregables y Módulos Desarrollados | Horas Equipo |
|---|---|---|---|
| **Sprint 1** | Semana 1 | **Arquitectura y Base de Datos:** Diseño relacional de 14 tablas (`schema.sql`), configuración `.env`, conexión PDO Singleton, módulo de seguridad (Bcrypt+Pepper, CSRF, XSS, Rate Limit) y autenticación local + Google OAuth 2.0. | 80 h |
| **Sprint 2** | Semana 2 | **Perfiles, Archivos y Buscador SPA:** Perfil dual (privado/público), descripción profesional del profesor, subida validada de foto y CV en PDF (`finfo`), gestión de materias/tarifas/horarios y buscador reactivo en Vue 3 (`api/buscar_tutores.php`). | 80 h |
| **Sprint 3** | Semana 3 | **Reservas, Correo SMTP, Chat y Reputación:** Agendamiento de tutorías (`reservar.php`), motor SMTP nativo con notificaciones automáticas al reservar/aceptar, chat en tiempo real por polling incremental y sistema de reputación de 1 a 5 estrellas. | 80 h |
| **Sprint 4** | Semana 4 | **Videoconferencia Privada, IA Generativa y Cierre:** Sala privada de videollamada embebida con token criptográfico y temporizador (`videollamada.php`), Asistente Académico con IA vía OpenRouter (`api/chat_ia.php`), pruebas de seguridad, manuales y despliegue. | 80 h |
| **TOTAL** | **4 Semanas** | **Plataforma TutoresOnLine 100% Operativa + Documentación Completa** | **320 horas** |

---

## 5. Presupuesto y Estimación de Costos Comerciales (En Pesos Colombianos - COP)

### 5.1. Costos de Mano de Obra (Ingeniería y Desarrollo)

| Integrante y Rol | Horas Totales (4 Semanas) | Valor Hora (COP) | Subtotal (COP) |
|---|---|---|---|
| **Jesús David Ayala Noche (Jeyfar)** — Líder Técnico / Scrum Master & Full-Stack | 80 h | \$85.000 | \$6.800.000 |
| **Ricardo Jesús Cudriz González** — Desarrollador Backend & API RESTful | 80 h | \$75.000 | \$6.000.000 |
| **Steven Manuel Yépez Navarro** — Desarrollador Frontend & UI/UX (Web/Móvil) | 80 h | \$70.000 | \$5.600.000 |
| **Yojaner Sanjuan** — Ingeniero QA, Seguridad & Documentación | 80 h | \$65.000 | \$5.200.000 |
| **Subtotal Desarrollo de Software** | **320 horas** | | **\$23.600.000 COP** |

### 5.2. Costos de Infraestructura, Despliegue y Servicios Cloud (Anual)

| Concepto | Proveedor / Tecnología | Periodicidad | Costo Estimado (COP) |
|---|---|---|---|
| **Dominio Web (`.com` o `.co`)** | Namecheap / Hostinger (`tutoresonline.co`) | Anual | \$95.000 |
| **Servidor Hosting Cloud (Apache + PHP + MySQL + SSL)** | Hostinger Business / VPS Cloud con certificado HTTPS Let's Encrypt incluido | Anual | \$540.000 |
| **Servicio de Videoconferencia Embebida** | Integración Jitsi Meet IFrame API con tokens criptográficos propios | Incluido | \$0 |
| **Servicio de Correo Transaccional (SMTP)** | SMTP Corporativo / Gmail App Password | Incluido | \$0 |
| **Consumo de API de Inteligencia Artificial** | OpenRouter API (`deepseek-v4.1-flash` — bolsa para ~50.000 consultas académicas) | Anual | \$180.000 |
| **Subtotal Infraestructura Primer Año** | | | **\$815.000 COP** |

### 5.3. Resumen Financiero Total del Proyecto

| Rubro | Valor (COP) |
|---|---|
| Desarrollo, Arquitectura, Pruebas QA, Gestión SCRUM y Manuales (Pago único) | \$23.600.000 COP |
| Infraestructura Cloud, Dominio, Certificado SSL y Bolsa IA (Por 1 año) | \$815.000 COP |
| **INVERSIÓN TOTAL DEL PROYECTO LLAVE EN MANO** | **\$24.415.000 COP** |

---

## 6. Modelo de Retorno de Inversión (Viabilidad Comercial)

- **Modelo de Monetización Sugerido:** Comisión de intermediación del **15%** por cada hora de tutoría reservada en la plataforma (valor promedio de clase: \$42.000 COP -> comisión de \$6.300 COP por sesión) o membresía destacada mensual para tutores (\$35.000 COP/mes).
- **Punto de Equilibrio (Break-Even):** Con **120 tutores activos** que dicten en promedio 8 horas de clase al mes, la plataforma genera ingresos mensuales cercanos a **\$6.048.000 COP**, recuperando el 100% de la inversión inicial en aproximadamente **4.1 meses de operación**.
