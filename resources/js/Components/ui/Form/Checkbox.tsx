import React, { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';
import { Check, Minus } from 'lucide-react';

export interface CheckboxProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
    label?: React.ReactNode;
    description?: React.ReactNode;
    indeterminate?: boolean;
    error?: boolean | string | string[] | null;
}

export const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(
    (
        {
            label,
            description,
            indeterminate = false,
            error,
            className,
            disabled,
            checked,
            id,
            ...props
        },
        ref
    ) => {
        const inputRef = useRef<HTMLInputElement>(null);
        const hasError = Boolean(error);

        useEffect(() => {
            if (inputRef.current) {
                inputRef.current.indeterminate = indeterminate;
            }
        }, [indeterminate]);

        return (
            <label
                htmlFor={id}
                className={cn(
                    'flex items-start gap-3 select-none text-xs',
                    disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                    className
                )}
            >
                <div className="relative flex items-center pt-0.5">
                    <input
                        ref={(node) => {
                            (inputRef as any).current = node;
                            if (typeof ref === 'function') {
                                ref(node);
                            } else if (ref) {
                                (ref as any).current = node;
                            }
                        }}
                        id={id}
                        type="checkbox"
                        checked={checked}
                        disabled={disabled}
                        aria-invalid={hasError ? 'true' : undefined}
                        className={cn(
                            'h-4 w-4 rounded border text-primary transition-all focus:ring-2 focus:ring-primary/20 focus:ring-offset-1',
                            hasError ? 'border-rose-500' : 'border-input hover:border-input/80',
                            disabled ? 'cursor-not-allowed' : 'cursor-pointer'
                        )}
                        {...props}
                    />
                </div>

                {(label || description) && (
                    <div className="flex-1 min-w-0">
                        {label && (
                            <span className="font-medium text-foreground block tracking-tight">
                                {label}
                            </span>
                        )}
                        {description && (
                            <span className="text-[11px] text-muted-foreground block mt-0.5 leading-relaxed">
                                {description}
                            </span>
                        )}
                    </div>
                )}
            </label>
        );
    }
);

Checkbox.displayName = 'Checkbox';
