<?php
// ============================================================
// Layout Footer — closes the page-content and loads all JS
// ============================================================
?>
    </main><!-- /page-content -->
</div><!-- /main-content -->

</div><!-- /app-shell -->

<!-- ── Scripts ───────────────────────────────────────────────── -->
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Core Scripts -->
<script src="<?= BASE_URL ?>js/theme.js"></script>
<script src="<?= BASE_URL ?>js/api_client.js"></script>
<script src="<?= BASE_URL ?>js/app.js"></script>
<script src="<?= BASE_URL ?>js/notifications.js"></script>

<!-- Page-specific scripts injected by individual pages -->
<?php if (!empty($extraScripts)): ?>
    <?php foreach ($extraScripts as $script): ?>
    <script src="<?= BASE_URL ?>js/<?= htmlspecialchars($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($inlineScript)): ?>
<script>
<?= $inlineScript ?>
</script>
<?php endif; ?>

</body>
</html>
