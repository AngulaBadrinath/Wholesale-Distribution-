import React from 'react';
import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    last_page: number;
}

export interface TablePaginationProps {
    links?: PaginationLink[];
    meta?: PaginationMeta;
    total?: number;
    from?: number | null;
    to?: number | null;
    currentPage?: number;
    lastPage?: number;
    onPageChange?: (url: string) => void;
    className?: string;
}

export function TablePagination({
    links,
    meta,
    total: rawTotal,
    from: rawFrom,
    to: rawTo,
    currentPage: rawCurrentPage,
    lastPage: rawLastPage,
    onPageChange,
    className,
}: TablePaginationProps) {
    const total = meta?.total ?? rawTotal ?? 0;
    const from = meta?.from ?? rawFrom ?? (total > 0 ? 1 : 0);
    const to = meta?.to ?? rawTo ?? total;
    const currentPage = meta?.current_page ?? rawCurrentPage ?? 1;
    const lastPage = meta?.last_page ?? rawLastPage ?? 1;

    // Handle pagination click
    const handleNavigate = (url: string | null) => {
        if (!url) return;
        if (onPageChange) {
            onPageChange(url);
        } else {
            router.visit(url, { preserveState: true, preserveScroll: true });
        }
    };

    if (total === 0 && (!links || links.length === 0)) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex flex-col sm:flex-row items-center justify-between gap-4 px-2 py-4 text-xs text-muted-foreground',
                className
            )}
            aria-label="Table pagination navigation"
        >
            {/* Record Summary */}
            <div className="font-medium text-foreground/80 text-center sm:text-left">
                Showing{' '}
                <span className="font-semibold text-foreground">{from ?? 0}</span> to{' '}
                <span className="font-semibold text-foreground">{to ?? 0}</span> of{' '}
                <span className="font-semibold text-foreground">{total}</span> results
            </div>

            {/* Navigation Controls */}
            {links && links.length > 0 ? (
                <div className="flex items-center gap-1 flex-wrap justify-center">
                    {links.map((link, idx) => {
                        const isPrevious = link.label.includes('&laquo;') || link.label.toLowerCase().includes('prev');
                        const isNext = link.label.includes('&raquo;') || link.label.toLowerCase().includes('next');
                        const isEllipsis = link.label === '...';

                        if (isEllipsis) {
                            return (
                                <span
                                    key={idx}
                                    className="px-2 py-1.5 text-muted-foreground/60 select-none"
                                >
                                    ...
                                </span>
                            );
                        }

                        let cleanLabel: React.ReactNode = link.label;
                        if (isPrevious) {
                            cleanLabel = <ChevronLeft className="w-4 h-4" />;
                        } else if (isNext) {
                            cleanLabel = <ChevronRight className="w-4 h-4" />;
                        } else {
                            // Strip any HTML entities if present
                            cleanLabel = link.label.replace(/&[a-z]+;/gi, '').trim();
                        }

                        if (!link.url) {
                            return (
                                <span
                                    key={idx}
                                    className="min-w-[36px] min-h-[36px] flex items-center justify-center rounded-lg border border-border/40 text-muted-foreground/40 text-xs cursor-not-allowed select-none"
                                >
                                    {cleanLabel}
                                </span>
                            );
                        }

                        return (
                            <button
                                key={idx}
                                type="button"
                                onClick={() => handleNavigate(link.url)}
                                className={cn(
                                    'min-w-[36px] min-h-[36px] px-2.5 flex items-center justify-center rounded-lg text-xs font-medium transition-colors cursor-pointer select-none',
                                    link.active
                                        ? 'bg-primary text-primary-foreground shadow-xs font-semibold'
                                        : 'border border-border bg-card text-foreground hover:bg-muted hover:text-foreground'
                                )}
                                aria-current={link.active ? 'page' : undefined}
                                aria-label={isPrevious ? 'Previous page' : isNext ? 'Next page' : `Page ${cleanLabel}`}
                            >
                                {cleanLabel}
                            </button>
                        );
                    })}
                </div>
            ) : lastPage > 1 ? (
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={currentPage <= 1}
                        onClick={() => handleNavigate(`?page=${currentPage - 1}`)}
                        className="h-9 px-3 gap-1"
                    >
                        <ChevronLeft className="w-4 h-4" />
                        <span className="hidden sm:inline">Previous</span>
                    </Button>
                    <span className="px-2 text-xs font-medium text-foreground">
                        Page {currentPage} of {lastPage}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={currentPage >= lastPage}
                        onClick={() => handleNavigate(`?page=${currentPage + 1}`)}
                        className="h-9 px-3 gap-1"
                    >
                        <span className="hidden sm:inline">Next</span>
                        <ChevronRight className="w-4 h-4" />
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
