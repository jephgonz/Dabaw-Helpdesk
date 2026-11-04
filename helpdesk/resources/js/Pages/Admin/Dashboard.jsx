import React from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import { appUrl } from '../../url';

export default function Dashboard({ users = [], ticketCounts = {}, newTicketCount = 0, openTaskCount = 0, pendingTaskCount = 0, completedThisQuarterCount = 0, pendingTasks = [] }) {
    const activeCount = users.filter((user) => Number(user.is_active) === 1).length;
    const adminCount = users.filter((user) => user.role === 'admin').length;
    const shortcuts = [
        ['/admin/tickets', '▤', 'Support requests', `${newTicketCount} new public report${newTicketCount === 1 ? '' : 's'} for IT.`],
        ['/admin/users', '＋', 'Create admin account', 'Add an administrator and manage access.'],
        ['/admin/users', '♙', 'Admin accounts', 'Review administrator account status.'],
        ['/admin/database', '▤', 'MySQL database', 'View connection status, tables, and backups.'],
    ];

    return (
        <DashboardLayout title="Administration" activePage="overview">
            <div className="overview-page">
                <div className="dashboard-title-row"><div><p className="eyebrow">ADMIN WORKSPACE</p><h1>Overview</h1><p className="dashboard-subtitle">Manage administrator accounts and monitor helpdesk activity.</p></div></div>
                <section className="admin-card overview-tasks-card" aria-labelledby="overview-tasks-title">
                    <div className="admin-card-heading overview-tasks-heading">
                        <div><span className="step-label">YOUR WORK</span><h2 id="overview-tasks-title">My pending tasks</h2><p>{openTaskCount} task{openTaskCount === 1 ? '' : 's'} still open</p></div>
                        <Link className="secondary-action" href={appUrl('/admin/accomplishments')}>View task board <span aria-hidden="true">→</span></Link>
                    </div>
                    <div className="overview-task-summary" aria-label="Task progress summary">
                        <article className="overview-task-summary-tile"><span>To do</span><strong>{pendingTaskCount}</strong></article>
                        <article className="overview-task-summary-tile completed"><span>Accomplished this quarter</span><strong>{completedThisQuarterCount}</strong></article>
                    </div>
                    {pendingTasks.length === 0 ? <p className="overview-tasks-empty">You’re all caught up. Add upcoming work on your accomplishment board.</p> :
                        <ul className="overview-task-list">{pendingTasks.map((task) => <li key={task.id}>
                            <Link className="overview-task-link" href={appUrl('/admin/accomplishments')}>
                                <span className="overview-task-dot" aria-hidden="true" />
                                <span className="overview-task-title">{task.title}</span>
                                <span className="overview-task-status">To do</span>
                                {task.due_date && <time dateTime={task.due_date}>Due {new Date(`${task.due_date.slice(0, 10)}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}</time>}
                            </Link>
                        </li>)}</ul>}
                </section>
                <section className="stat-grid" aria-label="Account and support summary">
                    <article className="stat-card"><span className="stat-icon blue">♙</span><span className="stat-label">Total accounts</span><strong>{users.length}</strong><small>Helpdesk users</small></article>
                    <article className="stat-card"><span className="stat-icon green">✓</span><span className="stat-label">Active accounts</span><strong>{activeCount}</strong><small>Can sign in</small></article>
                    <article className="stat-card"><span className="stat-icon gold">♜</span><span className="stat-label">Administrators</span><strong>{adminCount}</strong><small>Can manage accounts</small></article>
                    <article className="stat-card"><span className="stat-icon blue">▤</span><span className="stat-label">Open requests</span><strong>{Number(ticketCounts.new_count || 0) + Number(ticketCounts.in_progress_count || 0)}</strong><small>New and in progress</small></article>
                    <article className="stat-card"><span className="stat-icon green">✓</span><span className="stat-label">Tickets solved</span><strong>{Number(ticketCounts.resolved_this_week || 0)}</strong><small>Resolved this week</small></article>
                </section>
                <section className="quick-links" aria-label="Admin shortcuts">
                    {shortcuts.map(([href, icon, title, description]) => <Link className="quick-link" href={appUrl(href)} key={title}><span className="quick-link-icon">{icon}</span><span><strong>{title}</strong><small>{description}</small></span><span className="quick-link-arrow">→</span></Link>)}
                </section>
            </div>
        </DashboardLayout>
    );
}
