<?php
/**
 * inscripciones.php
 * Modulo de Inscripciones Espana
 */

require_once __DIR__ . '/config/conexion.php';

function fetch_count(PDO $pdo, string $fromSql, array $where, array $params): int
{
    $sql = "SELECT COUNT(*) {$fromSql}";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function format_datetime_value(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
}

function format_short_date(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    $formats = ['d/m/Y', 'Y-m-d', 'd/m/y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('d/m/Y');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $value;
}

function build_attendee_name(array $row): string
{
    $fullName = trim(($row['nombre_asistente'] ?? '') . ' ' . ($row['apellidos_asistente'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $fallback = trim((string) ($row['nombre_alumno'] ?? ''));
    return $fallback !== '' ? $fallback : 'Sin asignar';
}

$filtro_anio = isset($_GET['anio']) ? (int) $_GET['anio'] : null;
$filtro_empresa = isset($_GET['empresa']) ? trim($_GET['empresa']) : '';
$filtro_pago = isset($_GET['pago']) ? trim($_GET['pago']) : '';
$rango_grafica = isset($_GET['rango_grafica']) ? (int) $_GET['rango_grafica'] : 6;
$rangos_grafica = [
    2 => 'Vista de 2 meses',
    6 => 'Vista de 6 meses',
    12 => 'Vista de 12 meses',
];
if (!isset($rangos_grafica[$rango_grafica])) {
    $rango_grafica = 6;
}

$baseFrom = "FROM inscripciones i
LEFT JOIN Empresas e ON e.id = i.id_empresa
LEFT JOIN Cursos c ON c.id_curso = i.id_curso";

$where = [];
$params = [];

if ($filtro_anio) {
    $where[] = 'YEAR(i.fecha_inscripcion) = :anio';
    $params[':anio'] = $filtro_anio;
}
if ($filtro_empresa !== '') {
    $where[] = 'e.razon_social = :empresa';
    $params[':empresa'] = $filtro_empresa;
}
if ($filtro_pago !== '') {
    $where[] = 'i.pagado = :pagado';
    $params[':pagado'] = (int) $filtro_pago;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_inscripciones = fetch_count($pdo, $baseFrom, $where, $params);
$pendientes_pago = fetch_count($pdo, $baseFrom, array_merge($where, ['i.pagado = 0']), $params);
$novedades = fetch_count($pdo, $baseFrom, array_merge($where, ['i.leido = 0']), $params);

$sqlTabla = "SELECT
    i.id,
    i.fecha_inscripcion,
    i.f_ini,
    i.id_curso,
    i.curso,
    i.accion_formativa,
    i.accion_formativa_externa,
    i.nombre_asistente,
    i.apellidos_asistente,
    i.nombre_alumno,
    i.canal,
    i.bonificacion,
    i.facturado,
    i.pagado,
    i.leido,
    i.email_enviado,
    i.baja,
    i.pedido,
    COALESCE(NULLIF(TRIM(e.razon_social), ''), 'Empresa no asociada') AS empresa_nombre,
    COALESCE(
        NULLIF(TRIM(i.curso), ''),
        NULLIF(TRIM(c.nombre_curso), ''),
        NULLIF(TRIM(i.accion_formativa_externa), ''),
        NULLIF(TRIM(i.accion_formativa), ''),
        NULLIF(TRIM(i.id_curso), ''),
        'Curso sin identificar'
    ) AS curso_mostrado
{$baseFrom}
{$whereSql}
ORDER BY i.fecha_inscripcion DESC, i.id DESC";
$stmt = $pdo->prepare($sqlTabla);
$stmt->execute($params);
$inscripciones = $stmt->fetchAll();

$chartReferenceWhere = array_merge($where, ['i.fecha_inscripcion IS NOT NULL']);
$chartReferenceSql = "SELECT MAX(i.fecha_inscripcion) {$baseFrom}";
if ($chartReferenceWhere) {
    $chartReferenceSql .= ' WHERE ' . implode(' AND ', $chartReferenceWhere);
}
$stmt = $pdo->prepare($chartReferenceSql);
$stmt->execute($params);
$fecha_referencia_raw = $stmt->fetchColumn();
$fecha_referencia = $fecha_referencia_raw ? new DateTime($fecha_referencia_raw) : new DateTime();
$fecha_referencia->modify('first day of this month');
$fecha_inicio_grafica = (clone $fecha_referencia)->modify('-' . ($rango_grafica - 1) . ' months');
$fecha_fin_grafica = (clone $fecha_referencia)->modify('first day of next month');

$chartWhere = array_merge(
    $where,
    [
        'i.fecha_inscripcion >= :fecha_inicio_chart',
        'i.fecha_inscripcion < :fecha_fin_chart',
    ]
);
$chartParams = $params;
$chartParams[':fecha_inicio_chart'] = $fecha_inicio_grafica->format('Y-m-d 00:00:00');
$chartParams[':fecha_fin_chart'] = $fecha_fin_grafica->format('Y-m-d 00:00:00');
$chartSql = "SELECT DATE_FORMAT(i.fecha_inscripcion, '%Y-%m') AS mes, COUNT(*) AS total
{$baseFrom}
WHERE " . implode(' AND ', $chartWhere) . "
GROUP BY DATE_FORMAT(i.fecha_inscripcion, '%Y-%m')
ORDER BY mes";
$stmt = $pdo->prepare($chartSql);
$stmt->execute($chartParams);
$chartData = $stmt->fetchAll();

$incompletos = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM inscripciones i
     LEFT JOIN Empresas e ON e.id = i.id_empresa
     WHERE NULLIF(TRIM(COALESCE(e.razon_social, '')), '') IS NULL
        OR (
            NULLIF(TRIM(COALESCE(i.nombre_asistente, '')), '') IS NULL
            AND NULLIF(TRIM(COALESCE(i.nombre_alumno, '')), '') IS NULL
        )
        OR (
            NULLIF(TRIM(COALESCE(i.curso, '')), '') IS NULL
            AND NULLIF(TRIM(COALESCE(i.accion_formativa, '')), '') IS NULL
            AND NULLIF(TRIM(COALESCE(i.accion_formativa_externa, '')), '') IS NULL
            AND NULLIF(TRIM(COALESCE(i.id_curso, '')), '') IS NULL
        )"
)->fetchColumn();

$impagos_criticos = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM inscripciones
     WHERE facturado = 1 AND pagado = 0"
)->fetchColumn();

$anios = $pdo->query(
    "SELECT DISTINCT YEAR(fecha_inscripcion) AS anio
     FROM inscripciones
     WHERE fecha_inscripcion IS NOT NULL
     ORDER BY anio DESC"
)->fetchAll();

$empresas = $pdo->query(
    "SELECT DISTINCT razon_social AS empresa
     FROM Empresas
     WHERE TRIM(COALESCE(razon_social, '')) <> ''
     ORDER BY razon_social"
)->fetchAll();

$mesesEs = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre',
];
$mesesAbrev = [
    1 => 'Ene',
    2 => 'Feb',
    3 => 'Mar',
    4 => 'Abr',
    5 => 'May',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Ago',
    9 => 'Sep',
    10 => 'Oct',
    11 => 'Nov',
    12 => 'Dic',
];
$chartMap = [];
foreach ($chartData as $row) {
    $chartMap[$row['mes']] = (int) $row['total'];
}

$chart_labels = [];
$chart_values = [];
$cursorMes = clone $fecha_inicio_grafica;
while ($cursorMes < $fecha_fin_grafica) {
    $mesClave = $cursorMes->format('Y-m');
    $chart_labels[] = $mesesAbrev[(int) $cursorMes->format('n')] . ' ' . $cursorMes->format('Y');
    $chart_values[] = $chartMap[$mesClave] ?? 0;
    $cursorMes->modify('+1 month');
}

$chart_labels_js = json_encode($chart_labels);
$chart_values_js = json_encode($chart_values);
$chart_subtitle = $rangos_grafica[$rango_grafica] . ' hasta ' . end($chart_labels);

$page_title = 'Inscripciones ES';
$active_page = 'inscripciones_espana';
$breadcrumb_title = 'Inscripciones Espana';
$breadcrumb_desc = 'Gestion y control de inscripciones nacionales';
$breadcrumb_icon = 'fa-solid fa-user-plus';
$breadcrumb_buttons = '<button class="btn btn-sm" data-bs-toggle="tooltip" title="Exportar datos" onclick="exportarCSV()"><i class="fa-solid fa-file-export"></i></button>';
$extra_css = '';

require_once __DIR__ . '/includes/header.php';
?>

    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <div class="notika-status-area" style="margin-top: 30px;">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6 col-sm-6 col-12">
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
                <div class="col-lg-4 col-md-6 col-sm-6 col-12">
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
                <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="kpi-icon bg-info-blue">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div>
                            <h2><?= number_format($novedades, 0, ',', '.') ?></h2>
                            <p>Novedades sin leer</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: 30px;">
        <div class="filter-section">
            <h6 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Filtros</h6>
            <form method="GET" action="inscripciones.php" id="formFiltros">
                <div class="row g-3 align-items-end filter-form-row">
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                        <div class="filter-field">
                            <label for="filtroAnio" class="form-label">A&ntilde;o</label>
                            <select name="anio" id="filtroAnio" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach ($anios as $anio): ?>
                                    <option value="<?= (int) $anio['anio'] ?>" <?= $filtro_anio === (int) $anio['anio'] ? 'selected' : '' ?>>
                                        <?= (int) $anio['anio'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-6 col-12">
                        <div class="filter-field">
                            <label for="filtroEmpresa" class="form-label">Empresa</label>
                            <select name="empresa" id="filtroEmpresa" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?= htmlspecialchars($empresa['empresa'], ENT_QUOTES, 'UTF-8') ?>" <?= $filtro_empresa === $empresa['empresa'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($empresa['empresa'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                        <div class="filter-field">
                            <label for="filtroPago" class="form-label">Estado de pago</label>
                            <select name="pago" id="filtroPago" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="1" <?= $filtro_pago === '1' ? 'selected' : '' ?>>Pagado</option>
                                <option value="0" <?= $filtro_pago === '0' ? 'selected' : '' ?>>Pendiente</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-12 col-sm-6 col-12">
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-filter-primary">
                                <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                            </button>
                            <a href="inscripciones.php" class="btn btn-filter-secondary">
                                <i class="fa-solid fa-rotate-left"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="sale-statistic-area">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 col-md-7 col-sm-12">
                    <div class="sale-statistic-inner notika-shadow" style="padding: 24px; margin: 30px 0;">
                        <div class="chart-toolbar">
                            <div class="curved-ctn">
                                <h2>Inscripciones por mes</h2>
                                <p><?= htmlspecialchars($chart_subtitle, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <form method="GET" action="inscripciones.php" class="chart-range-form">
                                <?php if ($filtro_anio): ?>
                                    <input type="hidden" name="anio" value="<?= (int) $filtro_anio ?>">
                                <?php endif; ?>
                                <?php if ($filtro_empresa !== ''): ?>
                                    <input type="hidden" name="empresa" value="<?= htmlspecialchars($filtro_empresa, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                                <?php if ($filtro_pago !== ''): ?>
                                    <input type="hidden" name="pago" value="<?= htmlspecialchars($filtro_pago, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                                <div class="chart-range-group">
                                    <label for="rangoGrafica" class="form-label">Vista de la gr&aacute;fica</label>
                                    <select name="rango_grafica" id="rangoGrafica" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <?php foreach ($rangos_grafica as $valorRango => $textoRango): ?>
                                            <option value="<?= $valorRango ?>" <?= $rango_grafica === $valorRango ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($textoRango, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div style="position:relative; height:300px; margin-top:20px;">
                            <canvas id="chartInscripciones"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-5 col-sm-12">
                    <div class="recent-post-wrapper notika-shadow" style="padding: 24px; margin: 30px 0;">
                        <div class="recent-post-title">
                            <h2><i class="fa-solid fa-triangle-exclamation me-2"></i>Alertas operativas</h2>
                        </div>
                        <div class="alert-panel mt-3">
                            <?php if ($incompletos > 0): ?>
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                                    <div><strong><?= $incompletos ?></strong> registro(s) con datos incompletos.</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($impagos_criticos > 0): ?>
                                <div class="alert alert-danger d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-ban me-2"></i>
                                    <div><strong><?= $impagos_criticos ?></strong> inscripcion(es) facturada(s) sin pago.</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($novedades > 0): ?>
                                <div class="alert alert-info d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-envelope me-2"></i>
                                    <div><strong><?= $novedades ?></strong> registro(s) pendientes de revision.</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($pendientes_pago > 0): ?>
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-clock me-2"></i>
                                    <div><strong><?= $pendientes_pago ?></strong> inscripcion(es) pendientes de cobro.</div>
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

    <div class="data-table-area">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="recent-post-wrapper notika-shadow" style="padding: 24px; margin-bottom: 30px;">
                        <div class="recent-post-title">
                            <h2>Listado de inscripciones Espana</h2>
                            <p>Se muestran <?= count($inscripciones) ?> registros</p>
                        </div>
                        <div class="recent-post-items" style="margin-top: 20px;">
                            <div class="table-responsive">
                                <table id="tablaInscripciones" class="table table-striped table-hover align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha</th>
                                            <th>Asistente</th>
                                            <th>Curso</th>
                                            <th>Empresa</th>
                                            <th>Inicio</th>
                                            <th>Canal</th>
                                            <th>Bonif.</th>
                                            <th>Fact.</th>
                                            <th>Pagado</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($inscripciones as $row): ?>
                                        <?php $attendeeName = build_attendee_name($row); ?>
                                        <tr class="<?= (int) $row['leido'] === 0 ? 'row-novedad' : '' ?>">
                                            <td><?= (int) $row['id'] ?></td>
                                            <td><?= htmlspecialchars(format_datetime_value($row['fecha_inscripcion']), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?= htmlspecialchars($attendeeName, ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ((int) $row['leido'] === 0): ?>
                                                    <span class="badge bg-info ms-1">Nuevo</span>
                                                <?php endif; ?>
                                                <?php if (($row['baja'] ?? 'n') === 's'): ?>
                                                    <span class="badge bg-secondary ms-1">Baja</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['curso_mostrado'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['empresa_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars(format_short_date($row['f_ini']), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['canal'] !== '' ? $row['canal'] : 'Sin canal', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="badge <?= (int) $row['bonificacion'] === 1 ? 'bg-success' : 'bg-light text-dark' ?>"><?= (int) $row['bonificacion'] === 1 ? 'Si' : 'No' ?></span></td>
                                            <td><span class="badge <?= (int) $row['facturado'] === 1 ? 'bg-primary' : 'bg-light text-dark' ?>"><?= (int) $row['facturado'] === 1 ? 'Si' : 'No' ?></span></td>
                                            <td>
                                                <?php if ((int) $row['pagado'] === 1): ?>
                                                    <span class="badge bg-success">Pagado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ((int) $row['email_enviado'] === 1): ?>
                                                    <i class="fa-solid fa-circle-check text-success badge-enviado" title="Enviado"></i>
                                                <?php else: ?>
                                                    <i class="fa-solid fa-circle-xmark text-muted badge-enviado" title="No enviado"></i>
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

<?php
$extra_js = [
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
];

$inline_js = <<<JS
document.addEventListener('DOMContentLoaded', function () {
    $('#tablaInscripciones').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        },
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        order: [[1, 'desc']],
        responsive: true
    });

    const ctx = document.getElementById('chartInscripciones').getContext('2d');
    const chartLabels = {$chart_labels_js};
    const chartValues = {$chart_values_js};
    const chartColors = chartLabels.map(function (_, index) {
        return index === chartLabels.length - 1 ? 'rgba(0, 194, 146, 0.78)' : 'rgba(3, 169, 243, 0.58)';
    });
    const chartBorders = chartLabels.map(function (_, index) {
        return index === chartLabels.length - 1 ? '#00c292' : '#03a9f3';
    });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Inscripciones',
                data: chartValues,
                backgroundColor: chartColors,
                borderColor: chartBorders,
                borderWidth: 2,
                borderRadius: 8,
                barPercentage: 0.62,
                maxBarThickness: 52
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

    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) {
        return new bootstrap.Tooltip(el);
    });
});

function exportarCSV() {
    const table = document.getElementById('tablaInscripciones');
    const rows = table.querySelectorAll('tr');
    const csv = [];
    rows.forEach(function (row) {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(function (col) {
            const text = col.innerText.replace(/"/g, '""');
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(';'));
    });
    const blob = new Blob(['\\uFEFF' + csv.join('\\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'inscripciones_espana_' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
}
JS;

require_once __DIR__ . '/includes/footer.php';
?>
