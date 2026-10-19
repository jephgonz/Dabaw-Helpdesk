import React, { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import { appUrl } from '../../url';

export default function Users({ users = [] }) {
    const [showForm, setShowForm] = useState(false);
    const form = useForm({ display_name: '', username: '', email: '', password: '', role: 'staff' });
    const { errors } = usePage().props;
    const submit = (event) => {
        event.preventDefault();
        form.post(appUrl('/admin/users'), { onSuccess: () => { form.reset(); setShowForm(false); } });
    };
    const setStatus = (user) => {
        const activate = Number(user.is_active) !== 1;
        if (user.role === 'admin' && !activate && !window.confirm('Deactivate this administrator account?')) return;
        router.post(appUrl('/admin/users/status'), { user_id: user.id, is_active: activate ? 1 : 0 });
    };

    return (
        <DashboardLayout title="Staff accounts" activePage="users">
            <div className="dashboard-title-row"><div><p className="eyebrow">ACCOUNT ADMINISTRATION</p><h1>Staff accounts</h1><p className="dashboard-subtitle">Manage who can sign in to Dabaw Helpdesk.</p></div></div>
            <section className={`admin-card create-user-card collapsible-form-card${showForm ? ' is-expanded' : ''}`} id="add-account">
                <div className="admin-card-heading collapsible-form-heading"><div><span className="step-label">NEW ACCOUNT</span><h2>Create account</h2><p>Email addresses may be shared; each staff username must be unique.</p></div><button className="secondary-action" type="button" aria-expanded={showForm} aria-controls="account-form" onClick={() => setShowForm((current) => !current)}>{showForm ? 'Close form' : '+ Add account'}</button></div>
                <div className={`collapsible-form-content${showForm ? ' is-expanded' : ''}`} id="account-form" aria-hidden={!showForm} inert={!showForm}>
                    <div className="collapsible-form-inner"><form className="account-form" onSubmit={submit}>
                        <fieldset disabled={!showForm}>
                            <label>Display name<input value={form.data.display_name} onChange={(event) => form.setData('display_name', event.target.value)} maxLength="120" required />{errors.display_name && <small className="field-error">{errors.display_name}</small>}</label>
                            <label>Role<select value={form.data.role} onChange={(event) => { form.setData('role', event.target.value); if (event.target.value === 'admin') form.setData('username', ''); }}><option value="staff">Staff</option><option value="admin">Admin</option></select></label>
                            {form.data.role === 'staff' && <label>Username<input value={form.data.username} onChange={(event) => form.setData('username', event.target.value.toLowerCase())} autoComplete="off" minLength="3" maxLength="80" required />{errors.username && <small className="field-error">{errors.username}</small>}</label>}
                            <label>Email<input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} maxLength="190" required />{errors.email && <small className="field-error">{errors.email}</small>}</label>
                            <label>Initial password<input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} minLength="12" autoComplete="new-password" required /><small>At least 12 characters.</small>{errors.password && <small className="field-error">{errors.password}</small>}</label>
                            <div className="form-actions"><button className="sign-in-button" type="submit" disabled={form.processing}>Create account</button><button className="cancel-action" type="button" onClick={() => setShowForm(false)}>Cancel</button></div>
                        </fieldset>
                    </form></div>
                </div>
            </section>
            <section className="admin-card accounts-card directory-card">
                <div className="admin-card-heading accounts-heading"><div><span className="step-label">USER DIRECTORY</span><h2>Accounts</h2><p>Activate or deactivate access without removing account data.</p></div><span className="account-count">{users.length} accounts</span></div>
                {users.length === 0 ? <div className="empty-state"><span>♙</span><strong>No accounts found</strong></div> :
                    <div className="table-wrap"><table><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Access</th></tr></thead><tbody>
                        {users.map((user) => <tr key={user.id}><td><strong>{user.display_name}</strong></td><td>{user.username || '—'}</td><td>{user.email}</td><td><span className="role-tag">{user.role}</span></td><td><span className={`state-tag ${Number(user.is_active) === 1 ? 'is-active' : 'is-inactive'}`}>{Number(user.is_active) === 1 ? 'Active' : 'Inactive'}</span></td><td>{new Date(user.created_at).toLocaleDateString()}</td><td><button type="button" className="secondary-action account-status-button" onClick={() => setStatus(user)}>{Number(user.is_active) === 1 ? 'Deactivate' : 'Activate'}</button></td></tr>)}
                    </tbody></table></div>}
            </section>
        </DashboardLayout>
    );
}
