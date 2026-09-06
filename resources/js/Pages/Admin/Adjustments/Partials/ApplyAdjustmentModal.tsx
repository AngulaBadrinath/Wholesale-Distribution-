import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import {
    Zap,
    AlertTriangle,
    CheckCircle2,
    X,
    Loader2,
    Calculator,
    PackageCheck,
} from 'lucide-react';

interface ApplyAdjustmentModalProps {
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
    onClose: () => void;
}

export default function ApplyAdjustmentModal({
    isOpen,
    orderId,
    orderNumber,
    adjustmentId,
    adjustmentNumber,
    hasAllocationImpact,
    totalAffectedAllocationQuantity,
    projectedReductions,
    onClose,
}: ApplyAdjustmentModalProps) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    useEffect(() => {
        if (isOpen) {
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

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isSubmitting) return;

        setIsSubmitting(true);
        setErrorMessage(null);

        router.post(
            `/admin/orders/${orderId}/adjustments/${adjustmentId}/apply`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    setErrorMessage(first || 'Failed to apply adjustment.');
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-in fade-in duration-200">
            <div
                className="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-2xl animate-in zoom-in-95 duration-200"
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-apply-title"
                aria-describedby="modal-apply-description"
            >
                {/* Close Button */}
                <button
                    type="button"
                    onClick={onClose}
                    disabled={isSubmitting}
                    className="absolute right-4 top-4 rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors disabled:opacity-50"
                    aria-label="Close dialog"
                >
                    <X className="h-4 w-4" />
                </button>

                {/* Header */}
                <div className="flex items-start gap-3.5 pb-4 border-b border-border">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400 border border-teal-500/20">
                        <Zap className="h-5 w-5" />
                    </div>
                    <div className="space-y-0.5 pr-6">
                        <h2 id="modal-apply-title" className="text-base font-bold text-foreground">
                            Apply Approved Adjustment
                        </h2>
                        <p id="modal-apply-description" className="text-xs text-muted-foreground">
                            Order <span className="font-medium text-foreground">{orderNumber}</span> &bull; Adjustment{' '}
                            <span className="font-mono font-medium text-foreground">{adjustmentNumber}</span>
                        </p>
                    </div>
                </div>

                {errorMessage && (
                    <div className="mt-4 rounded-lg bg-destructive/10 border border-destructive/20 p-3 text-xs text-destructive flex items-center gap-2">
                        <AlertTriangle className="h-4 w-4 shrink-0" />
                        <span>{errorMessage}</span>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                    {/* Financial Summary Card */}
                    <div className="rounded-lg border border-border bg-muted/30 p-3.5 space-y-2.5">
                        <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            <Calculator className="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" />
                            <span>Commercial Reductions to Apply</span>
                        </div>
                        <div className="grid grid-cols-3 gap-2 pt-1 text-center">
                            <div className="rounded-md bg-card border border-border p-2">
                                <div className="text-[10px] text-muted-foreground">Subtotal</div>
                                <div className="text-xs font-mono font-bold text-foreground mt-0.5">
                                    -${projectedReductions.subtotal}
                                </div>
                            </div>
                            <div className="rounded-md bg-card border border-border p-2">
                                <div className="text-[10px] text-muted-foreground">Tax Total</div>
                                <div className="text-xs font-mono font-bold text-foreground mt-0.5">
                                    -${projectedReductions.tax_total}
                                </div>
                            </div>
                            <div className="rounded-md bg-card border border-border p-2">
                                <div className="text-[10px] text-muted-foreground">Grand Total</div>
                                <div className="text-xs font-mono font-bold text-teal-700 dark:text-teal-400 mt-0.5">
                                    -${projectedReductions.grand_total}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Allocation Impact Box */}
                    {hasAllocationImpact ? (
                        <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3.5 space-y-1.5 text-xs text-amber-800 dark:text-amber-300">
                            <div className="flex items-center gap-1.5 font-semibold text-amber-900 dark:text-amber-200">
                                <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400 shrink-0" />
                                <span>Case B: Active Allocations Will Be Released</span>
                            </div>
                            <p className="text-[11px] leading-relaxed text-amber-800/90 dark:text-amber-300/90">
                                Applying this adjustment will automatically release{' '}
                                <strong className="font-semibold text-foreground font-mono">
                                    {totalAffectedAllocationQuantity}
                                </strong>{' '}
                                allocated unit(s) from eligible unpicked warehouse allocations.
                            </p>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3.5 space-y-1 text-xs text-emerald-800 dark:text-emerald-300">
                            <div className="flex items-center gap-1.5 font-semibold text-emerald-900 dark:text-emerald-200">
                                <CheckCircle2 className="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                <span>Case A: Unallocated Units Only</span>
                            </div>
                            <p className="text-[11px] leading-relaxed text-emerald-800/90 dark:text-emerald-300/90">
                                This adjustment affects only unallocated order capacity. Existing active warehouse allocations will remain completely untouched.
                            </p>
                        </div>
                    )}

                    {/* Permanent Consequence Notice */}
                    <div className="rounded-lg border border-border bg-card p-3 text-[11px] text-muted-foreground flex items-start gap-2">
                        <PackageCheck className="h-4 w-4 text-primary shrink-0 mt-0.5" />
                        <div>
                            <span className="font-semibold text-foreground">Permanent Operational Mutation:</span> This action is transactional and irrevocable. It updates order quantities, recalculates authoritative financials, and marks the adjustment as <strong>APPLIED</strong>.
                        </div>
                    </div>

                    {/* Form Actions */}
                    <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-border">
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
                            size="sm"
                            disabled={isSubmitting}
                            className="h-9 text-xs gap-1.5 bg-teal-600 hover:bg-teal-700 text-white dark:bg-teal-600 dark:hover:bg-teal-500"
                        >
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    <span>Applying Adjustment...</span>
                                </>
                            ) : (
                                <>
                                    <Zap className="h-3.5 w-3.5" />
                                    <span>Confirm & Apply Adjustment</span>
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
