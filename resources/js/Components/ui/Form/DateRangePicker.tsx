import React from 'react';
import { Calendar as CalendarIcon, ArrowRight, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';

export interface DateRange {
    startDate: string;
    endDate: string;
}

export interface DateRangePickerProps {
    value: DateRange;
    onChange: (range: DateRange) => void;
    min?: string;
    max?: string;
    disabled?: boolean;
    error?: boolean | string | string[] | null;
    showPresets?: boolean;
    className?: string;
}

export function DateRangePicker({
    value,
    onChange,
    min,
    max,
    disabled = false,
    error,
    showPresets = true,
    className,
}: DateRangePickerProps) {
    const hasError = Boolean(error);

    const handleStartDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newStart = e.target.value;
        let newEnd = value.endDate;
        if (newEnd && newStart > newEnd) {
            newEnd = newStart;
        }
        onChange({ startDate: newStart, endDate: newEnd });
    };

    const handleEndDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newEnd = e.target.value;
        let newStart = value.startDate;
        if (newStart && newEnd < newStart) {
            newStart = newEnd;
        }
        onChange({ startDate: newStart, endDate: newEnd });
    };

    const handleClear = () => {
        onChange({ startDate: '', endDate: '' });
    };

    const applyPreset = (preset: 'today' | 'yesterday' | 'last7' | 'thisMonth' | 'lastMonth') => {
        const now = new Date();
        const formatDate = (d: Date) => d.toISOString().split('T')[0];

        let start = '';
        let end = '';

        if (preset === 'today') {
            start = end = formatDate(now);
        } else if (preset === 'yesterday') {
            const y = new Date(now);
            y.setDate(y.getDate() - 1);
            start = end = formatDate(y);
        } else if (preset === 'last7') {
            const s = new Date(now);
            s.setDate(s.getDate() - 6);
            start = formatDate(s);
            end = formatDate(now);
        } else if (preset === 'thisMonth') {
            const s = new Date(now.getFullYear(), now.getMonth(), 1);
            start = formatDate(s);
            end = formatDate(now);
        } else if (preset === 'lastMonth') {
            const s = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const e = new Date(now.getFullYear(), now.getMonth(), 0);
            start = formatDate(s);
            end = formatDate(e);
        }

        onChange({ startDate: start, endDate: end });
    };

    return (
        <div className={cn('space-y-2', className)}>
            {/* Range Inputs Container */}
            <div
                className={cn(
                    'flex flex-col sm:flex-row sm:items-center gap-2 rounded-lg border bg-background p-1.5 shadow-2xs transition-colors',
                    hasError ? 'border-rose-500' : 'border-input hover:border-input/80',
                    disabled ? 'opacity-50 cursor-not-allowed' : ''
                )}
            >
                {/* Start Date */}
                <div className="relative flex-1">
                    <div className="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-muted-foreground">
                        <CalendarIcon className="w-3.5 h-3.5 opacity-70" />
                    </div>
                    <input
                        type="date"
                        value={value.startDate}
                        onChange={handleStartDateChange}
                        min={min}
                        max={value.endDate || max}
                        disabled={disabled}
                        className="w-full pl-8 pr-2 py-1.5 text-xs font-mono bg-transparent text-foreground focus:outline-hidden"
                        placeholder="Start date"
                        aria-label="Start date"
                    />
                </div>

                <div className="hidden sm:flex items-center text-muted-foreground">
                    <ArrowRight className="w-3.5 h-3.5 opacity-50" />
                </div>

                {/* End Date */}
                <div className="relative flex-1">
                    <div className="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-muted-foreground">
                        <CalendarIcon className="w-3.5 h-3.5 opacity-70" />
                    </div>
                    <input
                        type="date"
                        value={value.endDate}
                        onChange={handleEndDateChange}
                        min={value.startDate || min}
                        max={max}
                        disabled={disabled}
                        className="w-full pl-8 pr-2 py-1.5 text-xs font-mono bg-transparent text-foreground focus:outline-hidden"
                        placeholder="End date"
                        aria-label="End date"
                    />
                </div>

                {(value.startDate || value.endDate) && !disabled && (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="p-1 rounded text-muted-foreground hover:text-foreground self-end sm:self-center cursor-pointer"
                        aria-label="Clear date range"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                )}
            </div>

            {/* Quick Presets */}
            {showPresets && !disabled && (
                <div className="flex flex-wrap items-center gap-1">
                    <button
                        type="button"
                        onClick={() => applyPreset('today')}
                        className="px-2 py-0.5 rounded text-[11px] font-medium bg-muted text-muted-foreground hover:text-foreground hover:bg-muted/80 transition-colors cursor-pointer"
                    >
                        Today
                    </button>
                    <button
                        type="button"
                        onClick={() => applyPreset('yesterday')}
                        className="px-2 py-0.5 rounded text-[11px] font-medium bg-muted text-muted-foreground hover:text-foreground hover:bg-muted/80 transition-colors cursor-pointer"
                    >
                        Yesterday
                    </button>
                    <button
                        type="button"
                        onClick={() => applyPreset('last7')}
                        className="px-2 py-0.5 rounded text-[11px] font-medium bg-muted text-muted-foreground hover:text-foreground hover:bg-muted/80 transition-colors cursor-pointer"
                    >
                        Last 7 Days
                    </button>
                    <button
                        type="button"
                        onClick={() => applyPreset('thisMonth')}
                        className="px-2 py-0.5 rounded text-[11px] font-medium bg-muted text-muted-foreground hover:text-foreground hover:bg-muted/80 transition-colors cursor-pointer"
                    >
                        This Month
                    </button>
                    <button
                        type="button"
                        onClick={() => applyPreset('lastMonth')}
                        className="px-2 py-0.5 rounded text-[11px] font-medium bg-muted text-muted-foreground hover:text-foreground hover:bg-muted/80 transition-colors cursor-pointer"
                    >
                        Last Month
                    </button>
                </div>
            )}
        </div>
    );
}
