<?php
/**
 * emagister.php — Módulo de Leads Emagister
 * Dashboard de Gestión Interna
 *
 * KPIs: Total Leads, Pendientes (No asignado), Ratio de Contacto
 * Tabla dinámica con DataTables, filtros por año y país
 */

require_once __DIR__ . '/config/conexion.php';

// ──────────────────────────────────────────────
// FILTROS (recibidos por GET, sanitizados)
// ──────────────────────────────────────────────
$filtro_anio = isset($_GET['anio']) ? (int) $_GET['anio'] : null;
$filtro_pais = isset($_GET['pais']) ? trim($_GET['pais']) : '';

// ──────────────────────────────────────────────
// CONSTRUIR WHERE DINÁMICO
// fecha es VARCHAR 'dd/mm/yyyy' → extraer año con STR_TO_DATE
// ──────────────────────────────────────────────
$where  = [];
$params = [];

if ($filtro_anio) {
    $where[]  = "YEAR(STR_TO_DATE(SUBSTRING_INDEX(fecha, ' ', 1), '%d/%m/%Y')) = :anio";
    $params[':anio'] = $filtro_anio;
}
if ($filtro_pais !== '') {
    $where[]  = 'pais = :pais';
    $params[':pais'] = $filtro_pais;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ──────────────────────────────────────────────
// KPIs
// ──────────────────────────────────────────────
// Total leads
$stmt = $pdo->prepare("SELECT COUNT(*) FROM emagister $where_sql");
$stmt->execute($params);
$total_leads = (int) $stmt->fetchColumn();

// Pendientes (estado = 'No asignado')
$sql_pend = "SELECT COUNT(*) FROM emagister $where_sql"
    . ($where ? " AND estado = 'No asignado'" : " WHERE estado = 'No asignado'");
$stmt = $pdo->prepare($sql_pend);
$stmt->execute($params);
$pendientes = (int) $stmt->fetchColumn();

// Ratio de contacto (enviado = 1)
$sql_env = "SELECT COUNT(*) FROM emagister $where_sql"
    . ($where ? ' AND enviado = 1' : ' WHERE enviado = 1');
$stmt = $pdo->prepare($sql_env);
$stmt->execute($params);
$contactados = (int) $stmt->fetchColumn();
$ratio_contacto = $total_leads > 0 ? round(($contactados / $total_leads) * 100, 1) : 0;

// ──────────────────────────────────────────────
// DATOS PARA LA TABLA
// ──────────────────────────────────────────────
$sql_tabla = "SELECT id_emagister, fecha, email, telefono, pais,
                     enviado, estado, resultado
              FROM emagister $where_sql
              ORDER BY id_emagister DESC";
$stmt = $pdo->prepare($sql_tabla);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// ──────────────────────────────────────────────
// LISTAS PARA FILTROS
// ──────────────────────────────────────────────
$anios = $pdo->query("SELECT DISTINCT YEAR(STR_TO_DATE(SUBSTRING_INDEX(fecha, ' ', 1), '%d/%m/%Y')) AS anio
                       FROM emagister
                       WHERE STR_TO_DATE(SUBSTRING_INDEX(fecha, ' ', 1), '%d/%m/%Y') IS NOT NULL
                       ORDER BY anio DESC")->fetchAll();
$paises = $pdo->query("SELECT DISTINCT pais FROM emagister WHERE pais != '' ORDER BY pais")->fetchAll();

// ──────────────────────────────────────────────
// HEADER (DRY)
// ──────────────────────────────────────────────
$page_title       = 'Emagister';
$active_page      = 'emagister';
$breadcrumb_title = 'Leads Emagister';
$breadcrumb_desc  = 'Gestión de leads procedentes de Emagister';
$breadcrumb_icon  = 'fa-solid fa-graduation-cap';
$breadcrumb_buttons = '<button class="btn btn-sm" data-bs-toggle="tooltip" title="Exportar datos" onclick="exportarCSV()"><i class="fa-solid fa-file-export"></i></button>';
$extra_css = '';

require_once __DIR__ . '/includes/header.php';
?>

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- ═══════════ KPIs ═══════════ -->
    <div class="notika-status-area" style="margin-top: 30px;">
        <div class="container">
            <div class="row g-4">
                <!-- Total Leads -->
                <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="kpi-icon bg-primary-green">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div>
                            <h2><?= number_format($total_leads, 0, ',', '.') ?></h2>
                            <p>Total Leads</p>
                        </div>
                    </div>
                </div>
                <!-- Pendientes -->
                <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="kpi-icon bg-warning-orange">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div>
                            <h2><?= number_format($pendientes, 0, ',', '.') ?></h2>
                            <p>Pendientes (No asignado)</p>
                        </div>
                    </div>
                </div>
                <!-- Ratio de Contacto -->
                <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="kpi-icon bg-info-blue">
                            <i class="fa-solid fa-percent"></i>
                        </div>
                        <div>
                            <h2><?= $ratio_contacto ?>%</h2>
                            <p>Ratio de Contacto</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════ FILTROS ═══════════ -->
    <div class="container" style="margin-top: 30px;">
        <div class="filter-section">
            <h6 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Filtros</h6>
            <form method="GET" action="emagister.php" id="formFiltros">
                <div class="row g-3 align-items-end">
                    <!-- Año -->
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                        <label for="filtroAnio" class="form-label">Año</label>
                        <select name="anio" id="filtroAnio" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($anios as $a): ?>
                                <option value="<?= (int) $a['anio'] ?>"
                                    <?= $filtro_anio === (int) $a['anio'] ? 'selected' : '' ?>>
                                    <?= (int) $a['anio'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- País -->
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                        <label for="filtroPais" class="form-label">País</label>
                        <select name="pais" id="filtroPais" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($paises as $p): ?>
                                <option value="<?= htmlspecialchars($p['pais'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $filtro_pais === $p['pais'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['pais'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Botones -->
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                        <button type="submit" class="btn btn-sm text-white" style="background:#00c292;">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Filtrar
                        </button>
                        <a href="emagister.php" class="btn btn-sm btn-outline-secondary ms-1">
                            <i class="fa-solid fa-rotate-left me-1"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══════════ TABLA DE LEADS ═══════════ -->
    <div class="data-table-area">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="recent-post-wrapper notika-shadow" style="padding: 24px; margin: 30px 0;">
                        <div class="recent-post-ctn">
                            <div class="recent-post-title">
                                <h2>Listado de Leads</h2>
                                <p>Se muestran <?= count($leads) ?> registros</p>
                            </div>
                        </div>
                        <div class="recent-post-items" style="margin-top: 20px;">
                            <div class="table-responsive">
                                <table id="tablaEmagister"
                                       class="table table-striped table-hover align-middle"
                                       style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha</th>
                                            <th>Email</th>
                                            <th>Teléfono</th>
                                            <th>País</th>
                                            <th>Enviado</th>
                                            <th>Estado</th>
                                            <th>Resultado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($leads as $row): ?>
                                        <tr>
                                            <td><?= (int) $row['id_emagister'] ?></td>
                                            <td><?= htmlspecialchars($row['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php if (!empty($row['email'])): ?>
                                                    <a href="mailto:<?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['telefono'])): ?>
                                                    <a href="tel:<?= htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['pais'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-center">
                                                <?php if ($row['enviado'] == 1): ?>
                                                    <i class="fa-solid fa-circle text-success" style="font-size:12px;"
                                                       title="Enviado"></i>
                                                <?php else: ?>
                                                    <i class="fa-solid fa-circle text-danger" style="font-size:12px;"
                                                       title="No enviado"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $estado_esc = htmlspecialchars($row['estado'], ENT_QUOTES, 'UTF-8');
                                                $badge_class = match($row['estado']) {
                                                    'No asignado' => 'bg-secondary',
                                                    'Asignado'    => 'bg-primary',
                                                    'Contactado'  => 'bg-success',
                                                    'Descartado'  => 'bg-danger',
                                                    default       => 'bg-secondary',
                                                };
                                                ?>
                                                <span class="badge <?= $badge_class ?>"><?= $estado_esc ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($row['resultado'], ENT_QUOTES, 'UTF-8') ?></td>
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
// ──────────────────────────────────────────────
// FOOTER con scripts específicos
// ──────────────────────────────────────────────
$extra_js = [
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js',
];

$inline_js = <<<'JSBLOCK'
document.addEventListener('DOMContentLoaded', function () {

    // ── DataTable ──
    $('#tablaEmagister').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        },
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        order: [[0, 'desc']],
        responsive: true
    });

    // ── Tooltips Bootstrap ──
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) {
        return new bootstrap.Tooltip(el);
    });
});

// ── Exportar CSV básico ──
function exportarCSV() {
    const table = document.getElementById('tablaEmagister');
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
    link.download = 'emagister_leads_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}
JSBLOCK;

require_once __DIR__ . '/includes/footer.php';
?>
