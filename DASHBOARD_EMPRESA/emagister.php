<?php
/**
 * emagister.php
 * Modulo de Leads Emagister
 */

require_once __DIR__ . '/config/conexion.php';

$filtro_anio = isset($_GET['anio']) ? (int) $_GET['anio'] : null;
$filtro_pais = isset($_GET['pais']) ? trim($_GET['pais']) : '';

$baseFrom = "FROM emagister e
LEFT JOIN seminarios_elearnings s ON s.id = e.curso";

$where = [];
$params = [];

if ($filtro_anio) {
    $where[] = 'YEAR(e.fecha) = :anio';
    $params[':anio'] = $filtro_anio;
}
if ($filtro_pais !== '') {
    $where[] = 'e.pais = :pais';
    $params[':pais'] = $filtro_pais;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sqlTotal = "SELECT COUNT(*) {$baseFrom} {$whereSql}";
$stmt = $pdo->prepare($sqlTotal);
$stmt->execute($params);
$total_leads = (int) $stmt->fetchColumn();

$sqlEnviados = "SELECT COUNT(*) {$baseFrom}";
$whereEnviados = array_merge($where, ['e.email_enviado = 1']);
if ($whereEnviados) {
    $sqlEnviados .= ' WHERE ' . implode(' AND ', $whereEnviados);
}
$stmt = $pdo->prepare($sqlEnviados);
$stmt->execute($params);
$emails_enviados = (int) $stmt->fetchColumn();

$pendientes_envio = max($total_leads - $emails_enviados, 0);
$ratio_contacto = $total_leads > 0 ? round(($emails_enviados / $total_leads) * 100, 1) : 0.0;

$sqlTabla = "SELECT
    e.id,
    e.fecha,
    e.nombre,
    e.apellidos,
    e.email,
    e.telefono,
    e.pais,
    e.provincia,
    e.email_enviado,
    e.curso,
    COALESCE(NULLIF(TRIM(s.titulo), ''), CONCAT('Curso #', e.curso)) AS curso_mostrado
{$baseFrom}
{$whereSql}
ORDER BY e.fecha DESC, e.id DESC";
$stmt = $pdo->prepare($sqlTabla);
$stmt->execute($params);
$leads = $stmt->fetchAll();

$anios = $pdo->query(
    "SELECT DISTINCT YEAR(fecha) AS anio
     FROM emagister
     WHERE fecha IS NOT NULL
     ORDER BY anio DESC"
)->fetchAll();

$paises = $pdo->query(
    "SELECT DISTINCT pais
     FROM emagister
     WHERE TRIM(COALESCE(pais, '')) <> ''
     ORDER BY pais"
)->fetchAll();

$sin_email = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM emagister
     WHERE NULLIF(TRIM(COALESCE(email, '')), '') IS NULL"
)->fetchColumn();

$sin_telefono = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM emagister
     WHERE NULLIF(TRIM(COALESCE(telefono, '')), '') IS NULL"
)->fetchColumn();

$paises_activos = (int) $pdo->query(
    "SELECT COUNT(DISTINCT pais)
     FROM emagister
     WHERE TRIM(COALESCE(pais, '')) <> ''"
)->fetchColumn();

$page_title = 'Emagister';
$active_page = 'emagister';
$breadcrumb_title = 'Leads Emagister';
$breadcrumb_desc = 'Gestion de leads procedentes de Emagister';
$breadcrumb_icon = 'fa-solid fa-graduation-cap';
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
                            <h2><?= number_format($total_leads, 0, ',', '.') ?></h2>
                            <p>Total leads</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="kpi-icon bg-warning-orange">
                            <i class="fa-solid fa-paper-plane"></i>
                        </div>
                        <div>
                            <h2><?= number_format($emails_enviados, 0, ',', '.') ?></h2>
                            <p>Emails enviados</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="kpi-icon bg-info-blue">
                            <i class="fa-solid fa-percent"></i>
                        </div>
                        <div>
                            <h2><?= htmlspecialchars((string) $ratio_contacto, ENT_QUOTES, 'UTF-8') ?>%</h2>
                            <p>Ratio de envio</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: 30px;">
        <div class="filter-section">
            <h6 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Filtros</h6>
            <form method="GET" action="emagister.php" id="formFiltros">
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
                            <label for="filtroPais" class="form-label">Pais</label>
                            <select name="pais" id="filtroPais" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach ($paises as $pais): ?>
                                    <option value="<?= htmlspecialchars($pais['pais'], ENT_QUOTES, 'UTF-8') ?>" <?= $filtro_pais === $pais['pais'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pais['pais'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                        <div class="kpi-card h-100" style="padding:16px 18px;">
                            <h2 style="font-size:22px;"><?= number_format($paises_activos, 0, ',', '.') ?></h2>
                            <p style="margin-top:4px;">Paises activos</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-12 col-sm-6 col-12">
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-filter-primary">
                                <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                            </button>
                            <a href="emagister.php" class="btn btn-filter-secondary">
                                <i class="fa-solid fa-rotate-left"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="container" style="margin-bottom: 30px;">
        <div class="row g-3">
            <div class="col-lg-4 col-md-6 col-12">
                <div class="alert alert-warning mb-0" role="alert">
                    <i class="fa-solid fa-clock me-2"></i>
                    Pendientes de envio: <strong><?= $pendientes_envio ?></strong>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-12">
                <div class="alert alert-secondary mb-0" role="alert">
                    <i class="fa-solid fa-at me-2"></i>
                    Leads sin email: <strong><?= $sin_email ?></strong>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-12">
                <div class="alert alert-info mb-0" role="alert">
                    <i class="fa-solid fa-phone me-2"></i>
                    Leads sin telefono: <strong><?= $sin_telefono ?></strong>
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
                            <h2>Listado de leads</h2>
                            <p>Se muestran <?= count($leads) ?> registros</p>
                        </div>
                        <div class="recent-post-items" style="margin-top: 20px;">
                            <div class="table-responsive">
                                <table id="tablaEmagister" class="table table-striped table-hover align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha</th>
                                            <th>Nombre</th>
                                            <th>Email</th>
                                            <th>Telefono</th>
                                            <th>Pais</th>
                                            <th>Provincia</th>
                                            <th>Curso</th>
                                            <th>Enviado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($leads as $row): ?>
                                        <?php $fullName = trim(($row['nombre'] ?? '') . ' ' . ($row['apellidos'] ?? '')); ?>
                                        <tr>
                                            <td><?= (int) $row['id'] ?></td>
                                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha'])), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($fullName !== '' ? $fullName : 'Sin nombre', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php if (!empty($row['email'])): ?>
                                                    <a href="mailto:<?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['telefono'])): ?>
                                                    <a href="tel:<?= htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['pais'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['provincia'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($row['curso_mostrado'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-center">
                                                <?php if ((int) $row['email_enviado'] === 1): ?>
                                                    <i class="fa-solid fa-circle-check text-success badge-enviado" title="Enviado"></i>
                                                <?php else: ?>
                                                    <i class="fa-solid fa-circle-xmark text-muted badge-enviado" title="Pendiente"></i>
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
];

$inline_js = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    $('#tablaEmagister').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        },
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        order: [[1, 'desc']],
        responsive: true
    });

    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) {
        return new bootstrap.Tooltip(el);
    });
});

function exportarCSV() {
    const table = document.getElementById('tablaEmagister');
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
    const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'emagister_' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
}
JS;

require_once __DIR__ . '/includes/footer.php';
?>
