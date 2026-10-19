import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import { appUrl } from '../../url';

function DeleteRow({ table, keyToken }) {
    const form = useForm({ table, key: keyToken });
    return <form onSubmit={(event) => { event.preventDefault(); if (window.confirm('Delete this row?')) form.post(appUrl('/admin/database/record/delete')); }}><button className="secondary-action danger-action" type="submit" disabled={form.processing}>Delete</button></form>;
}

export default function DatabaseTable({ table, columns = [], rows = [], keys = [], editable, canCreate }) {
    return <DashboardLayout title={`Table: ${table}`} activePage="database">
        <div className="dashboard-title-row"><div><p className="eyebrow">MYSQL TABLE</p><h1>{table}</h1><p className="dashboard-subtitle">Showing the first {rows.length} rows.</p></div><div className="table-page-actions"><Link className="secondary-action" href={appUrl('/admin/database')}>← Tables</Link>{canCreate && <Link className="secondary-action primary-action" href={`${appUrl('/admin/database/record/create')}?table=${encodeURIComponent(table)}`}>+ Add row</Link>}</div></div>
        <section className="admin-card accounts-card directory-card"><div className="table-wrap"><table><thead><tr>{columns.map((column) => <th key={column.name}>{column.name}</th>)}{editable && <th>Actions</th>}</tr></thead><tbody>
            {rows.length === 0 ? <tr><td colSpan={columns.length + (editable ? 1 : 0)}>No rows found.</td></tr> : rows.map((row, index) => <tr key={keys[index]}>{columns.map((column) => <td key={column.name}>{row[column.name] === null ? <span className="muted-cell">NULL</span> : String(row[column.name])}</td>)}{editable && <td className="database-row-actions"><Link className="secondary-action" href={`${appUrl('/admin/database/record/edit')}?table=${encodeURIComponent(table)}&key=${encodeURIComponent(keys[index])}`}>Edit</Link>{table !== 'helpdesk_users' && <DeleteRow table={table} keyToken={keys[index]} />}</td>}</tr>)}
        </tbody></table></div></section>
    </DashboardLayout>;
}
