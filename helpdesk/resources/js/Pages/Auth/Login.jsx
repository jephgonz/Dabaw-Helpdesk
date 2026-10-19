import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { appUrl } from '../../url';

export default function Login() {
    const form = useForm({ login_action: 'admin_login', email: '', password: '' });
    const error = form.errors.login || form.errors.login_action;

    return (
        <>
            <Head title="Administrator sign in" />
            <main className="login-shell admin-login-shell">
                <section className="welcome-panel" aria-label="Helpdesk introduction">
                    <Link className="brand" href={appUrl('/')}><span className="brand-mark brand-logo"><img src={appUrl('/public/assets/images/SP.ico')} alt="" /></span><span className="brand-name"><strong>Jephthah Helpdesk</strong></span></Link>
                    <div className="welcome-copy">
                        <span className="eyebrow"><span className="eyebrow-dot" /> PUBLIC SUPPORT PORTAL</span>
                        <h1>How can we<br />help you today?</h1>
                        <p>Send a support report without an account, then check the latest notices below.</p>
                        <div className="support-illustration" aria-hidden="true"><div className="illustration-orbit orbit-one" /><div className="illustration-orbit orbit-two" /><div className="ticket-card"><div className="ticket-top"><span className="ticket-symbol">✓</span><span className="ticket-status">SUPPORT REQUEST</span><span className="ticket-dots">•••</span></div><div className="ticket-line line-long" /><div className="ticket-line line-short" /><div className="ticket-bottom"><span className="ticket-avatar">JH</span><span>Help is on the way</span><span className="ticket-arrow">↗</span></div></div><span className="float-star star-one">✦</span><span className="float-star star-two">✦</span></div>
                    </div>
                    <div className="welcome-footer"><span className="online-dot" />Open support portal <span className="footer-divider">·</span> Jephthah Helpdesk</div>
                </section>
                <section className="form-panel" aria-labelledby="login-title">
                    <div className="mobile-brand"><span className="brand-mark brand-logo"><img src={appUrl('/public/assets/images/SP.ico')} alt="" /></span><span><strong>Jephthah Helpdesk</strong></span></div>
                    <div className="login-card">
                        <div className="login-heading"><span className="login-icon login-brand-logo" aria-hidden="true"><img src={appUrl('/public/assets/images/SP.ico')} alt="" /></span><p className="eyebrow">ADMIN ACCESS</p><h2 id="login-title">Administrator sign in</h2><p className="login-subtitle">Use your administrator email and password.</p></div>
                        {error && <div className="notice" role="alert">{error}</div>}
                        <form className="login-form" onSubmit={(event) => { event.preventDefault(); form.post(appUrl('/admin/login')); }}>
                            <div className="field"><label htmlFor="email">Administrator email</label><div className="input-wrap"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3.5" y="5.5" width="17" height="13" rx="2" /><path d="m4.5 7 7.5 5.5L19.5 7" /></svg><input id="email" type="email" autoComplete="username" placeholder="Your administrator email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required /></div></div>
                            <div className="field"><label htmlFor="password">Password</label><div className="input-wrap"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4.5" y="10" width="15" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /><path d="M12 14v2" /></svg><input id="password" type="password" autoComplete="current-password" placeholder="Enter your password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required /></div></div>
                            <button className="sign-in-button" type="submit" disabled={form.processing}>{form.processing ? 'Signing in…' : <>Sign in <span aria-hidden="true">→</span></>}</button>
                        </form>
                        <p className="access-note"><Link href={appUrl('/')}>← Return to public helpdesk</Link></p>
                    </div>
                    <footer className="form-footer"><span>© {new Date().getFullYear()} Jephthah Helpdesk</span><span>Administrator access only</span></footer>
                </section>
            </main>
        </>
    );
}
