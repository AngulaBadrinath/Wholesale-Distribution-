import React, { useEffect } from 'react';
import { ShieldCheck, AlertTriangle, Loader2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { AdminOrderReviewData } from '@/types/order';

interface ApproveOrderModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    isProcessing: boolean;
    orderNumber: string;
    grandTotal: string;
    currency: string;
    warnings: AdminOrderReviewData['warnings'];
}

export default function ApproveOrderModal({
    isOpen,
    onClose,
    onConfirm,
    isProcessing,
    orderNumber,
    grandTotal,
    currency,
    warnings,
}: ApproveOrderModalProps) {
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && isOpen && !isProcessing) {
                onClose();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, isProcessing, onClose]);

    if (!isOpen) return null;

    const softWarnings = warnings.filter((w) => w.severity !== 'blocker');

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="approve-modal-title"
            aria-describedby="approve-modal-description"
        >
            <div className="w-full max-w-lg bg-card border border-border rounded-lg shadow-xl overflow-hidden animate-in fade-in-50 zoom-in-95">
                {/* Modal Header */}
                <div className="px-6 py-4 border-b border-border bg-emerald-500/5 flex items-center justify-between">
                    <div className="flex items-center gap-2.5">
                        <div className="p-2 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                            <ShieldCheck className="h-5 w-5" />
                        </div>
                        <div>
                            <h3 id="approve-modal-title" className="text-base font-bold text-foreground">
                                Approve Order {orderNumber}
                            </h3>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Total: {currency} ${Number(grandTotal).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Modal Body */}
                <div className="p-6 space-y-4 text-xs">
                    <div id="approve-modal-description" className="p-3.5 rounded-md bg-muted/40 border text-muted-foreground leading-relaxed space-y-2">
                        <p className="font-semibold text-foreground text-sm">
                            Authoritative Approval & Order-Level Reservation
                        </p>
                        <p>
                            Approving this order authoritatively commits the transaction and establishes <strong>order-level quantity reservation</strong> across all line items.
                        </p>
                        <p className="text-[11px] text-muted-foreground">
                            Note: This establishes the order-level reservation state (<code className="font-mono text-[10px] bg-muted px-1 py-0.5 rounded">fulfillment_status = RESERVED</code>). Downstream physical warehouse allocations will process against these reserved quantities.
                        </p>
                    </div>

                    {/* Soft Warnings Summary (Non-Blockers) */}
                    {softWarnings.length > 0 && (
                        <div className="p-3.5 rounded-md bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200 space-y-1.5">
                            <div className="flex items-center gap-1.5 font-semibold text-xs text-amber-800 dark:text-amber-300">
                                <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <span>Operational Warnings Evaluated</span>
                            </div>
                            <ul className="list-disc list-inside space-y-1 text-[11px] text-amber-700 dark:text-amber-300/90 pl-1">
                                {softWarnings.map((w) => (
                                    <li key={w.code}>
                                        <strong>{w.title}:</strong> {w.description}
                                    </li>
                                ))}
                            </ul>
                            <p className="text-[10px] text-amber-600 dark:text-amber-400 pt-1">
                                Your administrative decision authoritatively acknowledges and approves these conditions.
                            </p>
                        </div>
                    )}
                </div>

                {/* Modal Actions */}
                <div className="px-6 py-3.5 bg-muted/40 border-t border-border flex items-center justify-end gap-2.5">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={isProcessing}
                        onClick={onClose}
                        className="text-xs h-9 px-4"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="default"
                        size="sm"
                        disabled={isProcessing}
                        onClick={onConfirm}
                        className="text-xs h-9 px-4 gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold"
                    >
                        {isProcessing ? (
                            <>
                                <Loader2 className="h-4 w-4 animate-spin" />
                                <span>Approving & Reserving...</span>
                            </>
                        ) : (
                            <>
                                <ShieldCheck className="h-4 w-4" />
                                <span>Confirm & Approve Order</span>
                            </>
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
