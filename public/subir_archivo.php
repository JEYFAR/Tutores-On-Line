<?php
/**
 * =============================================================================
 * TutoresOnLine - Gestión de Archivos y Documentos
 * Permite subir foto de perfil (JPG/PNG) y hoja de vida (PDF), listarlos y eliminarlos.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/archivos.php';

// Requiere sesión activa
requiere_login();

$usuario_id = (int)$_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$errores = [];

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        set_flash('danger', 'Error de validación de seguridad (CSRF). Intenta de nuevo.');
        header('Location: ' . APP_URL . '/subir_archivo.php');
        exit;
    }

    $accion = $_POST['accion'] ?? '';

    // ACCIÓN: Subir archivo
    if ($accion === 'subir') {
        $tipo = $_POST['tipo'] ?? 'foto';
        if (!isset($_FILES['archivo'])) {
            set_flash('danger', 'No se recibió ningún archivo.');
        } else {
            $resultado = guardar_archivo_subido($_FILES['archivo'], $usuario_id, $tipo);
            if ($resultado['exito']) {
                set_flash('success', $resultado['mensaje']);
            } else {
                set_flash('danger', $resultado['mensaje']);
            }
        }
        header('Location: ' . APP_URL . '/subir_archivo.php');
        exit;
    }

    // ACCIÓN: Eliminar archivo
    if ($accion === 'eliminar') {
        $archivo_id = (int)($_POST['archivo_id'] ?? 0);
        if ($archivo_id > 0 && eliminar_archivo_usuario($archivo_id, $usuario_id)) {
            set_flash('success', 'Archivo eliminado correctamente.');
        } else {
            set_flash('danger', 'No fue posible eliminar el archivo o no tienes permisos.');
        }
        header('Location: ' . APP_URL . '/subir_archivo.php');
        exit;
    }
}

// Obtener lista de archivos del usuario actual
$archivos = listar_archivos_usuario($usuario_id);

$titulo_pagina = 'Mis Archivos y Documentos';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-1">Mis Archivos y Documentos</h2>
                <p class="text-muted mb-0">Sube tu foto de perfil o tu hoja de vida en formato PDF.</p>
            </div>
            <div>
                <?php if ($rol === 'tutor'): ?>
                    <a href="<?= APP_URL ?>/panel_tutor.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Volver a Mi Panel
                    </a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/panel_estudiante.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Volver a Mi Panel
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Formulario de Subida -->
    <div class="col-lg-5">
        <div class="card card-custom p-4 border-0 shadow-sm">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-cloud-arrow-up text-primary"></i> Subir Archivo
            </h4>

            <form action="<?= APP_URL ?>/subir_archivo.php" method="POST" enctype="multipart/form-data">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="subir">

                <div class="mb-3">
                    <label for="tipo" class="form-label fw-medium">Tipo de Documento</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="foto">Foto de Perfil (JPG, PNG - Máx. 2 MB)</option>
                        <?php if ($rol === 'tutor'): ?>
                            <option value="hoja_vida" selected>Hoja de Vida (PDF - Máx. 5 MB)</option>
                        <?php else: ?>
                            <option value="hoja_vida">Documento de Soporte (PDF - Máx. 5 MB)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="archivo" class="form-label fw-medium">Seleccionar Archivo</label>
                    <input class="form-control" type="file" id="archivo" name="archivo" required>
                    <div class="form-text small">
                        El sistema valida la extensión y el contenido binario (MIME) antes de guardarlo.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary-custom w-100 py-2">
                    <i class="bi bi-upload me-1"></i> Subir Documento
                </button>
            </form>
        </div>
    </div>

    <!-- Lista de Archivos Subidos -->
    <div class="col-lg-7">
        <div class="card card-custom p-4 border-0 shadow-sm h-100">
            <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-folder2-open text-primary"></i> Archivos Registrados
            </h4>

            <?php if (empty($archivos)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-arrow-up fs-1 d-block mb-2"></i>
                    Aún no has subido ningún archivo a tu cuenta.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th>
                                <th>Nombre</th>
                                <th>Tamaño</th>
                                <th>Fecha</th>
                                <th class="text-end">Acciones</th>
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
                                        <div class="fw-medium text-dark text-truncate" style="max-width: 180px;" title="<?= e($arc['nombre_original']) ?>">
                                            <?= e($arc['nombre_original']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= number_format($arc['tamano'] / 1024, 1) ?> KB
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($arc['subido_en'])) ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <a href="<?= APP_URL ?>/uploads/<?= e($arc['nombre_guardado']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver archivo">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <form action="<?= APP_URL ?>/subir_archivo.php" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este archivo?');" class="d-inline">
                                                <?= csrf_campo() ?>
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="archivo_id" value="<?= (int)$arc['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
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
