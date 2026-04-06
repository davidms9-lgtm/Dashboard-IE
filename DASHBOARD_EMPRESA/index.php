<?php
/**
 * index.php — Página de inicio del Dashboard
 * Dashboard de Gestión Interna
 */

// ── Configuración del header ──
$page_title       = 'Inicio';
$active_page      = 'inicio';
$breadcrumb_title = 'Dashboard';
$breadcrumb_desc  = 'Panel principal';
$breadcrumb_icon  = 'fa-solid fa-house';

require_once __DIR__ . '/includes/header.php';
?>

    <!-- ═══════════ BIENVENIDA ═══════════ -->
    <div class="container" style="margin-top: 30px;">
        <div class="row g-4">
            <div class="col-12">
                <div class="welcome-card">
                    <h2><i class="fa-solid fa-hand-wave me-2"></i>Bienvenido al Dashboard</h2>
                    <p>Panel de gestión interna — selecciona una sección del menú para comenzar.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════ ACCESOS RÁPIDOS ═══════════ -->
    <div class="container" style="margin-top: 30px; margin-bottom: 60px;">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                <a href="inscripciones.php" class="quick-link-card">
                    <i class="fa-solid fa-user-plus d-block"></i>
                    <h5>Inscripciones</h5>
                    <p class="text-muted mt-2 mb-0" style="font-size:13px;">
                        Gestión y control de inscripciones a cursos
                    </p>
                </a>
            </div>
            <!-- Placeholder para futuras secciones -->
            <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                <div class="quick-link-card" style="opacity:.5; cursor:default;">
                    <i class="fa-solid fa-file-invoice-dollar d-block"></i>
                    <h5>Finanzas</h5>
                    <p class="text-muted mt-2 mb-0" style="font-size:13px;">
                        Próximamente
                    </p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                <div class="quick-link-card" style="opacity:.5; cursor:default;">
                    <i class="fa-solid fa-chart-line d-block"></i>
                    <h5>Actividad</h5>
                    <p class="text-muted mt-2 mb-0" style="font-size:13px;">
                        Próximamente
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php
// ── Footer (sin scripts extra para esta página) ──
require_once __DIR__ . '/includes/footer.php';
?>
