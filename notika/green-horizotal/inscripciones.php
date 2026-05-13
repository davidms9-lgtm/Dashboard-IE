<?php
/**
 * inscripciones.php — Módulo de Gestión de Inscripciones
 * Dashboard de Gestión Interna (España + Latam)
 *
 * KPIs: Total inscripciones, Pendientes de pago, Novedades (no leídas)
 * Tabla dinámica con DataTables, gráfico Chart.js, filtros y alertas
 */

require_once __DIR__ . '/config/conexion.php';

// ──────────────────────────────────────────────
// FILTROS (recibidos por GET, sanitizados)
// ──────────────────────────────────────────────
$filtro_anio    = isset($_GET['anio'])    ? (int) $_GET['anio']                          : null;
$filtro_empresa = isset($_GET['empresa']) ? trim($_GET['empresa'])                       : '';
$filtro_pago    = isset($_GET['pago'])    ? trim($_GET['pago'])                          : '';

// ──────────────────────────────────────────────
// CONSULTAS CON FILTROS DINÁMICOS
// ──────────────────────────────────────────────
$where   = [];
$params  = [];

if ($filtro_anio) {
    $where[]  = 'YEAR(fecha_insc) = :anio';
    $params[':anio'] = $filtro_anio;
}
if ($filtro_empresa !== '') {
    $where[]  = 'empresa = :empresa';
    $params[':empresa'] = $filtro_empresa;
}
if ($filtro_pago !== '') {
    $where[]  = 'pagado = :pagado';
    $params[':pagado'] = $filtro_pago;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ──────────────────────────────────────────────
// KPIs
// ──────────────────────────────────────────────
$sql_total = "SELECT COUNT(*) FROM inscripciones $where_sql";
$stmt = $pdo->prepare($sql_total);
$stmt->execute($params);
$total_inscripciones = (int) $stmt->fetchColumn();

$sql_pendientes = "SELECT COUNT(*) FROM inscripciones $where_sql" .
    ($where ? " AND pagado = 'No'" : " WHERE pagado = 'No'");
$stmt = $pdo->prepare($sql_pendientes);
$stmt->execute($params);
$pendientes_pago = (int) $stmt->fetchColumn();

$sql_novedades = "SELECT COUNT(*) FROM inscripciones $where_sql" .
    ($where ? ' AND leido = 0' : ' WHERE leido = 0');
$stmt = $pdo->prepare($sql_novedades);
$stmt->execute($params);
$novedades = (int) $stmt->fetchColumn();

// Facturas Emitidas (todas las facturas en el sistema, independiente de filtros)
$sql_facturas = "SELECT COUNT(*) FROM facturas";
$total_facturas = (int) $pdo->query($sql_facturas)->fetchColumn();

// ──────────────────────────────────────────────
// DATOS PARA LA TABLA
// ──────────────────────────────────────────────
$sql_tabla = "SELECT id_inscripcion, fecha_insc, inicio_curso, codigo_curso,
                     curso_nombre, empresa, asistente, perfil,
                     bonificado, facturado, pagado, leido, enviado
              FROM inscripciones $where_sql
              ORDER BY fecha_insc DESC";
$stmt = $pdo->prepare($sql_tabla);
$stmt->execute($params);
$inscripciones = $stmt->fetchAll();

// ──────────────────────────────────────────────
// DATOS PARA EL GRÁFICO: Inscripciones mes actual vs anterior
// ──────────────────────────────────────────────
$mes_actual   = date('Y-m');
$mes_anterior = date('Y-m', strtotime('-1 month'));

$sql_chart = "SELECT DATE_FORMAT(fecha_insc, '%Y-%m') AS mes, COUNT(*) AS total
              FROM inscripciones
              WHERE DATE_FORMAT(fecha_insc, '%Y-%m') IN (:mes_actual, :mes_anterior)
              GROUP BY mes ORDER BY mes";
$stmt = $pdo->prepare($sql_chart);
$stmt->execute([':mes_actual' => $mes_actual, ':mes_anterior' => $mes_anterior]);
$chart_data = $stmt->fetchAll();

$inscripciones_mes_anterior = 0;
$inscripciones_mes_actual   = 0;
foreach ($chart_data as $row) {
    if ($row['mes'] === $mes_anterior) $inscripciones_mes_anterior = (int) $row['total'];
    if ($row['mes'] === $mes_actual)   $inscripciones_mes_actual   = (int) $row['total'];
}

// ──────────────────────────────────────────────
// ALERTAS OPERATIVAS
// ──────────────────────────────────────────────
// Registros incompletos (campos clave vacíos)
$sql_incompletos = "SELECT COUNT(*) FROM inscripciones
                    WHERE empresa = '' OR asistente = '' OR curso_nombre = ''";
$incompletos = (int) $pdo->query($sql_incompletos)->fetchColumn();

// Impagos críticos (no pagados Y facturados)
$sql_impagos = "SELECT COUNT(*) FROM inscripciones
                WHERE pagado = 'No' AND facturado = 'Sí'";
$impagos_criticos = (int) $pdo->query($sql_impagos)->fetchColumn();

// ──────────────────────────────────────────────
// LISTAS PARA FILTROS
// ──────────────────────────────────────────────
$anios    = $pdo->query("SELECT DISTINCT YEAR(fecha_insc) AS anio FROM inscripciones ORDER BY anio DESC")->fetchAll();
$empresas = $pdo->query("SELECT DISTINCT empresa FROM inscripciones WHERE empresa != '' ORDER BY empresa")->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscripciones | Dashboard Gestión Interna</title>
    <meta name="description" content="Módulo de gestión de inscripciones — Dashboard interno">

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- DataTables Bootstrap 5 -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Notika Template CSS -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/header-modern-clean.css">
    <link rel="stylesheet" href="css/navbar-stable.css">
    <link rel="stylesheet" href="css/widgets-consistent.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/mobile-menu.css">

    <style>
        /* ── Estilos específicos de inscripciones ── */
        .kpi-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
            border: 1px solid rgba(0,0,0,.06);
            padding: 24px;
            transition: box-shadow .2s, transform .2s;
        }
        .kpi-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,.15);
            transform: translateY(-2px);
        }
        .kpi-card h2 { font-size: 28px; font-weight: 700; margin: 0; color: #333; }
        .kpi-card p  { font-size: 13px; color: #999; margin: 8px 0 0; }
        .kpi-card .kpi-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: #fff;
        }
        .kpi-icon.bg-primary-green { background: #00c292; }
        .kpi-icon.bg-warning-orange { background: #ff9800; }
        .kpi-icon.bg-info-blue { background: #03a9f3; }

        .row-novedad { background-color: #fff8e1 !important; }
        .badge-enviado { font-size: 16px; }

        .alert-panel { max-height: 360px; overflow-y: auto; }
        .alert-panel .alert { font-size: 13px; }

        .filter-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
            padding: 20px 24px;
            margin-bottom: 30px;
        }
    </style>
</head>

<body>
    <!-- ═══════════ HEADER ═══════════ -->
    <header class="notika-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-4 col-6">
                    <div class="notika-brand">
                        <a href="index.html">
                            <img src="img/logo/notika-logo-horizontal.svg" alt="Dashboard"
                                 class="d-none d-md-block" style="height:40px"
                                 onerror="this.onerror=null;this.outerHTML='<span style=\'font-size:22px;font-weight:700;color:#00c292\'>Dashboard</span>';">
                            <img src="img/logo/notika-icon.svg" alt="Dashboard"
                                 class="d-md-none" style="height:35px;width:35px"
                                 onerror="this.onerror=null;this.outerHTML='<span style=\'font-size:20px;font-weight:700;color:#00c292\'>D</span>';">
                        </a>
                    </div>
                </div>
                <div class="col-lg-8 col-6">
                    <div class="notika-nav justify-content-end d-flex align-items-center gap-2 gap-lg-3">
                        <button class="notika-nav-link d-lg-none" type="button"
                                data-bs-toggle="offcanvas" data-bs-target="#mobileNavOffcanvas">
                            <i class="fa-solid fa-bars"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ═══════════ DESKTOP NAV ═══════════ -->
    <nav class="navbar navbar-expand-lg notika-navbar d-none d-lg-block">
        <div class="container">
            <div class="navbar-collapse">
                <ul class="navbar-nav w-100 justify-content-center">
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">
                            <i class="fa-solid fa-house"></i> <span>Inicio</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="inscripciones.php">
                            <i class="fa-solid fa-user-plus"></i> <span>Inscripciones</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fa-solid fa-file-invoice-dollar"></i> <span>Finanzas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fa-solid fa-chart-line"></i> <span>Actividad</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fa-solid fa-building"></i> <span>Empresas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fa-solid fa-triangle-exclamation"></i> <span>Alertas</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ═══════════ MOBILE NAV ═══════════ -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileNavOffcanvas">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title">Dashboard</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <nav class="navbar-nav flex-column">
                <a class="nav-link px-3 py-2 border-bottom" href="index.html">
                    <i class="fa-solid fa-house me-2"></i> Inicio
                </a>
                <a class="nav-link px-3 py-2 border-bottom active" href="inscripciones.php">
                    <i class="fa-solid fa-user-plus me-2"></i> Inscripciones
                </a>
                <a class="nav-link px-3 py-2 border-bottom" href="#">
                    <i class="fa-solid fa-file-invoice-dollar me-2"></i> Finanzas
                </a>
                <a class="nav-link px-3 py-2 border-bottom" href="#">
                    <i class="fa-solid fa-chart-line me-2"></i> Actividad
                </a>
                <a class="nav-link px-3 py-2 border-bottom" href="#">
                    <i class="fa-solid fa-building me-2"></i> Empresas
                </a>
                <a class="nav-link px-3 py-2 border-bottom" href="#">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> Alertas
                </a>
            </nav>
        </div>
    </div>

    <!-- ═══════════ BREADCRUMB ═══════════ -->
    <div class="breadcomb-area">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="breadcomb-list">
                        <div class="row">
                            <div class="col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="breadcomb-wp">
                                    <div class="breadcomb-icon">
                                        <i class="fa-solid fa-user-plus"></i>
                                    </div>
                                    <div class="breadcomb-ctn">
                                        <h2>Inscripciones</h2>
                                        <p>Gestión y control de inscripciones</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="breadcomb-report text-end">
                                    <button class="btn btn-sm"
                                            data-bs-toggle="tooltip" title="Exportar datos"
                                            onclick="exportarCSV()">
                                        <i class="fa-solid fa-file-export"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════ KPIs ═══════════ -->
    <div class="notika-status-area" style="margin-top: 30px;">
        <div class="container">
            <div class="row g-4">
                <!-- Total Inscripciones -->
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="kpi-icon bg-primary-green">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div>
                            <h2><?= number_format($total_inscripciones, 0, ',', '.') ?></h2>
                            <p>Total Inscripciones</p>
                        </div>
                    </div>
                </div>
                <!-- Pendientes de Pago -->
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="kpi-icon bg-warning-orange">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div>
                            <h2><?= number_format($pendientes_pago, 0, ',', '.') ?></h2>
                            <p>Pendientes de Pago</p>
                        </div>
                    </div>
                </div>
                <!-- Novedades -->
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="kpi-icon bg-info-blue">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div>
                            <h2><?= number_format($novedades, 0, ',', '.') ?></h2>
                            <p>Novedades (sin leer)</p>
                        </div>
                    </div>
                </div>
                <!-- Facturas Emitidas -->
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="kpi-icon" style="background: #9c27b0;">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                        <div>
                            <h2><?= number_format($total_facturas, 0, ',', '.') ?></h2>
                            <p>Facturas Emitidas</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════ FILTROS AVANZADOS ═══════════ -->
    <div class="container" style="margin-top: 30px;">
        <div class="filter-section">
            <h6 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Filtros</h6>
            <form method="GET" action="inscripciones.php" id="formFiltros">
                <div class="row g-3 align-items-end">
                    <!-- Año -->
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                        <label for="filtroAnio" class="form-label">Año</label>
                        <select name="anio" id="filtroAnio" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($anios as $a): ?>
                                <option value="<?= $a['anio'] ?>"
                                    <?= $filtro_anio == $a['anio'] ? 'selected' : '' ?>>
                                    <?= $a['anio'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Empresa -->
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                        <label for="filtroEmpresa" class="form-label">Empresa</label>
                        <select name="empresa" id="filtroEmpresa" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <?php foreach ($empresas as $e): ?>
                                <option value="<?= htmlspecialchars($e['empresa'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $filtro_empresa === $e['empresa'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($e['empresa'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Estado de Pago -->
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                        <label for="filtroPago" class="form-label">Estado de Pago</label>
                        <select name="pago" id="filtroPago" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="Sí" <?= $filtro_pago === 'Sí' ? 'selected' : '' ?>>Pagado</option>
                            <option value="No" <?= $filtro_pago === 'No' ? 'selected' : '' ?>>No pagado</option>
                        </select>
                    </div>
                    <!-- Botones -->
                    <div class="col-lg-3 col-md-12 col-sm-6 col-12">
                        <button type="submit" class="btn btn-sm text-white" style="background:#00c292;">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Filtrar
                        </button>
                        <a href="inscripciones.php" class="btn btn-sm btn-outline-secondary ms-1">
                            <i class="fa-solid fa-rotate-left me-1"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══════════ GRÁFICO + ALERTAS ═══════════ -->
    <div class="sale-statistic-area">
        <div class="container">
            <div class="row">
                <!-- Gráfico -->
                <div class="col-lg-8 col-md-7 col-sm-12">
                    <div class="sale-statistic-inner notika-shadow" style="padding: 24px; margin: 30px 0;">
                        <div class="curved-inner-pro">
                            <div class="curved-ctn">
                                <h2>Inscripciones: Mes actual vs anterior</h2>
                                <p><?= date('F Y', strtotime('-1 month')) ?> frente a <?= date('F Y') ?></p>
                            </div>
                        </div>
                        <div style="position:relative; height:300px; margin-top:20px;">
                            <canvas id="chartInscripciones"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Panel de Alertas -->
                <div class="col-lg-4 col-md-5 col-sm-12">
                    <div class="recent-post-wrapper notika-shadow" style="padding: 24px; margin: 30px 0;">
                        <div class="recent-post-ctn">
                            <div class="recent-post-title">
                                <h2><i class="fa-solid fa-triangle-exclamation me-2"></i>Alertas Operativas</h2>
                            </div>
                        </div>
                        <div class="alert-panel mt-3">
                            <?php if ($incompletos > 0): ?>
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                                    <div>
                                        <strong><?= $incompletos ?></strong> registro(s) con datos incompletos
                                        (empresa, asistente o curso vacío).
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($impagos_criticos > 0): ?>
                                <div class="alert alert-danger d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-ban me-2"></i>
                                    <div>
                                        <strong><?= $impagos_criticos ?></strong> inscripción(es) facturada(s)
                                        pero <strong>sin pago registrado</strong>.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($novedades > 0): ?>
                                <div class="alert alert-info d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-envelope me-2"></i>
                                    <div>
                                        <strong><?= $novedades ?></strong> inscripción(es) nuevas pendientes
                                        de lectura.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($pendientes_pago > 0): ?>
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-clock me-2"></i>
                                    <div>
                                        <strong><?= $pendientes_pago ?></strong> inscripción(es) pendiente(s)
                                        de pago.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($incompletos === 0 && $impagos_criticos === 0 && $novedades === 0 && $pendientes_pago === 0): ?>
                                <div class="alert alert-success d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-circle-check me-2"></i>
                                    <div>Todo en orden. No hay alertas activas.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════ TABLA DE INSCRIPCIONES ═══════════ -->
    <div class="data-table-area">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="recent-post-wrapper notika-shadow" style="padding: 24px; margin-bottom: 30px;">
                        <div class="recent-post-ctn">
                            <div class="recent-post-title">
                                <h2>Listado de Inscripciones</h2>
                                <p>Se muestran <?= count($inscripciones) ?> registros</p>
                            </div>
                        </div>
                        <div class="recent-post-items" style="margin-top: 20px;">
                            <div class="table-responsive">
                                <table id="tablaInscripciones"
                                       class="table table-striped table-hover align-middle"
                                       style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha Insc.</th>
                                            <th>Asistente</th>
                                            <th>Curso</th>
                                            <th>Empresa</th>
                                            <th>Perfil</th>
                                            <th>Bonificado</th>
                                            <th>Facturado</th>
                                            <th>Pagado</th>
                                            <th>Enviado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($inscripciones as $row): ?>
                                        <tr class="<?= $row['leido'] == 0 ? 'row-novedad' : '' ?>">
                                            <td><?= (int) $row['id_inscripcion'] ?></td>
                                            <td><?= htmlspecialchars($row['fecha_insc']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($row['asistente'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ($row['leido'] == 0): ?>
                                                    <span class="badge bg-info ms-1">Nuevo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['curso_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['empresa'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['perfil'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['bonificado'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['facturado'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php if ($row['pagado'] === 'No'): ?>
                                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Pagado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($row['enviado'] == 1): ?>
                                                    <i class="fa-solid fa-circle-check text-success badge-enviado"
                                                       title="Enviado"></i>
                                                <?php else: ?>
                                                    <i class="fa-solid fa-circle-xmark text-muted badge-enviado"
                                                       title="No enviado"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════ FOOTER ═══════════ -->
    <div class="footer-copyright-area">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="footer-copy-right">
                        <p>Copyright &copy; <?= date('Y') ?>. Dashboard de Gestión Interna. Template by
                            <a href="https://colorlib.com">Colorlib</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════ SCRIPTS ═══════════ -->
    <!-- jQuery (requerido por DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // ── DataTable ──
        $('#tablaInscripciones').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
            },
            pageLength: 15,
            lengthMenu: [10, 15, 25, 50, 100],
            order: [[1, 'desc']],
            responsive: true
        });

        // ── Chart.js: Mes actual vs anterior ──
        const ctx = document.getElementById('chartInscripciones').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    '<?= date('M Y', strtotime('-1 month')) ?>',
                    '<?= date('M Y') ?>'
                ],
                datasets: [{
                    label: 'Inscripciones',
                    data: [<?= $inscripciones_mes_anterior ?>, <?= $inscripciones_mes_actual ?>],
                    backgroundColor: ['rgba(3, 169, 243, 0.7)', 'rgba(0, 194, 146, 0.7)'],
                    borderColor: ['#03a9f3', '#00c292'],
                    borderWidth: 2,
                    borderRadius: 8,
                    barPercentage: 0.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.parsed.y + ' inscripciones';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // ── Tooltips Bootstrap ──
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (el) {
            return new bootstrap.Tooltip(el);
        });
    });

    // ── Exportar CSV básico ──
    function exportarCSV() {
        const table = document.getElementById('tablaInscripciones');
        let csv = [];
        const rows = table.querySelectorAll('tr');
        rows.forEach(function(row) {
            const cols = row.querySelectorAll('td, th');
            let rowData = [];
            cols.forEach(function(col) {
                let text = col.innerText.replace(/"/g, '""');
                rowData.push('"' + text + '"');
            });
            csv.push(rowData.join(';'));
        });
        const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'inscripciones_' + new Date().toISOString().slice(0,10) + '.csv';
        link.click();
    }
    </script>
</body>
</html>
