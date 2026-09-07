import React from 'react';
import { cn } from '@/lib/utils';
import { FieldError } from './FieldError';

export interface FormFieldProps {
    id?: string;
    label?: React.ReactNode;
    description?: React.ReactNode;
    error?: string | string[] | null;
    required?: boolean;
    optional?: boolean;
    badge?: React.ReactNode;
    children: React.ReactNode;
    className?: string;
    labelClassName?: string;
}

export function FormField({
    id,
    label,
    description,
    error,
    required = false,
    optional = false,
    badge,
    children,
    className,
    labelClassName,
}: FormFieldProps) {
    const errorId = id ? `${id}-error` : undefined;
    const descriptionId = id ? `${id}-description` : undefined;

    return (
        <div className={cn('space-y-1.5', className)}>
            {(label || badge) && (
                <div className="flex items-center justify-between gap-2">
                    {label && (
                        <label
                            htmlFor={id}
                            className={cn(
                                'block text-xs font-semibold text-foreground tracking-tight select-none',
                                labelClassName
                            )}
                        >
                            {label}
                            {required && (
                                <span className="text-rose-500 ml-1 font-bold" aria-hidden="true">
                                    *
                                </span>
                            )}
                            {optional && (
                                <span className="text-muted-foreground font-normal ml-1.5 text-[11px]">
                                    (optional)
                                </span>
                            )}
                        </label>
                    )}
                    {badge && <div className="shrink-0">{badge}</div>}
                </div>
            )}

            {children}

            {description && (
                <p
                    id={descriptionId}
                    className="text-[11px] text-muted-foreground leading-relaxed"
                >
                    {description}
                </p>
            )}

            <FieldError error={error} id={errorId} />
        </div>
    );
}
