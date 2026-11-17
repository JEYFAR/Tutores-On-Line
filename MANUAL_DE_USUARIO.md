# Manual del Usuario — Plataforma TutoresOnLine

> **Documento:** Manual de Usuario Paso a Paso (Estudiantes y Tutores)  
> **Proyecto:** TutoresOnLine — Plataforma de Tutorías Académicas en Línea y Presenciales  
> **Product Owner:** Profesor Edgar Morillo  
> **Líder Técnico / Scrum Master:** Jesús David Ayala Noche (`Jeyfar`)  
> **Equipo de Desarrollo:** Ricardo Jesús Cudriz González, Steven Manuel Yépez Navarro, Yojaner Sanjuan  
> **Versión:** 3.0 (Proyecto Final)  

---

## Índice

1. [Introducción a TutoresOnLine](#1-introducción-a-tutoresonline)
2. [Registro y Activación de Cuenta](#2-registro-y-activación-de-cuenta)
3. [Inicio de Sesión y Acceso con Google](#3-inicio-de-sesión-y-acceso-con-google)
4. [Guía para el Estudiante](#4-guía-para-el-estudiante)
   - 4.1. [Buscar y Filtrar Tutores](#41-buscar-y-filtrar-tutores)
   - 4.2. [Consultar el Perfil Público del Profesor](#42-consultar-el-perfil-público-del-profesor)
   - 4.3. [Reservar una Sesión de Tutoría](#43-reservar-una-sesión-de-tutoría)
   - 4.4. [Seguimiento en el Panel del Estudiante](#44-seguimiento-en-el-panel-del-estudiante)
   - 4.5. [Ingresar a la Sala Privada de Videollamada](#45-ingresar-a-la-sala-privada-de-videollamada)
   - 4.6. [Calificar y Dejar Reseña al Profesor](#46-calificar-y-dejar-reseña-al-profesor)
5. [Guía para el Profesor (Tutor)](#5-guía-para-el-profesor-tutor)
   - 5.1. [Configurar Especialidades, Niveles y Tarifas por Hora](#51-configurar-especialidades-niveles-y-tarifas-por-hora)
   - 5.2. [Configurar Horarios de Disponibilidad Semanal](#52-configurar-horarios-de-disponibilidad-semanal)
   - 5.3. [Redactar Descripción Profesional y Subir Hoja de Vida (PDF)](#53-redactar-descripción-profesional-y-subir-hoja-de-vida-pdf)
   - 5.4. [Aceptar, Rechazar y Finalizar Solicitudes de Clase](#54-aceptar-rechazar-y-finalizar-solicitudes-de-clase)
6. [Mensajería Directa y Asistente Académico con IA](#6-mensajería-directa-y-asistente-académico-con-ia)
   - 6.1. [Chat Directo entre Estudiante y Tutor](#61-chat-directo-entre-estudiante-y-tutor)
   - 6.2. [Uso del Asistente Académico Virtual (IA)](#62-uso-del-asistente-académico-virtual-ia)
7. [Preguntas Frecuentes y Solución de Problemas](#7-preguntas-frecuentes-y-solución-de-problemas)

---

## 1. Introducción a TutoresOnLine

**TutoresOnLine** es una aplicación web diseñada para conectar desde cualquier computador o teléfono móvil a **estudiantes** de Primaria, Secundaria, Bachillerato y Universidad con **tutores calificados** para recibir clases personalizadas en modalidad **Virtual** (con sala de videollamada integrada) o **Presencial**.

---

## 2. Registro y Activación de Cuenta

1. Ingresa a la página principal (`index.php`) y haz clic en el botón **Registrarse** en la esquina superior derecha.
2. En el formulario de registro (`registro.php`):
   - Elige tu tipo de perfil: **Quiero Aprender (Estudiante)** o **Quiero Enseñar (Tutor)**.
   - Escribe tu **Nombre Completo**, **Correo Electrónico**, **Ciudad de Residencia** y una **Contraseña** de al menos 6 caracteres.
3. Haz clic en **Crear Cuenta**.
4. Recibirás un correo electrónico con un enlace de confirmación (en modo local también puedes abrir el enlace desde `logs/mail.log`). Al hacer clic en ese enlace, tu cuenta quedará verificada y activa.

> **Espacio para captura 1:** *Pantalla de Registro (`public/registro.php`) mostrando el selector de rol Estudiante / Tutor.*

---

## 3. Inicio de Sesión y Acceso con Google

1. Haz clic en **Iniciar Sesión** (`login.php`).
2. Ingresa tu correo y contraseña, o pulsa **Continuar con Google** para autenticarte con tu cuenta de Google en un solo clic.
3. Si marcas la casilla **Recordarme por 30 días**, no tendrás que volver a escribir tu clave al cerrar y abrir el navegador.
4. Si olvidaste tu clave, haz clic en **¿Olvidaste tu contraseña?** (`recuperar.php`) para recibir un enlace de restablecimiento.

> **Espacio para captura 2:** *Pantalla de Inicio de Sesión (`public/login.php`) con el botón de Google y la opción Recordarme.*

---

## 4. Guía para el Estudiante

### 4.1. Buscar y Filtrar Tutores
1. En el menú superior, entra a **Buscar Tutores** (`buscar.php`).
2. Utiliza los filtros interactivos (se actualizan sin recargar la página):
   - **Materia, Especialidad o Nombre:** Ej. *Cálculo, Programación, Inglés, Roberto...*
   - **Nivel Educativo:** *Primaria, Secundaria, Bachillerato o Universidad.*
   - **Modalidad:** *Virtual* o *Presencial*.
   - **Ciudad:** Ej. *Bogotá, Medellín, Cali.*
3. En cada tarjeta verás el nombre del profesor, su ciudad, calificación promedio con estrellas, materias que dicta, un extracto de su presentación profesional y su tarifa por hora en pesos colombianos (COP).

> **Espacio para captura 3:** *Buscador Reactivo de Tutores (`public/buscar.php`) con filtros activos y tarjetas de profesores.*

### 4.2. Consultar el Perfil Público del Profesor
1. Haz clic en **Ver Perfil** sobre cualquier tutor (`perfil.php?id=X`).
2. Encontrarás:
   - **Tarjeta lateral:** Foto, ciudad, promedio de estrellas, botón **Ver Hoja de Vida** (PDF), botón **Reservar Tutoría** y botón **Enviar Mensaje**.
   - **Sobre el Profesor:** Descripción profesional de su experiencia y metodología.
   - **Materias Ofertadas y Tarifas:** Tabla con cada materia, nivel y valor por hora.
   - **Horarios de Disponibilidad:** Días y franjas horarias en que atiende.
   - **Opiniones y Calificaciones:** Reseñas reales dejadas por otros estudiantes.

> **Espacio para captura 4:** *Perfil Público del Profesor (`public/perfil.php?id=4`) mostrando presentación, tarifas, horarios y reseñas.*

### 4.3. Reservar una Sesión de Tutoría
1. Desde el perfil del profesor, haz clic en **Reservar Tutoría** (`reservar.php?tutor_id=X`).
2. Selecciona:
   - **Materia a tratar** (muestra el nivel y la tarifa por hora).
   - **Modalidad de la clase:** *Virtual* o *Presencial*.
   - **Fecha**, **Hora Inicio** y **Hora Fin** (puedes guiarte por los horarios habituales del profesor mostrados abajo).
3. Pulsa **Confirmar Solicitud**. El sistema registrará la tutoría en estado `pendiente` y enviará una notificación por correo electrónico tanto al profesor como a ti.

> **Espacio para captura 5:** *Formulario de Reserva de Tutoría (`public/reservar.php`).*

### 4.4. Seguimiento en el Panel del Estudiante
1. En **Inicio / Mi Panel** (`panel.php`) verás la tabla **Mis Tutorías Solicitadas** con el estado actual de cada clase:
   - 🟨 **Pendiente:** Esperando confirmación del profesor.
   - 🟩 **Aceptada:** Confirmada; habilita el botón verde **Entrar a Sala**.
   - 🟦 **Finalizada:** Clase completada; habilita el botón **Calificar**.
   - 🟥 **Rechazada:** El tutor no tenía disponibilidad en ese horario.

> **Espacio para captura 6:** *Panel del Estudiante (`public/panel.php`) con sesiones en distintos estados y botón Entrar a Sala.*

### 4.5. Ingresar a la Sala Privada de Videollamada
1. Cuando tu tutoría esté **Aceptada**, haz clic en **Entrar a Sala** (`videollamada.php?id=X`) desde tu panel o desde el enlace recibido en tu correo.
2. La videollamada se abre **dentro de TutoresOnLine** en una sala privada verificada:
   - Tu nombre y rol (*Estudiante*) se configuran automáticamente.
   - En la barra superior verás el **temporizador regresivo** con el tiempo restante de la clase, un botón para abrir el **Chat con el Profesor** y el botón rojo **Finalizar y Cerrar Sala**.
   - Al terminar el tiempo o pulsar **Finalizar y Cerrar Sala**, la sala se destruye por seguridad.

> **Espacio para captura 7:** *Sala Privada de Videollamada (`public/videollamada.php`) con temporizador y video integrado.*

### 4.6. Calificar y Dejar Reseña al Profesor
1. Una vez finalizada la clase, entra a tu **Panel** (`panel.php`) y pulsa **Calificar** (o entra directamente a la sección *Opiniones* del perfil público del profesor).
2. Elige una puntuación de **1 a 5 estrellas**, escribe tu comentario y haz clic en **Guardar Calificación**.

---

## 5. Guía para el Profesor (Tutor)

### 5.1. Configurar Especialidades, Niveles y Tarifas por Hora
1. Desde tu **Panel de Tutor** (`panel.php`) o en **Mi Perfil -> Especialidades y Tarifas** (`perfil.php#tab-materias`):
2. Selecciona una materia del catálogo (o elige **+ Otra especialidad...** para crear una nueva), escoge el **Nivel** y define tu **Tarifa/h en COP**.
3. Pulsa **Guardar Especialidad**. Inmediatamente aparecerás en el buscador para esa asignatura.

### 5.2. Configurar Horarios de Disponibilidad Semanal
1. En tu **Panel de Tutor** (`panel.php`), ubica la tarjeta **Mi Disponibilidad Semanal**.
2. Selecciona el **Día de la semana**, la **Hora de inicio**, la **Hora de fin** y haz clic en el botón **+**.

### 5.3. Redactar Descripción Profesional y Subir Hoja de Vida (PDF)
1. Entra a **Mi Perfil** (`perfil.php`).
2. En **Información Personal**, completa el campo **Sobre mí / Presentación Profesional** describiendo tu experiencia y metodología, y pulsa **Guardar Cambios**.
3. En la pestaña **Foto y Documentos** (`perfil.php#tab-archivos`), sube tu **Foto de Perfil** (JPG/PNG) y tu **Hoja de Vida / Certificados** en formato PDF.

> **Espacio para captura 8:** *Panel del Tutor (`public/panel.php`) y configuración de Perfil (`public/perfil.php`).*

### 5.4. Aceptar, Rechazar y Finalizar Solicitudes de Clase
1. En la tabla **Solicitudes de Tutoría** de tu panel (`panel.php`):
   - Pulsa el botón verde **✔ (Aceptar)** para confirmar la clase: el sistema creará la sala privada de videollamada y enviará un correo automático al estudiante con el acceso.
   - Pulsa **✖ (Rechazar)** si no puedes atender en ese horario.
   - Cuando la clase está **Aceptada**, verás el botón **Entrar a Sala** para iniciar la videollamada y el botón **Marcar Finalizada** al concluir.

---

## 6. Mensajería Directa y Asistente Académico con IA

### 6.1. Chat Directo entre Estudiante y Tutor
1. Haz clic en **Mensajes** en la barra superior (`chat.php`) o en el ícono de chat junto al nombre del profesor/estudiante.
2. Envía y recibe mensajes en tiempo real sin necesidad de recargar la página.

> **Espacio para captura 9:** *Módulo de Mensajería en Tiempo Real (`public/chat.php`).*

### 6.2. Uso del Asistente Académico Virtual (IA)
1. En la esquina inferior derecha de cualquier pantalla verás el botón flotante **✨ Asistente IA**.
2. Al hacer clic se desplegará la ventana del **Asistente Académico**, donde puedes:
   - Hacer preguntas sobre cualquier materia (*Matemáticas, Cálculo, Física, Química, Programación, Inglés...*).
   - Pedirle que te explique un ejercicio paso a paso o te arme una guía de estudio.
   - Preguntarle qué profesores están disponibles en la plataforma y cuáles son sus tarifas.

> **Espacio para captura 10:** *Widget Flotante del Asistente Académico con IA respondiendo una consulta en vivo.*

---

## 7. Preguntas Frecuentes y Solución de Problemas

- **¿Por qué no puedo entrar a una sala de videollamada?**  
  Solo el estudiante y el profesor asignados a esa tutoría pueden entrar, y únicamente mientras el estado de la tutoría sea **Aceptada**. Si la clase ya fue marcada como **Finalizada**, la sala se cierra permanentemente por seguridad.
- **¿Cómo pruebo los correos si estoy sin internet en un servidor local?**  
  Todos los correos enviados (activación de cuenta, recuperación de clave, nueva reserva y tutoría aceptada) guardan automáticamente una copia con sus enlaces en el archivo `logs/mail.log`.
