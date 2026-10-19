import React, { useEffect, useRef, useState } from 'react';
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

export default function Database({ databaseName, serverVersion, tables = [], connected = false }) {
    const form = useForm({ sql_file: null, confirm_replace: false });
    const { errors } = usePage().props;
    const fileInput = useRef(null);
    const submit = (event) => {
        event.preventDefault();
        if (!window.confirm('Import may drop and replace tables and data in the configured database. Continue?')) return;
        form.post(appUrl('/admin/database/import'));
    };

    return <DashboardLayout title="MySQL database" activePage="database">
        <div className="dashboard-title-row"><div><p className="eyebrow">SYSTEM</p><h1>MySQL database</h1><p className="dashboard-subtitle">Connection status and tables available to Helpdesk.</p></div><a className="secondary-action" href={appUrl('/admin/database')}>↻ Refresh</a></div>
        <section className="admin-card accounts-card database-tables-card"><div className="admin-card-heading accounts-heading"><div><span className="step-label">SCHEMA OVERVIEW</span><h2>Tables</h2><p>Browse and manage table data in the configured MySQL schema.</p></div><span className="account-count">{tables.length} tables</span></div>
            {tables.length === 0 ? <div className="empty-state"><span>▤</span><strong>No tables found</strong><small>The configured database has no visible tables.</small></div> :
                <div className="table-wrap"><table><thead><tr><th>Table</th><th>Engine</th><th>Estimated rows</th><th>Data + indexes</th><th /></tr></thead><tbody>{tables.map((table) => <tr key={table.name}><td><strong>{table.name}</strong></td><td>{table.engine || '—'}</td><td>{Number(table.rows_estimate || 0).toLocaleString()}</td><td>{(Number(table.bytes || 0) / 1048576).toFixed(2)} MB</td><td><Link className="table-action" href={`${appUrl('/admin/database/table')}?table=${encodeURIComponent(table.name)}`}>Browse rows <span>→</span></Link></td></tr>)}</tbody></table></div>}
        </section>
        <section className="database-summary" aria-label="Database connection details">
            <article className="database-info-card"><span className="database-info-label">Connection</span><strong className={`database-state ${connected ? 'online' : 'offline'}`}><span />{connected ? 'Connected' : 'Unavailable'}</strong><small>{connected ? 'Using the configured MySQL connection.' : 'Check the configured MySQL connection.'}</small></article>
            <article className="database-info-card"><span className="database-info-label">Database</span><strong>{databaseName}</strong><small>MySQL schema used by Helpdesk</small></article>
            <article className="database-info-card"><span className="database-info-label">Server version</span><strong>{serverVersion}</strong><small>Configured MySQL server</small></article>
        </section>
        <ConnectionMonitor />
        <section className="database-transfer-grid" aria-label="Database import and export">
            <article className="admin-card database-transfer-card"><div className="admin-card-heading"><div><span className="step-label">BACKUP</span><h2>Export database</h2><p>Download a Laravel-compatible MySQL SQL backup with table definitions and data, including Laravel migration history. It can also be opened in phpMyAdmin or MySQL Workbench.</p></div><span className="section-mark">↓</span></div><a className="secondary-action primary-action" href={appUrl('/admin/database/export')}>Download SQL backup</a><p className="database-transfer-warning">The backup includes password hashes and other database contents. Keep the file private.</p></article>
            <article className="admin-card database-transfer-card"><div className="admin-card-heading"><div><span className="step-label">RESTORE</span><h2>Restore database</h2><p>Restore a .sql backup into the database configured for this Laravel application. Pending Laravel migrations run after the import.</p></div><span className="section-mark">↑</span></div>
                {errors.sql_file && <div className="dashboard-notice error">{errors.sql_file}</div>}
                {errors.confirm_replace && <div className="dashboard-notice error">{errors.confirm_replace}</div>}
                <form className="account-form database-import-form" onSubmit={submit}>
                    <label className="id-picture-field" htmlFor="database-sql-file">SQL file <span aria-hidden="true">*</span><FilePicker id="database-sql-file" inputRef={fileInput} name="sql_file" accept=".sql,application/sql,text/plain" selectedFile={form.data.sql_file} emptyText="No SQL file selected" onChange={(event) => form.setData('sql_file', event.target.files[0] || null)} required /><small>Choose a .sql dump. Maximum size: 25 MB.</small></label>
                    <label className="database-import-confirm"><input type="checkbox" checked={form.data.confirm_replace} onChange={(event) => form.setData('confirm_replace', event.target.checked)} required /><span>I understand matching tables and their data may be replaced in this Laravel database.</span></label>
                    <button className="sign-in-button" type="submit" disabled={form.processing}>{form.processing ? 'Restoring…' : 'Restore SQL backup'}</button>
                </form><p className="database-transfer-warning">The restore uses this app’s configured database connection. Only table and data statements are accepted; database-level commands, routines, and triggers are not imported.</p>
            </article>
        </section>
    </DashboardLayout>;
}
