import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { CheckCircle2, Server, Database, Cpu, Layout, Layers, Shield } from 'lucide-react';

interface WelcomeProps {
    phpVersion: string;
    laravelVersion: string;
}

export default function Welcome({ phpVersion, laravelVersion }: WelcomeProps) {
    const [testInput, setTestInput] = useState('');
    const [clickCount, setClickCount] = useState(0);

    return (
        <AppLayout title="Phase 00: Foundation & Core Infrastructure">
            <Head title="Platform Foundation" />

            <div className="space-y-6">
                {/* Hero / Phase Status Card */}
                <div className="rounded-lg border border-border bg-card p-6 shadow-xs">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <Badge variant="success">Phase 00 Active</Badge>
                                <Badge variant="outline">Infrastructure Ready</Badge>
                            </div>
                            <h2 className="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                Core Application Foundation
                            </h2>
                            <p className="text-xs sm:text-sm text-muted-foreground max-w-2xl">
                                Clean, reproducible baseline establishing Laravel 13, Inertia 3, React 19, TypeScript, Tailwind CSS 4, and PostgreSQL/Redis local infrastructure.
                            </p>
                        </div>

                        <div className="flex items-center gap-2 self-start md:self-auto font-mono text-xs bg-muted/50 p-2.5 rounded-md border border-border">
                            <span className="text-muted-foreground">PHP:</span>
                            <span className="font-semibold text-foreground">{phpVersion}</span>
                            <span className="text-border">|</span>
                            <span className="text-muted-foreground">Laravel:</span>
                            <span className="font-semibold text-foreground">{laravelVersion}</span>
                        </div>
                    </div>
                </div>

                {/* Architecture Subsystem Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {/* Subsystem 1: Backend */}
                    <Card>
                        <CardHeader className="pb-3">
                            <div className="flex items-center justify-between">
                                <div className="p-2 rounded-md bg-primary/10 text-primary">
                                    <Server className="h-5 w-5" />
                                </div>
                                <Badge variant="success">Verified</Badge>
                            </div>
                            <CardTitle className="text-base mt-2">Backend Framework</CardTitle>
                            <CardDescription className="text-xs">
                                Laravel 13 on PHP 8.5 with strict types and modern dependency management.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-xs space-y-1.5 text-muted-foreground">
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                <span>Artisan CLI & Environment Configuration</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                <span>Health Check API Endpoint (/health)</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                <span>PHPUnit & Pest Testing Harness</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Subsystem 2: Frontend */}
                    <Card>
                        <CardHeader className="pb-3">
                            <div className="flex items-center justify-between">
                                <div className="p-2 rounded-md bg-primary/10 text-primary">
                                    <Layout className="h-5 w-5" />
                                </div>
                                <Badge variant="success">Verified</Badge>
                            </div>
                            <CardTitle className="text-base mt-2">Frontend Stack</CardTitle>
                            <CardDescription className="text-xs">
                                React 19.2 + TypeScript + Inertia 3 with Vite HMR compilation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-xs space-y-1.5 text-muted-foreground">
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                <span>Tailwind CSS 4 + shadcn/ui Design Tokens</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                <span>Responsive Layout Engine (320px - 1920px)</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                <span>TypeScript Static Analysis (tsc --noEmit)</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Subsystem 3: Persistence & Cache */}
                    <Card>
                        <CardHeader className="pb-3">
                            <div className="flex items-center justify-between">
                                <div className="p-2 rounded-md bg-primary/10 text-primary">
                                    <Database className="h-5 w-5" />
                                </div>
                                <Badge variant="outline">Docker Local</Badge>
                            </div>
                            <CardTitle className="text-base mt-2">Data & Cache Services</CardTitle>
                            <CardDescription className="text-xs">
                                PostgreSQL 18 and Redis 7 isolated in Docker Compose.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-xs space-y-1.5 text-muted-foreground">
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                <span>PostgreSQL (Port 5433 host mapped)</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                <span>Redis (Port 6380 host mapped)</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                <span>Safe Environment Baseline (.env.example)</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* UI Foundation Primitives Verification */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Layers className="h-4 w-4 text-primary" />
                            <CardTitle className="text-base">Design System & UI Primitives</CardTitle>
                        </div>
                        <CardDescription className="text-xs">
                            Demonstration of foundational primitives conforming to Document 04 Design Tokens and WCAG AA contrast standards.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Buttons & Badges */}
                        <div className="space-y-2">
                            <div className="text-xs font-semibold text-foreground uppercase tracking-wider">
                                Button & Badge Variants
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <Button size="sm" onClick={() => setClickCount(c => c + 1)}>
                                    Primary Action ({clickCount})
                                </Button>
                                <Button size="sm" variant="secondary">
                                    Secondary
                                </Button>
                                <Button size="sm" variant="outline">
                                    Outline
                                </Button>
                                <Button size="sm" variant="destructive">
                                    Destructive
                                </Button>
                                <div className="h-4 w-px bg-border mx-1" />
                                <Badge variant="default">Default</Badge>
                                <Badge variant="secondary">Secondary</Badge>
                                <Badge variant="success">Success</Badge>
                                <Badge variant="warning">Warning</Badge>
                                <Badge variant="destructive">Danger</Badge>
                                <Badge variant="info">Info</Badge>
                            </div>
                        </div>

                        {/* Form Input Primitive */}
                        <div className="space-y-2">
                            <div className="text-xs font-semibold text-foreground uppercase tracking-wider">
                                Input Primitive & Reactive State
                            </div>
                            <div className="max-w-md flex items-center gap-2">
                                <Input
                                    placeholder="Type to verify React 19 reactivity..."
                                    value={testInput}
                                    onChange={(e) => setTestInput(e.target.value)}
                                />
                                {testInput && (
                                    <Button size="sm" variant="ghost" onClick={() => setTestInput('')}>
                                        Clear
                                    </Button>
                                )}
                            </div>
                            {testInput && (
                                <p className="text-xs text-muted-foreground font-mono bg-muted p-2 rounded">
                                    Echo: {testInput}
                                </p>
                            )}
                        </div>
                    </CardContent>
                    <CardFooter className="bg-muted/20 border-t border-border flex flex-col sm:flex-row items-start sm:items-center justify-between text-xs text-muted-foreground gap-2">
                        <div className="flex items-center gap-1.5">
                            <Shield className="h-4 w-4 text-emerald-600" />
                            <span>Strict Domain Firewall: No domain entities or fake business data populated.</span>
                        </div>
                        <span className="font-mono text-[11px]">TECH-FOUND-001</span>
                    </CardFooter>
                </Card>
            </div>
        </AppLayout>
    );
}
