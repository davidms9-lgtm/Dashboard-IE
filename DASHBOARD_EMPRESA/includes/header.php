<?php
/**
 * header.php - Cabecera reutilizable (DRY)
 *
 * Variables esperadas (definir ANTES del include):
 *   $page_title       - Titulo del <title>
 *   $active_page      - Slug de la pagina activa
 *   $breadcrumb_title - Texto del breadcrumb
 *   $breadcrumb_desc  - Descripcion bajo el titulo
 *   $breadcrumb_icon  - Clase FA del icono
 *   $extra_css        - CSS adicional en <style>
 */

$page_title = $page_title ?? 'Dashboard';
$active_page = $active_page ?? 'inicio';
$breadcrumb_title = $breadcrumb_title ?? $page_title;
$breadcrumb_desc = $breadcrumb_desc ?? '';
$breadcrumb_icon = $breadcrumb_icon ?? 'fa-solid fa-house';
$extra_css = $extra_css ?? '';

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> | Dashboard Gestion Interna</title>
    <meta name="description" content="Dashboard de Gestion Interna - <?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">

    <link rel="shortcut icon" type="image/x-icon" href="<?= $base ?>/assets/img/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/header-modern-clean.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/navbar-stable.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/widgets-consistent.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/responsive.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/mobile-menu.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/custom.css">

    <?php if ($extra_css): ?>
    <style><?= $extra_css ?></style>
    <?php endif; ?>
</head>

<body>
    <header class="notika-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-4 col-6">
                    <div class="notika-brand">
                        <a href="<?= $base ?>/index.php">
                            <img src="<?= $base ?>/assets/img/logo/notika-logo-horizontal.svg" alt="Dashboard"
                                 class="d-none d-md-block" style="height:40px"
                                 onerror="this.onerror=null;this.outerHTML='<span style=\'font-size:22px;font-weight:700;color:#00c292\'>Dashboard</span>';">
                            <img src="<?= $base ?>/assets/img/logo/notika-icon.svg" alt="Dashboard"
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

    <nav class="navbar navbar-expand-lg notika-navbar d-none d-lg-block">
        <div class="container">
            <div class="navbar-collapse">
                <ul class="navbar-nav w-100 justify-content-center">
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'inicio' ? 'active' : '' ?>" href="<?= $base ?>/index.php">
                            <i class="fa-solid fa-house"></i> <span>Inicio</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'inscripciones_espana' ? 'active' : '' ?>" href="<?= $base ?>/inscripciones.php">
                            <i class="fa-solid fa-user-plus"></i> <span>Inscripciones ES</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'inscripciones_latam' ? 'active' : '' ?>" href="<?= $base ?>/inscripciones_latam.php">
                            <i class="fa-solid fa-earth-americas"></i> <span>Inscripciones LATAM</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === 'emagister' ? 'active' : '' ?>" href="<?= $base ?>/emagister.php">
                            <i class="fa-solid fa-graduation-cap"></i> <span>Emagister</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileNavOffcanvas">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title">Dashboard</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <nav class="navbar-nav flex-column">
                <a class="nav-link px-3 py-2 border-bottom <?= $active_page === 'inicio' ? 'active' : '' ?>" href="<?= $base ?>/index.php">
                    <i class="fa-solid fa-house me-2"></i> Inicio
                </a>
                <a class="nav-link px-3 py-2 border-bottom <?= $active_page === 'inscripciones_espana' ? 'active' : '' ?>" href="<?= $base ?>/inscripciones.php">
                    <i class="fa-solid fa-user-plus me-2"></i> Inscripciones ES
                </a>
                <a class="nav-link px-3 py-2 border-bottom <?= $active_page === 'inscripciones_latam' ? 'active' : '' ?>" href="<?= $base ?>/inscripciones_latam.php">
                    <i class="fa-solid fa-earth-americas me-2"></i> Inscripciones LATAM
                </a>
                <a class="nav-link px-3 py-2 border-bottom <?= $active_page === 'emagister' ? 'active' : '' ?>" href="<?= $base ?>/emagister.php">
                    <i class="fa-solid fa-graduation-cap me-2"></i> Emagister
                </a>
            </nav>
        </div>
    </div>

    <div class="breadcomb-area">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="breadcomb-list">
                        <div class="row">
                            <div class="col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="breadcomb-wp">
                                    <div class="breadcomb-icon">
                                        <i class="<?= htmlspecialchars($breadcrumb_icon, ENT_QUOTES, 'UTF-8') ?>"></i>
                                    </div>
                                    <div class="breadcomb-ctn">
                                        <h2><?= htmlspecialchars($breadcrumb_title, ENT_QUOTES, 'UTF-8') ?></h2>
                                        <?php if ($breadcrumb_desc): ?>
                                            <p><?= htmlspecialchars($breadcrumb_desc, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="breadcomb-report text-end">
                                    <?php if (isset($breadcrumb_buttons)) echo $breadcrumb_buttons; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
