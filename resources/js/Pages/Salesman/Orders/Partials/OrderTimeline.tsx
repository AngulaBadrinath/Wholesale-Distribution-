import React from 'react';
import { OrderTimelineEvent } from '@/types/order';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';
import {
    History,
    FileCheck,
    Send,
    CheckCircle2,
    Clock,
    XCircle,
    Package,
    DollarSign,
    Truck,
    User,
    Calendar,
} from 'lucide-react';

interface OrderTimelineProps {
    timeline: OrderTimelineEvent[];
    className?: string;
}

export default function OrderTimeline({ timeline, className }: OrderTimelineProps) {
    if (!timeline || timeline.length === 0) {
        return null;
    }

    const renderIcon = (icon: OrderTimelineEvent['icon'], status: OrderTimelineEvent['status']) => {
        const iconClasses = "h-4 w-4";

        switch (icon) {
            case 'created':
                return <FileCheck className={iconClasses} />;
            case 'submitted':
                return <Send className={iconClasses} />;
            case 'approved':
            case 'completed':
                return <CheckCircle2 className={iconClasses} />;
            case 'cancelled':
                return <XCircle className={iconClasses} />;
            case 'fulfillment':
                return <Package className={iconClasses} />;
            case 'payment':
                return <DollarSign className={iconClasses} />;
            case 'delivery':
                return <Truck className={iconClasses} />;
            case 'processing':
            default:
                return <Clock className={iconClasses} />;
        }
    };

    const getIndicatorStyles = (status: OrderTimelineEvent['status']) => {
        switch (status) {
            case 'completed':
                return 'bg-primary text-primary-foreground border-primary';
            case 'current':
                return 'bg-sky-500 text-white border-sky-500 ring-4 ring-sky-500/20 animate-pulse';
            case 'cancelled':
                return 'bg-rose-500 text-white border-rose-500 ring-4 ring-rose-500/20';
            case 'pending':
            default:
                return 'bg-muted text-muted-foreground border-border';
        }
    };

    return (
        <Card className={cn('border-border/60', className)}>
            <CardHeader className="pb-4 border-b">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <History className="h-5 w-5 text-primary" />
                        <div>
                            <CardTitle className="text-base font-bold">Multi-State Order Timeline</CardTitle>
                            <CardDescription className="text-xs">
                                Persisted lifecycle milestones and live workflow state progression
                            </CardDescription>
                        </div>
                    </div>
                </div>
            </CardHeader>

            <CardContent className="pt-6">
                <ol className="relative border-l border-border/70 ml-3.5 space-y-6">
                    {timeline.map((event, index) => {
                        const isLast = index === timeline.length - 1;
                        const hasTimestamp = Boolean(event.timestamp);

                        return (
                            <li key={event.id} className="relative pl-6 group">
                                {/* Timeline Dot / Icon */}
                                <span
                                    className={cn(
                                        'absolute -left-3.5 flex h-7 w-7 items-center justify-center rounded-full border-2 text-xs transition-transform duration-200',
                                        getIndicatorStyles(event.status)
                                    )}
                                    aria-hidden="true"
                                >
                                    {renderIcon(event.icon, event.status)}
                                </span>

                                {/* Event Card Body */}
                                <div className="space-y-1.5 bg-muted/20 hover:bg-muted/30 transition-colors p-3 rounded-lg border border-border/40">
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                        <div className="flex items-center gap-2">
                                            <h4 className="text-sm font-semibold text-foreground tracking-tight">
                                                {event.title}
                                            </h4>
                                            {event.badge_label && (
                                                <Badge
                                                    variant={
                                                        event.status === 'cancelled'
                                                            ? 'destructive'
                                                            : event.status === 'completed'
                                                            ? 'success'
                                                            : event.status === 'current'
                                                            ? 'info'
                                                            : 'secondary'
                                                    }
                                                    className="text-[10px] px-2 py-0 font-mono"
                                                >
                                                    {event.badge_label}
                                                </Badge>
                                            )}
                                        </div>

                                        {/* Timestamp if genuinely persisted */}
                                        {hasTimestamp ? (
                                            <span className="text-[11px] font-mono text-muted-foreground flex items-center gap-1">
                                                <Calendar className="h-3 w-3" />
                                                <span>{new Date(event.timestamp!).toLocaleString()}</span>
                                            </span>
                                        ) : (
                                            <span className="text-[11px] font-mono text-muted-foreground/70 italic">
                                                {event.status === 'current' ? 'Active Workflow Stage' : 'Pending Stage'}
                                            </span>
                                        )}
                                    </div>

                                    {/* Description */}
                                    {event.description && (
                                        <p className="text-xs text-muted-foreground leading-relaxed">
                                            {event.description}
                                        </p>
                                    )}

                                    {/* Actor / Attribution */}
                                    {event.actor_name && (
                                        <div className="pt-1 flex items-center gap-1.5 text-[11px] text-foreground/80 font-medium">
                                            <User className="h-3 w-3 text-muted-foreground" />
                                            <span>Action recorded by: <strong className="font-semibold">{event.actor_name}</strong></span>
                                        </div>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ol>
            </CardContent>
        </Card>
    );
}
