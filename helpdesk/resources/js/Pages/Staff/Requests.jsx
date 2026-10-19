import React from 'react';
import { useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import { appUrl } from '../../url';

export default function Requests({ tickets = [] }) {
    const form = useForm({ subject: '', description: '' });
    const { errors } = usePage().props;
    const submit = (event) => {
        event.preventDefault();
        form.post(appUrl('/dashboard/requests'), { onSuccess: () => form.reset() });
    };

    return (
        <DashboardLayout title="Request support" activePage="support">
            <div className="dashboard-title-row"><div><p className="eyebrow">STAFF SUPPORT</p><h1>Request support</h1><p className="dashboard-subtitle">Report a problem to IT and track your support requests.</p></div></div>
            <section className="admin-grid staff-support-grid">
                <article className="admin-card">
                    <div className="admin-card-heading"><div><span className="step-label">NEW REQUEST</span><h2>Tell IT what you need help with</h2><p>Include enough detail for the support team to investigate.</p></div><span className="section-mark">＋</span></div>
                    <form className="account-form" onSubmit={submit}>
                        <label>Subject<input value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} maxLength="160" required placeholder="Briefly describe the issue" />{errors.subject && <small className="field-error">{errors.subject}</small>}</label>
                        <label>Description<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows="7" maxLength="10000" required placeholder="What happened? Include any error messages and steps to reproduce." />{errors.description && <small className="field-error">{errors.description}</small>}</label>
                        <button className="sign-in-button" type="submit" disabled={form.processing}>{form.processing ? 'Sending…' : 'Send to IT'}</button>
                    </form>
                </article>
                <article className="admin-card ticket-history-card">
                    <div className="admin-card-heading accounts-heading"><div><span className="step-label">YOUR REQUESTS</span><h2>Request history</h2><p>Check the latest status from IT.</p></div><span className="account-count">{tickets.length} total</span></div>
                    {tickets.length === 0 ? <div className="empty-state"><span>✓</span><strong>No requests yet</strong><small>Your submitted requests will appear here.</small></div> :
                        <div className="staff-ticket-list">{tickets.map((ticket) => <article className="staff-ticket-item" key={ticket.id}><div className="staff-ticket-header"><strong>{ticket.subject}</strong><span className={`ticket-status-tag ${ticket.status}`}>{ticket.status.replace('_', ' ')}</span></div><p>{ticket.description}</p><small>Submitted {new Date(ticket.created_at).toLocaleString()}</small></article>)}</div>}
                </article>
            </section>
        </DashboardLayout>
    );
}
