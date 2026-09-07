import React from 'react';
import { ChevronDown, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface SelectOption {
    label: string;
    value: string | number;
    disabled?: boolean;
}

export interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
    options?: SelectOption[];
    placeholder?: string;
    error?: boolean | string | string[] | null;
    isLoading?: boolean;
}

export const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
    (
        {
            options = [],
            placeholder,
            error,
            isLoading = false,
            disabled = false,
            className,
            children,
            id,
            ...props
        },
        ref
    ) => {
        const hasError = Boolean(error);

        return (
            <div className="relative rounded-lg shadow-2xs">
                <select
                    ref={ref}
                    id={id}
                    disabled={disabled || isLoading}
                    aria-invalid={hasError ? 'true' : undefined}
                    aria-describedby={hasError && id ? `${id}-error` : undefined}
                    className={cn(
                        'flex h-10 w-full appearance-none rounded-lg border bg-background px-3 py-2 pr-9 text-sm transition-colors text-foreground',
                        'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
                        hasError
                            ? 'border-rose-500 focus-visible:ring-rose-500/30'
                            : 'border-input hover:border-input/80',
                        disabled ? 'cursor-not-allowed opacity-50 bg-muted/50 select-none' : 'cursor-pointer',
                        className
                    )}
                    {...props}
                >
                    {placeholder && (
                        <option value="" disabled className="text-muted-foreground">
                            {placeholder}
                        </option>
                    )}
                    {options.map((opt) => (
                        <option
                            key={String(opt.value)}
                            value={opt.value}
                            disabled={opt.disabled}
                            className="text-foreground bg-background py-1"
                        >
                            {opt.label}
                        </option>
                    ))}
                    {children}
                </select>

                <div className="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-muted-foreground">
                    {isLoading ? (
                        <Loader2 className="w-4 h-4 animate-spin" />
                    ) : (
                        <ChevronDown className="w-4 h-4 opacity-70" />
                    )}
                </div>
            </div>
        );
    }
);

Select.displayName = 'Select';
