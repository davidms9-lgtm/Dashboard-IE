<?php
/**
 * index.php - Home ejecutiva del Dashboard
 *
 * Vista consolidada ES + LATAM con KPIs operativos, evolucion mensual,
 * top empresas, ultimas inscripciones y resumen de alertas.
 */

require_once __DIR__ . '/config/conexion.php';

// ============================================================================
// FILTROS GLOBALES
// ============================================================================
$filtro_anio = isset($_GET['anio']) && (int) $_GET['anio'] > 0 ? (int) $_GET['anio'] : null;

// Fragmento SQL reutilizable para normalizar la fecha LATAM (varchar -> date)
$fechaLatamSql = "COALESCE(STR_TO_DATE(i.fecha_inscripcion, '%d/%m/%Y'), STR_TO_DATE(i.fecha_inscripcion, '%d/%m/%y'))";

// ============================================================================
// HELPERS
// ============================================================================
function fetch_scalar(PDO $pdo, string $sql, array $params = []): float
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null ? 0.0 : (float) $value;
}

function build_where(array $where, array $extra = []): string
{
    $all = array_merge($where, $extra);
    return $all ? 'WHERE ' . implode(' AND ', $all) : '';
}

function format_eur(float $amount): string
{
    return number_format($amount, 0, ',', '.') . ' &euro;';
}

// ============================================================================
// CONSTRUCCION DE FILTROS POR FUENTE
// ============================================================================
$baseEsFrom = 'FROM inscripciones i LEFT JOIN Empresas e ON e.id = i.id_empresa';
$baseLatamFrom = 'FROM inscripciones_latam i LEFT JOIN Empresas_latam e ON e.id = i.id_empresa';

$whereEs = ["i.prova <> 's'", '(e.prova IS NULL OR e.prova = 0)'];
$paramsEs = [];
if ($filtro_anio) {
    $whereEs[] = 'YEAR(i.fecha_inscripcion) = :anio_es';
    $paramsEs[':anio_es'] = $filtro_anio;
}

$whereLatam = ['i.prova = 0', '(e.prova IS NULL OR e.prova = 0)'];
$paramsLatam = [];
if ($filtro_anio) {
    $whereLatam[] = "YEAR({$fechaLatamSql}) = :anio_latam";
    $paramsLatam[':anio_latam'] = $filtro_anio;
}

$whereEm = [];
$paramsEm = [];
if ($filtro_anio) {
    $whereEm[] = 'YEAR(fecha) = :anio_em';
    $paramsEm[':anio_em'] = $filtro_anio;
}

$wEs = build_where($whereEs);
$wLatam = build_where($whereLatam);
$wEm = build_where($whereEm);

// ============================================================================
// KPIs FILA 1 - OPERATIVOS
// ============================================================================
$total_es = (int) fetch_scalar($pdo, "SELECT COUNT(*) {$baseEsFrom} {$wEs}", $paramsEs);
$total_latam = (int) fetch_scalar($pdo, "SELECT COUNT(*) {$baseLatamFrom} {$wLatam}", $paramsLatam);
$total_inscripciones = $total_es + $total_latam;

$mesActual = date('Y-m');
$mes_es = (int) fetch_scalar(
    $pdo,
    "SELECT COUNT(*) {$baseEsFrom} " . build_where($whereEs, ["DATE_FORMAT(i.fecha_inscripcion, '%Y-%m') = :mes_es"]),
    array_merge($paramsEs, [':mes_es' => $mesActual])
);
$mes_latam = (int) fetch_scalar(
    $pdo,
    "SELECT COUNT(*) {$baseLatamFrom} " . build_where($whereLatam, ["DATE_FORMAT({$fechaLatamSql}, '%Y-%m') = :mes_latam"]),
    array_merge($paramsLatam, [':mes_latam' => $mesActual])
);
$inscripciones_mes = $mes_es + $mes_latam;

$pend_es = (int) fetch_scalar(
    $pdo,
    "SELECT COUNT(*) {$baseEsFrom} " . build_where($whereEs, ['i.pagado = 0']),
    $paramsEs
);
$pend_latam = (int) fetch_scalar(
    $pdo,
    "SELECT COUNT(*) {$baseLatamFrom} " . build_where($whereLatam, ['i.pagado = 0']),
    $paramsLatam
);
$pendientes_pago = $pend_es + $pend_latam;

$emp_es = (int) fetch_scalar($pdo, "SELECT COUNT(DISTINCT i.id_empresa) {$baseEsFrom} {$wEs}", $paramsEs);
$emp_latam = (int) fetch_scalar($pdo, "SELECT COUNT(DISTINCT i.id_empresa) {$baseLatamFrom} {$wLatam}", $paramsLatam);
$empresas_activas = $emp_es + $emp_latam;

// ============================================================================
// KPIs FILA 2 - FINANZAS + EXTRAS
// ============================================================================
// facturas_2026.fecha esta en formato YYYYMMDD (varchar)
$facturasParams = [];
$facturasWhereSql = '';
if ($filtro_anio) {
    $facturasWhereSql = 'WHERE LEFT(fecha, 4) = :anio_fact';
    $facturasParams[':anio_fact'] = (string) $filtro_anio;
}
$facturas_count = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM facturas_2026 {$facturasWhereSql}", $facturasParams);
$facturado_total = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(total), 0) FROM facturas_2026 {$facturasWhereSql}", $facturasParams);

// abonos.fecha esta en formato d/m/Y (varchar)
$abonosParams = [];
$abonosWhereSql = '';
if ($filtro_anio) {
    $abonosWhereSql = "WHERE YEAR(STR_TO_DATE(fecha, '%d/%m/%Y')) = :anio_ab";
    $abonosParams[':anio_ab'] = $filtro_anio;
}
$abonos_count = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM abonos {$abonosWhereSql}", $abonosParams);
$abonado_total = (float) fetch_scalar($pdo, "SELECT COALESCE(SUM(total), 0) FROM abonos {$abonosWhereSql}", $abonosParams);

$leads_emagister = (int) fetch_scalar($pdo, "SELECT COUNT(*) FROM emagister {$wEm}", $paramsEm);

// Alertas agregadas
$impagos_es = (int) fetch_scalar(
    $pdo,
    "SELECT COUNT(*) {$baseEsFrom} " . build_where($whereEs, ['i.facturado = 1', 'i.pagado = 0']),
    $paramsEs
);
$facturas_sin_pago_latam = (int) fetch_scalar(
    $pdo,
    "SELECT COUNT(*) {$baseLatamFrom} " . build_where($whereLatam, ["TRIM(COALESCE(i.factura, '')) <> ''", 'i.pagado = 0']),
    $paramsLatam
);
$descartadas_latam = (int) fetch_scalar(
    $pdo,
    "SELECT COUNT(*) {$baseLatamFrom} " . build_where($whereLatam, ['i.descartada = 1']),
    $paramsLatam
);
$alertas_total = $impagos_es + $facturas_sin_pago_latam + $descartadas_latam;

// ============================================================================
// GRAFICA: EVOLUCION 12 MESES (ES vs LATAM apilado)
// ============================================================================
if ($filtro_anio) {
    $chartStart = new DateTime("{$filtro_anio}-01-01");
    $chartEnd = new DateTime(($filtro_anio + 1) . '-01-01');
} else {
    $chartEnd = new DateTime('first day of next month');
    $chartStart = (clone $chartEnd)->modify('-12 months');
}

$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(i.fecha_inscripcion, '%Y-%m') AS mes, COUNT(*) AS total
     {$baseEsFrom}
     WHERE i.prova <> 's' AND (e.prova IS NULL OR e.prova = 0)
       AND i.fecha_inscripcion >= :start AND i.fecha_inscripcion < :end
     GROUP BY mes
     ORDER BY mes"
);
$stmt->execute([
    ':start' => $chartStart->format('Y-m-d 00:00:00'),
    ':end' => $chartEnd->format('Y-m-d 00:00:00'),
]);
$chartEsRows = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT({$fechaLatamSql}, '%Y-%m') AS mes, COUNT(*) AS total
     {$baseLatamFrom}
     WHERE i.prova = 0 AND (e.prova IS NULL OR e.prova = 0)
       AND {$fechaLatamSql} >= :start AND {$fechaLatamSql} < :end
     GROUP BY mes
     ORDER BY mes"
);
$stmt->execute([
    ':start' => $chartStart->format('Y-m-d'),
    ':end' => $chartEnd->format('Y-m-d'),
]);
$chartLatamRows = $stmt->fetchAll();

$mesesAbrev = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
$esMap = [];
foreach ($chartEsRows as $r) {
    $esMap[$r['mes']] = (int) $r['total'];
}
$latamMap = [];
foreach ($chartLatamRows as $r) {
    $latamMap[$r['mes']] = (int) $r['total'];
}

$chart_labels = [];
$chart_es_values = [];
$chart_latam_values = [];
$cursor = clone $chartStart;
while ($cursor < $chartEnd) {
    $key = $cursor->format('Y-m');
    $chart_labels[] = $mesesAbrev[(int) $cursor->format('n')] . ' ' . $cursor->format('y');
    $chart_es_values[] = $esMap[$key] ?? 0;
    $chart_latam_values[] = $latamMap[$key] ?? 0;
    $cursor->modify('+1 month');
}

$chart_labels_js = json_encode($chart_labels);
$chart_es_js = json_encode($chart_es_values);
$chart_latam_js = json_encode($chart_latam_values);
$donut_data_js = json_encode([$total_es, $total_latam]);
$chart_subtitle = ($chart_labels[0] ?? '') . ' a ' . (end($chart_labels) ?: '');

// ============================================================================
// TOP 5 EMPRESAS
// ============================================================================
$stmt = $pdo->prepare(
    "SELECT 'ES' AS pais, e.id AS empresa_id, e.razon_social, COUNT(*) AS total
     {$baseEsFrom}
     " . build_where($whereEs, ["TRIM(COALESCE(e.razon_social, '')) <> ''"]) . "
     GROUP BY e.id, e.razon_social"
);
$stmt->execute($paramsEs);
$topEmpresasEs = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT 'LATAM' AS pais, e.id AS empresa_id, e.razon_social, COUNT(*) AS total
     {$baseLatamFrom}
     " . build_where($whereLatam, ["TRIM(COALESCE(e.razon_social, '')) <> ''"]) . "
     GROUP BY e.id, e.razon_social"
);
$stmt->execute($paramsLatam);
$topEmpresasLatam = $stmt->fetchAll();

$topEmpresas = array_merge($topEmpresasEs, $topEmpresasLatam);
usort($topEmpresas, static fn ($a, $b) => (int) $b['total'] - (int) $a['total']);
$topEmpresas = array_slice($topEmpresas, 0, 5);

// ============================================================================
// ULTIMAS 10 INSCRIPCIONES (ES + LATAM)
// ============================================================================
$stmt = $pdo->prepare(
    "SELECT
        'ES' AS pais,
        i.id,
        i.fecha_inscripcion AS fecha,
        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', i.nombre_asistente, i.apellidos_asistente)), ''), i.nombre_alumno, 'Sin asignar') AS asistente,
        COALESCE(NULLIF(TRIM(i.curso), ''), NULLIF(TRIM(i.accion_formativa), ''), NULLIF(TRIM(i.accion_formativa_externa), ''), 'Curso s/i') AS curso,
        COALESCE(NULLIF(TRIM(e.razon_social), ''), 'Empresa s/a') AS empresa,
        i.pagado
     {$baseEsFrom}
     {$wEs}
     ORDER BY i.fecha_inscripcion DESC
     LIMIT 10"
);
$stmt->execute($paramsEs);
$latestEs = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT
        'LATAM' AS pais,
        i.id,
        {$fechaLatamSql} AS fecha,
        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', i.nombre_asistente, i.apellidos_asistente)), ''), 'Sin asignar') AS asistente,
        COALESCE(NULLIF(TRIM(i.curso), ''), NULLIF(TRIM(i.accion_formativa), ''), 'Curso s/i') AS curso,
        COALESCE(NULLIF(TRIM(e.razon_social), ''), 'Empresa s/a') AS empresa,
        i.pagado
     {$baseLatamFrom}
     {$wLatam}
     ORDER BY {$fechaLatamSql} DESC
     LIMIT 10"
);
$stmt->execute($paramsLatam);
$latestLatam = $stmt->fetchAll();

$latest = array_merge($latestEs, $latestLatam);
usort($latest, static function ($a, $b) {
    $da = $a['fecha'] ? strtotime($a['fecha']) : 0;
    $db = $b['fecha'] ? strtotime($b['fecha']) : 0;
    return $db <=> $da;
});
$latest = array_slice($latest, 0, 10);

// ============================================================================
// LISTA DE ANOS PARA EL FILTRO (union ES + LATAM)
// ============================================================================
$anios = $pdo->query(
    "SELECT DISTINCT y AS anio FROM (
        SELECT YEAR(fecha_inscripcion) AS y
        FROM inscripciones
        WHERE prova <> 's' AND fecha_inscripcion IS NOT NULL
        UNION
        SELECT YEAR(COALESCE(STR_TO_DATE(fecha_inscripcion, '%d/%m/%Y'), STR_TO_DATE(fecha_inscripcion, '%d/%m/%y'))) AS y
        FROM inscripciones_latam
        WHERE prova = 0
     ) t
     WHERE y IS NOT NULL
     ORDER BY anio DESC"
)->fetchAll();

// ============================================================================
// HEADER VARS
// ============================================================================
$page_title = 'Inicio';
$active_page = 'inicio';
$breadcrumb_title = 'Dashboard ejecutivo';
$breadcrumb_desc = 'Visión consolidada España + LATAM' . ($filtro_anio ? " · año {$filtro_anio}" : ' · últimos 12 meses');
$breadcrumb_icon = 'fa-solid fa-chart-line';

require_once __DIR__ . '/includes/header.php';
?>

    <!-- Filtros globales -->
    <div class="container" style="margin-top: 30px;">
        <div class="filter-section">
            <h6 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Filtros globales</h6>
            <form method="GET" action="index.php">
                <div class="row g-3 align-items-end filter-form-row">
                    <div class="col-lg-4 col-md-6">
                        <div class="filter-field">
                            <label for="filtroAnio" class="form-label">A&ntilde;o</label>
                            <select name="anio" id="filtroAnio" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todos los a&ntilde;os</option>
                                <?php foreach ($anios as $a): ?>
                                    <option value="<?= (int) $a['anio'] ?>" <?= $filtro_anio === (int) $a['anio'] ? 'selected' : '' ?>>
                                        <?= (int) $a['anio'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-8 col-md-6">
                        <div class="filter-actions">
                            <?php if ($filtro_anio): ?>
                                <a href="index.php" class="btn btn-filter-secondary">
                                    <i class="fa-solid fa-rotate-left"></i> Limpiar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- KPIs fila 1: operativos -->
    <div class="container" style="margin-top: 12px;">
        <div class="row g-4">
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-green">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <h2><?= number_format($total_inscripciones, 0, ',', '.') ?></h2>
                        <p>Total inscripciones</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info-blue">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                    <div>
                        <h2><?= number_format($inscripciones_mes, 0, ',', '.') ?></h2>
                        <p>Este mes (<?= htmlspecialchars(date('M Y'), ENT_QUOTES, 'UTF-8') ?>)</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning-orange">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <h2><?= number_format($pendientes_pago, 0, ',', '.') ?></h2>
                        <p>Pendientes de pago</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-dark-slate">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <div>
                        <h2><?= number_format($empresas_activas, 0, ',', '.') ?></h2>
                        <p>Empresas con inscripciones</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs fila 2: finanzas + extras -->
    <div class="container" style="margin-top: 24px;">
        <div class="row g-4">
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-green">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <div>
                        <h2><?= format_eur($facturado_total) ?></h2>
                        <p><?= $facturas_count ?> factura<?= $facturas_count === 1 ? '' : 's' ?> emitida<?= $facturas_count === 1 ? '' : 's' ?></p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-danger-red">
                        <i class="fa-solid fa-arrow-rotate-left"></i>
                    </div>
                    <div>
                        <h2><?= format_eur($abonado_total) ?></h2>
                        <p><?= $abonos_count ?> abono<?= $abonos_count === 1 ? '' : 's' ?> emitido<?= $abonos_count === 1 ? '' : 's' ?></p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-purple-soft">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <div>
                        <h2><?= number_format($leads_emagister, 0, ',', '.') ?></h2>
                        <p>Leads Emagister</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning-orange">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h2><?= number_format($alertas_total, 0, ',', '.') ?></h2>
                        <p>Alertas activas</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafica evolucion + donut -->
    <div class="container" style="margin-top: 30px;">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="recent-post-wrapper notika-shadow h-100" style="padding: 24px;">
                    <div class="recent-post-title">
                        <h2>Evoluci&oacute;n de inscripciones</h2>
                        <p><?= htmlspecialchars($chart_subtitle, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div style="position:relative; height:320px; margin-top:20px;">
                        <canvas id="chartEvolucion"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="recent-post-wrapper notika-shadow h-100" style="padding: 24px;">
                    <div class="recent-post-title">
                        <h2>Distribuci&oacute;n</h2>
                        <p>Espa&ntilde;a vs LATAM</p>
                    </div>
                    <?php if ($total_es + $total_latam > 0): ?>
                        <div style="position:relative; height:220px; margin-top:20px;">
                            <canvas id="chartDonut"></canvas>
                        </div>
                        <div class="d-flex justify-content-around mt-3">
                            <div class="text-center">
                                <div style="font-size:22px;font-weight:700;color:#00c292"><?= number_format($total_es, 0, ',', '.') ?></div>
                                <small class="text-muted">Espa&ntilde;a</small>
                            </div>
                            <div class="text-center">
                                <div style="font-size:22px;font-weight:700;color:#03a9f3"><?= number_format($total_latam, 0, ',', '.') ?></div>
                                <small class="text-muted">LATAM</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="home-empty mt-4">
                            <i class="fa-solid fa-chart-pie d-block mb-2" style="font-size:32px"></i>
                            Sin inscripciones en el periodo seleccionado
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top empresas + Ultimas inscripciones -->
    <div class="container" style="margin-top: 30px;">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="recent-post-wrapper notika-shadow h-100" style="padding: 24px;">
                    <div class="recent-post-title">
                        <h2>Top 5 empresas</h2>
                        <p>Con m&aacute;s inscripciones en el periodo</p>
                    </div>
                    <div class="mt-3">
                        <table class="table table-striped table-hover home-mini-table">
                            <thead>
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Empresa</th>
                                    <th style="width:90px">Pa&iacute;s</th>
                                    <th class="text-end" style="width:80px">Inscr.</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($topEmpresas)): ?>
                                <tr><td colspan="4" class="home-empty">Sin datos para el periodo</td></tr>
                            <?php else: ?>
                                <?php foreach ($topEmpresas as $i => $emp): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($emp['razon_social'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="country-badge <?= $emp['pais'] === 'ES' ? 'es' : 'latam' ?>">
                                            <?= htmlspecialchars($emp['pais'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold"><?= (int) $emp['total'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="recent-post-wrapper notika-shadow h-100" style="padding: 24px;">
                    <div class="recent-post-title">
                        <h2>&Uacute;ltimas inscripciones</h2>
                        <p>10 m&aacute;s recientes (Espa&ntilde;a + LATAM)</p>
                    </div>
                    <div class="mt-3 table-responsive">
                        <table class="table table-striped table-hover home-mini-table">
                            <thead>
                                <tr>
                                    <th style="width:90px">Fecha</th>
                                    <th>Asistente</th>
                                    <th>Empresa</th>
                                    <th style="width:80px">Pa&iacute;s</th>
                                    <th style="width:90px">Pago</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($latest)): ?>
                                <tr><td colspan="5" class="home-empty">Sin inscripciones en el periodo</td></tr>
                            <?php else: ?>
                                <?php foreach ($latest as $row): ?>
                                    <?php
                                    $fechaStr = '-';
                                    if (!empty($row['fecha'])) {
                                        $ts = strtotime($row['fecha']);
                                        if ($ts) {
                                            $fechaStr = date('d/m/Y', $ts);
                                        }
                                    }
                                    ?>
                                <tr>
                                    <td><?= htmlspecialchars($fechaStr, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['asistente'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(mb_strimwidth($row['empresa'], 0, 28, '…'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="country-badge <?= $row['pais'] === 'ES' ? 'es' : 'latam' ?>">
                                            <?= htmlspecialchars($row['pais'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ((int) $row['pagado'] === 1): ?>
                                            <span class="badge bg-success">Pagado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen de alertas -->
    <div class="container" style="margin-top: 30px; margin-bottom: 60px;">
        <div class="recent-post-wrapper notika-shadow" style="padding: 24px;">
            <div class="recent-post-title">
                <h2><i class="fa-solid fa-triangle-exclamation me-2"></i>Resumen de alertas</h2>
                <p>Incidencias detectadas en el periodo seleccionado</p>
            </div>
            <div class="row g-3 mt-2">
                <div class="col-md-4">
                    <div class="alert <?= $impagos_es > 0 ? 'alert-danger' : 'alert-success' ?> mb-0 d-flex align-items-center" role="alert">
                        <i class="fa-solid <?= $impagos_es > 0 ? 'fa-ban' : 'fa-circle-check' ?> me-2"></i>
                        <div>
                            <strong><?= number_format($impagos_es, 0, ',', '.') ?></strong> inscripci&oacute;n(es) ES facturada(s) sin pago
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert <?= $facturas_sin_pago_latam > 0 ? 'alert-danger' : 'alert-success' ?> mb-0 d-flex align-items-center" role="alert">
                        <i class="fa-solid <?= $facturas_sin_pago_latam > 0 ? 'fa-file-invoice-dollar' : 'fa-circle-check' ?> me-2"></i>
                        <div>
                            <strong><?= number_format($facturas_sin_pago_latam, 0, ',', '.') ?></strong> factura(s) LATAM sin pago asociado
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert <?= $descartadas_latam > 0 ? 'alert-secondary' : 'alert-success' ?> mb-0 d-flex align-items-center" role="alert">
                        <i class="fa-solid <?= $descartadas_latam > 0 ? 'fa-trash' : 'fa-circle-check' ?> me-2"></i>
                        <div>
                            <strong><?= number_format($descartadas_latam, 0, ',', '.') ?></strong> inscripci&oacute;n(es) LATAM descartada(s)
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
$extra_js = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
];

$inline_js = <<<JS
document.addEventListener('DOMContentLoaded', function () {
    const labels = {$chart_labels_js};
    const esData = {$chart_es_js};
    const latamData = {$chart_latam_js};
    const donutData = {$donut_data_js};

    const ctxEvol = document.getElementById('chartEvolucion');
    if (ctxEvol) {
        new Chart(ctxEvol.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Espana',
                        data: esData,
                        backgroundColor: 'rgba(0, 194, 146, 0.85)',
                        borderColor: '#00c292',
                        borderWidth: 1,
                        borderRadius: 6,
                        maxBarThickness: 38
                    },
                    {
                        label: 'LATAM',
                        data: latamData,
                        backgroundColor: 'rgba(3, 169, 243, 0.7)',
                        borderColor: '#03a9f3',
                        borderWidth: 1,
                        borderRadius: 6,
                        maxBarThickness: 38
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 14, padding: 16 } },
                    tooltip: {
                        callbacks: {
                            footer: function (items) {
                                let total = 0;
                                items.forEach(function (it) { total += it.parsed.y; });
                                return 'Total: ' + total;
                            }
                        }
                    }
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.05)' } }
                }
            }
        });
    }

    const ctxDonut = document.getElementById('chartDonut');
    if (ctxDonut) {
        new Chart(ctxDonut.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Espana', 'LATAM'],
                datasets: [{
                    data: donutData,
                    backgroundColor: ['#00c292', '#03a9f3'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                const pct = total > 0 ? Math.round((ctx.parsed * 100) / total) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
});
JS;

require_once __DIR__ . '/includes/footer.php';
?>
