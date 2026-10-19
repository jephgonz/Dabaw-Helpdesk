import React, { useRef } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Components/DashboardLayout';
import FilePicker from '../../Components/FilePicker';
import { appUrl } from '../../url';

export default function IdGenerator() {
    const { flash, errors } = usePage().props;
    const input = useRef(null);
    const form = useForm({ image: null, nickname: '', fullname: '', position: '', empnumber: '', fontsize: 70, fontsize2: 35 });
    const card = flash?.generatedCard;
    return <DashboardLayout title="SP ID Generator" activePage="id-generator">
        <div className="dashboard-title-row"><div><p className="eyebrow">EMPLOYEE SERVICES</p><h1>ID card generator</h1><p className="dashboard-subtitle">Create and download an employee identification card.</p></div></div>
        {errors.form && <div className="dashboard-notice error">{errors.form}</div>}
        <div className="admin-grid idgen-grid">
            <section className="admin-card"><div className="admin-card-heading"><div><span className="step-label">STEP 01</span><h2>Employee details</h2><p>Enter the information exactly as it should appear on the card.</p></div><span className="section-mark">✎</span></div>
                <form className="account-form idgen-form" onSubmit={(event) => { event.preventDefault(); form.post(appUrl('/admin/tools/id-generator')); }}>
                    <label className="idgen-photo-field" htmlFor="id-photo">ID picture
                        <FilePicker id="id-photo" inputRef={input} accept="image/jpeg,.jpg,.jpeg" selectedFile={form.data.image} buttonText="Choose photo" emptyText="No photo selected" required onChange={(event) => form.setData('image', event.target.files[0] || null)} />
                        <small>JPEG image with a white background.</small>
                        {errors.image && <small className="field-error">{errors.image}</small>}
                    </label>
                    <label>Nickname<input value={form.data.nickname} onChange={(event) => form.setData('nickname', event.target.value)} required /></label>
                    <label>Full name<input value={form.data.fullname} onChange={(event) => form.setData('fullname', event.target.value)} required /></label>
                    <label>Position<input value={form.data.position} onChange={(event) => form.setData('position', event.target.value)} required /></label>
                    <label>Employee number<input type="number" min="1" value={form.data.empnumber} onChange={(event) => form.setData('empnumber', event.target.value)} required /></label>
                    <label>Name font size<input type="number" min="10" max="120" value={form.data.fontsize} onChange={(event) => form.setData('fontsize', event.target.value)} /></label>
                    <label>Position font size<input type="number" min="10" max="80" value={form.data.fontsize2} onChange={(event) => form.setData('fontsize2', event.target.value)} /></label>
                    <button className="sign-in-button" type="submit" disabled={form.processing}>{form.processing ? 'Generating…' : 'Generate ID →'}</button>
                </form>
            </section>
            <section className="admin-card"><div className="admin-card-heading"><div><span className="step-label">STEP 02</span><h2>Card preview</h2><p>Review the generated card before downloading.</p></div></div>
                <div className="idgen-preview"><img src={card ? appUrl(`/admin/tools/id-generator/preview/${encodeURIComponent(card)}`) : appUrl('/public/tools/sp-id-generator/id.png')} alt={card ? 'Generated employee ID card' : 'Sample employee ID card template'} /></div>
                {card ? <p><a className="secondary-action primary-action" href={appUrl(`/admin/tools/id-generator/download/${encodeURIComponent(card)}`)}>↓ Download ID card</a></p> : <p className="dashboard-subtitle">Your generated card will appear here.</p>}
            </section>
        </div>
    </DashboardLayout>;
}
