import React, { useEffect, useRef, useState } from 'react';
import { appUrl } from '../url';

export default function ResolutionChart() {
    const canvas = useRef(null);
    const [period, setPeriod] = useState('month');
    const [chart, setChart] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        let active = true;
        fetch(`${appUrl('/admin/tickets/metrics')}?period=${encodeURIComponent(period)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then(async (response) => {
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Resolution metrics are unavailable.');
            if (active) {
                setChart(data);
                setError('');
            }
        }).catch((reason) => {
            if (active) setError(reason.message);
        });
        return () => { active = false; };
    }, [period]);

    useEffect(() => {
        const element = canvas.current;
        if (!element || !chart) return undefined;
        const draw = () => {
            const bounds = element.getBoundingClientRect();
            const ratio = window.devicePixelRatio || 1;
            element.width = Math.max(1, Math.floor(bounds.width * ratio));
            element.height = Math.max(1, Math.floor(bounds.height * ratio));
            const context = element.getContext('2d');
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            const width = bounds.width;
            const height = bounds.height;
            const pad = { top: 18, right: 12, bottom: 36, left: 42 };
            const chartWidth = width - pad.left - pad.right;
            const chartHeight = height - pad.top - pad.bottom;
            const max = Math.max(4, ...chart.values);
            context.clearRect(0, 0, width, height);
            context.font = '12px "DM Sans", sans-serif';
            context.textBaseline = 'middle';
            context.textAlign = 'right';
            for (let tick = 0; tick <= 4; tick += 1) {
                const y = pad.top + chartHeight * tick / 4;
                context.beginPath();
                context.moveTo(pad.left, y);
                context.lineTo(width - pad.right, y);
                context.strokeStyle = '#e9edf3';
                context.stroke();
                context.fillStyle = '#78849a';
                context.fillText(String(Math.round(max - max * tick / 4)), pad.left - 8, y);
            }
            const step = chart.values.length > 1 ? chartWidth / (chart.values.length - 1) : chartWidth;
            context.beginPath();
            chart.values.forEach((value, index) => {
                const x = pad.left + step * index;
                const y = pad.top + chartHeight * (1 - value / max);
                if (index === 0) context.moveTo(x, y);
                else context.lineTo(x, y);
            });
            context.strokeStyle = '#315e9e';
            context.lineWidth = 2.5;
            context.stroke();
            context.textAlign = 'center';
            context.fillStyle = '#78849a';
            const stride = Math.max(1, Math.ceil(chart.labels.length / 8));
            chart.labels.forEach((label, index) => {
                if (index % stride === 0 || index === chart.labels.length - 1) {
                    context.fillText(label, pad.left + step * index, height - 15);
                }
            });
        };
        draw();
        const observer = new ResizeObserver(draw);
        observer.observe(element);
        return () => observer.disconnect();
    }, [chart]);

    return (
        <section className="admin-card resolution-chart-card" aria-labelledby="resolution-title">
            <div className="admin-card-heading resolution-chart-heading">
                <div><span className="step-label">RESOLUTION TRENDS</span><h2 id="resolution-title">Tickets solved</h2><p>{chart?.title || 'Loading resolution data…'}</p></div>
                <label className="resolution-period-control">Show
                    <select value={period} onChange={(event) => setPeriod(event.target.value)} aria-label="Resolution chart time period">
                        <option value="day">Past 24 hours</option><option value="week">Past 7 days</option><option value="month">Past 30 days</option><option value="year">Past 12 months</option>
                    </select>
                </label>
            </div>
            <div className="resolution-total"><strong>{chart?.total ?? '—'}</strong><span>solved in this period</span></div>
            <div className="resolution-chart-wrap"><canvas ref={canvas} role="img" aria-label="Support tickets resolved over time" /></div>
            {error && <p className="resolution-chart-error" role="alert">{error}</p>}
        </section>
    );
}
