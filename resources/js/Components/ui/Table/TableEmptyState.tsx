import React from 'react';
import { FileQuestion, AlertCircle, RefreshCw } from 'lucide-react';
import { Button } from '@/Components/ui/button';

export interface TableEmptyStateProps {
    title?: string;
    description?: string;
    icon?: React.ComponentType<{ className?: string }>;
    action?: React.ReactNode;
    error?: string | null;
    onRetry?: () => void;
}

export function TableEmptyState({
    title = 'No records found',
    description = 'There are no items matching your criteria in the system.',
    icon: Icon = FileQuestion,
    action,
    error,
    onRetry,
}: TableEmptyStateProps) {
    if (error) {
        return (
            <div className="flex flex-col items-center justify-center p-8 sm:p-12 text-center rounded-xl border border-dashed border-rose-200 dark:border-rose-900/50 bg-rose-50/50 dark:bg-rose-950/20 my-2">
                <div className="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-900/50 flex items-center justify-center text-rose-600 dark:text-rose-400 mb-3.5 shadow-xs">
                    <AlertCircle className="w-6 h-6" />
                </div>
                <h3 className="text-sm font-semibold text-rose-900 dark:text-rose-200">
                    Failed to load data
                </h3>
                <p className="text-xs text-rose-700/80 dark:text-rose-400/80 max-w-sm mt-1 mb-4 leading-relaxed">
                    {error}
                </p>
                {onRetry && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onRetry}
                        className="gap-2 border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/40"
                    >
                        <RefreshCw className="w-3.5 h-3.5" />
                        Try Again
                    </Button>
                )}
            </div>
        );
    }

    return (
        <div className="flex flex-col items-center justify-center p-8 sm:p-12 text-center rounded-xl border border-dashed border-border bg-card/50 my-2">
            <div className="w-12 h-12 rounded-2xl bg-muted flex items-center justify-center text-muted-foreground mb-3.5 shadow-xs">
                <Icon className="w-6 h-6 stroke-[1.5]" />
            </div>
            <h3 className="text-sm font-semibold text-foreground tracking-tight">
                {title}
            </h3>
            <p className="text-xs text-muted-foreground max-w-sm mt-1 leading-relaxed">
                {description}
            </p>
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
