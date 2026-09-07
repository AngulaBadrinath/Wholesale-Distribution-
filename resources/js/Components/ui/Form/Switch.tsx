import React from 'react';
import { cn } from '@/lib/utils';
import { Loader2 } from 'lucide-react';

export interface SwitchProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label?: React.ReactNode;
    description?: React.ReactNode;
    disabled?: boolean;
    isLoading?: boolean;
    id?: string;
    className?: string;
}

export function Switch({
    checked,
    onChange,
    label,
    description,
    disabled = false,
    isLoading = false,
    id,
    className,
}: SwitchProps) {
    const handleToggle = () => {
        if (disabled || isLoading) return;
        onChange(!checked);
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (disabled || isLoading) return;
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            onChange(!checked);
        }
    };

    return (
        <div className={cn('flex items-start justify-between gap-3 text-xs select-none', className)}>
            {(label || description) && (
                <div
                    className={cn('flex-1 min-w-0', disabled ? 'opacity-60' : 'cursor-pointer')}
                    onClick={handleToggle}
                >
                    {label && (
                        <span className="font-semibold text-foreground block tracking-tight">
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

            <button
                id={id}
                type="button"
                role="switch"
                aria-checked={checked}
                disabled={disabled || isLoading}
                onClick={handleToggle}
                onKeyDown={handleKeyDown}
                className={cn(
                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-primary focus:ring-offset-2',
                    checked ? 'bg-primary' : 'bg-muted-foreground/30 dark:bg-muted-foreground/40',
                    disabled ? 'cursor-not-allowed opacity-50' : ''
                )}
            >
                <span
                    className={cn(
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out flex items-center justify-center',
                        checked ? 'translate-x-5' : 'translate-x-0'
                    )}
                >
                    {isLoading && <Loader2 className="w-3 h-3 animate-spin text-primary" />}
                </span>
            </button>
        </div>
    );
}
