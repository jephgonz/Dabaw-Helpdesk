import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { appUrl } from '../../url';

export default function Login({ type = 'staff' }) {
    const admin = type === 'admin';
    const form = useForm({
        login_action: admin ? 'admin_login' : 'staff_login',
        email: '',
        username: '',
        password: '',
    });
    const error = form.errors.login || form.errors.login_action;

    return (
        <>
        <Head title={admin ? 'Administrator sign in' : 'Staff sign in'} />
        <main className="login-shell">
            <section className="welcome-panel" aria-label="Helpdesk introduction">
                <Link className="brand" href={appUrl('/login')}><span className="brand-mark brand-logo"><img src={appUrl('/public/assets/images/SP.ico')} alt="" /></span><span className="brand-name">Dabaw <strong>Helpdesk</strong></span></Link>
                <div className="welcome-copy">
                    <span className="eyebrow"><span className="eyebrow-dot" /> STAFF SUPPORT PORTAL</span>
                    <h1>How can we<br />help you today?</h1>
                    <p>Sign in to request support, follow your tickets, and keep work moving.</p>
                    <div className="support-illustration" aria-hidden="true"><div className="illustration-orbit orbit-one" /><div className="illustration-orbit orbit-two" /><div className="ticket-card"><div className="ticket-top"><span className="ticket-symbol">✓</span><span className="ticket-status">SUPPORT REQUEST</span><span className="ticket-dots">•••</span></div><div className="ticket-line line-long" /><div className="ticket-line line-short" /><div className="ticket-bottom"><span className="ticket-avatar">SP</span><span>Help is on the way</span><span className="ticket-arrow">↗</span></div></div><span className="float-star star-one">✦</span><span className="float-star star-two">✦</span></div>
                </div>
                <div className="welcome-footer"><span className="online-dot" />Support portal <span className="footer-divider">·</span> City of Davao</div>
            </section>
            <section className="form-panel" aria-labelledby="login-title">
                <div className="mobile-brand"><span className="brand-mark brand-logo"><img src={appUrl('/public/assets/images/SP.ico')} alt="" /></span><span>Dabaw <strong>Helpdesk</strong></span></div>
                <div className="login-card">
                    <div className="login-heading"><span className="login-icon login-brand-logo" aria-hidden="true"><img src={appUrl('/public/assets/images/SP.ico')} alt="" /></span><p className="eyebrow">WELCOME BACK</p><h2 id="login-title">{admin ? 'Administrator sign in' : 'Staff sign in'}</h2><p className="login-subtitle">{admin ? 'Use your administrator email and password.' : 'Use the username and password provided by your administrator.'}</p></div>
                    <nav className="auth-tabs" aria-label="Choose account type">
                        <Link className={!admin ? 'active' : ''} href={appUrl('/login?type=staff')} aria-current={!admin ? 'page' : undefined}>Staff</Link>
                        <Link className={admin ? 'active' : ''} href={appUrl('/login?type=admin')} aria-current={admin ? 'page' : undefined}>Administrator</Link>
                    </nav>
                    {error && <div className="notice" role="alert">{error}</div>}
                    <form className="login-form" onSubmit={(event) => { event.preventDefault(); form.post(appUrl('/login')); }}>
                        <input type="hidden" value={form.data.login_action} readOnly />
                        <div className="field"><label htmlFor={admin ? 'email' : 'username'}>{admin ? 'Administrator email' : 'Username'}</label>
                            <div className="input-wrap">
                                {admin ? <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3.5" y="5.5" width="17" height="13" rx="2" /><path d="m4.5 7 7.5 5.5L19.5 7" /></svg> : <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="3.5" /><path d="M5 20c.5-3.5 3.1-5.5 7-5.5s6.5 2 7 5.5" /></svg>}
                                <input id={admin ? 'email' : 'username'} type={admin ? 'email' : 'text'} autoComplete="username" placeholder={admin ? 'admin@organization.gov.ph' : 'Enter your staff username'} value={admin ? form.data.email : form.data.username} onChange={(event) => form.setData(admin ? 'email' : 'username', event.target.value)} required />
                            </div>
                        </div>
                        <div className="field"><label htmlFor="password">Password</label><div className="input-wrap"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4.5" y="10" width="15" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /><path d="M12 14v2" /></svg><input id="password" type="password" autoComplete="current-password" placeholder="Enter your password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required /></div></div>
                        <button className="sign-in-button" type="submit" disabled={form.processing}>{form.processing ? 'Signing in…' : <>Sign in <span aria-hidden="true">→</span></>}</button>
                    </form>
                    <p className="access-note"><span aria-hidden="true">ⓘ</span> Need an account? Contact your helpdesk administrator.</p>
                    <div className="login-divider"><span /><span>{admin ? 'ADMINISTRATOR ACCESS' : 'SECURE STAFF ACCESS'}</span><span /></div>
                </div>
                <footer className="form-footer"><span>© {new Date().getFullYear()} Dabaw Helpdesk</span><span>For authorized staff</span></footer>
            </section>
        </main>
        </>
    );
}
