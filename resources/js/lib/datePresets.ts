export type DatePresetKey = 'this_month' | 'last_30_days' | 'ytd' | 'all_time';

export interface DatePreset {
    key: DatePresetKey;
    label: string;
    getRange: (now?: Date) => { startDate: string; endDate: string };
}

export function formatDate(d: Date): string {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export const DATE_PRESETS: DatePreset[] = [
    {
        key: 'this_month',
        label: 'This Month',
        getRange: (now = new Date()) => {
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            return {
                startDate: formatDate(start),
                endDate: formatDate(end),
            };
        },
    },
    {
        key: 'last_30_days',
        label: 'Last 30 Days',
        getRange: (now = new Date()) => {
            const end = new Date(now);
            const start = new Date(now);
            start.setDate(start.getDate() - 30);
            return {
                startDate: formatDate(start),
                endDate: formatDate(end),
            };
        },
    },
    {
        key: 'ytd',
        label: 'Year to Date',
        getRange: (now = new Date()) => {
            const start = new Date(now.getFullYear(), 0, 1);
            const end = new Date(now);
            return {
                startDate: formatDate(start),
                endDate: formatDate(end),
            };
        },
    },
    {
        key: 'all_time',
        label: 'All Time',
        getRange: (now = new Date()) => {
            return {
                startDate: '2020-01-01',
                endDate: formatDate(now),
            };
        },
    },
];

export function detectActivePreset(startDate: string, endDate: string, now = new Date()): DatePresetKey | null {
    for (const preset of DATE_PRESETS) {
        const range = preset.getRange(now);
        if (range.startDate === startDate && range.endDate === endDate) {
            return preset.key;
        }
    }
    return null;
}
