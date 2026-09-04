import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    ArrowLeft,
    Shield,
    CheckCircle2,
    Clock,
    Lock,
    Mail,
    User as UserIcon,
    Loader2,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface StatusOption {
    value: string;
    label: string;
    description: string;
}

interface SalesmanCreateProps {
    statuses: StatusOption[];
}

export default function SalesmanCreate({ statuses }: SalesmanCreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        status: 'ACTIVE',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/salesmen');
    };

    return (
        <AppLayout title="Provision Sales Representative">
            <Head title="Provision Sales Representative — Master Data" />

            <div className="space-y-6 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Header with Back Navigation */}
                <div className="flex items-center justify-between border-b border-border/80 pb-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/salesmen"
                            className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'h-8 w-8 p-0')}
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                Provision Sales Representative
                            </h1>
                            <p className="text-xs text-muted-foreground">
                                Create an authorized field sales account with portfolio management access
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Role Attribution Card */}
                    <Card className="bg-muted/40 border-border/70 shadow-xs">
                        <CardHeader className="pb-3">
                            <div className="flex items-center gap-2 text-primary font-medium text-xs">
                                <Shield className="h-4 w-4" />
                                <span>Role Assignment & System Authority</span>
                            </div>
                            <CardTitle className="text-base">Sales Representative (SALESMAN)</CardTitle>
                            <CardDescription className="text-xs">
                                Account is automatically granted field sales capabilities: customer portfolio viewing, catalogue price access, order submission, and payment collection.
                            </CardDescription>
                        </CardHeader>
                    </Card>

                    {/* Account Identity & Credentials */}
                    <Card className="border-border/70 shadow-xs">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Account Identity & Credentials</CardTitle>
                            <CardDescription className="text-xs">
                                Enter the representative's full legal name, login email, and initial password.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Full Name */}
                            <div className="space-y-1.5">
                                <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                                    <UserIcon className="h-3.5 w-3.5 text-muted-foreground" />
                                    <span>Full Legal Name</span>
                                    <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    type="text"
                                    placeholder="e.g. Jane Doe"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="bg-background/50 h-9 text-xs"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-[11px] text-red-500 font-medium">{errors.name}</p>
                                )}
                            </div>

                            {/* Email Address */}
                            <div className="space-y-1.5">
                                <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                                    <Mail className="h-3.5 w-3.5 text-muted-foreground" />
                                    <span>Login Email Address</span>
                                    <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    type="email"
                                    placeholder="e.g. jane.doe@distributor.com"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="bg-background/50 h-9 text-xs font-mono"
                                    required
                                />
                                {errors.email && (
                                    <p className="text-[11px] text-red-500 font-medium">{errors.email}</p>
                                )}
                            </div>

                            {/* Password */}
                            <div className="space-y-1.5">
                                <label className="text-xs font-medium text-foreground flex items-center gap-1.5">
                                    <Lock className="h-3.5 w-3.5 text-muted-foreground" />
                                    <span>Initial Secure Password</span>
                                    <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    type="password"
                                    placeholder="••••••••••••"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="bg-background/50 h-9 text-xs font-mono"
                                    required
                                />
                                <p className="text-[11px] text-muted-foreground">
                                    Minimum 8 characters. Hashed authoritatively using Argon2id/Bcrypt.
                                </p>
                                {errors.password && (
                                    <p className="text-[11px] text-red-500 font-medium">{errors.password}</p>
                                )}
                            </div>

                            {/* Initial Status */}
                            <div className="space-y-2 pt-2 border-t border-border/50">
                                <label className="text-xs font-medium text-foreground block">
                                    Initial Account Lifecycle State
                                </label>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    {statuses.map((status) => (
                                        <div
                                            key={status.value}
                                            onClick={() => setData('status', status.value)}
                                            className={`p-3 rounded-lg border text-xs cursor-pointer transition-all ${
                                                data.status === status.value
                                                    ? 'border-primary bg-primary/5 text-foreground ring-1 ring-primary'
                                                    : 'border-border/70 hover:border-border bg-card text-muted-foreground'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between font-medium text-foreground mb-1">
                                                <span className="flex items-center gap-1.5">
                                                    {status.value === 'ACTIVE' ? (
                                                        <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                                                    ) : (
                                                        <Clock className="h-3.5 w-3.5 text-amber-500" />
                                                    )}
                                                    {status.label}
                                                </span>
                                            </div>
                                            <p className="text-[11px] text-muted-foreground leading-relaxed">
                                                {status.description}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                                {errors.status && (
                                    <p className="text-[11px] text-red-500 font-medium">{errors.status}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit Actions */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href="/salesmen"
                            className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}
                        >
                            Cancel
                        </Link>
                        <Button type="submit" size="sm" disabled={processing} className="gap-2">
                            {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                            <span>Provision Sales Representative</span>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
