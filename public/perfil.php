<?php
/**
 * =============================================================================
 * TutoresOnLine - Perfil del Usuario Universal (Estudiantes y Tutores)
 * Gestión de datos personales, credenciales de acceso y subida de archivos/foto.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/archivos.php';

// Control estricto: requiere sesión activa
requiere_login();

$usuario_id = (int)$_SESSION['usuario_id'];
$usuario = usuario_actual();
$rol = $usuario['rol'];
$pdo = obtener_conexion();

// =============================================================================
// PROCESAMIENTO DE ACCIONES (POST)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        set_flash('danger', 'Error de seguridad (CSRF). Intenta de nuevo.');
        header('Location: ' . APP_URL . '/perfil.php');
        exit;
    }

    $accion = $_POST['accion'] ?? '';

    // ACCIÓN 1: Actualizar Información Personal (Nombre, Ciudad)
    if ($accion === 'actualizar_datos') {
        $nombre = trim($_POST['nombre'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? '');

        if (empty($nombre) || strlen($nombre) < 3) {
            set_flash('danger', 'El nombre completo debe tener al menos 3 caracteres.');
        } elseif (empty($ciudad)) {
            set_flash('danger', 'Por favor ingresa tu ciudad de residencia.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE `usuarios` SET `nombre` = ?, `ciudad` = ? WHERE `id` = ?");
                $stmt->execute([$nombre, $ciudad, $usuario_id]);
                $_SESSION['nombre'] = $nombre;
                set_flash('success', '¡Tus datos personales se han actualizado correctamente!');
            } catch (Exception $e) {
                error_log("[Perfil Datos Error]: " . $e->getMessage());
                set_flash('danger', 'Ocurrió un error al actualizar tus datos.');
            }
        }
        header('Location: ' . APP_URL . '/perfil.php');
        exit;
    }

    // ACCIÓN 2: Cambiar Contraseña
    if ($accion === 'cambiar_password') {
        $password_actual = $_POST['password_actual'] ?? '';
        $password_nuevo  = $_POST['password_nuevo'] ?? '';
        $password_conf   = $_POST['password_conf'] ?? '';

        // Consultar hash actual del usuario
        $stmt_u = $pdo->prepare("SELECT `password_hash` FROM `usuarios` WHERE `id` = ? LIMIT 1");
        $stmt_u->execute([$usuario_id]);
        $row_u = $stmt_u->fetch();
        $hash_actual = $row_u['password_hash'] ?? null;

        $es_valido = true;

        // Si ya tenía contraseña, debe ingresar la actual
        if (!empty($hash_actual)) {
            if (empty($password_actual) || !verificar_password($password_actual, $hash_actual)) {
                set_flash('danger', 'La contraseña actual ingresada es incorrecta.');
                $es_valido = false;
            }
        }

        if ($es_valido) {
            if (strlen($password_nuevo) < 6) {
                set_flash('danger', 'La nueva contraseña debe tener al menos 6 caracteres.');
            } elseif ($password_nuevo !== $password_conf) {
                set_flash('danger', 'La confirmación de la nueva contraseña no coincide.');
            } else {
                try {
                    $nuevo_hash = hashear_password($password_nuevo);
                    $stmt_upd = $pdo->prepare("UPDATE `usuarios` SET `password_hash` = ? WHERE `id` = ?");
                    $stmt_upd->execute([$nuevo_hash, $usuario_id]);
                    set_flash('success', '¡Tu contraseña ha sido actualizada exitosamente!');
                } catch (Exception $e) {
                    error_log("[Perfil Password Error]: " . $e->getMessage());
                    set_flash('danger', 'Error al guardar la nueva contraseña.');
                }
            }
        }
        header('Location: ' . APP_URL . '/perfil.php');
        exit;
    }

    // ACCIÓN 3: Subir Archivo (Foto o Documento/Hoja de Vida)
    if ($accion === 'subir_archivo') {
        $tipo = $_POST['tipo'] ?? 'foto';
        if (!isset($_FILES['archivo']) || empty($_FILES['archivo']['name'])) {
            set_flash('danger', 'Por favor selecciona un archivo para subir.');
        } else {
            $resultado = guardar_archivo_subido($_FILES['archivo'], $usuario_id, $tipo);
            if ($resultado['exito']) {
                set_flash('success', $resultado['mensaje']);
            } else {
                set_flash('danger', $resultado['mensaje']);
            }
        }
        header('Location: ' . APP_URL . '/perfil.php');
        exit;
    }

    // ACCIÓN 4: Eliminar Archivo
    if ($accion === 'eliminar_archivo') {
        $archivo_id = (int)($_POST['archivo_id'] ?? 0);
        if ($archivo_id > 0) {
            // Verificar si el archivo era la foto del usuario
            $stmt_arch = $pdo->prepare("SELECT `nombre_guardado`, `tipo` FROM `archivos` WHERE `id` = ? AND `usuario_id` = ? LIMIT 1");
            $stmt_arch->execute([$archivo_id, $usuario_id]);
            $arc = $stmt_arch->fetch();

            if ($arc && eliminar_archivo_usuario($archivo_id, $usuario_id)) {
                if ($arc['tipo'] === 'foto') {
                    $pdo->prepare("UPDATE `usuarios` SET `foto` = NULL WHERE `id` = ? AND `foto` = ?")->execute([$usuario_id, $arc['nombre_guardado']]);
                }
                set_flash('success', 'Archivo eliminado con éxito.');
            } else {
                set_flash('danger', 'No se pudo eliminar el archivo o no tienes permisos.');
            }
        }
        header('Location: ' . APP_URL . '/perfil.php');
        exit;
    }
}

// Consultar datos actualizados de usuario y sus archivos
$usuario = usuario_actual();
$archivos = listar_archivos_usuario($usuario_id);

// Determinar URL de la foto de perfil
$avatar_url = null;
if (!empty($usuario['foto'])) {
    if (strpos($usuario['foto'], 'http://') === 0 || strpos($usuario['foto'], 'https://') === 0) {
        $avatar_url = $usuario['foto'];
    } elseif (file_exists(UPLOADS_PATH . DIRECTORY_SEPARATOR . $usuario['foto'])) {
        $avatar_url = APP_URL . '/uploads/' . $usuario['foto'];
    }
}

$titulo_pagina = 'Mi Perfil';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- ENCABEZADO DE PERFIL -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card card-custom p-4 border-0 shadow-sm">
            <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                <!-- Avatar Circular -->
                <div class="position-relative">
                    <?php if ($avatar_url): ?>
                        <img src="<?= e($avatar_url) ?>" alt="<?= e($usuario['nombre']) ?>" class="rounded-circle shadow-sm border border-3 border-primary-subtle" style="width: 90px; height: 90px; object-fit: cover;" referrerpolicy="no-referrer">
                    <?php else: ?>
                        <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 90px; height: 90px; font-size: 2rem; font-weight: 700;">
                            <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Datos Principales -->
                <div class="flex-grow-1 text-center text-md-start">
                    <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-dark"><?= e($usuario['nombre']) ?></h3>
                        <?php if ($rol === 'tutor'): ?>
                            <span class="badge bg-success badge-rol">Tutor</span>
                        <?php else: ?>
                            <span class="badge bg-primary badge-rol">Estudiante</span>
                        <?php endif; ?>
                        <?php if ((int)$usuario['correo_verificado'] === 1): ?>
                            <span class="badge bg-success-subtle text-success small" title="Correo verificado">
                                <i class="bi bi-patch-check-fill me-1"></i> Verificado
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted mb-2">
                        <i class="bi bi-envelope me-1"></i> <?= e($usuario['correo']) ?>
                        <?php if (!empty($usuario['ciudad'])): ?>
                            &bull; <i class="bi bi-geo-alt me-1"></i> <?= e($usuario['ciudad']) ?>
                        <?php endif; ?>
                        &bull; <i class="bi bi-clock-history me-1"></i> Miembro desde <?= date('M Y', strtotime($usuario['creado_en'])) ?>
                    </p>
                </div>

                <!-- Botones de Navegación Rápida -->
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <a href="<?= APP_URL ?>/panel.php" class="btn btn-primary-custom btn-sm d-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-speedometer2"></i> Mi Panel
                    </a>
                    <?php if ($rol === 'tutor'): ?>
                        <a href="<?= APP_URL ?>/perfil_tutor.php?id=<?= $usuario_id ?>" class="btn btn-outline-success btn-sm d-flex align-items-center justify-content-center gap-1">
                            <i class="bi bi-eye"></i> Perfil Público
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- COLUMNA IZQUIERDA: DATOS PERSONALES Y SEGURIDAD -->
    <div class="col-lg-6">
        <!-- 1. Información Personal -->
        <div class="card card-custom p-4 border-0 shadow-sm mb-4">
            <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-person-gear text-primary"></i> Información Personal
            </h5>

            <form action="<?= APP_URL ?>/perfil.php" method="POST" autocomplete="off">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="actualizar_datos">

                <div class="mb-3">
                    <label for="nombre" class="form-label fw-medium small">Nombre Completo</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= e($usuario['nombre']) ?>" required minlength="3">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="correo" class="form-label fw-medium small">Correo Electrónico (Asociado a tu cuenta)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="correo" value="<?= e($usuario['correo']) ?>" disabled readonly>
                    </div>
                    <small class="text-muted">El correo electrónico no puede ser modificado directamente por seguridad.</small>
                </div>

                <div class="mb-4">
                    <label for="ciudad" class="form-label fw-medium small">Ciudad de Residencia</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-geo-alt"></i></span>
                        <input type="text" class="form-control" id="ciudad" name="ciudad" value="<?= e($usuario['ciudad'] ?? '') ?>" required placeholder="Ej. Bogotá, Medellín, Cali">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary-custom btn-sm">
                    <i class="bi bi-check2-circle me-1"></i> Guardar Cambios
                </button>
            </form>
        </div>

        <!-- 2. Seguridad y Contraseña -->
        <div class="card card-custom p-4 border-0 shadow-sm">
            <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock text-primary"></i> Seguridad de la Cuenta
            </h5>

            <?php
            // Consultar si tiene contraseña local o solo cuenta OAuth
            $stmt_hash = $pdo->prepare("SELECT `password_hash` FROM `usuarios` WHERE `id` = ? LIMIT 1");
            $stmt_hash->execute([$usuario_id]);
            $tiene_password = !empty($stmt_hash->fetchColumn());
            ?>

            <?php if (!$tiene_password): ?>
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-google me-1"></i> Tu cuenta está autenticada con <strong>Google</strong>. Si deseas, puedes establecer una contraseña local a continuación.
                </div>
            <?php endif; ?>

            <form action="<?= APP_URL ?>/perfil.php" method="POST" autocomplete="off">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="cambiar_password">

                <?php if ($tiene_password): ?>
                    <div class="mb-3">
                        <label for="password_actual" class="form-label fw-medium small">Contraseña Actual</label>
                        <input type="password" class="form-control form-control-sm" id="password_actual" name="password_actual" required placeholder="Tu contraseña actual">
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="password_nuevo" class="form-label fw-medium small">Nueva Contraseña</label>
                    <input type="password" class="form-control form-control-sm" id="password_nuevo" name="password_nuevo" required minlength="6" placeholder="Mínimo 6 caracteres">
                </div>

                <div class="mb-3">
                    <label for="password_conf" class="form-label fw-medium small">Confirmar Nueva Contraseña</label>
                    <input type="password" class="form-control form-control-sm" id="password_conf" name="password_conf" required minlength="6" placeholder="Repite la nueva contraseña">
                </div>

                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-key me-1"></i> <?= $tiene_password ? 'Actualizar Contraseña' : 'Crear Contraseña' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- COLUMNA DERECHA: FOTO DE PERFIL Y ARCHIVOS / DOCUMENTOS -->
    <div class="col-lg-6">
        <!-- 1. Subir Foto de Perfil -->
        <div class="card card-custom p-4 border-0 shadow-sm mb-4">
            <h5 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                <i class="bi bi-image text-primary"></i> Foto de Perfil
            </h5>
            <p class="text-muted small mb-3">Sube tu fotografía personal en formato JPG o PNG (máximo 2 MB).</p>

            <form action="<?= APP_URL ?>/perfil.php" method="POST" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row gap-2">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="subir_archivo">
                <input type="hidden" name="tipo" value="foto">

                <input type="file" name="archivo" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                <button type="submit" class="btn btn-primary-custom btn-sm flex-shrink-0">
                    <i class="bi bi-upload me-1"></i> Subir Foto
                </button>
            </form>
        </div>

        <!-- 2. Subir Hoja de Vida / Documento de Soporte (Especialmente relevante para Tutores) -->
        <?php if ($rol === 'tutor'): ?>
            <div class="card card-custom p-4 border-0 shadow-sm mb-4">
                <h5 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-pdf text-danger"></i> Hoja de Vida / Certificados
                </h5>
                <p class="text-muted small mb-3">Sube tu currículum o certificaciones académicas en formato PDF (máximo 5 MB) para que los estudiantes puedan consultarlos.</p>

                <form action="<?= APP_URL ?>/perfil.php" method="POST" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row gap-2">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="subir_archivo">
                    <input type="hidden" name="tipo" value="hoja_vida">

                    <input type="file" name="archivo" class="form-control form-control-sm" accept=".pdf,application/pdf" required>
                    <button type="submit" class="btn btn-outline-danger btn-sm flex-shrink-0">
                        <i class="bi bi-upload me-1"></i> Subir PDF
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- 3. Lista de Archivos y Documentos Almacenados -->
        <div class="card card-custom p-4 border-0 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-folder2-open text-primary"></i> Mis Archivos Guardados
                </h5>
                <span class="badge bg-light text-secondary border">Total: <?= count($archivos) ?></span>
            </div>

            <?php if (empty($archivos)): ?>
                <div class="text-center py-4 text-muted small">
                    <i class="bi bi-cloud-arrow-up fs-2 d-block mb-2 text-secondary"></i>
                    Aún no has subido archivos o documentos a tu cuenta.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th>
                                <th>Nombre</th>
                                <th>Tamaño</th>
                                <th>Fecha</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archivos as $arc): ?>
                                <tr>
                                    <td>
                                        <?php if ($arc['tipo'] === 'foto'): ?>
                                            <span class="badge bg-info-subtle text-info border">
                                                <i class="bi bi-image me-1"></i> Foto
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border">
                                                <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark text-truncate" style="max-width: 140px;" title="<?= e($arc['nombre_original']) ?>">
                                            <?= e($arc['nombre_original']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?= number_format($arc['tamano'] / 1024, 1) ?> KB</span>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?= date('d/m/Y', strtotime($arc['subido_en'])) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="<?= APP_URL ?>/uploads/<?= e($arc['nombre_guardado']) ?>" target="_blank" class="btn btn-sm btn-outline-primary p-1 px-2" title="Ver Archivo">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <form action="<?= APP_URL ?>/perfil.php" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este archivo?');" class="d-inline">
                                                <?= csrf_campo() ?>
                                                <input type="hidden" name="accion" value="eliminar_archivo">
                                                <input type="hidden" name="archivo_id" value="<?= (int)$arc['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-2" title="Eliminar Archivo">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
