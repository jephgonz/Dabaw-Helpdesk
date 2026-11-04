import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { appUrl } from '../url';

const defaultSettings = {
    enabled: false,
    task_due: true,
};

const notificationOptions = [
    ['task_due', 'Tasks due today'],
];

function readStorage(key) {
    try {
        return { value: window.localStorage.getItem(key), failed: false };
    } catch {
        return { value: null, failed: true };
    }
}

function writeStorage(key, value) {
    try {
        window.localStorage.setItem(key, value);
        return true;
    } catch {
        return false;
    }
}

export default function BrowserNotifications({ userId }) {
    const [open, setOpen] = useState(false);
    const [settings, setSettings] = useState(defaultSettings);
    const [loaded, setLoaded] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [permission, setPermission] = useState(() => ('Notification' in window ? window.Notification.permission : 'unsupported'));
    const menuRef = useRef(null);
    const deliveredRef = useRef(new Set());
    const deliveredKey = `workbench-notification-delivered-${userId}`;

    useEffect(() => {
        let cancelled = false;
        axios.get(appUrl('/admin/notifications/settings'))
            .then(({ data }) => {
                if (!cancelled) {
                    setSettings(data);
                    setLoaded(true);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError('Notification settings could not be loaded. Please try again.');
                    setLoaded(true);
                }
            });
        const savedDelivered = readStorage(deliveredKey);
        if (savedDelivered.failed) {
            setError('Browser storage is unavailable. Some notifications may repeat after a reload.');
        }
        if (savedDelivered.value) {
            try {
                deliveredRef.current = new Set(JSON.parse(savedDelivered.value));
            } catch {
                deliveredRef.current = new Set();
                setError('Saved notification history could not be read. Some notifications may repeat.');
            }
        }
        return () => { cancelled = true; };
    }, [deliveredKey]);

    useEffect(() => {
        const closeOnOutsideClick = (event) => {
            if (!menuRef.current?.contains(event.target)) setOpen(false);
        };
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') setOpen(false);
        };
        document.addEventListener('pointerdown', closeOnOutsideClick);
        document.addEventListener('keydown', closeOnEscape);
        return () => {
            document.removeEventListener('pointerdown', closeOnOutsideClick);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, []);

    useEffect(() => {
        if (!loaded || !settings.enabled || permission !== 'granted') return undefined;
        let checking = false;
        const checkNotifications = async () => {
            if (checking || document.visibilityState === 'hidden') return;
            checking = true;
            try {
                const { data } = await axios.get(appUrl('/admin/notifications'));
                let storageFailed = false;
                const seen = deliveredRef.current;
                for (const event of data.events) {
                    if (seen.has(event.id)) continue;
                    const notification = new window.Notification(event.title, {
                        body: event.body,
                        icon: appUrl('/public/assets/images/SP.ico'),
                        tag: event.id,
                    });
                    notification.onclick = () => {
                        window.focus();
                        window.location.assign(appUrl(event.url));
                    };
                    seen.add(event.id);
                }
                const recent = [...seen].slice(-200);
                deliveredRef.current = new Set(recent);
                storageFailed = !writeStorage(deliveredKey, JSON.stringify(recent)) || storageFailed;
                setError(storageFailed ? 'Browser storage is unavailable. Some notifications may repeat after a reload.' : '');
            } catch {
                setError('Browser notifications could not be checked. Please check your connection.');
            } finally {
                checking = false;
            }
        };
        checkNotifications();
        const interval = window.setInterval(checkNotifications, 30000);
        document.addEventListener('visibilitychange', checkNotifications);
        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', checkNotifications);
        };
    }, [deliveredKey, loaded, permission, settings.enabled]);

    const saveSettings = async (nextSettings) => {
        const previousSettings = settings;
        setSettings(nextSettings);
        setSaving(true);
        setError('');
        try {
            const { data } = await axios.post(appUrl('/admin/notifications/settings'), nextSettings);
            setSettings(data);
        } catch {
            setSettings(previousSettings);
            setError('Notification settings could not be saved. Please try again.');
        } finally {
            setSaving(false);
        }
    };

    const enableNotifications = async () => {
        setError('');
        if (!('Notification' in window)) {
            setError('This browser does not support desktop notifications.');
            setPermission('unsupported');
            return;
        }
        if (!window.isSecureContext) {
            setError('Browser notifications require a secure connection (HTTPS or localhost).');
            return;
        }
        let nextPermission;
        try {
            nextPermission = window.Notification.permission === 'default'
                ? await window.Notification.requestPermission()
                : window.Notification.permission;
        } catch {
            setError('Your browser could not request notification permission. Check the browser site settings.');
            return;
        }
        setPermission(nextPermission);
        if (nextPermission !== 'granted') {
            setError(nextPermission === 'denied'
                ? 'Notifications are blocked by your browser. Allow them in this site’s browser settings.'
                : 'Allow browser notifications to receive reminders.');
            return;
        }
        await saveSettings({ ...settings, enabled: true });
    };

    return (
        <div className="notification-center" ref={menuRef}>
            <button className="notification-trigger" type="button" aria-label="Notification settings" aria-expanded={open} aria-controls="notification-menu" onClick={() => setOpen((value) => !value)}>
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
            </button>
            <section className={`notification-menu${open ? ' is-open' : ''}`} id="notification-menu" aria-hidden={!open} inert={!open}>
                <div className="notification-menu-heading"><div><strong>Browser notifications</strong><small>Alerts while the workbench is open</small></div><span className={`notification-state${settings.enabled && permission === 'granted' ? ' is-enabled' : ''}`}>{settings.enabled && permission === 'granted' ? 'On' : 'Off'}</span></div>
                <div className="notification-menu-content">
                    {!settings.enabled || permission !== 'granted'
                        ? <button className="sign-in-button notification-enable" type="button" disabled={!loaded || saving} onClick={enableNotifications}>{saving ? 'Saving…' : 'Enable browser alerts'}</button>
                        : <button className="notification-pause" type="button" disabled={saving} onClick={() => saveSettings({ ...settings, enabled: false })}>{saving ? 'Saving…' : 'Pause all notifications'}</button>}
                    <fieldset disabled={!loaded || saving || !settings.enabled}>
                        <legend>Notify me about</legend>
                        {notificationOptions.map(([key, label]) => <label className="notification-option" key={key}><input type="checkbox" checked={settings[key]} onChange={(event) => saveSettings({ ...settings, [key]: event.target.checked })} /><span>{label}</span></label>)}
                    </fieldset>
                    <p className="notification-help">Task reminders appear on the due date.</p>
                    {error && <p className="notification-error" role="alert">{error}</p>}
                </div>
            </section>
        </div>
    );
}
