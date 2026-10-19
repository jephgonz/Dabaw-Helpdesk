import React from 'react';
import { useForm } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import ResolutionChart from '../../Components/ResolutionChart';
import { appUrl } from '../../url';

function TicketStatusForm({ ticket }) {
    const form = useForm({ ticket_id: ticket.id, status: ticket.status });
    return <form className="ticket-status-form" onSubmit={(event) => { event.preventDefault(); form.post(appUrl('/admin/tickets/status')); }}>
        <select aria-label={`Status for request ${ticket.id}`} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
            <option value="new">New</option><option value="in_progress">In progress</option><option value="resolved">Resolved</option>
        </select><button className="secondary-action" disabled={form.processing}>Save</button>
    </form>;
}

export default function Tickets({ tickets = [], newTicketCount = 0 }) {
    return (
        <DashboardLayout title="Support requests" activePage="tickets">
            <div className="dashboard-title-row"><div><p className="eyebrow">IT SUPPORT</p><h1>Support requests</h1><p className="dashboard-subtitle">Reports submitted through the public helpdesk page.</p></div></div>
            <section className="admin-card accounts-card directory-card staff-reports-card">
                <div className="admin-card-heading accounts-heading"><div><span className="step-label">PUBLIC REPORT INBOX</span><h2>Support reports</h2><p>New reports are shown first. Update each report as you work on it.</p></div><span className="account-count">{newTicketCount} new</span></div>
                {tickets.length === 0 ? <div className="empty-state"><span>✓</span><strong>No support requests</strong><small>New public reports will appear here.</small></div> :
                    <div className="table-wrap"><table className="support-ticket-table"><thead><tr><th>Request</th><th>Reported by</th><th>Details</th><th>Status</th><th>Submitted</th><th>Update status</th></tr></thead><tbody>
                        {tickets.map((ticket) => <tr key={ticket.id}><td><strong>{ticket.subject}</strong><small>Request #{ticket.id}</small></td><td><strong>{ticket.requester_name}</strong><small>{ticket.requester_email}</small></td><td className="ticket-description-cell">{ticket.description}</td><td><span className={`ticket-status-tag ${ticket.status}`}>{ticket.status.replace('_', ' ')}</span></td><td>{new Date(ticket.created_at).toLocaleString()}</td><td><TicketStatusForm ticket={ticket} /></td></tr>)}
                    </tbody></table></div>}
            </section>
            <ResolutionChart />
        </DashboardLayout>
    );
}
