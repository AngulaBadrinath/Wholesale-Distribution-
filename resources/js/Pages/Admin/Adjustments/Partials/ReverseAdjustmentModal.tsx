import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import {
    RotateCcw,
    AlertTriangle,
    CheckCircle2,
    X,
    Loader2,
    Calculator,
    PackageCheck,
    ShieldAlert,
} from 'lucide-react';

interface ReverseAdjustmentModalProps {
    isOpen: boolean;
    orderId: number;
    orderNumber: string;
    adjustmentId: number;
    adjustmentNumber: string;
    hasAllocationImpact: boolean;
    totalAffectedAllocationQuantity: number;
    projectedReductions: {
        subtotal: string;
        tax_total: string;
        grand_total: string;
    };
    isRequester?: boolean;
    isSuperAdmin?: boolean;
    onClose: () => void;
}

export default function ReverseAdjustmentModal({
    isOpen,
    orderId,
    orderNumber,
    adjustmentId,
    adjustmentNumber,
    hasAllocationImpact,
    totalAffectedAllocationQuantity,
    projectedReductions,
    isRequester = false,
    isSuperAdmin = false,
    onClose,
}: ReverseAdjustmentModalProps) {
    const [reason, setReason] = useState('');
    const [emergencyOverrideReason, setEmergencyOverrideReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    useEffect(() => {
        if (isOpen) {
            setReason('');
            setEmergencyOverrideReason('');
            setErrorMessage(null);
            setIsSubmitting(false);
        }
    }, [isOpen]);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && isOpen && !isSubmitting) {
                onClose();
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, isSubmitting, onClose]);

    if (!isOpen) return null;

    const trimmedReason = reason.trim();
    const requiresEmergencyOverride = isRequester && isSuperAdmin;
    const canSubmit =
        !isSubmitting &&
        trimmedReason.length >= 10 &&
        trimmedReason.length <= 1000 &&
        (!requiresEmergencyOverride || emergencyOverrideReason.trim().length >= 10);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!canSubmit) return;

        setIsSubmitting(true);
        setErrorMessage(null);

        router.post(
            `/admin/orders/${orderId}/adjustments/${adjustmentId}/reverse`,
            {
                reason: trimmedReason,
                emergency_override_reason: requiresEmergencyOverride ? emergencyOverrideReason.trim() : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    setErrorMessage(first || 'Failed to reverse adjustment.');
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-xs animate-in fade-in-0 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reverse-adjustment-title"
        >
            <div className="w-full max-w-lg my-8 p-6 bg-card border border-destructive/30 rounded-2xl shadow-xl space-y-5 text-foreground animate-in zoom-in-95 duration-200">
                {/* Modal Header */}
                <div className="flex items-start justify-between gap-4 border-b border-border pb-4">
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 bg-destructive/10 text-destructive rounded-xl">
                            <RotateCcw className="h-6 w-6" />
                        </div>
                        <div>
                            <h2 id="reverse-adjustment-title" className="text-lg font-bold text-foreground">
                                Reverse Applied Adjustment
                            </h2>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Order <span className="font-mono font-medium text-foreground">{orderNumber}</span> &bull;{' '}
                                <span className="font-mono font-medium text-foreground">{adjustmentNumber}</span>
                            </p>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={onClose}
                        disabled={isSubmitting}
                        className="h-8 w-8 text-muted-foreground hover:text-foreground"
                    >
                        <X className="h-4 w-4" />
                    </Button>
                </div>

                {errorMessage && (
                    <div className="p-3 bg-destructive/10 text-destructive text-xs rounded-lg border border-destructive/20 flex items-center gap-2">
                        <AlertTriangle className="h-4 w-4 shrink-0" />
                        <span>{errorMessage}</span>
                    </div>
                )}

                {/* Permanent Reversal Warning Banner */}
                <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3.5 flex items-start gap-2.5 text-amber-900 dark:text-amber-300 text-xs">
                    <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
                    <div className="space-y-1">
                        <span className="font-semibold block">Permanent Domain Transition Warning</span>
                        <p className="text-muted-foreground leading-relaxed">
                            This is an authoritative financial and operational transition, not a cosmetic undo. Reversal will permanently restore cancelled quantities, update order-item fulfillable totals, restore allocations, and increment the order version.
                        </p>
                    </div>
                </div>

                {/* Impact Preview Card */}
                <div className="space-y-3 bg-muted/40 border border-border/80 rounded-xl p-4">
                    <h3 className="text-xs font-semibold text-foreground uppercase tracking-wider flex items-center gap-1.5">
                        <Calculator className="h-3.5 w-3.5 text-primary" />
                        Restoration Impact Summary
                    </h3>

                    <div className="grid grid-cols-3 gap-2 pt-1 border-t border-border/60">
                        <div className="bg-card border border-border/60 rounded-lg p-2 text-center">
                            <span className="text-[10px] text-muted-foreground block">Restored Subtotal</span>
                            <span className="text-xs font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                +${projectedReductions.subtotal}
                            </span>
                        </div>
                        <div className="bg-card border border-border/60 rounded-lg p-2 text-center">
                            <span className="text-[10px] text-muted-foreground block">Restored Tax</span>
                            <span className="text-xs font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                +${projectedReductions.tax_total}
                            </span>
                        </div>
                        <div className="bg-card border border-border/60 rounded-lg p-2 text-center">
                            <span className="text-[10px] text-muted-foreground block">Restored Balance</span>
                            <span className="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                +${projectedReductions.grand_total}
                            </span>
                        </div>
                    </div>

                    {/* Operational Impact Badge */}
                    <div className="pt-2 border-t border-border/60 flex items-center gap-2 text-xs">
                        <PackageCheck className="h-4 w-4 text-primary shrink-0" />
                        <span className="text-muted-foreground">Allocation Impact:</span>
                        {hasAllocationImpact ? (
                            <span className="font-medium text-amber-600 dark:text-amber-400">
                                Case B &bull; Creates restoration allocation for {totalAffectedAllocationQuantity} units
                            </span>
                        ) : (
                            <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                Case A &bull; Restores to unallocated pool (zero allocation rows created)
                            </span>
                        )}
                    </div>
                </div>

                {/* Form Inputs */}
                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Mandatory Reversal Reason */}
                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between">
                            <label htmlFor="reversal-reason" className="text-xs font-semibold text-foreground">
                                Reversal Reason <span className="text-destructive">*</span>
                            </label>
                            <span
                                className={`text-[10px] ${
                                    trimmedReason.length < 10
                                        ? 'text-muted-foreground'
                                        : trimmedReason.length > 1000
                                        ? 'text-destructive font-semibold'
                                        : 'text-emerald-600 dark:text-emerald-400'
                                }`}
                            >
                                {trimmedReason.length}/1000 (min 10)
                            </span>
                        </div>
                        <textarea
                            id="reversal-reason"
                            rows={3}
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            disabled={isSubmitting}
                            placeholder="State the commercial or operational reason for reversing this applied adjustment..."
                            className="w-full rounded-lg border border-input bg-background px-3 py-2 text-xs placeholder:text-muted-foreground focus:outline-hidden focus:ring-2 focus:ring-destructive/30 focus:border-destructive transition-colors disabled:opacity-50"
                        />
                        {trimmedReason.length > 0 && trimmedReason.length < 10 && (
                            <p className="text-[10px] text-destructive">
                                Reason must be at least 10 characters ({10 - trimmedReason.length} more needed).
                            </p>
                        )}
                    </div>

                    {/* Maker-Checker Super Admin Emergency Override */}
                    {requiresEmergencyOverride && (
                        <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl space-y-2">
                            <div className="flex items-center gap-2 text-amber-800 dark:text-amber-300 text-xs font-semibold">
                                <ShieldAlert className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                <span>Super Admin Self-Reversal Emergency Override</span>
                            </div>
                            <p className="text-[11px] text-muted-foreground">
                                As the original requester of this adjustment, self-reversal violates standard maker-checker separation. As a Super Admin, you may proceed only by documenting an emergency override reason.
                            </p>
                            <div className="space-y-1">
                                <div className="flex items-center justify-between">
                                    <label htmlFor="override-reason" className="text-[11px] font-medium text-foreground">
                                        Emergency Override Reason <span className="text-destructive">*</span>
                                    </label>
                                    <span
                                        className={`text-[10px] ${
                                            emergencyOverrideReason.trim().length < 10
                                                ? 'text-muted-foreground'
                                                : 'text-emerald-600 dark:text-emerald-400'
                                        }`}
                                    >
                                        {emergencyOverrideReason.trim().length}/1000 (min 10)
                                    </span>
                                </div>
                                <textarea
                                    id="override-reason"
                                    rows={2}
                                    value={emergencyOverrideReason}
                                    onChange={(e) => setEmergencyOverrideReason(e.target.value)}
                                    disabled={isSubmitting}
                                    placeholder="Justify why this adjustment is being self-reversed without independent administrative review..."
                                    className="w-full rounded-lg border border-input bg-background px-3 py-2 text-xs placeholder:text-muted-foreground focus:outline-hidden focus:ring-2 focus:ring-destructive/30 focus:border-destructive transition-colors disabled:opacity-50"
                                />
                            </div>
                        </div>
                    )}

                    {/* Action Buttons */}
                    <div className="flex items-center justify-end gap-3 pt-3 border-t border-border">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onClose}
                            disabled={isSubmitting}
                            className="h-9 text-xs"
                        >
                            Cancel
                        </Button>

                        <Button
                            type="submit"
                            variant="destructive"
                            size="sm"
                            disabled={!canSubmit}
                            className="h-9 text-xs gap-1.5 shadow-xs font-medium"
                        >
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    <span>Reversing...</span>
                                </>
                            ) : (
                                <>
                                    <RotateCcw className="h-3.5 w-3.5" />
                                    <span>Confirm Reversal</span>
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
