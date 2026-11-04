import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import FilePicker from '../../Components/FilePicker';
import { appUrl } from '../../url';

function ConnectionMonitor() {
    const [metrics, setMetrics] = useState(null);
    const [history, setHistory] = useState([]);
    const [error, setError] = useState('');
    const canvas = useRef(null);

    useEffect(() => {
        let active = true;
        const sample = async () => {
            try {
                const response = await fetch(appUrl('/admin/database/metrics'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Database metrics are unavailable.');
                if (active) {
                    setMetrics(data);
                    setError('');
                    setHistory((items) => [...items, { value: data.connected, time: Date.now() }].slice(-60));
                }
            } catch (reason) {
                if (active) setError(reason.message);
            }
        };
        sample();
        const timer = window.setInterval(sample, 5000);
        return () => { active = false; window.clearInterval(timer); };
    }, []);

    useEffect(() => {
        const element = canvas.current;
        if (!element) return undefined;
        const draw = () => {
            const bounds = element.getBoundingClientRect();
            const ratio = window.devicePixelRatio || 1;
            element.width = Math.max(1, Math.floor(bounds.width * ratio));
            element.height = Math.max(1, Math.floor(bounds.height * ratio));
            const context = element.getContext('2d');
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            const max = Math.max(4, ...history.map((point) => point.value));
            const pad = 18;
            context.clearRect(0, 0, bounds.width, bounds.height);
            context.strokeStyle = '#e9edf3';
            context.beginPath();
            context.moveTo(0, bounds.height - pad);
            context.lineTo(bounds.width, bounds.height - pad);
            context.stroke();
            if (history.length < 2) return;
            context.beginPath();
            history.forEach((point, index) => {
                const x = pad + (bounds.width - pad * 2) * index / (history.length - 1);
                const y = bounds.height - pad - (bounds.height - pad * 2) * point.value / max;
                if (index === 0) context.moveTo(x, y);
                else context.lineTo(x, y);
            });
            context.strokeStyle = '#315e9e';
            context.lineWidth = 2;
            context.stroke();
        };
        draw();
        const observer = new ResizeObserver(draw);
        observer.observe(element);
        return () => observer.disconnect();
    }, [history]);

    return <section className="database-monitor" aria-label="Live database connection metrics">
        <div className="database-live-summary">
            <article className="database-info-card"><span className="database-info-label">Current connections</span><strong>{metrics?.connected ?? '—'}</strong><small>Open client threads</small></article>
            <article className="database-info-card"><span className="database-info-label">Active threads</span><strong>{metrics?.running ?? '—'}</strong><small>Threads processing work</small></article>
            <article className="database-info-card"><span className="database-info-label">Peak connections</span><strong>{metrics?.maxUsed ?? '—'}</strong><small>{metrics?.maxConnections ?? '—'} configured maximum</small></article>
        </div>
        <section className="admin-card database-chart-card"><div className="admin-card-heading accounts-heading"><div><span className="step-label">LIVE SERVER STATUS</span><h2>Database connections</h2><p>Connection counts sampled every five seconds.</p></div><span className={`database-live-status ${error ? 'offline' : ''}`}><span />{error || (metrics ? 'Live' : 'Connecting…')}</span></div><div className="database-chart-wrap"><canvas ref={canvas} aria-label="Live chart of MySQL client connections" /></div><div className="database-chart-footer"><span>Recent samples</span><span>{metrics ? new Date(metrics.sampledAt).toLocaleTimeString() : 'Waiting for first sample'}</span></div></section>
    </section>;
}

function SqlWorkbench({ driver }) {
    const formRef = useRef(null);
    const [sql, setSql] = useState(driver === 'sqlite'
        ? "SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') ORDER BY name;"
        : 'SELECT TABLE_NAME, ENGINE, TABLE_ROWS\nFROM INFORMATION_SCHEMA.TABLES\nWHERE TABLE_SCHEMA = DATABASE()\nORDER BY TABLE_NAME;');
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');
    const [running, setRunning] = useState(false);

    const execute = async (event) => {
        event.preventDefault();
        const changesData = !/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i.test(sql);
        if (changesData && !window.confirm('This statement can change database data or schema. Continue?')) return;

        setRunning(true);
        setError('');
        setResult(null);
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!token) throw new Error('The security token is missing. Refresh the page and try again.');
            const response = await fetch(appUrl('/admin/database/query'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ sql, confirm_changes: changesData }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'The query could not be completed.');
            setResult(data);
        } catch (reason) {
            setError(reason.message || 'The query could not be completed.');
        } finally {
            setRunning(false);
        }
    };

    return <section className="admin-card database-workbench" aria-label="SQL workbench">
        <div className="admin-card-heading accounts-heading"><div><span className="step-label">SQL WORKBENCH</span><h2>Query editor</h2><p>Run a single SQL statement against the configured database. Results are limited to 200 rows.</p></div><span className="section-mark">SQL</span></div>
        <form ref={formRef} onSubmit={execute}>
            <label className="database-sql-label" htmlFor="database-sql">SQL statement</label>
            <textarea id="database-sql" className="database-sql-editor" value={sql} onChange={(event) => setSql(event.target.value)} onKeyDown={(event) => { if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); formRef.current?.requestSubmit(); } }} spellCheck="false" autoCapitalize="off" autoCorrect="off" />
            <div className="database-workbench-actions"><small>Ctrl + Enter to run · Data and schema changes require confirmation.</small><button className="sign-in-button" type="submit" disabled={running || !sql.trim()}>{running ? 'Running…' : 'Run query'}</button></div>
        </form>
        {error && <div className="dashboard-notice error" role="alert">{error}</div>}
        {result && <div className="database-query-result" role="status">
            <div className="database-query-meta"><strong>{result.columns.length ? `${result.rowCount} row${result.rowCount === 1 ? '' : 's'}` : `${result.affectedRows} row${result.affectedRows === 1 ? '' : 's'} affected`}</strong><span>{result.durationMs} ms</span></div>
            {result.columns.length > 0 && <div className="table-wrap"><table><thead><tr>{result.columns.map((column, index) => <th key={`${column}-${index}`}>{column}</th>)}</tr></thead><tbody>{result.rows.length === 0 ? <tr><td colSpan={result.columns.length}>Query returned no rows.</td></tr> : result.rows.map((row, rowIndex) => <tr key={rowIndex}>{result.columns.map((column, columnIndex) => {
                const value = row[columnIndex];
                return <td key={`${column}-${columnIndex}`}>{value === null ? <span className="muted-cell">NULL</span> : String(value)}</td>;
            })}</tr>)}</tbody></table></div>}
            {result.truncated && <small className="database-query-truncated">Output was capped at 200 rows and 50 columns. Refine the query to see more detail.</small>}
        </div>}
    </section>;
}

export default function Database({ driver = 'sqlite', databaseName, serverVersion, tables = [], connected = false, backupStatus = 'unavailable', backupChanges = 0, backupSkippedTables = [] }) {
    const form = useForm({ sql_file: null, confirm_replace: false });
    const cloneForm = useForm({ operation: 'backup', preview_token: '', confirm_clone: false });
    const { errors, flash } = usePage().props;
    const fileInput = useRef(null);
    const databaseType = driver === 'sqlite' ? 'SQLite' : 'MySQL';
    const [tableSearch, setTableSearch] = useState('');
    const [clonePreview, setClonePreview] = useState(null);
    const [clonePreviewError, setClonePreviewError] = useState('');
    const [clonePreviewing, setClonePreviewing] = useState(false);
    const filteredTables = useMemo(() => tables.filter((table) => table.name.toLowerCase().includes(tableSearch.trim().toLowerCase())), [tables, tableSearch]);
    const previewClone = async (operation) => {
        cloneForm.setData({ operation, preview_token: '', confirm_clone: false });
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!token) {
            setClonePreviewError('The security token is missing. Refresh the page and try again.');
            return;
        }
        setClonePreviewing(true);
        setClonePreviewError('');
        setClonePreview(null);
        try {
            const response = await fetch(appUrl('/admin/database/backup/preview'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ operation }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Could not preview the database backup or restore.');
            setClonePreview(data);
            cloneForm.setData({ operation, preview_token: data.token, confirm_clone: false });
        } catch (reason) {
            setClonePreviewError(reason.message || 'Could not preview the database backup or restore.');
        } finally {
            setClonePreviewing(false);
        }
    };
    const submitSync = (event) => {
        event.preventDefault();
        if (!clonePreview || !cloneForm.data.confirm_clone) return;
        cloneForm.post(appUrl('/admin/database/backup'), {
            onSuccess: () => setClonePreview(null),
        });
    };
    const syncableChanges = clonePreview?.tables?.some((table) => table.status !== 'ready') ? 0 : (clonePreview?.tables || []).reduce((total, table) => total + table.source_rows + table.target_rows, 0);
    const skippedTables = clonePreview?.tables?.filter((table) => table.status !== 'ready') || [];
    const submit = (event) => {
        event.preventDefault();
        if (!window.confirm('Import may drop and replace tables and data in the configured database. Continue?')) return;
        form.post(appUrl('/admin/database/import'));
    };

    return <DashboardLayout title="Local database" activePage="database">
        <div className="dashboard-title-row"><div><p className="eyebrow">SYSTEM</p><h1>Local database</h1><p className="dashboard-subtitle">The application uses local SQLite as its primary database.</p></div><div className="title-actions"><a className="secondary-action" href="https://console.aiven.io/" target="_blank" rel="noopener noreferrer">Aiven Console ↗</a><a className="secondary-action" href={appUrl('/admin/database')}>↻ Refresh</a></div></div>
        <section className="admin-card database-sync-card" aria-label="Aiven database backup and restore">
            <div className="admin-card-heading"><div><span className="step-label">LOCAL PRIMARY · AIVEN BACKUP</span><h2>Backup and restore</h2><p>Local SQLite is the live database. Back up copies local data to Aiven; restore replaces local data with the Aiven copy. Both operations preview all managed tables and require confirmation.</p></div><span className={`database-backup-state ${backupStatus}`}><span />{backupStatus === 'current' ? 'Backup current' : backupStatus === 'outdated' ? 'Backup outdated' : backupStatus === 'needs_attention' ? 'Schema needs attention' : 'Aiven unavailable'}</span></div>
            <p className="database-backup-status-detail">{backupStatus === 'current'
                ? 'Aiven matches the local primary database.'
                : backupStatus === 'outdated'
                    ? `${backupChanges.toLocaleString()} rows differ. Local is authoritative; back up to refresh Aiven.`
                    : backupStatus === 'needs_attention'
                        ? `The database needs attention${backupSkippedTables.length ? ` (${backupSkippedTables.join(', ')})` : ' or exceeds the comparison limit'}.`
                        : 'Could not compare local SQLite with Aiven. Check the Aiven connection settings.'}</p>
            {errors.clone && <div className="dashboard-notice error" role="alert">{errors.clone}</div>}
            {flash?.backupSnapshot && <p className="database-sync-backup">A private pre-operation snapshot was created. <a href={appUrl(`/admin/database/backup-snapshots/${encodeURIComponent(flash.backupSnapshot)}`)}>Download snapshot</a></p>}
            {clonePreviewError && <div className="dashboard-notice error" role="alert">{clonePreviewError}</div>}
            <div className="database-backup-actions">
                <button className="sign-in-button" type="button" disabled={clonePreviewing} onClick={() => previewClone('backup')}>{clonePreviewing && cloneForm.data.operation === 'backup' ? 'Comparing…' : 'Back up local to Aiven'}</button>
                <button className="secondary-action" type="button" disabled={clonePreviewing} onClick={() => previewClone('restore')}>{clonePreviewing && cloneForm.data.operation === 'restore' ? 'Comparing…' : 'Restore Aiven to local'}</button>
            </div>
            {clonePreview && <>
                <p className="database-sync-direction">Clone <strong>{clonePreview.source === 'local' ? 'Local SQLite' : 'Aiven MySQL'}</strong> → <strong>{clonePreview.target === 'local' ? 'Local SQLite' : 'Aiven MySQL'}</strong>. Destination table data will be replaced.</p>
                <div className="table-wrap database-sync-table-wrap"><table className="database-sync-table"><thead><tr><th>Table</th><th>Source rows</th><th>Destination rows</th><th>Result</th></tr></thead><tbody>{clonePreview.tables.map((table) => {
                    const matches = table.status === 'ready' && table.new_rows + table.changed_rows + table.target_only_rows === 0;
                    const result = table.status !== 'ready'
                        ? table.status.replaceAll('_', ' ')
                        : matches
                            ? 'Matches'
                            : `${table.new_rows.toLocaleString()} added · ${table.changed_rows.toLocaleString()} changed · ${table.target_only_rows.toLocaleString()} removed`;
                    return <tr key={table.name}>
                        <td data-label="Table"><strong>{table.name}</strong></td>
                        <td data-label="Source rows">{table.status === 'ready' ? table.source_rows.toLocaleString() : '—'}</td>
                        <td data-label="Destination rows">{table.status === 'ready' ? table.target_rows.toLocaleString() : '—'}</td>
                        <td data-label="Result"><span className={`database-sync-result ${table.status !== 'ready' ? 'attention' : matches ? 'matches' : 'changes'}`}>{result}</span></td>
                    </tr>;
                })}</tbody></table></div>
                {skippedTables.length > 0 && <p className="database-sync-no-changes">Clone is blocked until every managed table has the same schema and transactional storage on Aiven. Check migrations and table engines, then preview again.</p>}
                {syncableChanges > 0 && skippedTables.length === 0 ? <form className="database-sync-confirm" onSubmit={submitSync}>
                    <label><input type="checkbox" checked={cloneForm.data.confirm_clone} onChange={(event) => cloneForm.setData('confirm_clone', event.target.checked)} required /><span>I reviewed the preview. Replace all destination data in these tables with the source copy. A private snapshot of the destination will be created first.{cloneForm.data.operation === 'restore' ? ' The restore is blocked if it would remove or disable your current administrator account.' : ''}</span></label>
                    <button className="sign-in-button" type="submit" disabled={cloneForm.processing || !cloneForm.data.confirm_clone}>{cloneForm.processing ? 'Cloning…' : cloneForm.data.operation === 'backup' ? 'Confirm backup to Aiven' : 'Confirm restore to local'}</button>
                </form> : skippedTables.length === 0 ? <p className="database-sync-no-changes">Both copies already match. No clone is needed.</p> : null}
            </>}
        </section>
        <section className="admin-card accounts-card database-tables-card"><div className="admin-card-heading accounts-heading"><div><span className="step-label">SCHEMA OVERVIEW</span><h2>Tables</h2><p>Browse and manage table data in the configured {databaseType} database.</p></div><span className="account-count">{tables.length} tables</span></div>
            {tables.length === 0 ? <div className="empty-state"><span>▤</span><strong>No tables found</strong><small>The configured database has no visible tables.</small></div> : <>
                <label className="database-table-search" htmlFor="database-table-search">Filter tables<input id="database-table-search" type="search" value={tableSearch} onChange={(event) => setTableSearch(event.target.value)} placeholder="Search table names…" /></label>
                <div className="table-wrap"><table><thead><tr><th>Table</th><th>Engine</th><th>{driver === 'sqlite' ? 'Rows' : 'Estimated rows'}</th>{driver === 'mysql' && <th>Data + indexes</th>}<th /></tr></thead><tbody>{filteredTables.length === 0 ? <tr><td colSpan={driver === 'sqlite' ? 4 : 5}>No tables match that search.</td></tr> : filteredTables.map((table) => <tr key={table.name}><td><strong>{table.name}</strong></td><td>{table.engine || '—'}</td><td>{Number(table.rows_estimate || 0).toLocaleString()}</td>{driver === 'mysql' && <td>{(Number(table.bytes || 0) / 1048576).toFixed(2)} MB</td>}<td><Link className="table-action" href={`${appUrl('/admin/database/table')}?table=${encodeURIComponent(table.name)}`}>Browse rows <span>→</span></Link></td></tr>)}</tbody></table></div>
            </>}
        </section>
        <section className="database-summary" aria-label="Database connection details">
            <article className="database-info-card"><span className="database-info-label">Connection</span><strong className={`database-state ${connected ? 'online' : 'offline'}`}><span />{connected ? 'Connected' : 'Unavailable'}</strong><small>{connected ? `Using the configured ${databaseType} connection.` : `Check the configured ${databaseType} connection.`}</small></article>
            <article className="database-info-card"><span className="database-info-label">Database</span><strong>{databaseName}</strong><small>{driver === 'sqlite' ? 'Local database file in this project' : 'MySQL schema used by the workbench'}</small></article>
            <article className="database-info-card"><span className="database-info-label">{driver === 'sqlite' ? 'SQLite version' : 'Server version'}</span><strong>{serverVersion}</strong><small>{driver === 'sqlite' ? 'Local database engine' : 'Configured MySQL server'}</small></article>
        </section>
        {driver === 'mysql' && <ConnectionMonitor />}
        <SqlWorkbench driver={driver} />
        <section className="database-transfer-grid" aria-label="Database import and export">
            <article className="admin-card database-transfer-card"><div className="admin-card-heading"><div><span className="step-label">BACKUP</span><h2>Export database</h2><p>{driver === 'sqlite' ? 'Download a copy of the local SQLite database file, including its schema and all records.' : 'Download a Laravel-compatible MySQL SQL backup with table definitions and data, including Laravel migration history. It can also be opened in phpMyAdmin or MySQL Workbench.'}</p></div><span className="section-mark">↓</span></div><a className="secondary-action primary-action" href={appUrl('/admin/database/export')}>{driver === 'sqlite' ? 'Download SQLite backup' : 'Download SQL backup'}</a><p className="database-transfer-warning">The backup includes password hashes and other database contents. Keep the file private.</p></article>
            {driver === 'sqlite' ? <article className="admin-card database-transfer-card"><div className="admin-card-heading"><div><span className="step-label">RESTORE</span><h2>Restore database</h2><p>To restore a SQLite backup, stop the application and replace <code>database/database.sqlite</code> with the backup file.</p></div><span className="section-mark">↑</span></div><p className="database-transfer-warning">Keep a separate backup before replacing the local database file.</p></article> : <article className="admin-card database-transfer-card"><div className="admin-card-heading"><div><span className="step-label">RESTORE</span><h2>Restore database</h2><p>Restore a .sql backup into the database configured for this Laravel application. Pending Laravel migrations run after the import.</p></div><span className="section-mark">↑</span></div>
                {errors.sql_file && <div className="dashboard-notice error">{errors.sql_file}</div>}
                {errors.confirm_replace && <div className="dashboard-notice error">{errors.confirm_replace}</div>}
                <form className="account-form database-import-form" onSubmit={submit}>
                    <label className="id-picture-field" htmlFor="database-sql-file">SQL file <span aria-hidden="true">*</span><FilePicker id="database-sql-file" inputRef={fileInput} name="sql_file" accept=".sql,application/sql,text/plain" selectedFile={form.data.sql_file} emptyText="No SQL file selected" onChange={(event) => form.setData('sql_file', event.target.files[0] || null)} required /><small>Choose a .sql dump. Maximum size: 25 MB.</small></label>
                    <label className="database-import-confirm"><input type="checkbox" checked={form.data.confirm_replace} onChange={(event) => form.setData('confirm_replace', event.target.checked)} required /><span>I understand matching tables and their data may be replaced in this Laravel database.</span></label>
                    <button className="sign-in-button" type="submit" disabled={form.processing}>{form.processing ? 'Restoring…' : 'Restore SQL backup'}</button>
                </form><p className="database-transfer-warning">The restore uses this app’s configured database connection. Only table and data statements are accepted; database-level commands, routines, and triggers are not imported.</p>
            </article>}
        </section>
    </DashboardLayout>;
}
