<?php
// includes/footer.php

// Set default path prefix jika belum diset
if (!isset($path_prefix)) {
    $path_prefix = '';
}
?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($load_chart) && $load_chart): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    <script src="<?php echo $path_prefix; ?>js/app.js"></script>
</body>
</html>
