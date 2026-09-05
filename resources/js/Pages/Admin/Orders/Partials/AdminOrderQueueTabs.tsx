import React from 'react';
import { AdminOrderQueueCounts } from '@/types/order';
import { Badge } from '@/Components/ui/badge';
import {
    Clock,
    AlertTriangle,
    Package,
    Truck,
    SlidersHorizontal,
    CheckCircle2,
    XCircle,
    Receipt,
} from 'lucide-react';

interface AdminOrderQueueTabsProps {
    activeQueue: string;
    counts: AdminOrderQueueCounts;
    onSelectQueue: (queueKey: string) => void;
}

export default function AdminOrderQueueTabs({
    activeQueue,
    counts,
    onSelectQueue,
}: AdminOrderQueueTabsProps) {
    const tabs = [
        {
            key: 'new',
            label: 'New Orders',
            count: counts.new,
            icon: Clock,
            variant: 'info',
            description: 'Submitted & awaiting review',
        },
        {
            key: 'attention',
            label: 'Needs Attention',
            count: counts.attention,
            icon: AlertTriangle,
            variant: 'destructive',
            description: 'Exceptions, overdue & aging',
        },
        {
            key: 'processing',
            label: 'Processing',
            count: counts.processing,
            icon: Package,
            variant: 'primary',
            description: 'Approved, picking & packing',
        },
        {
            key: 'delivery',
            label: 'Delivery',
            count: counts.delivery,
            icon: Truck,
            variant: 'warning',
            description: 'Dispatched & en route',
        },
        {
            key: 'adjustments',
            label: 'Adjustments',
            count: counts.adjustments,
            icon: SlidersHorizontal,
            variant: 'secondary',
            description: 'Quantity & cancellation requests',
        },
        {
            key: 'completed',
            label: 'Completed',
            count: counts.completed,
            icon: CheckCircle2,
            variant: 'success',
            description: 'Fulfilled & closed orders',
        },
        {
            key: 'cancelled',
            label: 'Cancelled',
            count: counts.cancelled,
            icon: XCircle,
            variant: 'outline',
            description: 'Rejected & cancelled orders',
        },
        {
            key: 'all',
            label: 'All Orders',
            count: counts.all,
            icon: Receipt,
            variant: 'secondary',
            description: 'Full order history archive',
        },
    ];

    return (
        <div className="w-full border-b border-border bg-card/50 backdrop-blur-xs rounded-t-lg">
            <nav
                className="flex space-x-1 overflow-x-auto p-1.5 scrollbar-thin"
                role="tablist"
                aria-label="Operational Order Queues"
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
                                        ? 'text-destructive animate-pulse'
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
