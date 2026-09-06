import React, { useState, useEffect } from 'react';
import {
    X,
    ZoomIn,
    ZoomOut,
    RotateCw,
    Download,
    ExternalLink,
    AlertCircle,
    Loader2,
    FileImage,
    ShieldCheck,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';

export interface PaymentEvidencePreviewModalProps {
    isOpen: boolean;
    onClose: () => void;
    payment: {
        id: number;
        payment_number: string;
        payment_method: string;
        amount: string | number;
        cheque_number?: string | null;
        bank_name?: string | null;
        money_order_number?: string | null;
        issuer_name?: string | null;
        payment_date: string;
        customer_name?: string;
        evidence_original_name?: string | null;
    } | null;
}

interface EvidenceUrlResponse {
    url: string;
    expires_at: string;
    mime_type: string;
    original_name: string;
    size_bytes?: number;
}

export function PaymentEvidencePreviewModal({
    isOpen,
    onClose,
    payment,
}: PaymentEvidencePreviewModalProps) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [evidenceData, setEvidenceData] = useState<EvidenceUrlResponse | null>(null);
    const [zoomLevel, setZoomLevel] = useState<number>(1);
    const [rotation, setRotation] = useState<number>(0);

    useEffect(() => {
        if (!isOpen || !payment) {
            setEvidenceData(null);
            setError(null);
            setZoomLevel(1);
            setRotation(0);
            return;
        }

        const fetchEvidenceUrl = async () => {
            setLoading(true);
            setError(null);

            try {
                const response = await fetch(`/admin/payments/${payment.id}/evidence-url`, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'Failed to retrieve secure evidence preview URL.');
                }

                const data: EvidenceUrlResponse = await response.json();
                setEvidenceData(data);
            } catch (err: any) {
                setError(err.message || 'Error loading evidence preview.');
            } finally {
                setLoading(false);
            }
        };

        fetchEvidenceUrl();
    }, [isOpen, payment]);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose]);

    if (!isOpen || !payment) return null;

    const instrumentDetail =
        payment.payment_method === 'CHEQUE'
            ? `Cheque #${payment.cheque_number || 'N/A'} • ${payment.bank_name || 'Bank'}`
            : payment.payment_method === 'MONEY_ORDER'
            ? `Money Order #${payment.money_order_number || 'N/A'} • ${payment.issuer_name || 'Issuer'}`
            : 'Payment Receipt';

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
            aria-labelledby="evidence-modal-title"
        >
            <div className="relative w-full max-w-4xl bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 flex flex-col max-h-[90vh] overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 id="evidence-modal-title" className="text-lg font-bold text-slate-900 dark:text-slate-100">
                                Payment Instrument Evidence
                            </h2>
                            <Badge variant="outline" className="gap-1 text-xs">
                                <ShieldCheck className="h-3 w-3 text-emerald-600 dark:text-emerald-400" /> Secure 15m Token
                            </Badge>
                        </div>
                        <div className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {payment.payment_number} • {payment.customer_name || 'Customer'} • {instrumentDetail}
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={onClose}
                        className="h-10 w-10 min-h-[44px] min-w-[44px] rounded-full text-slate-500 hover:text-slate-900 dark:hover:text-slate-100"
                        aria-label="Close evidence preview modal"
                    >
                        <X className="h-5 w-5" />
                    </Button>
                </div>

                {/* Toolbar */}
                {evidenceData && !loading && (
                    <div className="flex flex-wrap items-center justify-between px-6 py-2.5 bg-slate-100/60 dark:bg-slate-800/40 border-b border-slate-200 dark:border-slate-800 text-xs">
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setZoomLevel((z) => Math.max(0.5, z - 0.25))}
                                disabled={zoomLevel <= 0.5}
                                className="h-8 gap-1 min-h-[36px]"
                            >
                                <ZoomOut className="h-3.5 w-3.5" /> Zoom Out
                            </Button>
                            <span className="font-mono text-slate-600 dark:text-slate-400 min-w-[40px] text-center">
                                {Math.round(zoomLevel * 100)}%
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setZoomLevel((z) => Math.min(3, z + 0.25))}
                                disabled={zoomLevel >= 3}
                                className="h-8 gap-1 min-h-[36px]"
                            >
                                <ZoomIn className="h-3.5 w-3.5" /> Zoom In
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setRotation((r) => (r + 90) % 360)}
                                className="h-8 gap-1 min-h-[36px]"
                            >
                                <RotateCw className="h-3.5 w-3.5" /> Rotate
                            </Button>
                        </div>

                        <div className="flex items-center gap-2 mt-2 sm:mt-0">
                            <a
                                href={evidenceData.url}
                                download={evidenceData.original_name || 'evidence.jpg'}
                                className="inline-flex items-center justify-center rounded-md text-xs font-medium border border-input bg-background hover:bg-accent hover:text-accent-foreground h-8 px-3 gap-1 min-h-[36px]"
                            >
                                <Download className="h-3.5 w-3.5" /> Download
                            </a>
                            <a
                                href={evidenceData.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center justify-center rounded-md text-xs font-medium border border-input bg-background hover:bg-accent hover:text-accent-foreground h-8 px-3 gap-1 min-h-[36px]"
                            >
                                <ExternalLink className="h-3.5 w-3.5" /> Open Fullscreen
                            </a>
                        </div>
                    </div>
                )}

                {/* Viewport */}
                <div className="relative flex-1 min-h-[360px] max-h-[60vh] overflow-auto p-4 bg-slate-900/90 flex items-center justify-center">
                    {loading && (
                        <div className="flex flex-col items-center gap-3 text-slate-300">
                            <Loader2 className="h-8 w-8 animate-spin text-indigo-400" />
                            <p className="text-sm font-medium">Generating secure evidence preview token...</p>
                        </div>
                    )}

                    {error && !loading && (
                        <div className="flex flex-col items-center gap-3 p-6 text-center max-w-md bg-slate-800/80 rounded-lg border border-red-500/30 text-red-300">
                            <AlertCircle className="h-8 w-8 text-red-400" />
                            <p className="text-sm font-semibold">{error}</p>
                            <p className="text-xs text-slate-400">
                                Verify that the private evidence file exists in storage and you hold the required permissions.
                            </p>
                        </div>
                    )}

                    {evidenceData && !loading && (
                        <div
                            className="transition-transform duration-150 ease-out max-w-full max-h-full flex items-center justify-center"
                            style={{
                                transform: `scale(${zoomLevel}) rotate(${rotation}deg)`,
                            }}
                        >
                            <img
                                src={evidenceData.url}
                                alt={`Payment evidence for ${payment.payment_number}`}
                                className="max-w-full max-h-[55vh] object-contain rounded shadow-lg border border-slate-700 select-none"
                            />
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-between px-6 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-xs text-slate-500">
                    <div className="flex items-center gap-2">
                        <FileImage className="h-4 w-4" />
                        <span>{payment.evidence_original_name || 'cheque_scan.jpg'}</span>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={onClose}
                        className="h-8 min-h-[36px]"
                    >
                        Close Preview
                    </Button>
                </div>
            </div>
        </div>
    );
}
