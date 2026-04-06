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

    <!-- ═══════════ SCRIPTS COMUNES ═══════════ -->
    <!-- jQuery (requerido por DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php
    /**
     * Variable opcional: $extra_js
     * Array de URLs de scripts adicionales a cargar antes del JS inline.
     * Ejemplo: $extra_js = [
     *     'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
     *     'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
     * ];
     */
    if (!empty($extra_js) && is_array($extra_js)):
        foreach ($extra_js as $js_url): ?>
    <script src="<?= htmlspecialchars($js_url, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endforeach;
    endif; ?>

    <?php
    /**
     * Variable opcional: $inline_js
     * Bloque de JavaScript inline para la página.
     */
    if (!empty($inline_js)): ?>
    <script>
    <?= $inline_js ?>
    </script>
    <?php endif; ?>

</body>
</html>
