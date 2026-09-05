import React, { useState, useEffect } from 'react';
import { AlertOctagon, Loader2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';

interface RejectOrderModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: (reason: string) => void;
    isProcessing: boolean;
    orderNumber: string;
    errorMessage?: string;
}

export default function RejectOrderModal({
    isOpen,
    onClose,
    onConfirm,
    isProcessing,
    orderNumber,
    errorMessage,
}: RejectOrderModalProps) {
    const [reason, setReason] = useState('');
    const [touched, setTouched] = useState(false);

    useEffect(() => {
        if (isOpen) {
            setReason('');
            setTouched(false);
        }
    }, [isOpen]);

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

    const trimmedReason = reason.trim();
    const isValidLength = trimmedReason.length >= 5 && trimmedReason.length <= 1000;
    const isTooShort = touched && trimmedReason.length > 0 && trimmedReason.length < 5;
    const isEmptyAndTouched = touched && trimmedReason.length === 0;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setTouched(true);
        if (isValidLength && !isProcessing) {
            onConfirm(trimmedReason);
        }
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reject-modal-title"
            aria-describedby="reject-modal-description"
        >
            <div className="w-full max-w-lg bg-card border border-border rounded-lg shadow-xl overflow-hidden animate-in fade-in-50 zoom-in-95">
                <form onSubmit={handleSubmit}>
                    {/* Modal Header */}
                    <div className="px-6 py-4 border-b border-border bg-destructive/5 flex items-center justify-between">
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 rounded-full bg-destructive/10 text-destructive">
                                <AlertOctagon className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 id="reject-modal-title" className="text-base font-bold text-foreground">
                                    Reject Order {orderNumber}
                                </h3>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Document operational reason for rejecting this order.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Modal Body */}
                    <div className="p-6 space-y-4 text-xs">
                        <div id="reject-modal-description" className="p-3 rounded-md bg-muted/40 border text-muted-foreground leading-relaxed">
                            <p>
                                Rejecting this order transitions its status to <strong>REJECTED</strong>. Line items and historical records are preserved immutably for auditability, but the order cannot be fulfilled.
                            </p>
                        </div>

                        {errorMessage && (
                            <div className="p-3 rounded-md bg-destructive/10 border border-destructive/20 text-destructive font-medium text-xs">
                                {errorMessage}
                            </div>
                        )}

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="rejection-reason" className="text-xs font-semibold text-foreground">
                                    Rejection Reason <span className="text-destructive">*</span>
                                </Label>
                                <span className={`text-[11px] font-mono ${trimmedReason.length > 1000 ? 'text-destructive font-bold' : 'text-muted-foreground'}`}>
                                    {trimmedReason.length} / 1000
                                </span>
                            </div>

                            <textarea
                                id="rejection-reason"
                                rows={4}
                                disabled={isProcessing}
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                onBlur={() => setTouched(true)}
                                placeholder="Explain why this order is being rejected (e.g., Unresolved commercial discrepancy, unfulfillable product specifications, duplicate submission)..."
                                className={`w-full rounded-md border bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:outline-hidden focus:ring-2 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 transition-colors ${
                                    isTooShort || isEmptyAndTouched
                                        ? 'border-destructive focus:ring-destructive'
                                        : 'border-input'
                                }`}
                                aria-invalid={isTooShort || isEmptyAndTouched}
                                aria-describedby="reason-hint"
                            />

                            <div id="reason-hint" className="flex items-center justify-between text-[11px]">
                                {isTooShort && (
                                    <span className="text-destructive font-medium">
                                        Rejection reason must be at least 5 characters.
                                    </span>
                                )}
                                {isEmptyAndTouched && (
                                    <span className="text-destructive font-medium">
                                        A documented rejection reason is mandatory.
                                    </span>
                                )}
                                {!isTooShort && !isEmptyAndTouched && (
                                    <span className="text-muted-foreground">
                                        Minimum 5 characters required. Whitespace is automatically trimmed.
                                    </span>
                                )}
                            </div>
                        </div>
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
                            type="submit"
                            variant="destructive"
                            size="sm"
                            disabled={!isValidLength || isProcessing}
                            className="text-xs h-9 px-4 gap-2 font-semibold"
                        >
                            {isProcessing ? (
                                <>
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    <span>Rejecting Order...</span>
                                </>
                            ) : (
                                <>
                                    <AlertOctagon className="h-4 w-4" />
                                    <span>Confirm Rejection</span>
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
