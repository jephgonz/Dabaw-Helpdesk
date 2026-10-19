import React from 'react';
import DashboardLayout from '../../Components/DashboardLayout';

export default function Chat() {
    return (
        <DashboardLayout title="Chat" activePage="chat">
            <div className="dashboard-title-row">
                <div>
                    <p className="eyebrow">ACCOUNT MESSAGING</p>
                    <h1>Chat</h1>
                    <p className="dashboard-subtitle">Use the chat button in the lower-right corner to message staff and admins from any page.</p>
                </div>
            </div>
        </DashboardLayout>
    );
}
