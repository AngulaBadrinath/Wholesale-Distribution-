import React from 'react';
import { Calendar as CalendarIcon, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface DatePickerProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type' | 'onChange'> {
    value?: string;
    onChange?: (date: string) => void;
    error?: boolean | string | string[] | null;
    min?: string;
    max?: string;
    placeholder?: string;
}

export const DatePicker = React.forwardRef<HTMLInputElement, DatePickerProps>(
    (
        {
            value = '',
            onChange,
            error,
            min,
            max,
            disabled,
            readOnly,
            className,
            id,
            placeholder = 'YYYY-MM-DD',
            ...props
        },
        ref
    ) => {
        const hasError = Boolean(error);

        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            if (disabled || readOnly || !onChange) return;
            onChange(e.target.value);
        };

        const handleClear = (e: React.MouseEvent) => {
            e.stopPropagation();
            if (disabled || readOnly || !onChange) return;
            onChange('');
        };

        return (
            <div className="relative rounded-lg shadow-2xs">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted-foreground">
                    <CalendarIcon className="w-4 h-4 opacity-70" />
                </div>

                <input
                    ref={ref}
                    id={id}
                    type="date"
                    value={value}
                    onChange={handleChange}
                    min={min}
                    max={max}
                    disabled={disabled}
                    readOnly={readOnly}
                    aria-invalid={hasError ? 'true' : undefined}
                    aria-describedby={hasError && id ? `${id}-error` : undefined}
                    className={cn(
                        'flex h-10 w-full rounded-lg border bg-background pl-9 pr-8 py-2 text-sm text-foreground transition-colors font-mono',
                        'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
                        hasError
                            ? 'border-rose-500 focus-visible:ring-rose-500/30'
                            : 'border-input hover:border-input/80',
                        disabled ? 'cursor-not-allowed opacity-50 bg-muted/50 select-none' : '',
                        readOnly ? 'bg-muted/30 border-dashed cursor-default text-muted-foreground' : '',
                        className
                    )}
                    {...props}
                />

                {value && !disabled && !readOnly && (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute inset-y-0 right-0 pr-3 flex items-center text-muted-foreground hover:text-foreground cursor-pointer"
                        aria-label="Clear date"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                )}
            </div>
        );
    }
);

DatePicker.displayName = 'DatePicker';
