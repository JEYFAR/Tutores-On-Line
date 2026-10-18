<?php
/**
 * =============================================================================
 * TutoresOnLine - Búsqueda de Tutores
 * Filtros dinámicos con Vue 3 (CDN), llamada fetch a api/buscar_tutores.php
 * y persistencia de preferencias del usuario mediante cookies ("ultima_materia_buscada").
 * Compatible con PHP 7.4+
 * =============================================================================
 */

require_once dirname(__DIR__) . '/includes/auth.php';

// Manejo de la cookie de preferencia de búsqueda
$materia_get = trim($_GET['materia'] ?? '');
$materia_inicial = '';

if (!empty($materia_get)) {
    $materia_inicial = $materia_get;
    // Guardar preferencia en cookie por 30 días
    $es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('ultima_materia_buscada', $materia_get, time() + (30 * 86400), '/', '', $es_https, true);
} elseif (!empty($_COOKIE['ultima_materia_buscada'])) {
    // Prellenar con la última materia buscada si no se pasó por URL
    $materia_inicial = $_COOKIE['ultima_materia_buscada'];
}

$ciudad_inicial = trim($_GET['ciudad'] ?? '');
$modalidad_inicial = trim($_GET['modalidad'] ?? '');
$nivel_inicial = (int)($_GET['nivel_id'] ?? 0);

// Cargar catálogo de niveles desde la base de datos
$niveles = [];
try {
    $pdo = obtener_conexion();
    $niveles = $pdo->query("SELECT * FROM `niveles` ORDER BY `id` ASC")->fetchAll();
} catch (Exception $e) {
    error_log("[Buscar Niveles Error]: " . $e->getMessage());
}

$titulo_pagina = 'Buscar Tutores';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div id="app-busqueda">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold mb-1">Encuentra a tu Tutor</h2>
            <p class="text-muted">Filtra por materia, nivel académico, ciudad o modalidad de clase.</p>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="card card-custom p-4 border-0 shadow-sm mb-4">
        <form @submit.prevent="realizarBusqueda" class="row g-3 align-items-end">
            <!-- Filtro: Materia -->
            <div class="col-md-4 col-sm-6">
                <label class="form-label fw-medium small mb-1">Materia o Especialidad</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" v-model="filtros.materia" placeholder="Ej. Cálculo, Programación..." @keyup.enter="realizarBusqueda">
                </div>
            </div>

            <!-- Filtro: Nivel Académico -->
            <div class="col-md-3 col-sm-6">
                <label class="form-label fw-medium small mb-1">Nivel Educativo</label>
                <select class="form-select" v-model="filtros.nivel_id" @change="realizarBusqueda">
                    <option :value="0">Todos los niveles</option>
                    <?php foreach ($niveles as $niv): ?>
                        <option :value="<?= (int)$niv['id'] ?>"><?= e($niv['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtro: Modalidad -->
            <div class="col-md-2 col-sm-6">
                <label class="form-label fw-medium small mb-1">Modalidad</label>
                <select class="form-select" v-model="filtros.modalidad" @change="realizarBusqueda">
                    <option value="">Todas</option>
                    <option value="virtual">Virtual</option>
                    <option value="presencial">Presencial</option>
                </select>
            </div>

            <!-- Filtro: Ciudad -->
            <div class="col-md-3 col-sm-6">
                <label class="form-label fw-medium small mb-1">Ciudad</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-geo-alt"></i></span>
                    <input type="text" class="form-control" v-model="filtros.ciudad" placeholder="Ej. Bogotá, Cali..." @keyup.enter="realizarBusqueda">
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="col-12 text-end">
                <button type="button" @click="limpiarFiltros" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar
                </button>
                <button type="button" @click="realizarBusqueda" class="btn btn-primary-custom">
                    <i class="bi bi-funnel me-1"></i> Aplicar Filtros
                </button>
            </div>
        </form>
    </div>

    <!-- Indicador de Carga -->
    <div v-if="cargando" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando tutores...</span>
        </div>
        <p class="text-muted mt-2">Buscando profesores disponibles...</p>
    </div>

    <!-- Sin Resultados -->
    <div v-else-if="tutores.length === 0" class="card card-custom p-5 text-center border-0 shadow-sm">
        <i class="bi bi-search text-muted fs-1 mb-3"></i>
        <h4 class="fw-bold mb-2">No se encontraron tutores</h4>
        <p class="text-muted mb-4">No hay profesores que coincidan con los criterios seleccionados. Prueba cambiando la materia o la modalidad.</p>
        <div>
            <button @click="limpiarFiltros" class="btn btn-primary-custom btn-sm">
                Restablecer Búsqueda
            </button>
        </div>
    </div>

    <!-- Cuadrícula de Tutores Encontrados -->
    <div v-else class="row g-4">
        <div class="col-12 text-muted small mb-1">
            Se encontraron <strong>{{ tutores.length }}</strong> tutor(es) disponible(s):
        </div>

        <div v-for="tutor in tutores" :key="tutor.id" class="col-md-6 col-lg-4">
            <div class="card card-custom p-4 h-100 d-flex flex-column border-0 shadow-sm">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div v-if="tutor.foto">
                        <img :src="tutor.foto" :alt="tutor.nombre" class="rounded-circle object-fit-cover shadow-sm" style="width: 58px; height: 58px;">
                    </div>
                    <div v-else class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-4 shadow-sm" style="width: 58px; height: 58px;">
                        {{ tutor.nombre.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">{{ tutor.nombre }}</h5>
                        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>{{ tutor.ciudad }}</small>
                    </div>
                </div>

                <div class="mb-2">
                    <span class="estrellas-calificacion">
                        <i class="bi bi-star-fill text-warning me-1"></i>
                        <span class="fw-bold text-dark">{{ tutor.calificacion.toFixed(1) }}</span>
                    </span>
                    <small class="text-muted ms-1">({{ tutor.total_resenas }} calificaciones)</small>
                </div>

                <div class="mb-3 flex-grow-1">
                    <div class="small fw-semibold text-muted mb-1">Materias:</div>
                    <div class="small text-secondary">{{ tutor.materias }}</div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                    <div>
                        <small class="text-muted d-block">Tarifa desde</small>
                        <span class="fw-bold text-primary fs-5">${{ formatoMoneda(tutor.tarifa_min) }}</span>
                        <small class="text-muted">/h</small>
                    </div>
                    <a :href="tutor.perfil_url" class="btn btn-sm btn-primary-custom">
                        Ver Perfil y Reservar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Carga de Vue 3 por CDN (sin build tools) para reactividad simple y limpia
$scripts_extra = '
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script>
const { createApp } = Vue;

createApp({
    data() {
        return {
            cargando: false,
            tutores: [],
            filtros: {
                materia: ' . json_encode($materia_inicial) . ',
                nivel_id: ' . (int)$nivel_inicial . ',
                modalidad: ' . json_encode($modalidad_inicial) . ',
                ciudad: ' . json_encode($ciudad_inicial) . '
            }
        };
    },
    methods: {
        async realizarBusqueda() {
            this.cargando = true;
            try {
                const params = new URLSearchParams();
                if (this.filtros.materia) params.append("materia", this.filtros.materia);
                if (this.filtros.nivel_id > 0) params.append("nivel_id", this.filtros.nivel_id);
                if (this.filtros.modalidad) params.append("modalidad", this.filtros.modalidad);
                if (this.filtros.ciudad) params.append("ciudad", this.filtros.ciudad);

                const res = await fetch("' . APP_URL . '/api/buscar_tutores.php?" + params.toString());
                const data = await res.json();
                if (data.exito) {
                    this.tutores = data.tutores;
                } else {
                    this.tutores = [];
                }
            } catch (err) {
                console.error("Error buscando tutores:", err);
                this.tutores = [];
            } finally {
                this.cargando = false;
            }
        },
        limpiarFiltros() {
            this.filtros = {
                materia: "",
                nivel_id: 0,
                modalidad: "",
                ciudad: ""
            };
            this.realizarBusqueda();
        },
        formatoMoneda(val) {
            return new Intl.NumberFormat("es-CO").format(val);
        }
    },
    mounted() {
        this.realizarBusqueda();
    }
}).mount("#app-busqueda");
</script>
';

require_once dirname(__DIR__) . '/includes/footer.php';
?>
