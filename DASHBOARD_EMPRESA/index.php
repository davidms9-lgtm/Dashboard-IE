<?php
/**
 * index.php - Pagina de inicio del Dashboard
 */

$page_title = 'Inicio';
$active_page = 'inicio';
$breadcrumb_title = 'Dashboard';
$breadcrumb_desc = 'Panel principal conectado a la base dbs13710048';
$breadcrumb_icon = 'fa-solid fa-house';

require_once __DIR__ . '/includes/header.php';
?>

    <div class="container" style="margin-top: 30px;">
        <div class="row g-4">
            <div class="col-12">
                <div class="welcome-card">
                    <h2><i class="fa-solid fa-chart-column me-2"></i>Dashboard operativo</h2>
                    <p>Modulos separados para Espana, LATAM y captacion comercial sobre la base de prueba de empresa.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: 30px; margin-bottom: 60px;">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                <a href="inscripciones.php" class="quick-link-card">
                    <i class="fa-solid fa-user-plus d-block"></i>
                    <h5>Inscripciones ES</h5>
                    <p class="text-muted mt-2 mb-0" style="font-size:13px;">
                        Seguimiento de inscripciones y cobros de Espana
                    </p>
                </a>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                <a href="inscripciones_latam.php" class="quick-link-card">
                    <i class="fa-solid fa-earth-americas d-block"></i>
                    <h5>Inscripciones LATAM</h5>
                    <p class="text-muted mt-2 mb-0" style="font-size:13px;">
                        Registro separado para operaciones LATAM
                    </p>
                </a>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                <a href="emagister.php" class="quick-link-card">
                    <i class="fa-solid fa-graduation-cap d-block"></i>
                    <h5>Emagister</h5>
                    <p class="text-muted mt-2 mb-0" style="font-size:13px;">
                        Leads de captacion con filtros por fecha y pais
                    </p>
                </a>
            </div>
        </div>
    </div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
