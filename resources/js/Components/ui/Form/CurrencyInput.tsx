import React from 'react';
import { cn } from '@/lib/utils';
import { Loader2 } from 'lucide-react';

export interface CurrencyInputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'onChange'> {
    value: string | number;
    onChange?: (value: string, rawNumber: number) => void;
    currencySymbol?: string;
    error?: boolean | string | string[] | null;
    isLoading?: boolean;
    readOnly?: boolean;
    precision?: number;
}

export const CurrencyInput = React.forwardRef<HTMLInputElement, CurrencyInputProps>(
    (
        {
            value,
            onChange,
            currencySymbol = '$',
            error,
            isLoading = false,
            disabled = false,
            readOnly = false,
            precision = 2,
            className,
            placeholder = '0.00',
            id,
            ...props
        },
        ref
    ) => {
        const hasError = Boolean(error);

        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            if (disabled || readOnly || isLoading || !onChange) return;
            const inputVal = e.target.value;
            // Allow numbers and at most one decimal point
            const sanitized = inputVal.replace(/[^0-9.]/g, '');
            const parts = sanitized.split('.');
            let finalVal = sanitized;
            if (parts.length > 2) {
                finalVal = `${parts[0]}.${parts.slice(1).join('')}`;
            }
            if (parts[1] && parts[1].length > precision) {
                finalVal = `${parts[0]}.${parts[1].slice(0, precision)}`;
            }

            const numericValue = parseFloat(finalVal) || 0;
            onChange(finalVal, numericValue);
        };

        return (
            <div className="relative rounded-lg shadow-2xs">
                {/* Prefix Currency Symbol */}
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none select-none text-muted-foreground font-mono text-sm font-semibold">
                    {currencySymbol}
                </div>

                <input
                    ref={ref}
                    id={id}
                    type="text"
                    inputMode="decimal"
                    value={value ?? ''}
                    onChange={handleChange}
                    disabled={disabled || isLoading}
                    readOnly={readOnly}
                    placeholder={placeholder}
                    aria-invalid={hasError ? 'true' : undefined}
                    aria-describedby={hasError && id ? `${id}-error` : undefined}
                    className={cn(
                        'flex h-10 w-full rounded-lg border bg-background pl-8 pr-8 py-2 text-right font-mono text-sm font-medium transition-colors placeholder:text-muted-foreground/60',
                        'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
                        hasError
                            ? 'border-rose-500 text-rose-950 dark:text-rose-100 focus-visible:ring-rose-500/30'
                            : 'border-input hover:border-input/80',
                        disabled ? 'cursor-not-allowed opacity-50 bg-muted/50 select-none' : '',
                        readOnly ? 'bg-muted/30 border-dashed cursor-default text-muted-foreground' : '',
                        className
                    )}
                    {...props}
                />

                {/* Loading indicator */}
                {isLoading && (
                    <div className="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <Loader2 className="w-4 h-4 animate-spin text-muted-foreground" />
                    </div>
                )}
            </div>
        );
    }
);

CurrencyInput.displayName = 'CurrencyInput';
