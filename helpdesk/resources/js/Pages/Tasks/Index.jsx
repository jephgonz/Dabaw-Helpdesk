import React, { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import { appUrl } from '../../url';

const columns = [
    { key: 'todo', label: 'To do' },
    { key: 'in_progress', label: 'In progress' },
    { key: 'finished', label: 'Finished' },
];

function TaskCard({ task, isOwner, canManage, onDragStart, onDragEnd }) {
    const [editing, setEditing] = useState(false);
    const editForm = useForm({ title: task.title, description: task.description || '' });
    const canChangeStatus = isOwner || canManage;

    useEffect(() => {
        editForm.setData({ title: task.title, description: task.description || '' });
    }, [task.title, task.description]);

    const saveTask = (event) => {
        event.preventDefault();
        editForm.post(appUrl(`/tasks/${task.id}/update`), { onSuccess: () => setEditing(false) });
    };
    const setStatus = (event) => {
        const status = event.target.value;
        router.patch(appUrl(`/tasks/${task.id}/status`), { status }, { preserveScroll: true });
        const menu = event.currentTarget.closest('details');
        if (menu) menu.open = false;
    };

    return (
        <article
            className={`task-card${canChangeStatus ? ' is-draggable' : ''}`}
            draggable={canChangeStatus && !editing}
            onDragStart={(event) => {
                if (!canChangeStatus || editing || event.target.closest('button, select, input, textarea, summary, a')) {
                    event.preventDefault();
                    return;
                }
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(task.id));
                onDragStart(task.id);
            }}
            onDragEnd={onDragEnd}
        >
            {editing ? (
                <form className="task-edit-form" onSubmit={saveTask}>
                    <label>Task title<input value={editForm.data.title} onChange={(event) => editForm.setData('title', event.target.value)} maxLength="200" required /></label>
                    <label>Description<textarea value={editForm.data.description} onChange={(event) => editForm.setData('description', event.target.value)} maxLength="5000" rows="3" /></label>
                    {Object.entries(editForm.errors).map(([field, message]) => <small className="task-field-error" key={field}>{message}</small>)}
                    <div className="task-card-actions"><button className="task-save-button" type="submit" disabled={editForm.processing}>Save</button><button className="task-cancel-button" type="button" onClick={() => setEditing(false)}>Cancel</button></div>
                </form>
            ) : (
                <>
                    <h3>{task.title}</h3>
                    {task.description && <p>{task.description}</p>}
                    <small className="task-creator">Added by {task.creator_name}</small>
                    {(isOwner || canManage) && <details className="task-card-menu">
                        <summary aria-label={`Task options for ${task.title}`}>•••</summary>
                        <div className="task-card-menu-popover">
                            <label className="task-status-control">Move to
                                <select value={task.status} onChange={setStatus}>
                                    {columns.map((column) => <option key={column.key} value={column.key}>{column.label}</option>)}
                                </select>
                            </label>
                            {canManage && <>
                                <button type="button" onClick={(event) => { setEditing(true); event.currentTarget.closest('details').open = false; }}>Edit task</button>
                                <button className="task-menu-delete" type="button" onClick={() => {
                                    if (window.confirm('Delete this task?')) router.delete(appUrl(`/tasks/${task.id}`), { preserveScroll: true });
                                }}>Delete task</button>
                            </>}
                        </div>
                    </details>}
                </>
            )}
        </article>
    );
}

export default function TaskBoard({ users = [], board, tasks = [], search = '', currentUserId, canManage = false }) {
    const [searchText, setSearchText] = useState(search);
    const [showForm, setShowForm] = useState(false);
    const [draggedTaskId, setDraggedTaskId] = useState(null);
    const [dragOverStatus, setDragOverStatus] = useState(null);
    const form = useForm({ board_user_id: board?.id || '', title: '', description: '' });
    const isBoardOwner = Number(board?.id) === Number(currentUserId);
    const canChangeStatus = isBoardOwner || canManage;

    useEffect(() => {
        setSearchText(search);
    }, [search]);

    useEffect(() => {
        form.setData('board_user_id', board?.id || '');
    }, [board?.id]);

    const searchBoards = (event) => {
        event.preventDefault();
        router.get(appUrl('/tasks'), { search: searchText, board: board?.id }, { preserveState: true, preserveScroll: true, replace: true });
    };
    const openBoard = (user) => {
        router.get(appUrl('/tasks'), { search, board: user.id }, { preserveState: true, preserveScroll: true });
    };
    const addTask = (event) => {
        event.preventDefault();
        form.post(appUrl('/tasks'), { onSuccess: () => { form.reset('title', 'description'); setShowForm(false); } });
    };
    const moveTask = (taskId, status) => {
        const task = tasks.find((item) => Number(item.id) === Number(taskId));
        setDragOverStatus(null);
        setDraggedTaskId(null);
        if (!canChangeStatus || !task || task.status === status) return;
        router.patch(appUrl(`/tasks/${task.id}/status`), { status }, { preserveScroll: true });
    };

    if (!board) {
        return <DashboardLayout title="Task boards" activePage="tasks"><section className="admin-card task-empty-board">The task board is unavailable. Please refresh after the database migration is applied.</section></DashboardLayout>;
    }

    return (
        <DashboardLayout title="Task boards" activePage="tasks">
            <div className="dashboard-title-row task-board-title"><div><p className="eyebrow">SHARED WORKSPACE</p><h1>Task boards</h1></div></div>
            <div className="task-board-layout">
                <section className="task-workspace">
                    <div className="admin-card task-board-heading">
                        <div className="task-board-current"><span className="step-label">{board.role === 'admin' ? 'ADMIN BOARD' : 'STAFF BOARD'}</span><h2>{board.display_name}</h2><p>{board.username ? `@${board.username}` : 'Administrator tasks'}</p></div>
                        <div className="task-board-toolbar">
                            <details className="task-board-picker">
                                <summary><span>Switch board</span><strong>⌄</strong></summary>
                                <div className="task-board-picker-popover">
                                    <form className="task-search-form" onSubmit={(event) => {
                                        searchBoards(event);
                                        const picker = event.currentTarget.closest('details');
                                        if (picker) picker.open = false;
                                    }}>
                                        <label className="sr-only" htmlFor="task-board-search">Search boards by name</label>
                                        <input id="task-board-search" type="search" value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder="Search name or username" maxLength="100" />
                                        <button type="submit" aria-label="Search boards">Search</button>
                                    </form>
                                    <div className="task-user-list">
                                        {users.map((user) => <button key={user.id} type="button" className={`task-user-option${Number(user.id) === Number(board.id) ? ' is-selected' : ''}`} onClick={(event) => {
                                            event.currentTarget.closest('details').open = false;
                                            openBoard(user);
                                        }}>
                                            <span className="task-user-avatar">{(user.display_name || '?').slice(0, 1).toUpperCase()}</span>
                                            <span className="task-user-label"><strong>{user.display_name}</strong><small>{user.username || user.role}</small></span>
                                        </button>)}
                                        {users.length === 0 && <p className="task-directory-empty">No matching boards found.</p>}
                                    </div>
                                </div>
                            </details>
                            {(canManage || board.role === 'staff') && <button className="secondary-action" type="button" aria-expanded={showForm} onClick={() => setShowForm((open) => !open)}>{showForm ? 'Cancel' : '+ Add task'}</button>}
                        </div>
                    </div>
                    {showForm && <form className="admin-card task-create-form" onSubmit={addTask}>
                        <label>Task title<input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} maxLength="200" required /></label>
                        <label>Description<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} maxLength="5000" rows="3" /></label>
                        {Object.entries(form.errors).map(([field, message]) => <small className="task-field-error" key={field}>{message}</small>)}
                        <div className="task-card-actions"><button className="task-save-button" type="submit" disabled={form.processing}>{form.processing ? 'Adding…' : 'Add task'}</button></div>
                    </form>}
                    <div className="task-columns">
                        {columns.map((column) => {
                            const columnTasks = tasks.filter((task) => task.status === column.key);
                            const isDropTarget = canChangeStatus && dragOverStatus === column.key;
                            return <section key={column.key} className={`task-column task-column-${column.key}`}>
                                <div
                                    className={`task-column-dropzone${isDropTarget ? ' is-drop-target' : ''}${draggedTaskId ? ' has-dragging-task' : ''}`}
                                    onDragOver={(event) => {
                                        if (!canChangeStatus || !draggedTaskId) return;
                                        event.preventDefault();
                                        event.dataTransfer.dropEffect = 'move';
                                        setDragOverStatus(column.key);
                                    }}
                                    onDragLeave={(event) => {
                                        if (!event.currentTarget.contains(event.relatedTarget)) setDragOverStatus(null);
                                    }}
                                    onDrop={(event) => {
                                        event.preventDefault();
                                        const taskId = event.dataTransfer.getData('text/plain') || draggedTaskId;
                                        if (taskId) moveTask(taskId, column.key);
                                    }}
                                >
                                <div className="task-column-heading"><h3>{column.label}</h3><span>{columnTasks.length}</span></div>
                                <div className="task-column-cards">
                                    {columnTasks.map((task) => <TaskCard
                                        key={task.id}
                                        task={task}
                                        isOwner={isBoardOwner}
                                        canManage={canManage}
                                        onDragStart={setDraggedTaskId}
                                        onDragEnd={() => { setDraggedTaskId(null); setDragOverStatus(null); }}
                                    />)}
                                    {columnTasks.length === 0 && <p className="task-column-empty">No tasks here yet.</p>}
                                </div>
                                </div>
                            </section>;
                        })}
                    </div>
                </section>
            </div>
        </DashboardLayout>
    );
}
