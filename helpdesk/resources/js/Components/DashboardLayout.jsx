import React, { useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { appUrl } from '../url';
import ChatWidget from './ChatWidget';

const adminLinks = [
    { key: 'overview', label: 'Overview', icon: '▦', href: '/admin' },
    { key: 'tickets', label: 'Support requests', icon: '▤', href: '/admin/tickets' },
    { key: 'notices', label: 'Staff notices', icon: '▧', href: '/admin/notices' },
    { key: 'users', label: 'Staff accounts', icon: '♙', href: '/admin/users' },
    { key: 'tasks', label: 'Task boards', icon: '▦', href: '/tasks' },
    { key: 'database', label: 'MySQL database', icon: '▤', href: '/admin/database' },
    { key: 'id-generator', label: 'SP ID Generator', icon: '▧', href: '/admin/tools/id-generator' },
];

export default function DashboardLayout({ title, activePage, children }) {
    const { auth, errors, flash, newTicketCount } = usePage().props;
    const user = auth?.user;
    const isAdmin = user?.role === 'admin';
    const [profileOpen, setProfileOpen] = useState(false);
    const profileRef = useRef(null);
    const profile = useForm({
        display_name: user?.name || '',
        username: user?.username || '',
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
    const links = isAdmin ? adminLinks : [
        { key: 'overview', label: 'Overview', icon: '▦', href: '/dashboard' },
        { key: 'support', label: 'Request support', icon: '▤', href: '/dashboard/requests' },
        { key: 'tasks', label: 'Task boards', icon: '▦', href: '/tasks' },
    ];

    return (
        <div className="dashboard-shell">
            <Head title={title} />
            <aside className="dashboard-sidebar">
                <Link className="dashboard-brand" href={appUrl(isAdmin ? '/admin' : '/dashboard')}>
                    <span className="brand-mark brand-logo"><img src={appUrl('/public/assets/images/SP.ico')} alt="" /></span>
                    <span>Dabaw <strong>Helpdesk</strong></span>
                </Link>
                <p className="dashboard-nav-label">{isAdmin ? 'ADMINISTRATION' : 'STAFF WORKSPACE'}</p>
                {links.map((link) => (
                    <Link key={link.key} className={`dashboard-nav ${activePage === link.key ? 'active' : ''}`} href={appUrl(link.href)}>
                        <span>{link.icon}</span>{link.label}
                        {link.key === 'tickets' && Number.isInteger(newTicketCount) && newTicketCount > 0 && <span className="nav-count">{newTicketCount}</span>}
                    </Link>
                ))}
                <div className="dashboard-sidebar-foot"><span className="online-dot" />{isAdmin ? 'Admin workspace' : 'Staff workspace'}</div>
            </aside>
            <main className="dashboard-main">
                <header className="dashboard-topbar">
                    <div><span className="dashboard-crumb">Helpdesk /</span> {title}</div>
                    <div className="topbar-user">
                        <span className="admin-pill"><span />{isAdmin ? 'Admin access' : 'Staff access'}</span>
                        <div className="topbar-profile" ref={profileRef}>
                            <button className="profile-trigger" type="button" aria-expanded={profileOpen} aria-controls="profile-menu" onClick={() => setProfileOpen((open) => !open)}>
                                <span className="user-badge">{(user?.name || '?').slice(0, 1).toUpperCase()}</span>
                                <span className="profile-trigger-name">{user?.name}</span>
                                <span className={`profile-chevron${profileOpen ? ' is-open' : ''}`} aria-hidden="true">⌄</span>
                            </button>
                            <section className={`profile-menu${profileOpen ? ' is-open' : ''}`} id="profile-menu" aria-hidden={!profileOpen} inert={!profileOpen}>
                                <div className="profile-menu-heading"><strong>Your profile</strong><small>{isAdmin ? 'Administrator' : 'Staff account'}</small></div>
                                {isAdmin ? (
                                    <form className="profile-form" onSubmit={saveProfile}>
                                        <fieldset disabled={!profileOpen || profile.processing}>
                                            <label>Display name<input value={profile.data.display_name} onChange={(event) => profile.setData('display_name', event.target.value)} maxLength="120" required /></label>
                                            <label>Email<input type="email" value={profile.data.email} onChange={(event) => profile.setData('email', event.target.value)} maxLength="190" required /><small>Email addresses do not need to be unique.</small></label>
                                            {Object.entries(profile.errors).map(([field, message]) => <small className="profile-field-error" key={field}>{message}</small>)}
                                            <button className="sign-in-button" type="submit" disabled={profile.processing}>{profile.processing ? 'Saving…' : 'Save profile'}</button>
                                        </fieldset>
                                    </form>
                                ) : (
                                    <dl className="profile-details">
                                        <div><dt>Display name</dt><dd>{user?.name || '—'}</dd></div>
                                        <div><dt>Username</dt><dd>{user?.username || '—'}</dd></div>
                                    </dl>
                                )}
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
                    <footer className="dashboard-footer">Dabaw Helpdesk <span>·</span> {isAdmin ? 'Administration' : 'Staff support'}</footer>
                </div>
            </main>
            <ChatWidget />
        </div>
    );
}
