import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildMonthlyAllocationPlan,
    MONTHLY_ALLOCATION_TARGET,
    WEEKDAY_ALLOCATION,
} from '../../resources/js/Pages/Admin/monthlyAllocation.js';

test('September 2026 plan schedules weekdays and totals 4500', () => {
    const plan = buildMonthlyAllocationPlan('2026-09');

    assert.equal(plan.length, 22);
    assert.equal(plan[0].date, '2026-09-01');
    assert.equal(plan.at(-1).date, '2026-09-30');
    assert.deepEqual(plan.slice(-2).map((entry) => entry.amount), [200, 300]);
    assert.ok(plan.slice(-2).every((entry) => entry.isMonthEndAdjustment));
    assert.equal(plan.reduce((total, entry) => total + entry.amount, 0), MONTHLY_ALLOCATION_TARGET);
    assert.ok(plan.every((entry) => {
        const weekday = new Date(`${entry.date}T00:00:00Z`).getUTCDay();
        return weekday >= 1 && weekday <= 5;
    }));
});

test('months with 23 weekdays keep sub-500 extra on the last day', () => {
    const plan = buildMonthlyAllocationPlan('2027-03');

    assert.equal(plan.length, 23);
    assert.deepEqual(plan.slice(-2).map((entry) => entry.amount), [200, 100]);
    assert.equal(plan.slice(0, -2).reduce((total, entry) => total + entry.amount, 0), 4200);
});

test('21-weekday months split the final allocation into 350 per day', () => {
    const plan = buildMonthlyAllocationPlan('2026-08');

    assert.equal(plan.length, 21);
    assert.deepEqual(plan.slice(-2).map((entry) => entry.amount), [350, 350]);
    assert.equal(plan.reduce((total, entry) => total + entry.amount, 0), MONTHLY_ALLOCATION_TARGET);
});

test('a custom target is used and persists as a balanced month-end allocation', () => {
    const plan = buildMonthlyAllocationPlan('2026-08', 5000);

    assert.deepEqual(plan.slice(-2).map((entry) => entry.amount), [600, 600]);
    assert.equal(plan.reduce((total, entry) => total + entry.amount, 0), 5000);
});

test('a custom base weekday amount adjusts the preview and splits the remaining total', () => {
    const plan = buildMonthlyAllocationPlan('2026-08', 4500, [], 150);

    assert.ok(plan.slice(0, -2).every((entry) => entry.amount === 150));
    assert.deepEqual(plan.slice(-2).map((entry) => entry.amount), [825, 825]);
    assert.equal(plan.reduce((total, entry) => total + entry.amount, 0), 4500);
});

test('small positive targets remain valid and total exactly', () => {
    const plan = buildMonthlyAllocationPlan('2026-08', 10);

    assert.deepEqual(plan.slice(-2).map((entry) => entry.amount), [0, 10]);
    assert.equal(plan.reduce((total, entry) => total + entry.amount, 0), 10);
});

test('reference numbers and ad paths are included in preview rows', () => {
    const adPaths = ['D:\\ads\\ad1.png', 'D:\\ads\\ad2.png'];
    const plan = buildMonthlyAllocationPlan('2026-08', 4500, adPaths);

    assert.equal(plan.length, 21);
    for (const entry of plan) {
        assert.match(entry.referenceNumber, /^1\d{8}$/);
        assert.ok(adPaths.includes(entry.adPath));
    }
});

test('ad paths are unique for the month when enough assets are available', () => {
    const adPaths = Array.from({ length: 21 }, (_, index) => `D:\\ads\\ad${index}.png`);
    const plan = buildMonthlyAllocationPlan('2026-08', 4500, adPaths);
    const assignedPaths = plan.map((entry) => entry.adPath);

    assert.equal(new Set(assignedPaths).size, plan.length);
    assert.deepEqual([...assignedPaths].sort(), [...adPaths].sort());
});

test('ad paths do not repeat consecutively when the asset pool is smaller than the month', () => {
    const adPaths = ['D:\\ads\\ad1.png', 'D:\\ads\\ad2.png', 'D:\\ads\\ad3.png'];
    const plan = buildMonthlyAllocationPlan('2026-08', 4500, adPaths);

    assert.ok(plan.every((entry) => adPaths.includes(entry.adPath)));
    assert.ok(plan.every((entry, index) => index === 0 || entry.adPath !== plan[index - 1].adPath));
    for (let index = 0; index < plan.length; index += adPaths.length) {
        assert.equal(new Set(plan.slice(index, index + adPaths.length).map((entry) => entry.adPath)).size, adPaths.length);
    }
});

test('invalid month values are rejected', () => {
    assert.throws(() => buildMonthlyAllocationPlan('2026-13'), RangeError);
});

test('invalid target values are rejected', () => {
    assert.throws(() => buildMonthlyAllocationPlan('2026-08', 0), RangeError);
    assert.throws(() => buildMonthlyAllocationPlan('2026-08', 1.5), RangeError);
    assert.throws(() => buildMonthlyAllocationPlan('2026-08', 4500, [], 0), RangeError);
    assert.throws(() => buildMonthlyAllocationPlan('2026-08', 4500, [], 1.5), RangeError);
});
