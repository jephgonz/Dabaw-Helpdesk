<?php
declare(strict_types=1);

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#10254a">
    <title>MySQL database · Dabaw Helpdesk</title><link rel="icon" type="image/x-icon" href="<?= $escape($basePath) ?>/public/assets/images/SP.ico">
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/public/assets/css/app.css">
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <?php require __DIR__ . '/partials/sidebar.php'; ?>
        <main class="dashboard-main">
            <?php require __DIR__ . '/partials/topbar.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-title-row"><div><p class="eyebrow">SYSTEM</p><h1>MySQL database</h1><p class="dashboard-subtitle">Connection status and tables available to the Helpdesk application.</p></div><a class="secondary-action" href="<?= $escape($basePath) ?>/admin/database">↻ Refresh</a></div>
                <?php if ($error): ?><div class="dashboard-notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>

                <section class="database-summary" aria-label="Database connection details">
                    <article class="database-info-card"><span class="database-info-label">Connection</span><strong class="database-state <?= $error ? 'offline' : 'online' ?>"><span></span><?= $error ? 'Unavailable' : 'Connected' ?></strong><small><?= $error ? 'Check the configured MySQL connection.' : 'TLS connection using the configured CA certificate.' ?></small></article>
                    <article class="database-info-card"><span class="database-info-label">Database</span><strong><?= $escape($databaseName ?: 'Not configured') ?></strong><small>MySQL schema used by Helpdesk</small></article>
                    <article class="database-info-card"><span class="database-info-label">Server version</span><strong><?= $escape($serverVersion ?: '—') ?></strong><small><?= $escape($connectionConfig['host']) ?>:<?= (int) $connectionConfig['port'] ?></small></article>
                </section>

                <section class="database-monitor" aria-labelledby="database-monitor-title">
                    <div class="database-live-summary" aria-label="Live MySQL connection metrics">
                        <article class="database-info-card"><span class="database-info-label">Current connections</span><strong id="db-current-connections">—</strong><small>Open client threads</small></article>
                        <article class="database-info-card"><span class="database-info-label">Active threads</span><strong id="db-running-threads">—</strong><small>Threads processing work</small></article>
                        <article class="database-info-card"><span class="database-info-label">Peak connections</span><strong id="db-peak-connections">—</strong><small><span id="db-max-connections">—</span> configured maximum</small></article>
                    </div>
                    <section class="admin-card database-chart-card">
                        <div class="admin-card-heading accounts-heading"><div><span class="step-label">LIVE SERVER STATUS</span><h2 id="database-monitor-title">Database connections</h2><p>Connected client threads sampled every five seconds. History is kept while this page remains open.</p></div><span class="database-live-status" id="db-live-status"><span></span><span id="db-live-status-text">Connecting…</span></span></div>
                        <div class="database-chart-wrap"><canvas id="database-connections-chart" role="img" aria-label="Live chart of MySQL client connections over time">Live database connections chart</canvas></div>
                        <div class="database-chart-footer"><span>Recent samples</span><span id="db-last-updated">Waiting for first sample</span></div>
                    </section>
                </section>
                <section class="admin-card accounts-card database-tables-card">
                    <div class="admin-card-heading accounts-heading"><div><span class="step-label">SCHEMA OVERVIEW</span><h2>Tables</h2><p>Table sizes and row counts are estimates reported by MySQL.</p></div><span class="account-count"><?= count($tables) ?> tables</span></div>
                    <?php if (!$tables && !$error): ?>
                        <div class="empty-state"><span>▤</span><strong>No tables found</strong><small>The configured database has no visible tables.</small></div>
                    <?php elseif ($tables): ?>
                        <div class="table-wrap"><table><thead><tr><th>Table</th><th>Engine</th><th>Estimated rows</th><th>Data + indexes</th><th></th></tr></thead><tbody>
                        <?php foreach ($tables as $table): ?>
                            <tr><td><strong><?= $escape($table['table_name']) ?></strong></td><td><?= $escape($table['engine'] ?: '—') ?></td><td><?= number_format((int) ($table['estimated_rows'] ?? 0)) ?></td><td><?= number_format(((int) ($table['total_bytes'] ?? 0)) / 1048576, 2) ?> MB</td><td><a class="table-action" href="<?= $escape($basePath) ?>/admin/database/table?table=<?= rawurlencode((string) $table['table_name']) ?>">Browse rows <span>→</span></a></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </section>
                <p class="database-note"><span aria-hidden="true">ⓘ</span> Open a table to add, edit, or delete rows. Password and secret fields are never displayed. Database credentials remain in the private local <code>.env</code> file.</p>
                <footer class="dashboard-footer">Dabaw Helpdesk <span>·</span> Database overview</footer>
            </div>
        </main>
    </div>
    <script>
        (() => {
            const endpoint = <?= json_encode($basePath . '/admin/database/metrics', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const canvas = document.getElementById('database-connections-chart');
            const context = canvas.getContext('2d');
            const history = [];
            const current = document.getElementById('db-current-connections');
            const running = document.getElementById('db-running-threads');
            const peak = document.getElementById('db-peak-connections');
            const capacity = document.getElementById('db-max-connections');
            const status = document.getElementById('db-live-status');
            const statusText = document.getElementById('db-live-status-text');
            const updated = document.getElementById('db-last-updated');
            let requestInProgress = false;

            const drawChart = () => {
                const rect = canvas.getBoundingClientRect();
                const ratio = window.devicePixelRatio || 1;
                canvas.width = Math.max(1, Math.floor(rect.width * ratio));
                canvas.height = Math.max(1, Math.floor(rect.height * ratio));
                context.setTransform(ratio, 0, 0, ratio, 0, 0);
                const width = rect.width;
                const height = rect.height;
                const pad = {top: 14, right: 16, bottom: 28, left: 42};
                const plotWidth = Math.max(1, width - pad.left - pad.right);
                const plotHeight = Math.max(1, height - pad.top - pad.bottom);
                const highest = Math.max(4, ...history.map(point => point.value));
                const maxValue = Math.ceil(highest * 1.2);

                context.clearRect(0, 0, width, height);
                context.font = '10px "DM Sans", Segoe UI, sans-serif';
                context.textAlign = 'right';
                context.textBaseline = 'middle';
                for (let tick = 0; tick <= 4; tick++) {
                    const value = maxValue - (maxValue * tick / 4);
                    const y = pad.top + plotHeight * tick / 4;
                    context.strokeStyle = '#e9edf3';
                    context.lineWidth = 1;
                    context.beginPath();
                    context.moveTo(pad.left, y);
                    context.lineTo(width - pad.right, y);
                    context.stroke();
                    context.fillStyle = '#8792a4';
                    context.fillText(String(Math.round(value)), pad.left - 9, y);
                }
                if (history.length === 0) {
                    context.textAlign = 'center';
                    context.fillStyle = '#8792a4';
                    context.fillText('Collecting connection samples…', width / 2, height / 2);
                    return;
                }
                const points = history.map((point, index) => ({
                    x: pad.left + (history.length === 1 ? plotWidth : plotWidth * index / (history.length - 1)),
                    y: pad.top + plotHeight - (point.value / maxValue * plotHeight),
                    label: point.label,
                }));
                if (points.length > 1) {
                    const area = context.createLinearGradient(0, pad.top, 0, pad.top + plotHeight);
                    area.addColorStop(0, 'rgba(49,94,158,.20)');
                    area.addColorStop(1, 'rgba(49,94,158,.015)');
                    context.beginPath();
                    context.moveTo(points[0].x, pad.top + plotHeight);
                    points.forEach(point => context.lineTo(point.x, point.y));
                    context.lineTo(points[points.length - 1].x, pad.top + plotHeight);
                    context.closePath();
                    context.fillStyle = area;
                    context.fill();
                }
                context.beginPath();
                points.forEach((point, index) => index ? context.lineTo(point.x, point.y) : context.moveTo(point.x, point.y));
                context.strokeStyle = '#315e9e';
                context.lineWidth = 2.5;
                context.lineJoin = 'round';
                context.lineCap = 'round';
                context.stroke();
                const last = points[points.length - 1];
                context.beginPath();
                context.arc(last.x, last.y, 4, 0, Math.PI * 2);
                context.fillStyle = '#315e9e';
                context.fill();
                context.textAlign = 'left';
                context.textBaseline = 'top';
                context.fillStyle = '#8792a4';
                context.fillText(points[0].label, pad.left, height - 18);
                if (points.length > 2) {
                    const middle = points[Math.floor(points.length / 2)];
                    context.textAlign = 'center';
                    context.fillText(middle.label, middle.x, height - 18);
                }
                context.textAlign = 'right';
                context.fillText(last.label, width - pad.right, height - 18);
            };

            const sample = async () => {
                if (requestInProgress || document.hidden) return;
                requestInProgress = true;
                try {
                    const response = await fetch(endpoint, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
                    if (!response.ok) throw new Error('Metrics endpoint unavailable');
                    const metrics = await response.json();
                    const now = new Date();
                    history.push({value: metrics.connected, label: now.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'})});
                    if (history.length > 60) history.shift();
                    current.textContent = metrics.connected.toLocaleString();
                    running.textContent = metrics.running.toLocaleString();
                    peak.textContent = metrics.maxUsed.toLocaleString();
                    capacity.textContent = metrics.maxConnections.toLocaleString();
                    updated.textContent = 'Updated ' + now.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
                    status.classList.add('is-live');
                    status.classList.remove('is-error');
                    statusText.textContent = 'Live · 5 sec';
                    drawChart();
                } catch (error) {
                    status.classList.remove('is-live');
                    status.classList.add('is-error');
                    statusText.textContent = 'Unavailable';
                    updated.textContent = 'Could not load database metrics';
                    drawChart();
                } finally {
                    requestInProgress = false;
                }
            };

            new ResizeObserver(drawChart).observe(canvas);
            sample();
            window.setInterval(sample, 5000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) sample(); });
        })();
    </script>
</body>
</html>
