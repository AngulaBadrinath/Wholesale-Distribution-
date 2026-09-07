import React from 'react';
import { Plus, Minus, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface QuantityInputProps {
    value: number | string;
    onChange?: (quantity: number) => void;
    min?: number;
    max?: number;
    step?: number;
    disabled?: boolean;
    readOnly?: boolean;
    isLoading?: boolean;
    error?: boolean | string | string[] | null;
    id?: string;
    className?: string;
    unit?: string;
}

export const QuantityInput = React.forwardRef<HTMLInputElement, QuantityInputProps>(
    (
        {
            value,
            onChange,
            min = 1,
            max,
            step = 1,
            disabled = false,
            readOnly = false,
            isLoading = false,
            error,
            id,
            className,
            unit,
            ...props
        },
        ref
    ) => {
        const hasError = Boolean(error);
        const numericValue = typeof value === 'number' ? value : parseInt(String(value), 10) || 0;

        const handleIncrement = () => {
            if (disabled || readOnly || isLoading || !onChange) return;
            const nextVal = numericValue + step;
            if (max !== undefined && nextVal > max) return;
            onChange(nextVal);
        };

        const handleDecrement = () => {
            if (disabled || readOnly || isLoading || !onChange) return;
            const nextVal = numericValue - step;
            if (nextVal < min) return;
            onChange(nextVal);
        };

        const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            if (disabled || readOnly || isLoading || !onChange) return;
            const raw = e.target.value.replace(/[^0-9]/g, '');
            if (raw === '') {
                onChange(0);
                return;
            }
            let val = parseInt(raw, 10);
            if (max !== undefined && val > max) val = max;
            onChange(val);
        };

        return (
            <div className={cn('relative inline-flex items-center rounded-lg border bg-background shadow-2xs', hasError ? 'border-rose-500' : 'border-input', className)}>
                {/* Decrement Button */}
                <button
                    type="button"
                    onClick={handleDecrement}
                    disabled={disabled || readOnly || isLoading || numericValue <= min}
                    aria-label="Decrease quantity"
                    className="inline-flex h-10 w-9 items-center justify-center rounded-l-lg bg-muted/30 text-muted-foreground hover:bg-muted hover:text-foreground active:scale-95 disabled:pointer-events-none disabled:opacity-40 transition-all cursor-pointer border-r border-border"
                >
                    <Minus className="h-3.5 w-3.5" />
                </button>

                {/* Number Input */}
                <input
                    ref={ref}
                    id={id}
                    type="text"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    value={numericValue === 0 ? '' : numericValue}
                    onChange={handleInputChange}
                    disabled={disabled || isLoading}
                    readOnly={readOnly}
                    aria-invalid={hasError ? 'true' : undefined}
                    aria-describedby={hasError && id ? `${id}-error` : undefined}
                    className="h-10 w-16 sm:w-20 bg-transparent text-center font-mono text-sm font-semibold text-foreground focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50"
                    {...props}
                />

                {/* Unit label if specified */}
                {unit && (
                    <span className="pr-2 text-xs text-muted-foreground font-medium select-none">
                        {unit}
                    </span>
                )}

                {/* Increment Button */}
                <button
                    type="button"
                    onClick={handleIncrement}
                    disabled={disabled || readOnly || isLoading || (max !== undefined && numericValue >= max)}
                    aria-label="Increase quantity"
                    className="inline-flex h-10 w-9 items-center justify-center rounded-r-lg bg-muted/30 text-muted-foreground hover:bg-muted hover:text-foreground active:scale-95 disabled:pointer-events-none disabled:opacity-40 transition-all cursor-pointer border-l border-border"
                >
                    <Plus className="h-3.5 w-3.5" />
                </button>

                {isLoading && (
                    <div className="absolute inset-0 bg-background/60 backdrop-blur-2xs flex items-center justify-center rounded-lg">
                        <Loader2 className="h-4 w-4 animate-spin text-primary" />
                    </div>
                )}
            </div>
        );
    }
);

QuantityInput.displayName = 'QuantityInput';
