import React, { useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { appUrl } from '../url';

const adminLinks = [
    { key: 'overview', label: 'Overview', icon: '▦', href: '/admin' },
    { key: 'tickets', label: 'Support requests', icon: '▤', href: '/admin/tickets' },
    { key: 'notices', label: 'Public notices', icon: '▧', href: '/admin/notices' },
    { key: 'users', label: 'Admin accounts', icon: '♙', href: '/admin/users' },
    { key: 'database', label: 'MySQL database', icon: '▤', href: '/admin/database' },
    { key: 'id-generator', label: 'SP ID Generator', icon: '▧', href: '/admin/tools/id-generator' },
    { key: 'monthly-allocation', label: 'Monthly CSV', icon: '▤', href: '/admin/tools/monthly-allocation' },
];

export default function DashboardLayout({ title, activePage, children }) {
    const { auth, errors, flash, newTicketCount } = usePage().props;
    const user = auth?.user;
    const [profileOpen, setProfileOpen] = useState(false);
    const profileRef = useRef(null);
    const navigationRef = useRef(null);
    const profile = useForm({
        display_name: user?.name || '',
        email: user?.email || '',
    });
    const saveProfile = (event) => {
        event.preventDefault();
        profile.post(appUrl('/profile'), { onSuccess: () => setProfileOpen(false) });
    };
    useEffect(() => {
        const closeOnOutsideClick = (event) => {
            if (!profileRef.current?.contains(event.target)) setProfileOpen(false);
        };
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') setProfileOpen(false);
        };
        document.addEventListener('pointerdown', closeOnOutsideClick);
        document.addEventListener('keydown', closeOnEscape);
        return () => {
            document.removeEventListener('pointerdown', closeOnOutsideClick);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, []);
    useEffect(() => {
        if (!window.matchMedia('(max-width: 760px)').matches) return;
        const navigation = navigationRef.current;
        const sidebar = navigation?.parentElement;
        const activeLink = navigation?.querySelector('.dashboard-nav.active');
        if (!sidebar || !activeLink) return;
        sidebar.scrollLeft = activeLink.offsetLeft - (sidebar.clientWidth - activeLink.offsetWidth) / 2;
    }, [activePage]);
    return (
        <div className="dashboard-shell">
            <Head title={title} />
            <aside className="dashboard-sidebar">
                <Link className="dashboard-brand" href={appUrl('/admin')}>
                    <span className="brand-mark brand-logo"><img src={appUrl('/public/assets/images/SP.ico')} alt="" /></span>
                    <span>Jephthah <strong>Helpdesk</strong></span>
                </Link>
                <p className="dashboard-nav-label">ADMINISTRATION</p>
                <nav className="dashboard-navigation" aria-label="Admin navigation" ref={navigationRef}>
                    {adminLinks.map((link) => (
                        <Link key={link.key} className={`dashboard-nav ${activePage === link.key ? 'active' : ''}`} href={appUrl(link.href)}>
                            <span>{link.icon}</span>{link.label}
                            {link.key === 'tickets' && Number.isInteger(newTicketCount) && newTicketCount > 0 && <span className="nav-count">{newTicketCount}</span>}
                        </Link>
                    ))}
                </nav>
                <div className="dashboard-sidebar-foot"><span className="online-dot" />Admin workspace</div>
            </aside>
            <main className="dashboard-main">
                <header className="dashboard-topbar">
                    <div><span className="dashboard-crumb">Helpdesk /</span> {title}</div>
                    <div className="topbar-user">
                        <span className="admin-pill"><span />Admin access</span>
                        <div className="topbar-profile" ref={profileRef}>
                            <button className="profile-trigger" type="button" aria-label={`Profile menu for ${user?.name || 'administrator'}`} aria-expanded={profileOpen} aria-controls="profile-menu" onClick={() => setProfileOpen((open) => !open)}>
                                <span className="user-badge">{(user?.name || '?').slice(0, 1).toUpperCase()}</span>
                                <span className="profile-trigger-name">{user?.name}</span>
                                <span className={`profile-chevron${profileOpen ? ' is-open' : ''}`} aria-hidden="true">⌄</span>
                            </button>
                            <section className={`profile-menu${profileOpen ? ' is-open' : ''}`} id="profile-menu" aria-hidden={!profileOpen} inert={!profileOpen}>
                                <div className="profile-menu-heading"><strong>Your profile</strong><small>Administrator</small></div>
                                <form className="profile-form" onSubmit={saveProfile}>
                                    <fieldset disabled={!profileOpen || profile.processing}>
                                        <label>Display name<input value={profile.data.display_name} onChange={(event) => profile.setData('display_name', event.target.value)} maxLength="120" required /></label>
                                        <label>Email<input type="email" value={profile.data.email} onChange={(event) => profile.setData('email', event.target.value)} maxLength="190" required /><small>Email addresses do not need to be unique.</small></label>
                                        {Object.entries(profile.errors).map(([field, message]) => <small className="profile-field-error" key={field}>{message}</small>)}
                                        <button className="sign-in-button" type="submit" disabled={profile.processing}>{profile.processing ? 'Saving…' : 'Save profile'}</button>
                                    </fieldset>
                                </form>
                                <button className="profile-signout" type="button" onClick={() => router.post(appUrl('/logout'))}>Sign out</button>
                            </section>
                        </div>
                    </div>
                </header>
                <div className="dashboard-content laravel-content">
                    {flash?.success && <div className="dashboard-notice success" role="status">{flash.success}</div>}
                    {flash?.error && <div className="dashboard-notice error" role="alert">{flash.error}</div>}
                    {errors?.form && <div className="dashboard-notice error" role="alert">{errors.form}</div>}
                    {children}
                    <footer className="dashboard-footer">Jephthah Helpdesk <span>·</span> Administration</footer>
                </div>
            </main>
        </div>
    );
}
