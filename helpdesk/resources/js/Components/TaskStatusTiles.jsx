import React from 'react';
import { Link } from '@inertiajs/react';
import { appUrl } from '../url';

const statuses = [
    { key: 'todo', label: 'To do' },
    { key: 'in_progress', label: 'In progress' },
    { key: 'finished', label: 'Finished' },
];

export default function TaskStatusTiles({ counts = {} }) {
    return (
        <section className="task-status-summary" aria-label="Task board status summary">
            <div className="task-status-summary-heading">
                <h2>Task board status</h2>
                <Link href={appUrl('/tasks')}>View boards <span aria-hidden="true">→</span></Link>
            </div>
            <div className="task-status-tiles">
                {statuses.map((status) => <Link
                    key={status.key}
                    className={`task-status-tile task-status-tile-${status.key}`}
                    href={appUrl('/tasks')}
                >
                    <span>{status.label}</span>
                    <strong>{Number(counts[status.key] || 0)}</strong>
                </Link>)}
            </div>
        </section>
    );
}
