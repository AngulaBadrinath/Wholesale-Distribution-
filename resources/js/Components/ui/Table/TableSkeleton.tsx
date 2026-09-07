import React from 'react';

export interface TableSkeletonProps {
    columns?: number;
    rows?: number;
    showCheckbox?: boolean;
}

export function TableSkeleton({
    columns = 5,
    rows = 5,
    showCheckbox = false,
}: TableSkeletonProps) {
    return (
        <div className="w-full animate-pulse" aria-busy="true" aria-label="Loading table data">
            {/* Desktop / Tablet Table Skeleton */}
            <div className="hidden md:block rounded-xl border border-border bg-card overflow-hidden">
                <div className="border-b border-border bg-muted/40 px-4 py-3 flex items-center gap-4">
                    {showCheckbox && <div className="w-4 h-4 rounded bg-muted-foreground/20" />}
                    {Array.from({ length: columns }).map((_, i) => (
                        <div
                            key={i}
                            className="h-3.5 bg-muted-foreground/20 rounded flex-1 max-w-[120px]"
                            style={{ opacity: 0.9 - i * 0.1 }}
                        />
                    ))}
                </div>
                <div className="divide-y divide-border">
                    {Array.from({ length: rows }).map((_, r) => (
                        <div key={r} className="px-4 py-3.5 flex items-center gap-4">
                            {showCheckbox && <div className="w-4 h-4 rounded bg-muted-foreground/15" />}
                            {Array.from({ length: columns }).map((_, c) => (
                                <div
                                    key={c}
                                    className="h-4 bg-muted-foreground/15 rounded flex-1"
                                    style={{
                                        maxWidth: c === 0 ? '160px' : c === columns - 1 ? '80px' : '110px',
                                        opacity: 0.8 - c * 0.08,
                                    }}
                                />
                            ))}
                        </div>
                    ))}
                </div>
            </div>

            {/* Mobile Cards Skeleton */}
            <div className="md:hidden space-y-3">
                {Array.from({ length: Math.min(rows, 4) }).map((_, r) => (
                    <div
                        key={r}
                        className="p-4 rounded-xl border border-border bg-card space-y-3"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <div className="h-4 bg-muted-foreground/20 rounded w-2/5" />
                            <div className="h-5 bg-muted-foreground/20 rounded-full w-16" />
                        </div>
                        <div className="space-y-1.5 pt-1">
                            <div className="h-3 bg-muted-foreground/15 rounded w-3/4" />
                            <div className="h-3 bg-muted-foreground/15 rounded w-1/2" />
                        </div>
                        <div className="pt-2 border-t border-border/60 flex items-center justify-between">
                            <div className="h-4 bg-muted-foreground/20 rounded w-20" />
                            <div className="h-8 bg-muted-foreground/20 rounded-lg w-20" />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
