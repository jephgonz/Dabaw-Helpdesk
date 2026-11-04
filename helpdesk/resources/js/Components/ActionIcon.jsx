import React from 'react';

export default function ActionIcon({ type }) {
    if (type === 'edit') {
        return <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false"><path d="M22.7 19 13.6 9.9a6 6 0 0 0-7.8-7.8l3.5 3.5-2.8 2.8L3 4.9a6 6 0 0 0 7.8 7.8l9.1 9.1a2 2 0 0 0 2.8-2.8Z" /></svg>;
    }

    return <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false"><path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="m19 6-1 14H6L5 6" /><path d="M10 11v5M14 11v5" /></svg>;
}
