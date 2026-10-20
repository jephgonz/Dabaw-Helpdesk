import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { appUrl } from '../../url';

export default function Landing({ notices = [], ticketStats = {} }) {
    const { errors, flash } = usePage().props;
    const form = useForm({
        requester_name: '',
        requester_email: '',
        subject: '',
        description: '',
        website: '',
    });

    const submitReport = (event) => {
        event.preventDefault();
        form.post(appUrl('/reports'), {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Jephthah Helpdesk" />
            <header className="landing-topbar" id="top">
                <a className="landing-brand" href={appUrl('/')}>
                    <span className="brand-mark brand-logo"><img src={appUrl('/public/assets/images/SP.ico')} alt="" /></span>
                    <span>Jephthah <strong>Helpdesk</strong></span>
                </a>
                <Link className="landing-admin-link" href={appUrl('/admin/login')}>Admin login <span aria-hidden="true">→</span></Link>
            </header>

            <main className="landing-page">
                <section className="landing-hero landing-hero-with-report" aria-labelledby="landing-title">
                    <div className="landing-hero-content">
                        <span className="eyebrow"><span className="eyebrow-dot" /> OPEN SUPPORT PORTAL</span>
                        <h1 id="landing-title">How can we<br />help you today?</h1>
                        <p>Report an issue in a few steps. No staff account or sign-in is required.</p>
                        <a className="landing-primary-action" href="#report">Submit a support report <span aria-hidden="true">↓</span></a>
                        <div className="support-illustration" aria-hidden="true"><div className="illustration-orbit orbit-one" /><div className="illustration-orbit orbit-two" /><div className="ticket-card"><div className="ticket-top"><span className="ticket-symbol">✓</span><span className="ticket-status">SUPPORT REQUEST</span><span className="ticket-dots">•••</span></div><div className="ticket-line line-long" /><div className="ticket-line line-short" /><div className="ticket-bottom"><span className="ticket-avatar">JH</span><span>Help is on the way</span><span className="ticket-arrow">↗</span></div></div><span className="float-star star-one">✦</span><span className="float-star star-two">✦</span></div>
                    </div>
                    <form className="landing-report-form" id="report" aria-labelledby="report-title" aria-busy={form.processing} onSubmit={submitReport}>
                        <div className="landing-form-heading">
                            <span className="eyebrow">WE’RE HERE TO HELP</span>
                            <h2 id="report-title">Submit a support report</h2>
                            <p>Tell us what happened and how to reach you. No sign-in required.</p>
                        </div>
                        {flash?.success && <div className="landing-feedback success" role="status">{flash.success}</div>}
                        {flash?.error && <div className="landing-feedback error" role="alert">{flash.error}</div>}
                        <div className="landing-form-grid">
                            <label>Your name<input value={form.data.requester_name} onChange={(event) => form.setData('requester_name', event.target.value)} maxLength="120" autoComplete="name" aria-invalid={Boolean(errors.requester_name)} aria-describedby={errors.requester_name ? 'requester-name-error' : undefined} required />{errors.requester_name && <small id="requester-name-error">{errors.requester_name}</small>}</label>
                            <label>Email address<input type="email" value={form.data.requester_email} onChange={(event) => form.setData('requester_email', event.target.value)} maxLength="190" autoComplete="email" aria-invalid={Boolean(errors.requester_email)} aria-describedby={errors.requester_email ? 'requester-email-error' : undefined} required />{errors.requester_email && <small id="requester-email-error">{errors.requester_email}</small>}</label>
                        </div>
                        <label>What do you need help with?<input value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} maxLength="160" placeholder="Briefly describe the issue" aria-invalid={Boolean(errors.subject)} aria-describedby={errors.subject ? 'subject-error' : undefined} required />{errors.subject && <small id="subject-error">{errors.subject}</small>}</label>
                        <label>Details<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} maxLength="10000" rows="4" placeholder="Include any details that can help us understand the issue." aria-invalid={Boolean(errors.description)} aria-describedby={errors.description ? 'description-error' : undefined} required />{errors.description && <small id="description-error">{errors.description}</small>}</label>
                        <label className="landing-honeypot" aria-hidden="true">Website<input value={form.data.website} onChange={(event) => form.setData('website', event.target.value)} tabIndex="-1" autoComplete="off" /></label>
                        {errors.website && <div className="landing-feedback error" role="alert">{errors.website}</div>}
                        {errors.form && <div className="landing-feedback error" role="alert">{errors.form}</div>}
                        <div className="landing-form-footer"><small>Your report will be sent to the helpdesk administrator.</small><button className="landing-primary-action" type="submit" disabled={form.processing}>{form.processing ? 'Sending…' : <>Send report <span aria-hidden="true">→</span></>}</button></div>
                    </form>
                    <a className="landing-scroll-cue" href="#notices"><span />Notices &amp; support overview</a>
                </section>

                <section className="landing-notices-section" id="notices" aria-labelledby="overview-title">
                    <div className="landing-section-heading">
                        <span className="eyebrow">HELPDESK UPDATES</span>
                        <h2 id="overview-title">Notices &amp; support overview</h2>
                        <p>See the latest announcements and the support requests handled by our team.</p>
                    </div>
                    <div className="landing-overview-grid">
                        <div className="landing-support-stats" aria-label="Support request totals">
                            <article className="landing-support-stat completed"><span>Resolved</span><strong>{Number(ticketStats.resolved || 0).toLocaleString()}</strong><small>Requests completed</small></article>
                            <article className="landing-support-stat"><span>Total reports</span><strong>{Number(ticketStats.total || 0).toLocaleString()}</strong><small>All-time requests</small></article>
                            <article className="landing-support-stat"><span>In progress</span><strong>{Number(ticketStats.in_progress || 0).toLocaleString()}</strong><small>Being handled now</small></article>
                            <article className="landing-support-stat"><span>New</span><strong>{Number(ticketStats.new_count || 0).toLocaleString()}</strong><small>Awaiting review</small></article>
                        </div>
                        <div className="landing-notice-column">
                            <h3>Latest notices</h3>
                            {notices.length === 0 ? <div className="landing-empty-notices">There are no notices to display right now.</div> :
                                <div className="landing-notice-list">{notices.map((notice) => <article className="landing-notice" key={notice.id}>
                                    <div className="landing-notice-heading"><h4>{notice.title}</h4><time dateTime={notice.created_at}>{new Date(notice.created_at).toLocaleString()}</time></div>
                                    <p>{notice.body}</p>
                                    {notice.attachment_url && <a className="landing-notice-attachment" href={notice.attachment_url} target="_blank" rel="noreferrer"><img src={notice.attachment_url} alt={`Attached memo for ${notice.title}`} /><span>View attached memo</span></a>}
                                    <small>Posted by {notice.author || 'Helpdesk administrator'}</small>
                                </article>)}</div>}
                        </div>
                    </div>
                </section>
            </main>
            <footer className="landing-footer"><span>© {new Date().getFullYear()} Jephthah Helpdesk</span><a href="#top" onClick={(event) => { event.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); }}>Back to top ↑</a></footer>
        </>
    );
}
