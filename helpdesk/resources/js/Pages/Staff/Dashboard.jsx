import React from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import { appUrl } from '../../url';

export default function Dashboard({ notices = [] }) {
    return (
        <DashboardLayout title="Staff overview" activePage="overview">
            <div className="dashboard-title-row"><div><p className="eyebrow">STAFF WORKSPACE</p><h1>Overview</h1><p className="dashboard-subtitle">Latest updates and important information from your helpdesk team.</p></div><Link className="secondary-action primary-action" href={appUrl('/dashboard/requests')}>Request support →</Link></div>
            <section className="admin-card staff-notices-card" aria-labelledby="staff-notices-title">
                <div className="admin-card-heading accounts-heading"><div><span className="step-label">FROM YOUR ADMINISTRATOR</span><h2 id="staff-notices-title">Latest memos &amp; notices</h2><p>Updates and announcements for staff.</p></div><span className="account-count">{notices.length} latest</span></div>
                {notices.length === 0 ? <div className="empty-state"><span>▧</span><strong>No notices yet</strong><small>New memos and announcements from your administrator will appear here.</small></div> :
                    <div className="staff-notice-list">{notices.map((notice) => <article className="staff-notice-item" key={notice.id}><div className="staff-notice-heading"><h3>{notice.title}</h3><time dateTime={notice.created_at}>{new Date(notice.created_at).toLocaleString()}</time></div><p>{notice.body}</p>{notice.attachment_url && <a className="notice-attachment-link" href={notice.attachment_url} target="_blank" rel="noreferrer"><img className="notice-attachment-preview" src={notice.attachment_url} alt={`Attached memo for ${notice.title}`} /><span>Open scanned memo</span></a>}<small>Posted by {notice.author || 'Administrator'}</small></article>)}</div>}
            </section>
        </DashboardLayout>
    );
}
