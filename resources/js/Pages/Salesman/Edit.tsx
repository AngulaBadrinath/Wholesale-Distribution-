import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    ArrowLeft,
    Mail,
    User as UserIcon,
    Loader2,
    Info,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface SalesmanEditProps {
    salesman: {
        id: number;
        name: string;
        email: string;
        status: string;
        status_label: string;
    };
}

export default function SalesmanEdit({ salesman }: SalesmanEditProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: salesman.name,
        email: salesman.email,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/salesmen/${salesman.id}`);
    };

    return (
        <AppLayout title={`Edit ${salesman.name}`}>
            <Head title={`Edit ${salesman.name} — Sales Representative`} />

            <div className="space-y-6 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Header with Back Navigation */}
                <div className="flex items-center justify-between border-b border-border/80 pb-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={`/salesmen/${salesman.id}`}
                            className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'h-8 w-8 p-0')}
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                Edit Sales Representative
                            </h1>
                            <p className="text-xs text-muted-foreground">
                                Update profile contact details for {salesman.name}
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card className="border-border/70 shadow-xs">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Profile Information</CardTitle>
                            <CardDescription className="text-xs">
                                Update representative legal name and login email address.
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
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="bg-background/50 h-9 text-xs font-mono"
                                    required
                                />
                                {errors.email && (
                                    <p className="text-[11px] text-red-500 font-medium">{errors.email}</p>
                                )}
                            </div>

                            {/* Lifecycle Note */}
                            <div className="p-3 rounded-md bg-muted/50 border border-border text-xs text-muted-foreground flex items-start gap-2.5">
                                <Info className="h-4 w-4 text-primary shrink-0 mt-0.5" />
                                <div>
                                    <span className="font-medium text-foreground">Lifecycle State Notice:</span>
                                    <p className="text-[11px] mt-0.5">
                                        Current status is <strong className="text-foreground">{salesman.status_label}</strong>. Account activation, suspension, or disablement is managed through the dedicated lifecycle controls on the representative profile page.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit Actions */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href={`/salesmen/${salesman.id}`}
                            className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}
                        >
                            Cancel
                        </Link>
                        <Button type="submit" size="sm" disabled={processing} className="gap-2">
                            {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                            <span>Save Profile Changes</span>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
