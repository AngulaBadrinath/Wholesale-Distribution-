import React from 'react';
import { OrderAdjustmentQueueCounts } from '@/types/order';
import { Badge } from '@/Components/ui/badge';
import {
    AlertTriangle,
    Clock,
    CheckCircle2,
    Package,
    RotateCcw,
    XCircle,
    SlidersHorizontal,
} from 'lucide-react';

interface AdminAdjustmentQueueTabsProps {
    activeQueue: string;
    counts: OrderAdjustmentQueueCounts;
    onSelectQueue: (queueKey: string) => void;
}

export default function AdminAdjustmentQueueTabs({
    activeQueue,
    counts,
    onSelectQueue,
}: AdminAdjustmentQueueTabsProps) {
    const tabs = [
        {
            key: 'attention',
            label: 'Needs Attention',
            count: counts.attention,
            icon: AlertTriangle,
            variant: 'destructive',
            description: 'Exceptions, conflicts & aging requests',
        },
        {
            key: 'pending',
            label: 'Pending Review',
            count: counts.pending,
            icon: Clock,
            variant: 'info',
            description: 'Submitted & awaiting review',
        },
        {
            key: 'ready_to_apply',
            label: 'Ready to Apply',
            count: counts.ready_to_apply,
            icon: CheckCircle2,
            variant: 'success',
            description: 'Approved without blockers',
        },
        {
            key: 'applied',
            label: 'Applied',
            count: counts.applied,
            icon: Package,
            variant: 'primary',
            description: 'Applied order adjustments',
        },
        {
            key: 'reversed',
            label: 'Reversed',
            count: counts.reversed,
            icon: RotateCcw,
            variant: 'warning',
            description: 'Reversed adjustments',
        },
        {
            key: 'closed',
            label: 'Closed',
            count: counts.closed,
            icon: XCircle,
            variant: 'outline',
            description: 'Rejected & withdrawn requests',
        },
        {
            key: 'all',
            label: 'All Adjustments',
            count: counts.all,
            icon: SlidersHorizontal,
            variant: 'secondary',
            description: 'Complete adjustment archive',
        },
    ];

    return (
        <div className="w-full border-b border-border bg-card/50 backdrop-blur-xs rounded-t-lg">
            <nav
                className="flex space-x-1 overflow-x-auto p-1.5 scrollbar-thin"
                role="tablist"
                aria-label="Operational Adjustment Queues"
            >
                {tabs.map((tab) => {
                    const Icon = tab.icon;
                    const isActive = activeQueue === tab.key;
                    const isUrgent = tab.key === 'attention' && tab.count > 0;

                    return (
                        <button
                            key={tab.key}
                            role="tab"
                            type="button"
                            aria-selected={isActive}
                            onClick={() => onSelectQueue(tab.key)}
                            className={`flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-md whitespace-nowrap transition-all duration-150 shrink-0 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary ${
                                isActive
                                    ? 'bg-background text-foreground shadow-xs border border-border font-semibold'
                                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'
                            }`}
                        >
                            <Icon
                                className={`h-4 w-4 ${
                                    isActive
                                        ? 'text-primary'
                                        : isUrgent
                                        ? 'text-destructive'
                                        : 'text-muted-foreground'
                                }`}
                            />
                            <span>{tab.label}</span>
                            <Badge
                                variant={isActive ? 'default' : isUrgent ? 'destructive' : 'secondary'}
                                className={`h-5 min-w-5 px-1.5 text-[10px] font-mono justify-center rounded-full ${
                                    isActive ? 'bg-primary text-primary-foreground' : ''
                                }`}
                            >
                                {tab.count.toLocaleString()}
                            </Badge>
                        </button>
                    );
                })}
            </nav>
        </div>
    );
}
