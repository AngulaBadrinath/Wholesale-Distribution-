import React from 'react';
import { cn } from '@/lib/utils';

export interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
    error?: boolean | string | string[] | null;
    showCount?: boolean;
    maxLength?: number;
}

export const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
    (
        {
            error,
            showCount = false,
            maxLength,
            className,
            disabled,
            readOnly,
            id,
            value,
            defaultValue,
            ...props
        },
        ref
    ) => {
        const hasError = Boolean(error);
        const currentLength = typeof value === 'string' ? value.length : typeof defaultValue === 'string' ? defaultValue.length : 0;

        return (
            <div className="relative space-y-1">
                <textarea
                    ref={ref}
                    id={id}
                    disabled={disabled}
                    readOnly={readOnly}
                    maxLength={maxLength}
                    value={value}
                    defaultValue={defaultValue}
                    aria-invalid={hasError ? 'true' : undefined}
                    aria-describedby={hasError && id ? `${id}-error` : undefined}
                    className={cn(
                        'flex min-h-[80px] w-full rounded-lg border bg-background px-3 py-2 text-sm text-foreground transition-colors placeholder:text-muted-foreground/60',
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

                {showCount && maxLength !== undefined && (
                    <div className="text-right text-[10px] text-muted-foreground font-mono">
                        {currentLength} / {maxLength}
                    </div>
                )}
            </div>
        );
    }
);

Textarea.displayName = 'Textarea';
