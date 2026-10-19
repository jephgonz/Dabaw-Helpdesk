import React from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import { appUrl } from '../../url';

function inputType(column) {
    if (/date$/.test(column.type) && !/datetime|timestamp/.test(column.type)) return 'date';
    if (/datetime|timestamp/.test(column.type)) return 'datetime-local';
    if (/int|decimal|float|double/.test(column.type)) return 'number';
    return 'text';
}

export default function DatabaseRecord({ table, mode, fields = [], record = {}, key: keyToken }) {
    const initial = Object.fromEntries(fields.map((field) => {
        let value = record[field.name] ?? '';
        if (value && /datetime|timestamp/.test(field.type)) {
            value = String(value).replace(' ', 'T').slice(0, 16);
        }
        return [field.name, value];
    }));
    const form = useForm({ table, mode, key: keyToken, values: initial });
    const { errors } = usePage().props;
    return <DashboardLayout title={mode === 'create' ? 'Add database row' : 'Edit database row'} activePage="database">
        <div className="dashboard-title-row"><div><p className="eyebrow">{table}</p><h1>{mode === 'create' ? 'Add row' : 'Edit row'}</h1><p className="dashboard-subtitle">Update fields in the selected table.</p></div><Link className="secondary-action" href={`${appUrl('/admin/database/table')}?table=${encodeURIComponent(table)}`}>← Back to table</Link></div>
        <section className="admin-card create-user-card"><form className="account-form database-record-form" onSubmit={(event) => { event.preventDefault(); form.post(appUrl('/admin/database/record/save')); }}>
            {fields.map((field) => <label key={field.name}>{field.name}<input type={inputType(field)} value={form.data.values[field.name] ?? ''} required={!field.nullable && field.default === null} onChange={(event) => form.setData('values', { ...form.data.values, [field.name]: event.target.value })} /><small>{field.type}{field.nullable ? ', optional' : ''}{field.default !== null ? `, default ${field.default}` : ''}</small></label>)}
            <button className="sign-in-button" type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save row'}</button>
        </form></section>
    </DashboardLayout>;
}
