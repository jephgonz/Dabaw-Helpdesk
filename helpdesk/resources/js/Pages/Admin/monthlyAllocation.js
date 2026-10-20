export const MONTHLY_ALLOCATION_TARGET = 4500;
export const WEEKDAY_ALLOCATION = 200;

function randomizeAdPaths(adPaths, count) {
    const uniqueAdPaths = [...new Set(adPaths)];
    if (uniqueAdPaths.length === 0) {
        return Array(count).fill('');
    }

    const assignments = [];
    while (assignments.length < count) {
        const shuffled = [...uniqueAdPaths];
        for (let index = shuffled.length - 1; index > 0; index -= 1) {
            const swapIndex = Math.floor(Math.random() * (index + 1));
            [shuffled[index], shuffled[swapIndex]] = [shuffled[swapIndex], shuffled[index]];
        }

        if (shuffled.length > 1 && shuffled[0] === assignments.at(-1)) {
            const differentIndex = shuffled.findIndex((path) => path !== assignments.at(-1));
            [shuffled[0], shuffled[differentIndex]] = [shuffled[differentIndex], shuffled[0]];
        }
        assignments.push(...shuffled.slice(0, count - assignments.length));
    }

    return assignments;
}

export function buildMonthlyAllocationPlan(
    month,
    monthlyTarget = MONTHLY_ALLOCATION_TARGET,
    adPaths = [],
    weekdayAllocation = WEEKDAY_ALLOCATION,
) {
    if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month)) {
        throw new RangeError('Choose a valid month.');
    }
    if (!Number.isSafeInteger(monthlyTarget) || monthlyTarget <= 0) {
        throw new RangeError('Enter a positive whole-peso monthly target.');
    }
    if (!Number.isSafeInteger(weekdayAllocation) || weekdayAllocation <= 0) {
        throw new RangeError('Enter a positive whole-peso base weekday amount.');
    }

    const [year, monthNumber] = month.split('-').map(Number);
    const dates = [];

    for (let day = 1; day <= new Date(Date.UTC(year, monthNumber, 0)).getUTCDate(); day += 1) {
        const date = new Date(Date.UTC(year, monthNumber - 1, day));
        const weekday = date.getUTCDay();
        if (weekday !== 0 && weekday !== 6) {
            dates.push(date.toISOString().slice(0, 10));
        }
    }

    if (dates.length < 2) {
        throw new RangeError('The selected month needs at least two weekdays.');
    }

    const standardAllocationFits = monthlyTarget >= weekdayAllocation * (dates.length - 2) + 2;
    const baseAllocation = standardAllocationFits
        ? weekdayAllocation
        : Math.min(weekdayAllocation, Math.floor(monthlyTarget / dates.length));
    const finalTwoWeekdayTotal = monthlyTarget - baseAllocation * (dates.length - 2);
    const lastDayIfUnsplit = finalTwoWeekdayTotal - baseAllocation;
    const [firstFinalAllocation, lastFinalAllocation] = lastDayIfUnsplit < 500
        ? [baseAllocation, lastDayIfUnsplit]
        : [
            Math.floor(finalTwoWeekdayTotal / 2),
            finalTwoWeekdayTotal - Math.floor(finalTwoWeekdayTotal / 2),
        ];
    const randomizedAdPaths = randomizeAdPaths(adPaths, dates.length);

    return dates.map((date, index) => ({
        date,
        amount: index === dates.length - 2
            ? firstFinalAllocation
            : index === dates.length - 1
                ? lastFinalAllocation
                : baseAllocation,
        isMonthEndAdjustment: index >= dates.length - 2,
        referenceNumber: String(Math.floor(Math.random() * 100000000) + 100000000),
        adPath: randomizedAdPaths[index],
    }));
}
