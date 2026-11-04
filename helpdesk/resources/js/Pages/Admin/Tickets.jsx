import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import ActionIcon from '../../Components/ActionIcon';
import ResolutionChart from '../../Components/ResolutionChart';
import { appUrl } from '../../url';

function TicketEditForm({ ticket, onCancel, onSaved }) {
    const form = useForm({
        requester_name: ticket.requester_name,
        requester_email: ticket.requester_email,
        subject: ticket.subject,
        description: ticket.description,
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(appUrl(`/admin/tickets/${ticket.id}`), { onSuccess: onSaved });
    };

    return <form className="ticket-edit-form" onSubmit={submit}>
        <label>Requester name<input value={form.data.requester_name} onChange={(event) => form.setData('requester_name', event.target.value)} maxLength="120" required />{form.errors.requester_name && <small className="field-error">{form.errors.requester_name}</small>}</label>
        <label>Requester email<input type="email" value={form.data.requester_email} onChange={(event) => form.setData('requester_email', event.target.value)} maxLength="190" required />{form.errors.requester_email && <small className="field-error">{form.errors.requester_email}</small>}</label>
        <label>Subject<input value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} maxLength="160" required />{form.errors.subject && <small className="field-error">{form.errors.subject}</small>}</label>
        <label className="ticket-edit-description">Details<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows="4" maxLength="10000" required />{form.errors.description && <small className="field-error">{form.errors.description}</small>}</label>
        {form.errors.form && <small className="field-error ticket-edit-form-error">{form.errors.form}</small>}
        <div className="ticket-edit-actions"><button className="sign-in-button" type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save changes'}</button><button className="secondary-action" type="button" onClick={onCancel}>Cancel</button></div>
    </form>;
}

function TicketStatusForm({ ticket }) {
    const form = useForm({ ticket_id: ticket.id, status: ticket.status });
    return <form className="ticket-status-form" onSubmit={(event) => { event.preventDefault(); form.post(appUrl('/admin/tickets/status')); }}>
        <select aria-label={`Status for request ${ticket.id}`} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
            <option value="new">New</option><option value="in_progress">In progress</option><option value="resolved">Resolved</option>
        </select><button className="secondary-action" disabled={form.processing}>Save</button>
    </form>;
}

export default function Tickets({ tickets = [], newTicketCount = 0 }) {
    const [editingTicketId, setEditingTicketId] = useState(null);

    return (
        <DashboardLayout title="Support requests" activePage="tickets">
            <div className="dashboard-title-row"><div><p className="eyebrow">IT SUPPORT</p><h1>Support requests</h1><p className="dashboard-subtitle">Reports submitted through the public helpdesk page.</p></div></div>
            <section className="admin-card accounts-card directory-card ticket-inbox-card">
                <div className="admin-card-heading accounts-heading"><div><span className="step-label">PUBLIC REPORT INBOX</span><h2>Support reports</h2><p>New reports are shown first. Update each report as you work on it.</p></div><span className="account-count">{newTicketCount} new</span></div>
                {tickets.length === 0 ? <div className="empty-state"><span>✓</span><strong>No support requests</strong><small>New public reports will appear here.</small></div> :
                    <div className="table-wrap"><table className="support-ticket-table"><thead><tr><th>Request</th><th>Reported by</th><th>Details</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead><tbody>
                        {tickets.map((ticket) => <React.Fragment key={ticket.id}>
                            <tr><td><strong>{ticket.subject}</strong><small>Request #{ticket.id}</small></td><td><strong>{ticket.requester_name}</strong><small>{ticket.requester_email}</small></td><td className="ticket-description-cell">{ticket.description}</td><td><span className={`ticket-status-tag ${ticket.status}`}>{ticket.status.replace('_', ' ')}</span></td><td>{new Date(ticket.created_at).toLocaleString()}</td><td className="ticket-actions"><button className="secondary-action action-icon-button" type="button" aria-label={`Edit request ${ticket.id}`} title="Edit request" aria-expanded={editingTicketId === ticket.id} onClick={() => setEditingTicketId((current) => current === ticket.id ? null : ticket.id)}><ActionIcon type="edit" /></button><TicketStatusForm ticket={ticket} /></td></tr>
                            {editingTicketId === ticket.id && <tr className="ticket-edit-row"><td colSpan="6"><TicketEditForm ticket={ticket} onCancel={() => setEditingTicketId(null)} onSaved={() => setEditingTicketId(null)} /></td></tr>}
                        </React.Fragment>)}
                    </tbody></table></div>}
            </section>
            <ResolutionChart />
        </DashboardLayout>
    );
}
