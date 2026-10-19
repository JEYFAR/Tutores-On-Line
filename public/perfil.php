<?php
/**
 * =============================================================================
 * TutoresOnLine - Perfil Universal (Público y Privado)
 * - Sin parámetro ?id=: Vista privada de "Mi Perfil" con selector lateral de apartados.
 * - Con parámetro ?id=X: Vista pública del perfil (materias, tarifas, horarios, reseñas).
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/archivos.php';

$pdo = obtener_conexion();
$id_param = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modo_edicion = isset($_GET['editar']) && $_GET['editar'] == '1';

// Si no se pasó id por GET, o se pide editar, o id es el propio usuario logueado
$es_mi_perfil = false;

if ($id_param <= 0) {
    requiere_login();
    $perfil_id = (int)$_SESSION['usuario_id'];
    $es_mi_perfil = true;
} else {
    $perfil_id = $id_param;
    if (!empty($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === $perfil_id && $modo_edicion) {
        $es_mi_perfil = true;
    }
}

// =============================================================================
// PROCESAMIENTO DE ACCIONES (POST) - Solo permitido para el dueño de la cuenta
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requiere_login();
    $usuario_id = (int)$_SESSION['usuario_id'];

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
        header('Location: ' . APP_URL . '/perfil.php#tab-datos');
        exit;
    }

    // ACCIÓN 2: Cambiar Contraseña
    if ($accion === 'cambiar_password') {
        $password_actual = $_POST['password_actual'] ?? '';
        $password_nuevo  = $_POST['password_nuevo'] ?? '';
        $password_conf   = $_POST['password_conf'] ?? '';

        $stmt_u = $pdo->prepare("SELECT `password_hash` FROM `usuarios` WHERE `id` = ? LIMIT 1");
        $stmt_u->execute([$usuario_id]);
        $row_u = $stmt_u->fetch();
        $hash_actual = $row_u['password_hash'] ?? null;

        $es_valido = true;

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
        header('Location: ' . APP_URL . '/perfil.php#tab-seguridad');
        exit;
    }

    // ACCIÓN 3: Subir Archivo (Foto o Hoja de Vida/Documento)
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
        header('Location: ' . APP_URL . '/perfil.php#tab-archivos');
        exit;
    }

    // ACCIÓN 4: Eliminar Archivo
    if ($accion === 'eliminar_archivo') {
        $archivo_id = (int)($_POST['archivo_id'] ?? 0);
        if ($archivo_id > 0) {
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
        header('Location: ' . APP_URL . '/perfil.php#tab-archivos');
        exit;
    }
}

// =============================================================================
// CONSULTA DE DATOS DEL PERFIL A MOSTRAR
// =============================================================================
try {
    $stmt_p = $pdo->prepare("SELECT `id`, `nombre`, `correo`, `rol`, `ciudad`, `foto`, `correo_verificado`, `creado_en` FROM `usuarios` WHERE `id` = ? LIMIT 1");
    $stmt_p->execute([$perfil_id]);
    $perfil_usuario = $stmt_p->fetch();

    if (!$perfil_usuario) {
        set_flash('danger', 'El perfil de usuario solicitado no existe.');
        header('Location: ' . APP_URL . '/buscar.php');
        exit;
    }
} catch (Exception $e) {
    error_log("[Perfil Query Error]: " . $e->getMessage());
    set_flash('danger', 'Error al cargar la información del perfil.');
    header('Location: ' . APP_URL . '/buscar.php');
    exit;
}

// Determinar URL de avatar
$avatar_url = null;
if (!empty($perfil_usuario['foto'])) {
    if (strpos($perfil_usuario['foto'], 'http://') === 0 || strpos($perfil_usuario['foto'], 'https://') === 0) {
        $avatar_url = $perfil_usuario['foto'];
    } elseif (file_exists(UPLOADS_PATH . DIRECTORY_SEPARATOR . $perfil_usuario['foto'])) {
        $avatar_url = APP_URL . '/uploads/' . $perfil_usuario['foto'];
    }
}

$es_dueno = (!empty($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === (int)$perfil_usuario['id']);

// =============================================================================
// RENDERIZADO SEGÚN MODO
// =============================================================================

// CASO A: MODO PRIVADO (MI PERFIL - Separado por apartados con selector lateral)
if ($es_mi_perfil && $es_dueno):
    $archivos = listar_archivos_usuario($perfil_id);
    $titulo_pagina = 'Mi Perfil';
    require_once dirname(__DIR__) . '/includes/header.php';
?>
    <!-- CABECERA PRINCIPAL DE MI PERFIL -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-custom p-4 border-0 shadow-sm">
                <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                    <div class="position-relative">
                        <?php if ($avatar_url): ?>
                            <img src="<?= e($avatar_url) ?>" alt="<?= e($perfil_usuario['nombre']) ?>" class="icono-circulo shadow-sm border border-3 border-primary-subtle" style="width: 90px; height: 90px; object-fit: cover;" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <div class="bg-primary-subtle text-primary icono-circulo shadow-sm" style="width: 90px; height: 90px; font-size: 2rem; font-weight: 700;">
                                <?= strtoupper(substr($perfil_usuario['nombre'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex-grow-1 text-center text-md-start">
                        <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
                            <h3 class="fw-bold mb-0 text-dark"><?= e($perfil_usuario['nombre']) ?></h3>
                            <?php if ($perfil_usuario['rol'] === 'tutor'): ?>
                                <span class="badge bg-success badge-rol">Tutor</span>
                            <?php else: ?>
                                <span class="badge bg-primary badge-rol">Estudiante</span>
                            <?php endif; ?>
                            <?php if ((int)$perfil_usuario['correo_verificado'] === 1): ?>
                                <span class="badge bg-success-subtle text-success small" title="Correo verificado">
                                    <i class="bi bi-patch-check-fill me-1"></i> Verificado
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted mb-0">
                            <i class="bi bi-envelope me-1"></i> <?= e($perfil_usuario['correo']) ?>
                            <?php if (!empty($perfil_usuario['ciudad'])): ?>
                                &bull; <i class="bi bi-geo-alt me-1"></i> <?= e($perfil_usuario['ciudad']) ?>
                            <?php endif; ?>
                            &bull; <i class="bi bi-clock-history me-1"></i> Miembro desde <?= date('M Y', strtotime($perfil_usuario['creado_en'])) ?>
                        </p>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="<?= APP_URL ?>/panel.php" class="btn btn-primary-custom btn-sm d-flex align-items-center justify-content-center gap-1 shadow-sm">
                            <i class="bi bi-speedometer2"></i> Mi Panel
                        </a>
                        <?php if ($perfil_usuario['rol'] === 'tutor'): ?>
                            <a href="<?= APP_URL ?>/perfil.php?id=<?= $perfil_id ?>" class="btn btn-outline-success btn-sm d-flex align-items-center justify-content-center gap-1">
                                <i class="bi bi-eye"></i> Ver Perfil Público
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DISTRIBUCIÓN POR APARTADOS: SELECTOR A LA IZQUIERDA Y CONTENIDO A LA DERECHA -->
    <div class="row g-4">
        <!-- COLUMNA IZQUIERDA: SELECTOR DE APARTADOS -->
        <div class="col-lg-3 col-md-4">
            <div class="card card-custom p-3 border-0 shadow-sm sticky-top" style="top: 85px;">
                <div class="small fw-bold text-uppercase text-muted px-3 pt-2 pb-2">Configuración</div>
                <div class="nav flex-column nav-pills perfil-menu-nav gap-1" id="perfilTabs" role="tablist" aria-orientation="vertical">
                    <!-- Apartado 1: Datos Personales -->
                    <button class="nav-link text-start active d-flex align-items-center gap-3 p-3" id="tab-btn-datos" data-bs-toggle="pill" data-bs-target="#tab-datos" type="button" role="tab" aria-controls="tab-datos" aria-selected="true">
                        <i class="bi bi-person-gear fs-4 text-primary"></i>
                        <div class="lh-sm">
                            <strong class="d-block text-dark">Información Personal</strong>
                            <small class="text-muted" style="font-size: 0.78rem;">Nombre y ciudad</small>
                        </div>
                    </button>

                    <!-- Apartado 2: Seguridad y Contraseña -->
                    <button class="nav-link text-start d-flex align-items-center gap-3 p-3" id="tab-btn-seguridad" data-bs-toggle="pill" data-bs-target="#tab-seguridad" type="button" role="tab" aria-controls="tab-seguridad" aria-selected="false">
                        <i class="bi bi-shield-lock fs-4 text-primary"></i>
                        <div class="lh-sm">
                            <strong class="d-block text-dark">Seguridad de Cuenta</strong>
                            <small class="text-muted" style="font-size: 0.78rem;">Contraseña de acceso</small>
                        </div>
                    </button>

                    <!-- Apartado 3: Foto y Documentos -->
                    <button class="nav-link text-start d-flex align-items-center gap-3 p-3" id="tab-btn-archivos" data-bs-toggle="pill" data-bs-target="#tab-archivos" type="button" role="tab" aria-controls="tab-archivos" aria-selected="false">
                        <i class="bi bi-cloud-arrow-up fs-4 text-primary"></i>
                        <div class="lh-sm">
                            <strong class="d-block text-dark">Foto y Documentos</strong>
                            <small class="text-muted" style="font-size: 0.78rem;">Avatar y certificados</small>
                        </div>
                    </button>

                    <?php if ($perfil_usuario['rol'] === 'tutor'): ?>
                        <hr class="my-2">
                        <a href="<?= APP_URL ?>/perfil.php?id=<?= $perfil_id ?>" class="nav-link text-start d-flex align-items-center gap-3 p-3 text-success">
                            <i class="bi bi-eye fs-4"></i>
                            <div class="lh-sm">
                                <strong class="d-block text-success">Ver Perfil Público</strong>
                                <small class="text-muted" style="font-size: 0.78rem;">Vista de los estudiantes</small>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: CONTENIDO DEL APARTADO SELECCIONADO -->
        <div class="col-lg-9 col-md-8">
            <div class="tab-content" id="perfilTabsContent">

                <!-- ========================================================= -->
                <!-- APARTADO 1: INFORMACIÓN PERSONAL                          -->
                <!-- ========================================================= -->
                <div class="tab-pane fade show active" id="tab-datos" role="tabpanel" aria-labelledby="tab-btn-datos" tabindex="0">
                    <div class="card card-custom p-4 border-0 shadow-sm">
                        <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                            <i class="bi bi-person-gear fs-4 text-primary"></i>
                            <h4 class="fw-bold text-dark mb-0">Información Personal</h4>
                        </div>
                        <p class="text-muted small mb-4">Actualiza tu nombre completo y la ciudad en la que te encuentras.</p>

                        <form action="<?= APP_URL ?>/perfil.php" method="POST" autocomplete="off">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="actualizar_datos">

                            <div class="mb-3">
                                <label for="nombre" class="form-label fw-medium small">Nombre Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?= e($perfil_usuario['nombre']) ?>" required minlength="3">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="correo" class="form-label fw-medium small">Correo Electrónico (Asociado a tu cuenta)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="correo" value="<?= e($perfil_usuario['correo']) ?>" disabled readonly>
                                </div>
                                <small class="text-muted">El correo electrónico no puede ser modificado por motivos de seguridad.</small>
                            </div>

                            <div class="mb-4">
                                <label for="ciudad" class="form-label fw-medium small">Ciudad de Residencia</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" class="form-control" id="ciudad" name="ciudad" value="<?= e($perfil_usuario['ciudad'] ?? '') ?>" required placeholder="Ej. Bogotá, Medellín, Cali">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary-custom px-4">
                                    <i class="bi bi-check2-circle me-1"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- APARTADO 2: SEGURIDAD Y CONTRASEÑA                        -->
                <!-- ========================================================= -->
                <div class="tab-pane fade" id="tab-seguridad" role="tabpanel" aria-labelledby="tab-btn-seguridad" tabindex="0">
                    <div class="card card-custom p-4 border-0 shadow-sm">
                        <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                            <i class="bi bi-shield-lock fs-4 text-primary"></i>
                            <h4 class="fw-bold text-dark mb-0">Seguridad de la Cuenta</h4>
                        </div>
                        <p class="text-muted small mb-4">Administra tu clave de acceso para mantener protegida tu cuenta.</p>

                        <?php
                        $stmt_hash = $pdo->prepare("SELECT `password_hash` FROM `usuarios` WHERE `id` = ? LIMIT 1");
                        $stmt_hash->execute([$perfil_id]);
                        $tiene_password = !empty($stmt_hash->fetchColumn());
                        ?>

                        <?php if (!$tiene_password): ?>
                            <div class="alert alert-info py-3 d-flex align-items-center gap-3 mb-4">
                                <i class="bi bi-google fs-3 text-danger"></i>
                                <div>
                                    <strong>Autenticado con Google:</strong> Tu cuenta ingresa mediante Google OAuth2. Si lo deseas, puedes establecer una contraseña local a continuación para también iniciar sesión con correo y clave.
                                </div>
                            </div>
                        <?php endif; ?>

                        <form action="<?= APP_URL ?>/perfil.php" method="POST" autocomplete="off">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="cambiar_password">

                            <?php if ($tiene_password): ?>
                                <div class="mb-3">
                                    <label for="password_actual" class="form-label fw-medium small">Contraseña Actual</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="password_actual" name="password_actual" required placeholder="Tu contraseña actual">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="password_nuevo" class="form-label fw-medium small">Nueva Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                                        <input type="password" class="form-control" id="password_nuevo" name="password_nuevo" required minlength="6" placeholder="Mínimo 6 caracteres">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="password_conf" class="form-label fw-medium small">Confirmar Nueva Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-check"></i></span>
                                        <input type="password" class="form-control" id="password_conf" name="password_conf" required minlength="6" placeholder="Repite la nueva contraseña">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-outline-primary px-4">
                                    <i class="bi bi-key me-1"></i> <?= $tiene_password ? 'Actualizar Contraseña' : 'Crear Contraseña' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- APARTADO 3: FOTO DE PERFIL Y ARCHIVOS / DOCUMENTOS        -->
                <!-- ========================================================= -->
                <div class="tab-pane fade" id="tab-archivos" role="tabpanel" aria-labelledby="tab-btn-archivos" tabindex="0">
                    <!-- 1. Subida de Foto de Perfil -->
                    <div class="card card-custom p-4 border-0 shadow-sm mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                            <i class="bi bi-image fs-4 text-primary"></i>
                            <h4 class="fw-bold text-dark mb-0">Foto de Perfil (Avatar)</h4>
                        </div>
                        <p class="text-muted small mb-3">Sube tu fotografía personal en formato JPG o PNG (máximo 2 MB).</p>

                        <form action="<?= APP_URL ?>/perfil.php" method="POST" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row gap-2">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="subir_archivo">
                            <input type="hidden" name="tipo" value="foto">

                            <input type="file" name="archivo" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                            <button type="submit" class="btn btn-primary-custom flex-shrink-0 px-4">
                                <i class="bi bi-upload me-1"></i> Subir Foto
                            </button>
                        </form>
                    </div>

                    <!-- 2. Subida de Hoja de Vida / Certificados (Para Tutores) -->
                    <?php if ($perfil_usuario['rol'] === 'tutor'): ?>
                        <div class="card card-custom p-4 border-0 shadow-sm mb-4">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <i class="bi bi-file-earmark-pdf fs-4 text-danger"></i>
                                <h4 class="fw-bold text-dark mb-0">Hoja de Vida / Certificados</h4>
                            </div>
                            <p class="text-muted small mb-3">Sube tu currículum académico o títulos en formato PDF (máximo 5 MB) para que los estudiantes puedan consultarlos.</p>

                            <form action="<?= APP_URL ?>/perfil.php" method="POST" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row gap-2">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="subir_archivo">
                                <input type="hidden" name="tipo" value="hoja_vida">

                                <input type="file" name="archivo" class="form-control" accept=".pdf,application/pdf" required>
                                <button type="submit" class="btn btn-outline-danger flex-shrink-0 px-4">
                                    <i class="bi bi-upload me-1"></i> Subir PDF
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- 3. Lista de Archivos Guardados -->
                    <div class="card card-custom p-4 border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-folder2-open fs-4 text-primary"></i>
                                <h5 class="fw-bold text-dark mb-0">Mis Archivos Guardados</h5>
                            </div>
                            <span class="badge bg-light text-secondary border">Total: <?= count($archivos) ?></span>
                        </div>

                        <?php if (empty($archivos)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-cloud-arrow-up fs-1 d-block mb-2 text-secondary"></i>
                                Aún no has subido archivos a tu cuenta.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Nombre del Archivo</th>
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
                                                        <span class="badge bg-info-subtle text-info border"><i class="bi bi-image me-1"></i> Foto</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger-subtle text-danger border"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="fw-medium text-dark text-truncate" style="max-width: 220px;" title="<?= e($arc['nombre_original']) ?>">
                                                        <?= e($arc['nombre_original']) ?>
                                                    </div>
                                                </td>
                                                <td><span class="text-muted small"><?= number_format($arc['tamano'] / 1024, 1) ?> KB</span></td>
                                                <td><span class="text-muted small"><?= date('d/m/Y', strtotime($arc['subido_en'])) ?></span></td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-2">
                                                        <a href="<?= APP_URL ?>/uploads/<?= e($arc['nombre_guardado']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver Archivo">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <form action="<?= APP_URL ?>/perfil.php" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este archivo?');" class="d-inline">
                                                            <?= csrf_campo() ?>
                                                            <input type="hidden" name="accion" value="eliminar_archivo">
                                                            <input type="hidden" name="archivo_id" value="<?= (int)$arc['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar Archivo">
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
        </div>
    </div>

    <!-- Script para persistir pestaña activa según hash en URL -->
    <?php
    $scripts_extra = '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var hash = window.location.hash;
        if (hash) {
            var triggerBtn = document.querySelector(\'button[data-bs-target="\' + hash + \'"]\');
            if (triggerBtn) {
                var tab = new bootstrap.Tab(triggerBtn);
                tab.show();
            }
        }

        // Actualizar URL al hacer click en cada pestaña
        var tabBtns = document.querySelectorAll(\'#perfilTabs button[data-bs-toggle="pill"]\');
        tabBtns.forEach(function(btn) {
            btn.addEventListener("shown.bs.tab", function(event) {
                var targetId = event.target.getAttribute("data-bs-target");
                if (targetId) {
                    history.replaceState(null, null, targetId);
                }
            });
        });
    });
    </script>
    ';
    ?>

<?php
// CASO B: MODO PÚBLICO (PERFIL PÚBLICO DE TUTOR O ESTUDIANTE)
else:
    $materias = [];
    $disponibilidad = [];
    $calificaciones = [];
    $promedio = 5.0;
    $total_resenas = 0;
    $hoja_vida = null;

    if ($perfil_usuario['rol'] === 'tutor') {
        $stmt_mat = $pdo->prepare(
            "SELECT tm.*, m.nombre AS materia_nombre, n.nombre AS nivel_nombre
             FROM `tutor_materia` tm
             INNER JOIN `materias` m ON m.id = tm.materia_id
             INNER JOIN `niveles` n ON n.id = tm.nivel_id
             WHERE tm.tutor_id = ?
             ORDER BY m.nombre ASC"
        );
        $stmt_mat->execute([$perfil_id]);
        $materias = $stmt_mat->fetchAll();

        $stmt_disp = $pdo->prepare(
            "SELECT * FROM `disponibilidad`
             WHERE `tutor_id` = ?
             ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'), hora_inicio ASC"
        );
        $stmt_disp->execute([$perfil_id]);
        $disponibilidad = $stmt_disp->fetchAll();

        $stmt_calif = $pdo->prepare(
            "SELECT c.*, u.nombre AS estudiante_nombre
             FROM `calificaciones` c
             INNER JOIN `usuarios` u ON u.id = c.estudiante_id
             WHERE c.tutor_id = ?
             ORDER BY c.creado_en DESC"
        );
        $stmt_calif->execute([$perfil_id]);
        $calificaciones = $stmt_calif->fetchAll();

        $stmt_prom = $pdo->prepare("SELECT COALESCE(ROUND(AVG(puntaje), 1), 5.0) AS promedio, COUNT(*) AS total FROM `calificaciones` WHERE `tutor_id` = ?");
        $stmt_prom->execute([$perfil_id]);
        $rating_info = $stmt_prom->fetch();
        $promedio = (float)$rating_info['promedio'];
        $total_resenas = (int)$rating_info['total'];

        $stmt_cv = $pdo->prepare("SELECT `nombre_guardado`, `nombre_original` FROM `archivos` WHERE `usuario_id` = ? AND `tipo` = 'hoja_vida' ORDER BY `subido_en` DESC LIMIT 1");
        $stmt_cv->execute([$perfil_id]);
        $hoja_vida = $stmt_cv->fetch();
    }

    $titulo_pagina = 'Perfil de ' . $perfil_usuario['nombre'];
    require_once dirname(__DIR__) . '/includes/header.php';
?>

    <div class="row g-4">
        <!-- Columna Izquierda: Tarjeta del Perfil -->
        <div class="col-lg-4">
            <div class="card card-custom p-4 border-0 shadow-sm text-center mb-4 sticky-top" style="top: 85px;">
                <div class="mb-3">
                    <?php if ($avatar_url): ?>
                        <img src="<?= e($avatar_url) ?>" alt="<?= e($perfil_usuario['nombre']) ?>" class="icono-circulo shadow-sm mx-auto" style="width: 110px; height: 110px; object-fit: cover;" referrerpolicy="no-referrer">
                    <?php else: ?>
                        <div class="bg-primary text-white icono-circulo fw-bold fs-1 mx-auto shadow-sm" style="width: 110px; height: 110px;">
                            <?= strtoupper(substr($perfil_usuario['nombre'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h3 class="fw-bold mb-1"><?= e($perfil_usuario['nombre']) ?></h3>
                <p class="text-muted small mb-2">
                    <i class="bi bi-geo-alt me-1"></i><?= e($perfil_usuario['ciudad'] ?: 'Colombia') ?>
                    &bull; <span class="badge <?= $perfil_usuario['rol'] === 'tutor' ? 'bg-success' : 'bg-primary' ?> badge-rol"><?= ucfirst(e($perfil_usuario['rol'])) ?></span>
                </p>

                <?php if ($perfil_usuario['rol'] === 'tutor'): ?>
                    <div class="mb-3">
                        <span class="estrellas-calificacion">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi <?= $i <= round($promedio) ? 'bi-star-fill text-warning' : 'bi-star text-secondary' ?>"></i>
                            <?php endfor; ?>
                            <span class="fw-bold text-dark ms-1"><?= number_format($promedio, 1) ?></span>
                        </span>
                        <div class="small text-muted">(<?= $total_resenas ?> reseñas)</div>
                    </div>

                    <?php if ($hoja_vida): ?>
                        <div class="mb-3">
                            <a href="<?= APP_URL ?>/uploads/<?= e($hoja_vida['nombre_guardado']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-file-earmark-pdf me-1"></i> Ver Hoja de Vida
                            </a>
                        </div>
                    <?php endif; ?>

                    <hr class="my-3">

                    <div class="d-grid gap-2">
                        <?php if ($es_dueno): ?>
                            <a href="<?= APP_URL ?>/perfil.php" class="btn btn-primary-custom py-2">
                                <i class="bi bi-pencil-square me-1"></i> Configurar Mi Perfil
                            </a>
                        <?php else: ?>
                            <a href="<?= APP_URL ?>/reservar.php?tutor_id=<?= (int)$perfil_usuario['id'] ?>" class="btn btn-primary-custom py-2">
                                <i class="bi bi-calendar-plus me-1"></i> Reservar Tutoría
                            </a>
                            <a href="<?= APP_URL ?>/chat.php?con=<?= (int)$perfil_usuario['id'] ?>" class="btn btn-outline-primary py-2">
                                <i class="bi bi-chat-dots me-1"></i> Enviar Mensaje
                            </a>
                        <?php endif; ?>
                        <a href="<?= APP_URL ?>/buscar.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Explorar Tutores
                        </a>
                    </div>
                <?php else: ?>
                    <hr class="my-3">
                    <?php if ($es_dueno): ?>
                        <div class="d-grid gap-2">
                            <a href="<?= APP_URL ?>/perfil.php" class="btn btn-primary-custom py-2">
                                <i class="bi bi-pencil-square me-1"></i> Configurar Mi Perfil
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Columna Derecha: Materias, Horarios y Reseñas (Para Tutores) -->
        <div class="col-lg-8">
            <?php if ($perfil_usuario['rol'] === 'tutor'): ?>
                <!-- 1. Materias y Tarifas -->
                <div class="card card-custom p-4 border-0 shadow-sm mb-4">
                    <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-book text-primary"></i> Materias Ofertadas y Tarifas
                    </h4>

                    <?php if (empty($materias)): ?>
                        <p class="text-muted">Este tutor no tiene materias asignadas actualmente.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Materia</th>
                                        <th>Nivel Académico</th>
                                        <th class="text-end">Tarifa por Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materias as $mat): ?>
                                        <tr>
                                            <td class="fw-semibold text-dark"><?= e($mat['materia_nombre']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= e($mat['nivel_nombre']) ?></span></td>
                                            <td class="text-end fw-bold text-primary">$<?= number_format($mat['tarifa_hora'], 0, ',', '.') ?> COP</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 2. Horarios de Disponibilidad Semanal -->
                <div class="card card-custom p-4 border-0 shadow-sm mb-4">
                    <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-calendar-week text-primary"></i> Horarios de Disponibilidad
                    </h4>

                    <?php if (empty($disponibilidad)): ?>
                        <p class="text-muted">El tutor no ha publicado horarios fijos de atención semanal.</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($disponibilidad as $disp): ?>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-capitalize text-dark"><?= e($disp['dia_semana']) ?></div>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i><?= substr($disp['hora_inicio'], 0, 5) ?> - <?= substr($disp['hora_fin'], 0, 5) ?>
                                            </small>
                                        </div>
                                        <span class="badge <?= $disp['modalidad'] === 'virtual' ? 'bg-info-subtle text-info' : 'bg-success-subtle text-success' ?> border">
                                            <?= ucfirst(e($disp['modalidad'])) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3. Calificaciones y Opiniones -->
                <div class="card card-custom p-4 border-0 shadow-sm">
                    <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-chat-quote text-primary"></i> Opiniones de Estudiantes
                    </h4>

                    <?php if (empty($calificaciones)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-chat-square-dots fs-2 d-block mb-2 text-secondary"></i>
                            Aún no hay opiniones registradas para este profesor.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($calificaciones as $cal): ?>
                                <div class="p-3 border rounded-3 bg-white">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-semibold text-dark"><?= e($cal['estudiante_nombre']) ?></span>
                                        <span class="estrellas-calificacion">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <i class="bi <?= $s <= (int)$cal['puntaje'] ? 'bi-star-fill text-warning' : 'bi-star text-secondary' ?> small"></i>
                                            <?php endfor; ?>
                                        </span>
                                    </div>
                                    <p class="text-secondary small mb-1"><?= nl2br(e($cal['comentario'] ?: 'Sin comentario adicional.')) ?></p>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($cal['creado_en'])) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Perfil Básico de Estudiante -->
                <div class="card card-custom p-4 border-0 shadow-sm">
                    <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-mortarboard text-primary"></i> Perfil de Estudiante
                    </h4>
                    <p class="text-muted">Estudiante registrado en la plataforma TutoresOnLine.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
