import React, { useMemo, useState } from 'react';
import axios from 'axios';
import DashboardLayout from '../../Components/DashboardLayout';
import { appUrl } from '../../url';
import {
    buildMonthlyAllocationPlan,
    MONTHLY_ALLOCATION_TARGET,
    WEEKDAY_ALLOCATION,
} from './monthlyAllocation';

function currentMonth() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

function formatDate(date) {
    return new Date(`${date}T00:00:00Z`).toLocaleDateString(undefined, {
        timeZone: 'UTC',
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
}

function formatMonthFilename(month) {
    return new Date(`${month}-01T00:00:00Z`).toLocaleDateString('en-US', {
        timeZone: 'UTC',
        month: 'long',
        year: 'numeric',
    }).replace(' ', '-');
}

function formatAmount(amount) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(amount);
}

export default function MonthlyAllocationPlanner({
    monthlyTarget = MONTHLY_ALLOCATION_TARGET,
    weekdayAllocation = WEEKDAY_ALLOCATION,
    adPaths = [],
}) {
    const [month, setMonth] = useState(currentMonth);
    const [target, setTarget] = useState(String(monthlyTarget));
    const [baseWeekdayAmount, setBaseWeekdayAmount] = useState(String(weekdayAllocation));
    const [downloaded, setDownloaded] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [previewRandomization, setPreviewRandomization] = useState(0);
    const [error, setError] = useState('');

    const numericTarget = Number(target);
    const validTarget = Number.isSafeInteger(numericTarget) && numericTarget > 0;
    const numericBaseWeekdayAmount = Number(baseWeekdayAmount);
    const validBaseWeekdayAmount = Number.isSafeInteger(numericBaseWeekdayAmount) && numericBaseWeekdayAmount > 0;
    const validAmounts = validTarget && validBaseWeekdayAmount;
    const plan = useMemo(
        () => validAmounts
            ? buildMonthlyAllocationPlan(month, numericTarget, adPaths, numericBaseWeekdayAmount)
            : [],
        [month, numericTarget, numericBaseWeekdayAmount, validAmounts, adPaths, previewRandomization],
    );
    const baseAmount = plan.length > 0
        ? numericTarget >= numericBaseWeekdayAmount * (plan.length - 2) + 2
            ? numericBaseWeekdayAmount
            : Math.min(numericBaseWeekdayAmount, Math.floor(numericTarget / plan.length))
        : 0;
    const finalTwoWeekdayAmounts = `${formatAmount(plan.at(-2)?.amount ?? 0)} + ${formatAmount(plan.at(-1)?.amount ?? 0)}`;

    const downloadPlan = async () => {
        setError('');
        setDownloaded(false);
        setProcessing(true);
        try {
            const response = await axios.post(appUrl('/admin/tools/monthly-allocation/download'), {
                month,
                target: numericTarget,
                base_amount: numericBaseWeekdayAmount,
                references: plan.map((entry) => entry.referenceNumber),
                ads: plan.map((entry) => entry.adPath),
            }, {
                responseType: 'blob',
                headers: { Accept: 'text/csv' },
                timeout: 30000,
            });
            const url = URL.createObjectURL(response.data);
            const link = document.createElement('a');
            link.href = url;
            link.download = `monthly-plan-${formatMonthFilename(month)}.csv`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            setDownloaded(true);
        } catch (reason) {
            const responseData = reason.response?.data;
            if (responseData instanceof Blob) {
                try {
                    const payload = JSON.parse(await responseData.text());
                    setError(payload.message || 'CSV download failed.');
                } catch {
                    setError('CSV download failed.');
                }
            } else {
                setError(reason.response?.data?.message || 'CSV download failed.');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <DashboardLayout title="Monthly CSV plan" activePage="monthly-allocation">
            <div className="dashboard-title-row">
                <div>
                    <p className="eyebrow">ADMIN TOOLS</p>
                    <h1>Monthly CSV plan</h1>
                    <p className="dashboard-subtitle">Download a CSV with one row for each weekday in the selected month.</p>
                </div>
            </div>

            <section className="admin-card allocation-planner-card">
                <div className="admin-card-heading">
                    <div>
                        <span className="step-label">MONTHLY CSV</span>
                        <h2>Select a month</h2>
                        <p>The CSV matches your example columns: number, datetime, value, refnumber, and ads. It includes weekdays only, uses times from 8:00–11:59 AM, and applies your month-end allocation rule: extra below PHP 500 goes to the last day; PHP 500 or more is split across the final two days.</p>
                    </div>
                </div>

                <div className="allocation-controls">
                    <label htmlFor="allocation-month">Month</label>
                    <input
                        id="allocation-month"
                        type="month"
                        value={month}
                        onChange={(event) => {
                            if (!event.target.value) return;
                            setMonth(event.target.value);
                            setDownloaded(false);
                        }}
                    />
                    <label htmlFor="allocation-target">Monthly target (PHP)</label>
                    <input
                        id="allocation-target"
                        type="number"
                        min="1"
                        max={Number.MAX_SAFE_INTEGER}
                        step="1"
                        value={target}
                        onChange={(event) => {
                            setTarget(event.target.value);
                            setDownloaded(false);
                        }}
                    />
                    <label htmlFor="allocation-base-amount">Base weekday amount (PHP)</label>
                    <input
                        id="allocation-base-amount"
                        type="number"
                        min="1"
                        max={Number.MAX_SAFE_INTEGER}
                        step="1"
                        value={baseWeekdayAmount}
                        onChange={(event) => {
                            setBaseWeekdayAmount(event.target.value);
                            setDownloaded(false);
                        }}
                    />
                    <button className="sign-in-button" type="button" onClick={downloadPlan} disabled={processing || !validAmounts}>
                        {processing ? 'Preparing CSV…' : 'Download CSV'}
                    </button>
                    <button
                        className="secondary-action"
                        type="button"
                        onClick={() => {
                            setPreviewRandomization((value) => value + 1);
                            setDownloaded(false);
                        }}
                        disabled={processing || adPaths.length === 0}
                    >
                        Rerandomize preview
                    </button>
                </div>
                {!validAmounts && <p className="dashboard-notice error" role="alert">Enter positive whole-peso values for the monthly target and base weekday amount.</p>}

                <div className="allocation-summary" aria-live="polite">
                    <div><span>Monthly target</span><strong>{formatAmount(validTarget ? numericTarget : 0)}</strong></div>
                    <div><span>Weekdays scheduled</span><strong>{plan.length}</strong></div>
                    <div><span>Base weekday amount</span><strong>{formatAmount(baseAmount)}</strong></div>
                    <div><span>Final two weekday amounts</span><strong>{finalTwoWeekdayAmounts}</strong></div>
                </div>

                <div className="table-wrap allocation-table-wrap">
                    <table>
                        <thead><tr><th>CSV date</th><th>Weekday</th><th>Value</th><th>Reference number</th><th>Ad path</th></tr></thead>
                        <tbody>
                            {plan.map((entry) => (
                                <tr key={entry.date}>
                                    <td>{entry.date}</td>
                                    <td>{formatDate(entry.date)}</td>
                                    <td>{formatAmount(entry.amount)}</td>
                                    <td>{entry.referenceNumber}</td>
                                    <td className="allocation-ad-path" title={entry.adPath}>{entry.adPath || 'No ad assets configured'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {error && <p className="dashboard-notice error" role="alert">{error}</p>}
                {downloaded && <p className="allocation-download-status" role="status">The monthly CSV has been downloaded.</p>}
            </section>
        </DashboardLayout>
    );
}
