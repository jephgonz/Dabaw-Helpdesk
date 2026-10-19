import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { appUrl } from '../url';

async function chatRequest(path, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content || '';
    }
    const options = {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    };
    let response = await fetch(appUrl(path), options);
    if (response.status === 419 && body !== undefined) {
        const tokenResponse = await fetch(appUrl('/chat/state'), {
            method: 'GET',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const tokenData = await tokenResponse.json().catch(() => ({}));
        if (!tokenResponse.ok || typeof tokenData.csrfToken !== 'string' || tokenData.csrfToken.length === 0) {
            throw new Error('Your chat session expired. Refresh the page and try again.');
        }
        headers['X-CSRF-TOKEN'] = tokenData.csrfToken;
        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', tokenData.csrfToken);
        response = await fetch(appUrl(path), options);
    }
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const validation = data.errors ? Object.values(data.errors).flat()[0] : null;
        throw new Error(validation || data.message || 'The chat service could not complete the request.');
    }
    return data;
}

export default function ChatWidget() {
    const { auth } = usePage().props;
    const userId = Number(auth?.user?.id);
    const [isOpen, setIsOpen] = useState(() => window.location.pathname.endsWith('/chat'));
    const [contacts, setContacts] = useState([]);
    const [conversations, setConversations] = useState([]);
    const [selectedId, setSelectedId] = useState(null);
    const [selectedUser, setSelectedUser] = useState(null);
    const [showContacts, setShowContacts] = useState(false);
    const [messages, setMessages] = useState([]);
    const [draft, setDraft] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const refreshing = useRef(false);
    const messageList = useRef(null);

    const selectedConversation = conversations.find((conversation) => conversation.id === selectedId) || null;
    const selectedContact = selectedConversation?.user || selectedUser;
    const selectedMessages = useMemo(() => messages.filter((message) => message.conversationId === selectedId), [messages, selectedId]);

    const loadMessages = useCallback(async (conversationId) => {
        const result = await chatRequest(`/chat/conversations/${conversationId}/messages`);
        setMessages(result.messages.map((message) => ({ ...message, conversationId })));
    }, []);

    const refresh = useCallback(async () => {
        if (refreshing.current) return;
        refreshing.current = true;
        try {
            const state = await chatRequest('/chat/state');
            setContacts(state.users);
            setConversations(state.conversations);
            if (selectedId && state.conversations.some((conversation) => conversation.id === selectedId)) {
                await loadMessages(selectedId);
            } else if (selectedId) {
                setSelectedId(null);
                setMessages([]);
            }
            setError('');
        } catch (reason) {
            setError(reason.message);
        } finally {
            refreshing.current = false;
        }
    }, [loadMessages, selectedId]);

    useEffect(() => {
        refresh();
        const timer = window.setInterval(refresh, 5000);
        return () => window.clearInterval(timer);
    }, [refresh]);

    useEffect(() => {
        messageList.current?.scrollTo({ top: messageList.current.scrollHeight, behavior: 'smooth' });
    }, [selectedMessages.length, selectedId]);

    const openConversation = async (contact) => {
        setError('');
        setSelectedUser(contact);
        const conversation = conversations.find((entry) => entry.user.id === contact.id);
        if (conversation) {
            setSelectedId(conversation.id);
            setBusy(true);
            try {
                await loadMessages(conversation.id);
            } catch (reason) {
                setError(reason.message);
            } finally {
                setBusy(false);
            }
        } else {
            setSelectedId(null);
            setMessages([]);
        }
    };

    const sendMessage = async (event) => {
        event.preventDefault();
        const body = draft.trim();
        if (!selectedContact || !body || busy) return;
        setBusy(true);
        setError('');
        try {
            let conversation = selectedConversation;
            if (!conversation) {
                const result = await chatRequest('/chat/conversations', { method: 'POST', body: { user_id: selectedContact.id } });
                conversation = { id: result.conversation_id, user: selectedContact, lastMessage: null };
                setConversations((current) => [conversation, ...current.filter((entry) => entry.id !== conversation.id)]);
                setSelectedId(conversation.id);
            }
            await chatRequest(`/chat/conversations/${conversation.id}/messages`, { method: 'POST', body: { body } });
            setDraft('');
            await loadMessages(conversation.id);
            await refresh();
        } catch (reason) {
            setError(reason.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className={`messenger-dock${isOpen ? ' is-open' : ''}`}>
            <section className={`messenger-panel${isOpen ? ' is-visible' : ''}${showContacts ? ' has-contacts' : ''}`} aria-label="Chat" aria-hidden={!isOpen} inert={!isOpen}>
                <header className="messenger-header">
                    {selectedContact && <button type="button" className="messenger-back" aria-label="Back to chats" onClick={() => { setSelectedId(null); setSelectedUser(null); setMessages([]); }}>‹</button>}
                    <div><strong>{selectedContact?.name || 'Chats'}</strong><small>{selectedContact?.role || 'Message staff and admins'}</small></div>
                    <button type="button" className={`messenger-contacts-toggle${showContacts ? ' active' : ''}`} aria-expanded={showContacts} aria-controls="messenger-contacts" onClick={() => setShowContacts((visible) => !visible)}>People</button>
                    <button type="button" className="messenger-close" aria-label="Minimize chat" onClick={() => setIsOpen(false)}>−</button>
                </header>
                {error && <div className="messenger-error" role="alert">{error}</div>}
                <div className="messenger-body">
                {!selectedContact ? <div className="messenger-list">
                    {conversations.map((conversation) => <button type="button" className="messenger-person" key={conversation.id} onClick={() => openConversation(conversation.user)} disabled={busy}>
                        <span className="messenger-avatar">{conversation.user.name.slice(0, 1).toUpperCase()}</span>
                        <span><strong>{conversation.user.name}</strong><small>{conversation.lastMessage?.body || 'Start a conversation'}</small></span>
                    </button>)}
                    {conversations.length === 0 && <p className="messenger-empty">Choose someone from People to start a chat.</p>}
                </div> : <div className="messenger-conversation">
                    {!selectedConversation && <p className="messenger-new-conversation">Send a message to start your conversation with {selectedContact.name}.</p>}
                    <div className="messenger-messages" ref={messageList} aria-live="polite">
                        {selectedConversation && selectedMessages.length === 0 ? <p className="messenger-empty">Say hello to {selectedContact.name}.</p> : selectedMessages.map((message) => <article className={`messenger-message${message.senderId === userId ? ' mine' : ''}`} key={message.id}>
                            <p>{message.body}</p><time dateTime={message.createdAt}>{new Date(message.createdAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}</time>
                        </article>)}
                    </div>
                    <form className="messenger-compose" onSubmit={sendMessage}>
                        <textarea value={draft} onChange={(event) => setDraft(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); event.currentTarget.form.requestSubmit(); } }} rows="1" maxLength="8000" placeholder="Aa" aria-label="Message" />
                        <button type="submit" disabled={busy || !draft.trim()} aria-label="Send message">➤</button>
                    </form>
                </div>}
                <aside className="messenger-contacts" id="messenger-contacts" aria-hidden={!showContacts} inert={!showContacts}>
                    <strong className="messenger-contacts-title">All people</strong>
                    {contacts.map((contact) => <button type="button" className={`messenger-person${selectedContact?.id === contact.id ? ' selected' : ''}`} key={contact.id} onClick={() => openConversation(contact)} disabled={busy}>
                        <span className="messenger-avatar">{contact.name.slice(0, 1).toUpperCase()}</span>
                        <span><strong>{contact.name}</strong><small>{contact.role} · {conversations.some((conversation) => conversation.user.id === contact.id) ? 'conversation' : 'new message'}</small></span>
                    </button>)}
                    {contacts.length === 0 && <p className="messenger-empty">No other active accounts.</p>}
                </aside>
                </div>
            </section>
            <button className="messenger-launcher" type="button" aria-expanded={isOpen} aria-label={isOpen ? 'Minimize chat' : 'Open chat'} onClick={() => setIsOpen((open) => !open)}>
                <strong>Chat</strong>
            </button>
        </div>
    );
}
