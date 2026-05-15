Listo, ya tengo todo el contexto. Te dejo el panorama claro y los puntos donde hay que decidir.

1. Lo que tenéis hecho
Estructura limpia en DASHBOARD_EMPRESA/ — la decisión de separar del Notika fue acertada:

index.php — landing con 3 accesos rápidos
inscripciones.php (ES), inscripciones_latam.php, emagister.php — los 3 módulos reales
config/conexion.php — PDO a XAMPP local, BD dbs13710048
includes/header.php + includes/footer.php — layout DRY parametrizable
Los 3 módulos siguen el mismo patrón sólido: 3 KPIs + filtros (año/empresa/pago) + Chart.js + DataTable + panel de alertas + exportación CSV. Las consultas usan PDO preparadas y htmlspecialchars. Bien hecho.

2. Lo que pide el PDF y aún NO tenéis
El tutor pide un MVP con 5 bloques y vosotros tenéis cubiertos 1,5 de los 5:

Bloque PDF	Estado
3.1 Resumen ejecutivo (Home con KPIs globales)	❌ Falta — el index.php actual es solo 3 atajos
3.2 Finanzas y cobros	❌ Falta — facturas_2026, abonos, neto, % cobrado
3.3 Inscripciones y cursos	🟡 Parcial — listas hechas, pero falta "cursos con baja actividad", ratio inscripciones/finalización, agregación por área temática
3.4 Actividad y uso	❌ Falta — visitas_mes casi vacía, sin tabla alumnos
3.5 Empresas y clientes	❌ Falta — ranking, deuda acumulada, nuevos vs recurrentes
Y los entregables previos del PDF (sección 15) que el tutor espera antes de programar más: diccionario de tablas, fichas de KPIs con fórmulas, wireframes de Home y Finanzas, plan técnico, backlog priorizado, cronograma 6-8 semanas. Nada de eso existe todavía en el repo.

3. Problemas serios del esquema que vais a chocar pronto
Tras leer el dump completo, hay cosas que romperán los KPIs si no las tenéis en cuenta (las he guardado en memoria como project-schema-gotchas para futuras sesiones):

id_curso es varchar en inscripciones pero int en inscripciones_latam / facturas_2026 / emagister. No podéis hacer un UNION directo ES↔LATAM.
Cursos (5 filas demo) NO es el catálogo real. El catálogo real es seminarios_elearnings (~250 filas, ~130 columnas). Vuestro inscripciones.php hace LEFT JOIN Cursos c ON c.id_curso = i.id_curso y por eso depende del fallback de i.curso.
No hay tabla pagos ni alumnos. Los pagos viven como flags en inscripciones (pagado, facturado, abonado). Los alumnos están desnormalizados como nombre_asistente. KPIs de "alumnos activos / sin actividad" no tienen fuente clara.
Fechas como varchar con formatos mixtos ('15/05/2020', '2026-04-15', '15/03/23'). Ya lo estás resolviendo en LATAM con STR_TO_DATE + COALESCE.
facturas_2026 sugiere particionamiento manual anual; consultas multi-año necesitarán UNION.
pais solo lista LATAM+USA; España queda como string literal 'esp'.
seminarios_elearnings en latin1, el resto en utf8mb4 → riesgo de mojibake con tildes/ñ.
Mucha tabla con 0-5 filas → los gráficos van a salir prácticamente vacíos. Es esperable hasta que el tutor os dé el dump completo.
4. Mi recomendación de próximo paso
El PDF es explícito en su sección 17: durante las primeras 2 semanas no se debería intentar "hacer el dashboard completo". Tres caminos razonables; tú decides cuál:

¿Por dónde quieres tirar?

/////////////////////////////////////////////////
REVISAR PARTE FINAL DE LA RESPUESTAst
Bugs corregidos
1. Registros de prueba (prova) se colaban en la vista de producción
La BD usa una columna prova (varchar en inscripciones, tinyint en el resto) para marcar registros de prueba que no deberían contar como inscripciones reales. Ninguna consulta lo filtraba.

Archivo	Cambio
DASHBOARD_EMPRESA/inscripciones.php:78-81	i.prova <> 's' + (e.prova IS NULL OR e.prova = 0) en el $where base
DASHBOARD_EMPRESA/inscripciones_latam.php:66-69	i.prova = 0 + (e.prova IS NULL OR e.prova = 0) en el $where base
Dropdowns de Empresa (ES y LATAM)	añadido AND prova = 0
Dropdown de Año (ES y LATAM)	añadido AND prova = 0 / AND prova <> 's'
DASHBOARD_EMPRESA/emagister.php	no aplica — la tabla emagister no tiene columna prova
Hice el filtro con e.prova IS NULL OR e.prova = 0 (no solo e.prova = 0) para que inscripciones cuya empresa no exista en la tabla (caso real: hay registros con id_empresa=89, 178, 342 y los Empresas solo tienen IDs concretos) no se pierdan por el LEFT JOIN.

2. Los contadores de "Alertas operativas" ignoraban los filtros del usuario
Si filtrabas por año=2025, las KPI cards de arriba se filtraban pero el panel de alertas seguía mostrando totales globales (2010-2026). UX inconsistente.

Archivo	Contadores afectados
DASHBOARD_EMPRESA/inscripciones.php:183-184	$incompletos, $impagos_criticos ahora usan fetch_count() con $where + $params
DASHBOARD_EMPRESA/inscripciones_latam.php:152-154	$incompletos, $facturas_sin_pago, $descartadas igual
DASHBOARD_EMPRESA/emagister.php:92-112	$sin_email, $sin_telefono, $paises_activos igual (creé helper fetch_emagister_count)
Lo que NO toqué y deberías validar conmigo
Hay tres cosas que parecen "bugs" pero son decisiones de negocio, no decido yo:

¿Las inscripciones con baja='s' cuentan en "Total inscripciones"? Hoy se cuentan. En inscripciones.php y inscripciones_latam.php el badge "Baja" aparece en la tabla pero el total las incluye.
¿Las inscripciones con descartada=1 (solo LATAM) cuentan en "Total"? Hoy se cuentan. La alerta dice "X descartadas" como aviso, pero las suma igual.
Footer dice "Template by Colorlib" en includes/footer.php:8. Para un dashboard interno de empresa probablemente quieras quitarlo.
Dime cuál es la siguiente faena.

/////////////////////////////////////////////////

Lo que tienes ahora en DASHBOARD_EMPRESA/index.php

┌────────────────────────────────────────────────────────┐
│ Filtros globales:  [Año ▼]            [Limpiar]        │
├────────────────────────────────────────────────────────┤
│ KPIs operativos (fila 1):                              │
│  Total inscr. │ Este mes │ Pendientes │ Empresas       │
├────────────────────────────────────────────────────────┤
│ KPIs financieros + extras (fila 2):                    │
│  Facturado €  │ Abonado € │ Leads Emag. │ Alertas      │
├────────────────────────────────────────────────────────┤
│ Evolución 12 meses (barras apiladas      │   Donut     │
│  España + LATAM)  col-lg-8               │  ES vs LATAM│
├────────────────────────────────────────────────────────┤
│ Top 5 empresas       │  Últimas 10 inscripciones       │
│ (cruza ES + LATAM)   │  (mezcla ES + LATAM)            │
├────────────────────────────────────────────────────────┤
│ Resumen alertas: impagos ES · facturas LATAM · descart.│
└────────────────────────────────────────────────────────┘
Características clave:

8 KPIs consolidados ES + LATAM, igual de exigentes con el filtro de año y el filtro prova que los módulos individuales
Filtro global año se aplica a todo: KPIs, gráfica, tablas, alertas. Auto-submit al cambiar.
Gráfica apilada (Chart.js stacked bar) con tooltip que muestra el total ES+LATAM del mes
Donut con % ES vs LATAM. Si no hay datos, muestra empty state con icono
Top 5 empresas combina ranking ES + LATAM ordenado por inscripciones
Últimas 10 inscripciones funden ambas tablas con normalización de fecha LATAM (STR_TO_DATE con doble formato %d/%m/%Y y %d/%m/%y)
Country badges (verde España / azul LATAM) reutilizables en futuros módulos
Empty states en todas las tablas y en el donut si el filtro deja datos vacíos
Filtros prova y e.prova consistentes con el resto de módulos — los datos de prueba no se cuelan
Cambios en archivos:

DASHBOARD_EMPRESA/index.php — reescrito completo (~470 LOC)
DASHBOARD_EMPRESA/assets/css/custom.css — añadidos 3 colores nuevos para KPIs (bg-dark-slate, bg-danger-red, bg-purple-soft), estilos .home-mini-table, .home-empty, .country-badge
Verificado:

✅ php -l pasa
⏳ No he podido probarlo en navegador porque XAMPP está apagado
Tu siguiente paso para validarlo:

Arranca XAMPP (Apache + MySQL)
Abre http://localhost/DASHBOARD_EMPRESA/index.php (o la ruta que uses)
Prueba con ?anio=2026 en la URL para verificar que el filtro funciona
Si hay algún problema visual o de SQL, dímelo y lo afino. Si todo se ve bien, ¿comiteamos esto y empezamos con el módulo de Finanzas?