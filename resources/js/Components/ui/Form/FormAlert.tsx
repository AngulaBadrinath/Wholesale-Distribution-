import React from 'react';
import { AlertCircle, AlertTriangle, CheckCircle2, Info, X, RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';

export type FormAlertVariant = 'error' | 'warning' | 'conflict' | 'success' | 'info';

export interface FormAlertProps {
    variant?: FormAlertVariant;
    title?: string;
    message?: React.ReactNode;
    errors?: Record<string, string | string[]> | string[];
    onRetry?: () => void;
    onDismiss?: () => void;
    className?: string;
}

export function FormAlert({
    variant = 'error',
    title,
    message,
    errors,
    onRetry,
    onDismiss,
    className,
}: FormAlertProps) {
    if (!message && !title && (!errors || (Array.isArray(errors) && errors.length === 0) || Object.keys(errors).length === 0)) {
        return null;
    }

    const getVariantStyles = () => {
        switch (variant) {
            case 'success':
                return {
                    container: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-900 dark:text-emerald-200',
                    iconBg: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400',
                    Icon: CheckCircle2,
                    defaultTitle: 'Changes saved successfully',
                };
            case 'warning':
                return {
                    container: 'bg-amber-500/10 border-amber-500/30 text-amber-900 dark:text-amber-200',
                    iconBg: 'bg-amber-500/20 text-amber-600 dark:text-amber-400',
                    Icon: AlertTriangle,
                    defaultTitle: 'Attention required',
                };
            case 'conflict':
                return {
                    container: 'bg-amber-500/15 border-amber-500/40 text-amber-950 dark:text-amber-100',
                    iconBg: 'bg-amber-500/25 text-amber-700 dark:text-amber-300',
                    Icon: AlertTriangle,
                    defaultTitle: 'Concurrent modification conflict',
                };
            case 'info':
                return {
                    container: 'bg-sky-500/10 border-sky-500/30 text-sky-900 dark:text-sky-200',
                    iconBg: 'bg-sky-500/20 text-sky-600 dark:text-sky-400',
                    Icon: Info,
                    defaultTitle: 'Notice',
                };
            case 'error':
            default:
                return {
                    container: 'bg-rose-500/10 border-rose-500/30 text-rose-900 dark:text-rose-200',
                    iconBg: 'bg-rose-500/20 text-rose-600 dark:text-rose-400',
                    Icon: AlertCircle,
                    defaultTitle: 'Form submission failed',
                };
        }
    };

    const { container, iconBg, Icon, defaultTitle } = getVariantStyles();

    // Flatten errors list if provided
    const errorList: string[] = [];
    if (errors) {
        if (Array.isArray(errors)) {
            errorList.push(...errors);
        } else {
            Object.values(errors).forEach((val) => {
                if (Array.isArray(val)) {
                    errorList.push(...val);
                } else if (typeof val === 'string') {
                    errorList.push(val);
                }
            });
        }
    }

    return (
        <div
            role="alert"
            className={cn(
                'rounded-xl border p-4 text-xs space-y-2 relative animate-in fade-in-50 duration-200 shadow-xs',
                container,
                className
            )}
        >
            <div className="flex items-start gap-3">
                <div className={cn('w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5', iconBg)}>
                    <Icon className="w-4 h-4" />
                </div>
                <div className="flex-1 min-w-0 pr-4">
                    <div className="font-semibold text-sm">
                        {title || defaultTitle}
                    </div>
                    {message && (
                        <div className="mt-1 leading-relaxed opacity-90">
                            {message}
                        </div>
                    )}

                    {errorList.length > 0 && (
                        <ul className="mt-2 space-y-1 list-disc list-inside font-medium opacity-95">
                            {errorList.map((err, idx) => (
                                <li key={idx}>{err}</li>
                            ))}
                        </ul>
                    )}

                    {variant === 'conflict' && (
                        <p className="mt-2 font-medium text-[11px] opacity-80">
                            The record was updated by another session or user while you were editing. Please refresh the page to load the latest server state.
                        </p>
                    )}

                    {onRetry && (
                        <div className="mt-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onRetry}
                                className="h-7 text-xs gap-1.5"
                            >
                                <RefreshCw className="w-3 h-3" />
                                Retry Action
                            </Button>
                        </div>
                    )}
                </div>

                {onDismiss && (
                    <button
                        type="button"
                        onClick={onDismiss}
                        className="absolute top-3 right-3 p-1 rounded-md opacity-70 hover:opacity-100 hover:bg-black/10 dark:hover:bg-white/10 transition-all cursor-pointer"
                        aria-label="Dismiss alert"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                )}
            </div>
        </div>
    );
}
