<?php
/**
 * inscripciones_latam.php
 * Modulo de Inscripciones LATAM
 */

require_once __DIR__ . '/config/conexion.php';

function fetch_count_latam(PDO $pdo, string $fromSql, array $where, array $params): int
{
    $sql = "SELECT COUNT(*) {$fromSql}";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function format_latam_date(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    $formats = ['d/m/y', 'd/m/Y', 'Y-m-d'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('d/m/Y');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $value;
}

function build_latam_attendee_name(array $row): string
{
    $fullName = trim(($row['nombre_asistente'] ?? '') . ' ' . ($row['apellidos_asistente'] ?? ''));
    return $fullName !== '' ? $fullName : 'Sin asignar';
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

$baseFrom = "FROM inscripciones_latam i
LEFT JOIN Empresas_latam e ON e.id = i.id_empresa";
$fechaLatamSql = "COALESCE(
    STR_TO_DATE(i.fecha_inscripcion, '%d/%m/%Y'),
    STR_TO_DATE(i.fecha_inscripcion, '%d/%m/%y')
)";

$where = [
    'i.prova = 0',
    '(e.prova IS NULL OR e.prova = 0)',
];
$params = [];

if ($filtro_anio) {
    $where[] = "YEAR({$fechaLatamSql}) = :anio";
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

$total_inscripciones = fetch_count_latam($pdo, $baseFrom, $where, $params);
$pendientes_pago = fetch_count_latam($pdo, $baseFrom, array_merge($where, ['i.pagado = 0']), $params);
$novedades = fetch_count_latam($pdo, $baseFrom, array_merge($where, ['i.leido = 0']), $params);

$sqlTabla = "SELECT
    i.id,
    i.fecha_inscripcion,
    i.f_ini,
    i.curso,
    i.nombre_asistente,
    i.apellidos_asistente,
    i.proforma,
    i.pagado,
    i.canal,
    i.leido,
    i.descartada,
    i.baja,
    i.factura,
    i.grupo,
    i.accion_formativa,
    COALESCE(NULLIF(TRIM(e.razon_social), ''), 'Empresa no asociada') AS empresa_nombre
{$baseFrom}
{$whereSql}
ORDER BY {$fechaLatamSql} DESC, i.id DESC";
$stmt = $pdo->prepare($sqlTabla);
$stmt->execute($params);
$inscripciones = $stmt->fetchAll();

$chartReferenceWhere = array_merge($where, ["{$fechaLatamSql} IS NOT NULL"]);
$chartReferenceSql = "SELECT MAX({$fechaLatamSql}) {$baseFrom}";
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
        "{$fechaLatamSql} >= :fecha_inicio_chart",
        "{$fechaLatamSql} < :fecha_fin_chart",
    ]
);
$chartParams = $params;
$chartParams[':fecha_inicio_chart'] = $fecha_inicio_grafica->format('Y-m-d');
$chartParams[':fecha_fin_chart'] = $fecha_fin_grafica->format('Y-m-d');
$chartSql = "SELECT DATE_FORMAT({$fechaLatamSql}, '%Y-%m') AS mes, COUNT(*) AS total
{$baseFrom}
WHERE " . implode(' AND ', $chartWhere) . "
GROUP BY DATE_FORMAT({$fechaLatamSql}, '%Y-%m')
ORDER BY mes";
$stmt = $pdo->prepare($chartSql);
$stmt->execute($chartParams);
$chartData = $stmt->fetchAll();

$incompletosCondition = "(
    NULLIF(TRIM(COALESCE(e.razon_social, '')), '') IS NULL
    OR NULLIF(TRIM(COALESCE(i.nombre_asistente, '')), '') IS NULL
    OR NULLIF(TRIM(COALESCE(i.curso, '')), '') IS NULL
)";
$incompletos = fetch_count_latam($pdo, $baseFrom, array_merge($where, [$incompletosCondition]), $params);
$facturas_sin_pago = fetch_count_latam($pdo, $baseFrom, array_merge($where, ["TRIM(COALESCE(i.factura, '')) <> ''", 'i.pagado = 0']), $params);
$descartadas = fetch_count_latam($pdo, $baseFrom, array_merge($where, ['i.descartada = 1']), $params);

$anios = $pdo->query(
    "SELECT DISTINCT YEAR(COALESCE(STR_TO_DATE(fecha_inscripcion, '%d/%m/%Y'), STR_TO_DATE(fecha_inscripcion, '%d/%m/%y'))) AS anio
     FROM inscripciones_latam
     WHERE COALESCE(STR_TO_DATE(fecha_inscripcion, '%d/%m/%Y'), STR_TO_DATE(fecha_inscripcion, '%d/%m/%y')) IS NOT NULL
       AND prova = 0
     ORDER BY anio DESC"
)->fetchAll();

$empresas = $pdo->query(
    "SELECT DISTINCT razon_social AS empresa
     FROM Empresas_latam
     WHERE TRIM(COALESCE(razon_social, '')) <> ''
       AND prova = 0
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

$page_title = 'Inscripciones LATAM';
$active_page = 'inscripciones_latam';
$breadcrumb_title = 'Inscripciones LATAM';
$breadcrumb_desc = 'Gestion y control de inscripciones LATAM';
$breadcrumb_icon = 'fa-solid fa-earth-americas';
$breadcrumb_buttons = '<button class="btn btn-sm" data-bs-toggle="tooltip" title="Exportar datos" onclick="exportarCSV()"><i class="fa-solid fa-file-export"></i></button>';
$extra_css = '';

require_once __DIR__ . '/includes/header.php';
?>

    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

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
            <form method="GET" action="inscripciones_latam.php" id="formFiltros">
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
                            <a href="inscripciones_latam.php" class="btn btn-filter-secondary">
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
                            <form method="GET" action="inscripciones_latam.php" class="chart-range-form">
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
                                    <label for="rangoGraficaLatam" class="form-label">Vista de la gr&aacute;fica</label>
                                    <select name="rango_grafica" id="rangoGraficaLatam" class="form-select form-select-sm" onchange="this.form.submit()">
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
                            <canvas id="chartInscripcionesLatam"></canvas>
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
                            <?php if ($facturas_sin_pago > 0): ?>
                                <div class="alert alert-danger d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-file-invoice-dollar me-2"></i>
                                    <div><strong><?= $facturas_sin_pago ?></strong> factura(s) emitidas sin pago asociado.</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($descartadas > 0): ?>
                                <div class="alert alert-secondary d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-ban me-2"></i>
                                    <div><strong><?= $descartadas ?></strong> registro(s) descartados.</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($novedades > 0): ?>
                                <div class="alert alert-info d-flex align-items-center" role="alert">
                                    <i class="fa-solid fa-envelope me-2"></i>
                                    <div><strong><?= $novedades ?></strong> registro(s) pendientes de revision.</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($incompletos === 0 && $facturas_sin_pago === 0 && $descartadas === 0 && $novedades === 0): ?>
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
                            <h2>Listado de inscripciones LATAM</h2>
                            <p>Se muestran <?= count($inscripciones) ?> registros</p>
                        </div>
                        <div class="recent-post-items" style="margin-top: 20px;">
                            <div class="dashboard-table-shell">
                                <table id="tablaInscripcionesLatam" class="table table-striped table-hover align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha</th>
                                            <th>Asistente</th>
                                            <th>Curso</th>
                                            <th>Empresa</th>
                                            <th>Inicio</th>
                                            <th>Canal</th>
                                            <th>Proforma</th>
                                            <th>Pagado</th>
                                            <th>Factura</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($inscripciones as $row): ?>
                                        <?php $attendeeName = build_latam_attendee_name($row); ?>
                                        <tr class="<?= (int) $row['leido'] === 0 ? 'row-novedad' : '' ?>">
                                            <td><?= (int) $row['id'] ?></td>
                                            <td><?= htmlspecialchars(format_latam_date($row['fecha_inscripcion']), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?= htmlspecialchars($attendeeName, ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ((int) $row['leido'] === 0): ?>
                                                    <span class="badge bg-info ms-1">Nuevo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['curso'] !== '' ? $row['curso'] : ($row['accion_formativa'] !== '' ? $row['accion_formativa'] : 'Curso sin identificar'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['empresa_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars(format_latam_date($row['f_ini']), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['canal'] !== '' ? $row['canal'] : 'Sin canal', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['proforma'] !== '' ? $row['proforma'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php if ((int) $row['pagado'] === 1): ?>
                                                    <span class="badge bg-success">Pagado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['factura'] !== '' ? $row['factura'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php if ((int) $row['descartada'] === 1): ?>
                                                    <span class="badge bg-secondary">Descartada</span>
                                                <?php elseif (($row['baja'] ?? 'n') === 's'): ?>
                                                    <span class="badge bg-dark">Baja</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Activa</span>
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
    'https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js',
    'https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
];

$inline_js = <<<JS
document.addEventListener('DOMContentLoaded', function () {
    $('#tablaInscripcionesLatam').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json',
            search: '',
            searchPlaceholder: 'Buscar en la tabla'
        },
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        order: [[1, 'desc']],
        autoWidth: false,
        scrollX: false,
        responsive: true,
        pagingType: 'simple_numbers',
        dom: "<'dt-toolbar row align-items-center g-3'<'col-sm-12 col-lg-6'l><'col-sm-12 col-lg-6'f>>" +
             "t" +
             "<'dt-footer row align-items-center g-3'<'col-sm-12 col-md-6'i><'col-sm-12 col-md-6'p>>",
        infoCallback: function(settings, start, end, max, total) {
            return 'Mostrando ' + total + ' registro' + (total === 1 ? '' : 's');
        }
    });

    const ctx = document.getElementById('chartInscripcionesLatam').getContext('2d');
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
    const table = document.getElementById('tablaInscripcionesLatam');
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
    link.download = 'inscripciones_latam_' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
}
JS;

require_once __DIR__ . '/includes/footer.php';
?>
