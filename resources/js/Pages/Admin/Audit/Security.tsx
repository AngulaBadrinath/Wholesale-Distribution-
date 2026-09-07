import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { 
    ShieldAlert, 
    Search, 
    Filter, 
    Calendar, 
    Code, 
    X, 
    AlertTriangle, 
    AlertCircle, 
    Info, 
    CheckCircle2, 
    History,
    Shield
} from 'lucide-react';

interface Actor {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface SecurityLogItem {
    id: number;
    event_type: string;
    severity: 'INFO' | 'WARNING' | 'CRITICAL';
    actor_id: number | null;
    actor_email: string | null;
    actor_role: string | null;
    ip_address: string | null;
    user_agent: string | null;
    context: Record<string, any> | null;
    created_at: string;
    actor?: Actor | null;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    logs: PaginatedData<SecurityLogItem>;
    event_types: string[];
    filters: {
        event_type?: string;
        severity?: string;
        actor_id?: string;
        date_from?: string;
        date_to?: string;
        search?: string;
    };
}

export default function SecurityLogsIndex({ logs, event_types, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [eventType, setEventType] = useState(filters.event_type || '');
    const [severity, setSeverity] = useState(filters.severity || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [selectedContext, setSelectedContext] = useState<SecurityLogItem | null>(null);

    const applyFilters = (newFilters: Record<string, any>) => {
        router.get('/admin/audit/security', {
            event_type: eventType || undefined,
            severity: severity || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            search: search || undefined,
            ...newFilters,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({ search });
    };

    const handleClearFilters = () => {
        setSearch('');
        setEventType('');
        setSeverity('');
        setDateFrom('');
        setDateTo('');
        router.get('/admin/audit/security', {}, { replace: true });
    };

    const getSeverityBadge = (sev: string) => {
        switch (sev.toUpperCase()) {
            case 'CRITICAL':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                        <AlertCircle className="h-3 w-3" />
                        CRITICAL
                    </span>
                );
            case 'WARNING':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                        <AlertTriangle className="h-3 w-3" />
                        WARNING
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-400 border border-sky-200 dark:border-sky-800">
                        <Info className="h-3 w-3" />
                        INFO
                    </span>
                );
        }
    };

    return (
        <AppLayout title="Security Event Logs">
            <Head title="Security Event Log - Platform Audit" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border">
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                            <ShieldAlert className="h-6 w-6" />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                Security Event & Access Logs
                            </h2>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Restricted audit stream for authentication milestones, MFA challenges, permission denials, and privilege mutations.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href="/admin/audit/timeline"
                            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-border bg-card hover:bg-muted text-foreground transition-colors shadow-xs"
                        >
                            <History className="h-4 w-4 text-primary" />
                            Business Audit Timeline
                        </Link>
                    </div>
                </div>

                {/* Filter Controls */}
                <div className="p-4 rounded-xl bg-card border border-border shadow-xs space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        {/* Search Input */}
                        <div className="lg:col-span-2">
                            <label className="block text-[11px] font-medium text-muted-foreground mb-1">
                                Search Event / Email / IP / Actor
                            </label>
                            <form onSubmit={handleSearchSubmit} className="relative">
                                <input
                                    type="text"
                                    placeholder="Search security events..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-border bg-background text-foreground focus:ring-2 focus:ring-primary"
                                />
                                <Search className="h-3.5 w-3.5 text-muted-foreground absolute left-2.5 top-2.5" />
                            </form>
                        </div>

                        {/* Event Type Dropdown */}
                        <div>
                            <label className="block text-[11px] font-medium text-muted-foreground mb-1">
                                Event Type
                            </label>
                            <select
                                value={eventType}
                                onChange={(e) => {
                                    setEventType(e.target.value);
                                    applyFilters({ event_type: e.target.value });
                                }}
                                className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-border bg-background text-foreground focus:ring-2 focus:ring-primary"
                            >
                                <option value="">All Event Types</option>
                                {event_types.map((et) => (
                                    <option key={et} value={et}>
                                        {et}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Severity Dropdown */}
                        <div>
                            <label className="block text-[11px] font-medium text-muted-foreground mb-1">
                                Severity
                            </label>
                            <select
                                value={severity}
                                onChange={(e) => {
                                    setSeverity(e.target.value);
                                    applyFilters({ severity: e.target.value });
                                }}
                                className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-border bg-background text-foreground focus:ring-2 focus:ring-primary"
                            >
                                <option value="">All Severities</option>
                                <option value="INFO">Info</option>
                                <option value="WARNING">Warning</option>
                                <option value="CRITICAL">Critical</option>
                            </select>
                        </div>

                        {/* Date From */}
                        <div>
                            <label className="block text-[11px] font-medium text-muted-foreground mb-1">
                                Date From
                            </label>
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => {
                                    setDateFrom(e.target.value);
                                    applyFilters({ date_from: e.target.value });
                                }}
                                className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-border bg-background text-foreground focus:ring-2 focus:ring-primary"
                            />
                        </div>
                    </div>

                    {(search || eventType || severity || dateFrom || dateTo) && (
                        <div className="flex items-center justify-end pt-1">
                            <button
                                type="button"
                                onClick={handleClearFilters}
                                className="text-xs text-muted-foreground hover:text-foreground underline cursor-pointer"
                            >
                                Clear All Filters
                            </button>
                        </div>
                    )}
                </div>

                {/* Security Log Table */}
                <div className="border border-border rounded-xl bg-card overflow-hidden shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-muted/50 border-b border-border text-muted-foreground font-medium uppercase text-[10px] tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Timestamp</th>
                                    <th className="px-4 py-3">Event Type</th>
                                    <th className="px-4 py-3">Severity</th>
                                    <th className="px-4 py-3">Target / Actor Email</th>
                                    <th className="px-4 py-3">IP Address</th>
                                    <th className="px-4 py-3 text-right">Context</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {logs.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-12 text-center text-muted-foreground">
                                            <Shield className="h-10 w-10 mx-auto mb-2 opacity-25 text-emerald-500" />
                                            <p className="font-semibold text-foreground">No security events found</p>
                                            <p className="text-[11px] mt-0.5">All monitored security channels are nominal.</p>
                                        </td>
                                    </tr>
                                ) : (
                                    logs.data.map((item) => (
                                        <tr key={item.id} className="hover:bg-muted/30 transition-colors">
                                            {/* Timestamp */}
                                            <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                <div className="font-mono text-[11px]">
                                                    {new Date(item.created_at).toLocaleDateString()}
                                                </div>
                                                <div className="text-[10px] opacity-75">
                                                    {new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                                                </div>
                                            </td>

                                            {/* Event Type */}
                                            <td className="px-4 py-3">
                                                <span className="font-mono font-bold text-xs text-foreground">
                                                    {item.event_type}
                                                </span>
                                            </td>

                                            {/* Severity */}
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {getSeverityBadge(item.severity)}
                                            </td>

                                            {/* Actor / Target Email */}
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">
                                                    {item.actor_email || item.actor?.email || 'N/A'}
                                                </div>
                                                {item.actor_role && (
                                                    <div className="text-[10px] font-mono text-muted-foreground">
                                                        Role: {item.actor_role}
                                                    </div>
                                                )}
                                            </td>

                                            {/* IP Address */}
                                            <td className="px-4 py-3 font-mono text-[11px] text-muted-foreground whitespace-nowrap">
                                                {item.ip_address || '&mdash;'}
                                            </td>

                                            {/* Context JSON Button */}
                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                {item.context && Object.keys(item.context).length > 0 ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelectedContext(item)}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-mono bg-muted hover:bg-primary/10 hover:text-primary transition-colors cursor-pointer border border-border"
                                                    >
                                                        <Code className="h-3 w-3" />
                                                        Payload
                                                    </button>
                                                ) : (
                                                    <span className="text-muted-foreground text-[10px]">&mdash;</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border pt-4">
                        <span className="text-xs text-muted-foreground">
                            Showing page {logs.current_page} of {logs.last_page} ({logs.total} total security logs)
                        </span>
                        <div className="flex items-center gap-1">
                            {logs.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    preserveScroll
                                    className={`px-3 py-1 text-xs rounded-md border ${
                                        link.active
                                            ? 'bg-primary text-primary-foreground border-primary font-medium'
                                            : 'bg-card text-muted-foreground border-border hover:bg-muted'
                                    } ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}

                {/* Context Modal */}
                {selectedContext && (
                    <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-card border border-border rounded-xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in-50 zoom-in-95 duration-100">
                            <div className="p-4 border-b border-border flex items-center justify-between bg-muted/30">
                                <div>
                                    <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
                                        <ShieldAlert className="h-4 w-4 text-rose-500" />
                                        Security Event Context Details
                                    </h3>
                                    <p className="text-[11px] text-muted-foreground font-mono mt-0.5">
                                        ID: {selectedContext.id} &bull; Type: {selectedContext.event_type}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setSelectedContext(null)}
                                    className="p-1 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>

                            <div className="p-4 overflow-y-auto space-y-3 font-mono text-xs">
                                <div className="grid grid-cols-2 gap-2 text-[11px] p-2.5 rounded-lg bg-muted/40 border border-border">
                                    <div>
                                        <span className="text-muted-foreground">IP Address:</span>{' '}
                                        <span className="text-foreground font-semibold">{selectedContext.ip_address || 'N/A'}</span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Severity:</span>{' '}
                                        <span className="text-foreground font-semibold">{selectedContext.severity}</span>
                                    </div>
                                    <div className="col-span-2 truncate" title={selectedContext.user_agent || ''}>
                                        <span className="text-muted-foreground">User Agent:</span>{' '}
                                        <span className="text-foreground">{selectedContext.user_agent || 'N/A'}</span>
                                    </div>
                                </div>

                                <div>
                                    <span className="text-muted-foreground text-[11px] font-semibold uppercase tracking-wider block mb-1">
                                        Sanitized Security Context
                                    </span>
                                    <pre className="p-3 rounded-lg bg-slate-950 text-slate-100 dark:bg-black overflow-x-auto text-[11px] leading-relaxed border border-border">
                                        {JSON.stringify(selectedContext.context, null, 2)}
                                    </pre>
                                </div>
                            </div>

                            <div className="p-3 border-t border-border bg-muted/20 flex justify-end">
                                <button
                                    type="button"
                                    onClick={() => setSelectedContext(null)}
                                    className="px-4 py-1.5 text-xs font-medium rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
