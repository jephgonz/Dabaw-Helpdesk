import React, { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import ActionIcon from '../../Components/ActionIcon';
import { appUrl } from '../../url';

const statusLabels = { todo: 'To do', completed: 'Completed' };

function AccomplishmentForm({ task, onCancel, onSaved }) {
    const form = useForm({
        title: task?.title || '',
        description: task?.description || '',
        due_date: task?.due_date || '',
        status: task?.status || 'todo',
    });
    const submit = (event) => {
        event.preventDefault();
        form.post(appUrl(task ? `/admin/accomplishments/${task.id}` : '/admin/accomplishments'), { onSuccess: onSaved });
    };

    return (
        <form className="account-form accomplishment-form" onSubmit={submit}>
            <label>Task title<input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} maxLength="200" required placeholder="What did you work on?" />{form.errors.title && <small className="field-error">{form.errors.title}</small>}</label>
            <label>Details<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows="3" maxLength="5000" placeholder="Add context or an outcome for your report." />{form.errors.description && <small className="field-error">{form.errors.description}</small>}</label>
            <div className="accomplishment-form-row">
                <label>Due date<input type="date" value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} />{form.errors.due_date && <small className="field-error">{form.errors.due_date}</small>}</label>
                <label>Status<select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="todo">To do</option><option value="completed">Completed</option></select>{form.errors.status && <small className="field-error">{form.errors.status}</small>}</label>
            </div>
            {form.errors.form && <small className="field-error">{form.errors.form}</small>}
            <div className="form-actions"><button className="sign-in-button" type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : task ? 'Save changes' : 'Add to board'}</button><button className="secondary-action accomplishment-cancel-button" type="button" onClick={onCancel}>Cancel</button></div>
        </form>
    );
}

function formatDate(value) {
    if (!value) return '';
    const datePart = value.slice(0, 10);
    const [year, month, day] = datePart.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function Accomplishments({ accomplishments = [] }) {
    const currentDate = new Date();
    const [statusFilter, setStatusFilter] = useState('all');
    const [reportYear, setReportYear] = useState(String(currentDate.getFullYear()));
    const [reportQuarter, setReportQuarter] = useState(String(Math.floor(currentDate.getMonth() / 3) + 1));
    const [showForm, setShowForm] = useState(false);
    const [editingTask, setEditingTask] = useState(null);
    const [pendingAction, setPendingAction] = useState(null);
    const quarterStartMonth = (Number(reportQuarter) - 1) * 3 + 1;
    const quarterStart = `${reportYear}-${String(quarterStartMonth).padStart(2, '0')}`;
    const quarterEndDate = new Date(Number(reportYear), quarterStartMonth + 2, 1);
    const quarterEnd = `${quarterEndDate.getFullYear()}-${String(quarterEndDate.getMonth() + 1).padStart(2, '0')}-01`;
    const completedThisQuarter = accomplishments.filter((task) => task.status === 'completed' && task.completed_at >= `${quarterStart}-01` && task.completed_at < quarterEnd).length;
    const canDownloadReport = /^\d{4}$/.test(reportYear) && Number(reportYear) >= 2000 && Number(reportYear) <= 2100;
    const counts = useMemo(() => ({
        todo: accomplishments.filter((task) => task.status === 'todo').length,
        completed: accomplishments.filter((task) => task.status === 'completed').length,
    }), [accomplishments]);
    const visibleTasks = statusFilter === 'all' ? accomplishments : accomplishments.filter((task) => task.status === statusFilter);
    const closeForm = () => { setShowForm(false); setEditingTask(null); };
    const removeTask = (task) => {
        if (window.confirm(`Remove "${task.title}" from your accomplishment board?`)) {
            setPendingAction({ id: task.id, action: 'remove' });
            router.post(appUrl(`/admin/accomplishments/${task.id}/delete`), {}, {
                onFinish: () => setPendingAction(null),
            });
        }
    };
    const completeTask = (task) => {
        setPendingAction({ id: task.id, action: 'complete' });
        router.post(appUrl(`/admin/accomplishments/${task.id}/complete`), {}, {
            onFinish: () => setPendingAction(null),
        });
    };

    return (
        <DashboardLayout title="Accomplishment board" activePage="accomplishments">
            <div className="dashboard-title-row accomplishment-page-heading"><div><p className="eyebrow">PERSONAL WORK TRACKER</p><h1>Accomplishment board</h1><p className="dashboard-subtitle">Track your work from planned to completed, then export a quarter-end report.</p></div><button className="secondary-action primary-action accomplishment-add-button" type="button" onClick={() => { setEditingTask(null); setShowForm((open) => !open); }}>{showForm ? 'Close form' : '+ Add task'}</button></div>
            <section className="accomplishment-stats" aria-label="Task summary">
                <article className="accomplishment-stat"><span>To do</span><strong>{counts.todo}</strong><small>Waiting to start</small></article>
                <article className="accomplishment-stat completed"><span>Completed this quarter</span><strong>{completedThisQuarter}</strong><small>For Q{reportQuarter} {reportYear}</small></article>
            </section>
            {showForm && <section className="admin-card accomplishment-editor"><div className="admin-card-heading"><div><span className="step-label">NEW TASK</span><h2>Add an accomplishment</h2><p>Completed tasks are dated automatically and included in the matching quarterly report.</p></div></div><AccomplishmentForm key="new" onCancel={closeForm} onSaved={closeForm} /></section>}
            <section className="admin-card accomplishment-board-card">
                <div className="admin-card-heading accomplishment-heading"><div><span className="step-label">YOUR WORK</span><h2>Tasks and accomplishments</h2><p>{counts.completed} completed · {counts.todo} still open</p></div><span className="account-count">{accomplishments.length} total</span></div>
                <div className="accomplishment-toolbar">
                    <div className="accomplishment-filters" role="group" aria-label="Filter tasks by status">
                        {[['all', 'All'], ['todo', 'To do'], ['completed', 'Completed']].map(([value, label]) => <button key={value} className={`filter-chip${statusFilter === value ? ' active' : ''}`} type="button" aria-pressed={statusFilter === value} onClick={() => setStatusFilter(value)}>{label}</button>)}
                    </div>
                    <form className="quarter-report-form" onSubmit={(event) => { event.preventDefault(); if (canDownloadReport) window.location.assign(appUrl(`/admin/accomplishments/report?year=${encodeURIComponent(reportYear)}&quarter=${reportQuarter}`)); }}>
                        <label>Quarter<select aria-label="Report quarter" value={reportQuarter} onChange={(event) => setReportQuarter(event.target.value)}>{[1, 2, 3, 4].map((quarter) => <option key={quarter} value={quarter}>Q{quarter}</option>)}</select></label>
                        <label>Year<input aria-label="Report year" type="number" min="2000" max="2100" required value={reportYear} onChange={(event) => setReportYear(event.target.value)} /></label>
                        <button className="secondary-action report-download" type="submit" disabled={!canDownloadReport}>Download CSV</button>
                    </form>
                </div>
                {visibleTasks.length === 0 ? <div className="empty-state"><span>✓</span><strong>{accomplishments.length === 0 ? 'Your board is ready' : 'No tasks in this view'}</strong><small>{accomplishments.length === 0 ? 'Add your first task to start tracking your work.' : 'Choose another status filter to see more tasks.'}</small></div> :
                    <div className="accomplishment-list">{visibleTasks.map((task) => <article className="accomplishment-item" key={task.id}>
                        <div className="accomplishment-item-main"><div className="accomplishment-item-heading"><h3>{task.title}</h3><span className={`accomplishment-status ${task.status}`}>{statusLabels[task.status]}</span></div>{task.description && <p>{task.description}</p>}<div className="accomplishment-dates">{task.due_date && <span>Due {formatDate(task.due_date)}</span>}{task.completed_at && <span>Completed {formatDate(task.completed_at)}</span>}</div>{editingTask?.id === task.id && <div className="accomplishment-item-edit" id={`accomplishment-edit-${task.id}`}><AccomplishmentForm key={task.id} task={task} onCancel={() => setEditingTask(null)} onSaved={() => setEditingTask(null)} /></div>}</div>
                        <div className="accomplishment-actions">
                            {task.status !== 'completed' && <button className="task-action complete action-icon-button" type="button" aria-label="Mark complete" title="Mark complete" disabled={pendingAction !== null} onClick={() => completeTask(task)}><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false"><path d="m5 12 4 4L19 6" /></svg></button>}
                            <button className="task-action action-icon-button" type="button" aria-label={editingTask?.id === task.id ? 'Collapse edit task form' : 'Edit task'} title={editingTask?.id === task.id ? 'Collapse edit form' : 'Edit task'} aria-expanded={editingTask?.id === task.id} aria-controls={editingTask?.id === task.id ? `accomplishment-edit-${task.id}` : undefined} disabled={pendingAction !== null} onClick={() => { setShowForm(false); setEditingTask((current) => current?.id === task.id ? null : task); }}><ActionIcon type="edit" /></button>
                            <button className="task-action remove action-icon-button" type="button" aria-label="Remove task" title="Remove task" disabled={pendingAction !== null} onClick={() => removeTask(task)}><ActionIcon type="remove" /></button>
                            {pendingAction?.id === task.id && <span className="accomplishment-action-pending" role="status">{pendingAction.action === 'complete' ? 'Completing…' : 'Removing…'}</span>}
                        </div>
                    </article>)}</div>}
            </section>
        </DashboardLayout>
    );
}
