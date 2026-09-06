import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { 
    X, 
    RotateCcw, 
    Warehouse, 
    FileText,
    AlertCircle
} from 'lucide-react';

interface DeliveryReturnModalProps {
    isOpen: boolean;
    onClose: () => void;
    deliveryId: number;
    deliveryNumber: string;
}

export default function DeliveryReturnModal({
    isOpen,
    onClose,
    deliveryId,
    deliveryNumber,
}: DeliveryReturnModalProps) {
    const [notes, setNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    if (!isOpen) return null;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setErrorMessage(null);

        router.post(`/delivery/${deliveryId}/return-to-warehouse`, {
            notes: notes.trim() || undefined,
        }, {
            onSuccess: () => {
                setIsSubmitting(false);
                onClose();
            },
            onError: (errors) => {
                setIsSubmitting(false);
                setErrorMessage(Object.values(errors)[0] as string || 'Failed to return shipment to warehouse.');
            },
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div className="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="p-4 border-b border-slate-800 flex items-center justify-between">
                    <div className="flex items-center gap-2.5">
                        <div className="w-9 h-9 rounded-xl bg-purple-500/15 border border-purple-500/30 flex items-center justify-center text-purple-400">
                            <RotateCcw className="w-5 h-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-white tracking-tight">Return to Warehouse</h2>
                            <p className="text-xs font-mono text-slate-400">#{deliveryNumber}</p>
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-colors"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="p-4 space-y-4 overflow-y-auto">
                    {errorMessage && (
                        <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-medium flex items-center gap-2">
                            <AlertCircle className="w-4 h-4 shrink-0 text-rose-400" />
                            <span>{errorMessage}</span>
                        </div>
                    )}

                    <div className="p-3 rounded-xl bg-purple-500/10 border border-purple-500/20 text-xs text-purple-200">
                        <p className="font-semibold text-purple-300 mb-1 flex items-center gap-1.5">
                            <Warehouse className="w-4 h-4" />
                            Custody Handover to Warehouse
                        </p>
                        <p>
                            Returning goods will restore physical custody back to warehouse inventory. Dispatched line quantities will reset to 0, maintaining reserved balances safely.
                        </p>
                    </div>

                    {/* Return Notes */}
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Handover Notes (Optional)
                        </label>
                        <textarea
                            rows={3}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="e.g., Goods safely returned to Dock 4, verified by receiving supervisor..."
                            className="w-full bg-slate-950 border border-slate-800 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 rounded-xl p-3 text-sm text-white placeholder-slate-500 outline-hidden transition-all resize-none"
                        />
                    </div>

                    {/* Submit Bar */}
                    <div className="pt-2 flex gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex-1 min-h-[44px] rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className="flex-2 min-h-[44px] rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs flex items-center justify-center gap-2 active:scale-98 transition-all disabled:opacity-50"
                        >
                            <RotateCcw className="w-4 h-4" />
                            <span>{isSubmitting ? 'Processing...' : 'Confirm Return'}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
