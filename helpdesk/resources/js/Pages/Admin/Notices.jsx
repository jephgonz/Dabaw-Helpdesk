import React, { useRef, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import FilePicker from '../../Components/FilePicker';
import { appUrl } from '../../url';

export default function Notices({ notices = [] }) {
    const fileInput = useRef(null);
    const [showForm, setShowForm] = useState(false);
    const form = useForm({ title: '', body: '', attachment: null });
    const submit = (event) => {
        event.preventDefault();
        form.post(appUrl('/admin/notices'), { onSuccess: () => { form.reset(); setShowForm(false); if (fileInput.current) fileInput.current.value = ''; } });
    };
    const removeNotice = (notice) => {
        if (window.confirm(`Remove "${notice.title}" from the public helpdesk page?`)) {
            router.post(appUrl('/admin/notices/delete'), { notice_id: notice.id });
        }
    };

    return (
        <DashboardLayout title="Public notices" activePage="notices">
            <div className="dashboard-title-row"><div><p className="eyebrow">PUBLIC COMMUNICATIONS</p><h1>Public notices</h1><p className="dashboard-subtitle">Post announcements and memos on the public helpdesk page.</p></div></div>
            <section className={`admin-card notice-editor-card collapsible-form-card${showForm ? ' is-expanded' : ''}`}>
                <div className="admin-card-heading collapsible-form-heading"><div><span className="step-label">NEW ANNOUNCEMENT</span><h2>Post a memo or notice</h2><p>Notices are visible on the public helpdesk page as soon as you post them.</p></div><button className="secondary-action" type="button" aria-expanded={showForm} aria-controls="notice-form" onClick={() => setShowForm((current) => !current)}>{showForm ? 'Close form' : '+ Write a notice'}</button></div>
                <div className={`collapsible-form-content${showForm ? ' is-expanded' : ''}`} id="notice-form" aria-hidden={!showForm} inert={!showForm}>
                    <div className="collapsible-form-inner"><form className="account-form" onSubmit={submit}>
                        <fieldset disabled={!showForm}>
                            <label>Title<input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} maxLength="180" required placeholder="Notice title" /></label>
                            <label>Message<textarea value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} rows="5" maxLength="10000" required placeholder="Write a public memo or announcement." /></label>
                            <label htmlFor="notice-attachment">Scanned memo image
                                <FilePicker id="notice-attachment" inputRef={fileInput} accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" selectedFile={form.data.attachment} emptyText="No image attached" onChange={(event) => form.setData('attachment', event.target.files[0] || null)} />
                                <small>Optional. JPEG, PNG, or WebP image; maximum 10 MB. Served through the public helpdesk page.</small>
                                {form.errors.attachment && <small className="field-error">{form.errors.attachment}</small>}
                            </label>
                            <div className="form-actions"><button className="sign-in-button" type="submit" disabled={form.processing}>{form.processing ? 'Posting…' : 'Post notice'}</button><button className="cancel-action" type="button" onClick={() => setShowForm(false)}>Cancel</button></div>
                        </fieldset>
                    </form></div>
                </div>
            </section>
            <section className="admin-card notice-list-card">
                <div className="admin-card-heading accounts-heading"><div><span className="step-label">NOTICE BOARD</span><h2>Posted notices</h2><p>Most recent notices appear first on the public helpdesk page.</p></div><span className="account-count">{notices.length} shown</span></div>
                {notices.length === 0 ? <div className="empty-state"><span>▧</span><strong>No notices posted</strong><small>Use the form above to share the first public notice.</small></div> :
                    <div className="notice-list">{notices.map((notice) => <article className="notice-item" key={notice.id}><div className="notice-item-heading"><h3>{notice.title}</h3><time dateTime={notice.created_at}>{new Date(notice.created_at).toLocaleString()}</time></div><p>{notice.body}</p>{notice.attachment_url && <a className="notice-attachment-link" href={notice.attachment_url} target="_blank" rel="noreferrer"><img className="notice-attachment-preview" src={notice.attachment_url} alt={`Attached memo for ${notice.title}`} /><span>Open scanned memo</span></a>}<div className="admin-notice-footer"><small>Posted by {notice.author || 'Administrator'}</small><button className="cancel-action" type="button" onClick={() => removeNotice(notice)}>Remove</button></div></article>)}</div>}
            </section>
        </DashboardLayout>
    );
}
