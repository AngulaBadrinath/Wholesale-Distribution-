import React from 'react';
import { AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface FieldErrorProps {
    error?: string | string[] | null;
    className?: string;
    id?: string;
}

export function FieldError({ error, className, id }: FieldErrorProps) {
    if (!error) return null;

    const errorMessages = Array.isArray(error) ? error : [error];

    return (
        <div
            id={id}
            role="alert"
            className={cn('space-y-1 mt-1.5 animate-in fade-in-50 duration-150', className)}
        >
            {errorMessages.map((msg, idx) => (
                <div
                    key={idx}
                    className="flex items-center gap-1.5 text-xs font-medium text-rose-600 dark:text-rose-400"
                >
                    <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                    <span>{msg}</span>
                </div>
            ))}
        </div>
    );
}
