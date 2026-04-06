# Cambios Realizados — Módulo Inscripciones

**Fecha:** 2026-04-04

---

## Archivos creados

### 1. `config/conexion.php`
- Conexión PDO a MySQL (`dashboard_gestion`) con charset `utf8mb4`.
- Manejo de errores con `PDO::ERRMODE_EXCEPTION`.
- Las credenciales por defecto apuntan a XAMPP local (`root` sin contraseña).

### 2. `inscripciones.php`
Página completa del módulo de inscripciones con 5 bloques funcionales:

| Bloque | Descripción |
|--------|-------------|
| **KPIs** | 3 tarjetas: Total inscripciones, Pendientes de pago (`pagado = 'No'`), Novedades (`leido = 0`) |
| **Filtros** | Selectores por Año, Empresa y Estado de Pago. Envío por GET con consultas parametrizadas (PDO) |
| **Gráfico** | Chart.js — comparativa barras: inscripciones del mes actual vs. mes anterior |
| **Tabla** | DataTables (Bootstrap 5) con columnas: Fecha, Asistente, Curso, Empresa, Perfil, Bonificado, Facturado, Pagado, Enviado. Filas con `leido = 0` resaltadas en amarillo y badge "Nuevo". Icono ✓ verde si `enviado = 1` |
| **Alertas** | Panel lateral: registros incompletos, impagos críticos (facturados sin pago), novedades sin leer, pendientes de pago |

#### Características técnicas
- **Seguridad**: Consultas preparadas PDO, `htmlspecialchars()` en toda salida, casting de enteros en parámetros numéricos.
- **Diseño**: Usa las clases CSS de Notika (`notika-shadow`, `breadcomb-area`, `sale-statistic-inner`, etc.) + CSS inline mínimo para KPIs.
- **Responsivo**: Grid Bootstrap 5 (`col-lg-*`, `col-md-*`, `col-sm-*`).
- **Navegación**: Header, navbar horizontal desktop y offcanvas mobile reproduciendo la estructura de la plantilla.
- **Exportación CSV**: Botón en breadcrumb para descargar la tabla visible.
- **Idioma**: DataTables configurado en español (`es-ES`).

#### Dependencias CDN
- Bootstrap 5.3.3 (CSS + JS)
- Font Awesome 6.5.1
- jQuery 3.7.1 (requerido por DataTables)
- DataTables 1.13.8 + Bootstrap 5 styling
- Chart.js 4.4.1

---

## Estructura de la base de datos esperada

```sql
-- Tabla: inscripciones
CREATE TABLE inscripciones (
    id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
    fecha_insc     DATE,
    inicio_curso   DATE,
    codigo_curso   VARCHAR(50),
    curso_nombre   VARCHAR(255),
    empresa        VARCHAR(255),
    asistente      VARCHAR(255),
    perfil         VARCHAR(100),
    bonificado     VARCHAR(10),
    facturado      VARCHAR(10),
    pagado         VARCHAR(10),
    leido          TINYINT(1) DEFAULT 0,
    enviado        TINYINT(1) DEFAULT 0
);
```

---

## Próximos pasos sugeridos
1. Probar en XAMPP con datos reales/simulados.
2. Crear módulo de Finanzas (`finanzas.php`).
3. Implementar login básico y control de acceso.
4. Añadir filtros AJAX para no recargar la página.

---

## Reestructuración: Carpeta `DASHBOARD_EMPRESA/`

**Fecha:** 2026-04-06

### Objetivo
Crear una estructura de proyecto independiente y limpia separada de los cientos de archivos de ejemplo de la plantilla Notika. Solo se conservan los assets "core" estrictamente necesarios.

### Nueva estructura de carpetas

```
DASHBOARD_EMPRESA/
├── index.php                  # Página de inicio con accesos rápidos
├── inscripciones.php          # Módulo de inscripciones (refactorizado)
├── config/
│   └── conexion.php           # Conexión PDO a MySQL
├── includes/
│   ├── header.php             # Cabecera reutilizable (DRY)
│   └── footer.php             # Pie de página reutilizable (DRY)
└── assets/
    ├── css/
    │   ├── style.css              # Notika — estilos principales de la plantilla
    │   ├── header-modern-clean.css # Notika — header moderno
    │   ├── navbar-stable.css      # Notika — barra de navegación
    │   ├── widgets-consistent.css # Notika — cards y widgets
    │   ├── responsive.css         # Notika — reglas responsive
    │   ├── mobile-menu.css        # Notika — menú mobile offcanvas
    │   └── custom.css             # Estilos propios del dashboard (KPIs, filtros, etc.)
    └── img/
        ├── favicon.ico
        └── logo/                  # SVGs del logotipo
```

### Archivos creados

#### `includes/header.php` — Cabecera DRY
- Recibe variables PHP antes del `require`: `$page_title`, `$active_page`, `$breadcrumb_title`, `$breadcrumb_desc`, `$breadcrumb_icon`, `$breadcrumb_buttons`, `$extra_css`.
- Genera el `<head>` completo con CDNs (Bootstrap 5.3.3, Font Awesome 6.5.1, Google Fonts).
- Apunta todos los `<link>` CSS a `assets/css/`.
- Menú de navegación simplificado a solo 2 opciones: **Inicio** e **Inscripciones** (desktop + mobile offcanvas).
- Marca automáticamente la pestaña activa según `$active_page`.
- Breadcrumb parametrizable con icono, título, descripción y botones opcionales.

#### `includes/footer.php` — Pie de página DRY
- Footer con copyright dinámico.
- Scripts base: jQuery 3.7.1 + Bootstrap 5.3.3 Bundle.
- Acepta `$extra_js` (array de URLs) para scripts específicos de cada página.
- Acepta `$inline_js` (string) para JavaScript inline de la página.

#### `index.php` — Página de inicio
- Tarjeta de bienvenida con gradiente verde (#00c292).
- Accesos rápidos a secciones: Inscripciones (activo), Finanzas y Actividad (próximamente).
- Usa `includes/header.php` y `includes/footer.php` sin scripts extra.

#### `inscripciones.php` — Refactorizado
- Toda la lógica PHP de consultas se mantiene intacta.
- Se eliminó el HTML duplicado del header/nav/footer → ahora usa `includes/header.php` y `includes/footer.php`.
- Los estilos inline de KPIs y filtros se movieron a `assets/css/custom.css`.
- DataTables CSS se carga como `<link>` adicional solo en esta página.
- Chart.js + DataTables JS se pasan como `$extra_js` al footer.
- El JS inline se pasa como `$inline_js` (heredoc PHP).

#### `assets/css/custom.css` — Estilos propios
Centraliza los estilos que antes estaban inline en `inscripciones.php`:
- `.kpi-card` — tarjetas de KPIs con hover.
- `.kpi-icon` con variantes de color (green, orange, blue).
- `.row-novedad` — resaltado de filas no leídas.
- `.filter-section` — panel de filtros.
- `.welcome-card` — tarjeta de bienvenida del index.
- `.quick-link-card` — tarjetas de acceso rápido.

### Assets core copiados de la plantilla original

| Archivo | Función |
|---------|---------|
| `style.css` | Estilos base de Notika (layout, cards, breadcrumb, footer, tablas…) |
| `header-modern-clean.css` | Header con logo y nav icons |
| `navbar-stable.css` | Barra horizontal de navegación desktop |
| `widgets-consistent.css` | Estilos de widgets y cards |
| `responsive.css` | Media queries y reglas responsive |
| `mobile-menu.css` | Offcanvas mobile + ocultación en desktop |
| `img/logo/*` | 9 variantes SVG/PNG del logotipo |
| `img/favicon.ico` | Favicon |

### Dependencias CDN (no se incluyeron archivos locales de estas librerías)

| Librería | Versión | Uso |
|----------|---------|-----|
| Bootstrap | 5.3.3 | CSS + JS Bundle (grid, componentes, offcanvas) |
| Font Awesome | 6.5.1 | Iconos |
| jQuery | 3.7.1 | Requerido por DataTables |
| DataTables | 1.13.8 | Tabla interactiva en inscripciones |
| Chart.js | 4.4.1 | Gráfico de barras en inscripciones |
| Google Fonts (Roboto) | — | Tipografía |

### Cómo añadir una nueva sección

1. Crear `nueva_seccion.php` en la raíz de `DASHBOARD_EMPRESA/`.
2. Definir las variables del header antes del include:
   ```php
   $page_title  = 'Mi Sección';
   $active_page = 'mi_seccion';  // añadir al nav en header.php
   // ... más variables opcionales
   require_once __DIR__ . '/includes/header.php';
   ```
3. Escribir el contenido HTML de la página.
4. Incluir el footer al final:
   ```php
   $extra_js  = [...];  // opcional
   $inline_js = '...';  // opcional
   require_once __DIR__ . '/includes/footer.php';
   ```
5. Añadir el nuevo `<li>` / `<a>` en `includes/header.php` (nav desktop + mobile).

---

## Módulo Emagister (`emagister.php`)

**Fecha:** 2026-04-07

### Descripción
Nuevo módulo para gestionar leads procedentes de la plataforma Emagister. Incluye KPIs, filtros y tabla interactiva.

### Tabla de base de datos esperada

```sql
CREATE TABLE emagister (
    id_emagister INT AUTO_INCREMENT PRIMARY KEY,
    fecha        VARCHAR(20),      -- formato 'dd/mm/yyyy'
    email        VARCHAR(255),
    telefono     VARCHAR(50),
    pais         VARCHAR(100),
    enviado      TINYINT(1) DEFAULT 0,
    estado       VARCHAR(100),
    resultado    VARCHAR(255)
);
```

### Funcionalidades

| Bloque | Descripción |
|--------|-------------|
| **KPIs** | 3 tarjetas: Total Leads, Pendientes (`estado = 'No asignado'`), Ratio de Contacto (`% enviado = 1`) |
| **Filtros** | Selectores por Año (extraído de `fecha` VARCHAR) y País. Consultas parametrizadas PDO |
| **Tabla** | DataTables (Bootstrap 5) con columnas: #, Fecha, Email, Teléfono, País, Enviado, Estado, Resultado |
| **Exportación** | Botón CSV en breadcrumb |

### Características técnicas
- **Seguridad**: Consultas preparadas PDO, `htmlspecialchars()` en toda salida, casting de enteros.
- **Fecha VARCHAR**: `STR_TO_DATE(SUBSTRING_INDEX(fecha, ' ', 1), '%d/%m/%Y')` para extraer año (4 dígitos).
- **Indicador visual**: Punto verde (●) si `enviado = 1`, rojo si `enviado = 0`.
- **Badges de estado**: Colores según estado (`No asignado` → gris, `Asignado` → azul, `Contactado` → verde, `Descartado` → rojo).
- **Enlaces interactivos**: `mailto:` en emails, `tel:` en teléfonos.
- **JS inline**: Usa heredoc `<<<'JSBLOCK'` (nowdoc) para evitar problemas de interpolación PHP.

### Cambios en archivos existentes
- **`includes/header.php`**: Añadido enlace "Emagister" (`fa-graduation-cap`) en nav desktop y mobile offcanvas. Slug: `emagister`.
