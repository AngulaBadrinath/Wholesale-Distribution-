import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageProps, SessionRecord } from '@/types';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
    Laptop,
    Smartphone,
    Tablet,
    Globe,
    Shield,
    CheckCircle2,
    AlertTriangle,
    LogOut,
    Trash2,
    ArrowLeft,
    Clock,
    MapPin,
    ShieldAlert,
} from 'lucide-react';

interface SessionsProps extends PageProps {
    sessions: SessionRecord[];
}

export default function Sessions({ sessions }: SessionsProps) {
    const { flash } = usePage<SessionsProps>().props;
    const [revokingId, setRevokingId] = useState<string | null>(null);
    const [isRevokingOthers, setIsRevokingOthers] = useState(false);
    const [isRevokingAll, setIsRevokingAll] = useState(false);
    const [confirmRevokeOthersOpen, setConfirmRevokeOthersOpen] = useState(false);
    const [confirmRevokeAllOpen, setConfirmRevokeAllOpen] = useState(false);
    const [sessionToRevoke, setSessionToRevoke] = useState<SessionRecord | null>(null);

    const currentSession = sessions.find((s) => s.is_current);
    const otherSessions = sessions.filter((s) => !s.is_current);

    const getDeviceIcon = (type: SessionRecord['device_type']) => {
        switch (type) {
            case 'mobile':
                return <Smartphone className="h-5 w-5 text-muted-foreground" aria-hidden="true" />;
            case 'tablet':
                return <Tablet className="h-5 w-5 text-muted-foreground" aria-hidden="true" />;
            case 'desktop':
                return <Laptop className="h-5 w-5 text-muted-foreground" aria-hidden="true" />;
            default:
                return <Globe className="h-5 w-5 text-muted-foreground" aria-hidden="true" />;
        }
    };

    const handleRevokeSingle = (session: SessionRecord) => {
        setRevokingId(session.id);
        router.post(
            `/security/sessions/${session.id}/revoke`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setRevokingId(null);
                    setSessionToRevoke(null);
                },
            }
        );
    };

    const handleRevokeOthers = () => {
        setIsRevokingOthers(true);
        router.post(
            '/security/sessions/revoke-others',
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsRevokingOthers(false);
                    setConfirmRevokeOthersOpen(false);
                },
            }
        );
    };

    const handleRevokeAll = () => {
        setIsRevokingAll(true);
        router.post(
            '/security/sessions/revoke-all',
            {},
            {
                onFinish: () => {
                    setIsRevokingAll(false);
                    setConfirmRevokeAllOpen(false);
                },
            }
        );
    };

    return (
        <div className="min-h-screen bg-background text-foreground flex flex-col">
            <Head title="Security & Active Sessions" />

            {/* Top Navigation Bar */}
            <header className="border-b border-border bg-card/60 backdrop-blur-md sticky top-0 z-30">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/dashboard"
                            className="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-md p-1"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            <span>Dashboard</span>
                        </Link>
                        <span className="text-muted-foreground">/</span>
                        <div className="flex items-center gap-2">
                            <Shield className="h-4 w-4 text-primary" />
                            <span className="text-sm font-semibold">Security & Sessions</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/security/mfa">
                            <Button variant="outline" size="sm" className="gap-1.5 h-9">
                                <Shield className="h-4 w-4 text-primary" />
                                <span>Two-Factor Auth</span>
                            </Button>
                        </Link>
                        <form method="POST" action="/logout">
                            <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''} />
                            <Button
                                variant="ghost"
                                size="sm"
                                type="button"
                                onClick={() => router.post('/logout')}
                                className="gap-2 text-muted-foreground hover:text-destructive"
                            >
                                <LogOut className="h-4 w-4" />
                                <span>Sign Out</span>
                            </Button>
                        </form>
                    </div>
                </div>
            </header>

            {/* Main Content Area */}
            <main className="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
                {/* Header Banner */}
                <div className="space-y-1">
                    <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                        Active Sessions & Devices
                    </h1>
                    <p className="text-sm sm:text-base text-muted-foreground">
                        Review all devices and browser sessions currently authenticated into your account. Revoke any unrecognized access immediately.
                    </p>
                </div>

                {/* Flash Messages */}
                {flash?.success && (
                    <div
                        role="alert"
                        className="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-400 flex items-start gap-3 text-sm font-medium"
                    >
                        <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-500" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div
                        role="alert"
                        className="rounded-lg border border-destructive/20 bg-destructive/10 p-4 text-destructive flex items-start gap-3 text-sm font-medium"
                    >
                        <AlertTriangle className="h-5 w-5 shrink-0 text-destructive" />
                        <span>{flash.error}</span>
                    </div>
                )}

                {/* Current Session Section */}
                <section aria-labelledby="current-session-heading">
                    <Card className="border-border shadow-sm">
                        <CardHeader className="pb-4">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <CardTitle id="current-session-heading" className="text-lg font-semibold flex items-center gap-2">
                                        <span>Current Active Session</span>
                                        <Badge variant="outline" className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 gap-1 font-normal">
                                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                            Active Now
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription>
                                        This is the device and browser window you are currently using.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {currentSession ? (
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-lg bg-muted/40 border border-border/60">
                                    <div className="flex items-start gap-3.5">
                                        <div className="p-2.5 rounded-md bg-background border border-border shadow-2xs mt-0.5">
                                            {getDeviceIcon(currentSession.device_type)}
                                        </div>
                                        <div className="space-y-1">
                                            <div className="font-semibold text-sm sm:text-base text-foreground flex items-center gap-2">
                                                <span>{currentSession.browser}</span>
                                                <span className="text-muted-foreground font-normal">on</span>
                                                <span>{currentSession.platform}</span>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                                <div className="flex items-center gap-1">
                                                    <MapPin className="h-3.5 w-3.5" />
                                                    <span>IP: {currentSession.ip_address}</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Clock className="h-3.5 w-3.5" />
                                                    <span>Last active: {currentSession.last_active_human}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-xs text-muted-foreground sm:text-right font-mono bg-background/80 px-2.5 py-1 rounded border border-border/50 self-start sm:self-auto">
                                        Current Session
                                    </div>
                                </div>
                            ) : (
                                <div className="text-sm text-muted-foreground italic">
                                    Session details unavailable.
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>

                {/* Other Active Sessions Section */}
                <section aria-labelledby="other-sessions-heading">
                    <Card className="border-border shadow-sm">
                        <CardHeader className="pb-4">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <CardTitle id="other-sessions-heading" className="text-lg font-semibold flex items-center gap-2">
                                        <span>Other Active Sessions</span>
                                        <Badge variant="secondary" className="font-normal text-xs">
                                            {otherSessions.length} {otherSessions.length === 1 ? 'device' : 'devices'}
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription>
                                        Other browsers or mobile devices currently authenticated under your account.
                                    </CardDescription>
                                </div>
                                {otherSessions.length > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setConfirmRevokeOthersOpen(true)}
                                        disabled={isRevokingOthers}
                                        className="gap-2 text-destructive border-destructive/20 hover:bg-destructive/10 hover:text-destructive self-start sm:self-auto"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        <span>Revoke All Other Devices</span>
                                    </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {otherSessions.length === 0 ? (
                                <div className="text-center py-10 px-4 rounded-lg border border-dashed border-border bg-muted/20">
                                    <Shield className="h-10 w-10 text-muted-foreground/60 mx-auto mb-3" />
                                    <h3 className="font-medium text-foreground text-sm sm:text-base">
                                        No Other Active Sessions
                                    </h3>
                                    <p className="text-xs sm:text-sm text-muted-foreground max-w-sm mx-auto mt-1">
                                        You are only signed in on this current device. No other concurrent sessions exist.
                                    </p>
                                </div>
                            ) : (
                                <ul className="divide-y divide-border/60" role="list">
                                    {otherSessions.map((session) => (
                                        <li
                                            key={session.id}
                                            className="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 first:pt-0 last:pb-0"
                                        >
                                            <div className="flex items-start gap-3.5">
                                                <div className="p-2.5 rounded-md bg-muted/60 border border-border mt-0.5">
                                                    {getDeviceIcon(session.device_type)}
                                                </div>
                                                <div className="space-y-1">
                                                    <div className="font-medium text-sm text-foreground flex items-center gap-2">
                                                        <span>{session.browser}</span>
                                                        <span className="text-muted-foreground font-normal">on</span>
                                                        <span>{session.platform}</span>
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                                        <div className="flex items-center gap-1">
                                                            <MapPin className="h-3.5 w-3.5" />
                                                            <span>IP: {session.ip_address}</span>
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <Clock className="h-3.5 w-3.5" />
                                                            <span>Last active: {session.last_active_human}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2 self-end sm:self-center">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={revokingId === session.id}
                                                    onClick={() => setSessionToRevoke(session)}
                                                    className="text-xs h-8 px-3 text-muted-foreground hover:text-destructive hover:border-destructive/30"
                                                >
                                                    {revokingId === session.id ? 'Signing out...' : 'Revoke'}
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </section>

                {/* Emergency Revoke All Option */}
                <section aria-labelledby="emergency-revoke-heading">
                    <Card className="border-destructive/20 bg-destructive/5 shadow-none">
                        <CardHeader className="pb-3">
                            <CardTitle id="emergency-revoke-heading" className="text-base font-semibold text-destructive flex items-center gap-2">
                                <ShieldAlert className="h-4 w-4" />
                                <span>Security Sign-Out: Revoke All Sessions</span>
                            </CardTitle>
                            <CardDescription className="text-xs sm:text-sm">
                                If you suspect your account has been compromised, you can sign out of all devices immediately, including this current session. You will be redirected to the login screen.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => setConfirmRevokeAllOpen(true)}
                                disabled={isRevokingAll}
                                className="text-xs h-8"
                            >
                                <span>Sign Out Everywhere (All Devices)</span>
                            </Button>
                        </CardContent>
                    </Card>
                </section>
            </main>

            {/* Modal: Confirm Revoke Single Session */}
            {sessionToRevoke && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="confirm-revoke-single-title"
                    className="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-xs p-4"
                >
                    <div className="bg-card border border-border shadow-lg rounded-lg max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 rounded-full bg-destructive/10 text-destructive">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                            <h3 id="confirm-revoke-single-title" className="text-lg font-semibold text-foreground">
                                Revoke Session?
                            </h3>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            This device ({sessionToRevoke.browser} on {sessionToRevoke.platform}) will be immediately signed out and must log in again to regain access.
                        </p>
                        <div className="flex justify-end gap-3 pt-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setSessionToRevoke(null)}
                                disabled={revokingId === sessionToRevoke.id}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => handleRevokeSingle(sessionToRevoke)}
                                disabled={revokingId === sessionToRevoke.id}
                            >
                                {revokingId === sessionToRevoke.id ? 'Revoking...' : 'Revoke Session'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal: Confirm Revoke All Other Sessions */}
            {confirmRevokeOthersOpen && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="confirm-revoke-others-title"
                    className="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-xs p-4"
                >
                    <div className="bg-card border border-border shadow-lg rounded-lg max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 rounded-full bg-destructive/10 text-destructive">
                                <Trash2 className="h-5 w-5" />
                            </div>
                            <h3 id="confirm-revoke-others-title" className="text-lg font-semibold text-foreground">
                                Revoke All Other Devices?
                            </h3>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            This will sign out all {otherSessions.length} other active devices. Your current device and browser session will remain active.
                        </p>
                        <div className="flex justify-end gap-3 pt-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setConfirmRevokeOthersOpen(false)}
                                disabled={isRevokingOthers}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={handleRevokeOthers}
                                disabled={isRevokingOthers}
                            >
                                {isRevokingOthers ? 'Signing Out...' : 'Revoke All Other Devices'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal: Confirm Revoke All Sessions */}
            {confirmRevokeAllOpen && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="confirm-revoke-all-title"
                    className="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-xs p-4"
                >
                    <div className="bg-card border border-border shadow-lg rounded-lg max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 rounded-full bg-destructive/10 text-destructive">
                                <ShieldAlert className="h-5 w-5" />
                            </div>
                            <h3 id="confirm-revoke-all-title" className="text-lg font-semibold text-destructive">
                                Sign Out Everywhere?
                            </h3>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            This will invalidate ALL active sessions across all devices, including this current device. You will be logged out and returned to the login page immediately.
                        </p>
                        <div className="flex justify-end gap-3 pt-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setConfirmRevokeAllOpen(false)}
                                disabled={isRevokingAll}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={handleRevokeAll}
                                disabled={isRevokingAll}
                            >
                                {isRevokingAll ? 'Signing Out Everywhere...' : 'Sign Out Everywhere'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
