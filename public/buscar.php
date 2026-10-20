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

// Parámetros de búsqueda iniciales por URL
$materia_inicial = trim($_GET['materia'] ?? ($_GET['q'] ?? ''));
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
            <!-- Filtro: Materia o Tutor -->
            <div class="col-md-4 col-sm-6">
                <label class="form-label fw-medium small mb-1">Materia, Especialidad o Nombre</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" v-model="filtros.materia" placeholder="Ej. Cálculo, Programación, Juan..." @keyup.enter="realizarBusqueda">
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
                    <span v-if="tutor.total_resenas > 0" class="estrellas-calificacion">
                        <i class="bi bi-star-fill text-warning me-1"></i>
                        <span class="fw-bold text-dark">{{ tutor.calificacion.toFixed(1) }}</span>
                        <small class="text-muted ms-1">({{ tutor.total_resenas }} {{ tutor.total_resenas === 1 ? 'reseña' : 'reseñas' }})</small>
                    </span>
                    <span v-else class="badge bg-light text-secondary border fw-normal py-1 px-2">
                        <i class="bi bi-star me-1 text-warning"></i> Sin reseñas aún
                    </span>
                </div>

                <div class="mb-3 flex-grow-1">
                    <div class="small fw-semibold text-muted mb-1">Especialidades:</div>
                    <div class="small text-secondary">{{ tutor.materias }}</div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                    <div>
                        <small class="text-muted d-block">Tarifa por hora</small>
                        <span v-if="tutor.tarifa_min > 0">
                            <span class="fw-bold text-primary fs-5">${{ formatoMoneda(tutor.tarifa_min) }}</span>
                            <small class="text-muted">/h</small>
                        </span>
                        <span v-else class="badge bg-light text-secondary border fw-normal py-1 px-2">
                            A convenir
                        </span>
                    </div>
                    <a :href="tutor.perfil_url" class="btn btn-sm btn-primary-custom">
                        Ver Perfil
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
                const mat = (this.filtros.materia || "").trim();
                const ciu = (this.filtros.ciudad || "").trim();

                if (mat) params.append("materia", mat);
                if (this.filtros.nivel_id > 0) params.append("nivel_id", this.filtros.nivel_id);
                if (this.filtros.modalidad) params.append("modalidad", this.filtros.modalidad);
                if (ciu) params.append("ciudad", ciu);

                const res = await fetch("' . APP_URL . '/api/buscar_tutores.php?" + params.toString());
                if (!res.ok) {
                    throw new Error("Respuesta inválida del servidor: " + res.status);
                }
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
            document.cookie = "ultima_materia_buscada=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
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
