import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { 
    X, 
    AlertTriangle, 
    FileText,
    AlertCircle
} from 'lucide-react';

interface DeliveryFailureModalProps {
    isOpen: boolean;
    onClose: () => void;
    deliveryId: number;
    deliveryNumber: string;
}

const FAILURE_REASONS = [
    { value: 'CUSTOMER_UNAVAILABLE', label: 'Customer Unavailable' },
    { value: 'ADDRESS_NOT_FOUND', label: 'Address Not Found / Invalid' },
    { value: 'CUSTOMER_REFUSED', label: 'Customer Refused Delivery' },
    { value: 'BUSINESS_CLOSED', label: 'Business / Store Closed' },
    { value: 'ACCESS_RESTRICTED', label: 'Access Restricted / Gated' },
    { value: 'VEHICLE_BREAKDOWN', label: 'Vehicle Breakdown / Transit Issue' },
    { value: 'WEATHER_EMERGENCY', label: 'Weather Emergency / Road Closure' },
    { value: 'OTHER', label: 'Other Operational Issue' },
];

export default function DeliveryFailureModal({
    isOpen,
    onClose,
    deliveryId,
    deliveryNumber,
}: DeliveryFailureModalProps) {
    const [failureReason, setFailureReason] = useState('CUSTOMER_UNAVAILABLE');
    const [driverNotes, setDriverNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    if (!isOpen) return null;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!failureReason) {
            setErrorMessage('Please select a failure reason.');
            return;
        }
        if (!driverNotes.trim() || driverNotes.trim().length < 5) {
            setErrorMessage('Please provide detailed driver explanation notes (at least 5 characters).');
            return;
        }

        setIsSubmitting(true);
        setErrorMessage(null);

        router.post(`/delivery/${deliveryId}/fail`, {
            failure_reason: failureReason,
            driver_notes: driverNotes.trim(),
        }, {
            onSuccess: () => {
                setIsSubmitting(false);
                onClose();
            },
            onError: (errors) => {
                setIsSubmitting(false);
                setErrorMessage(Object.values(errors)[0] as string || 'Failed to record delivery exception.');
            },
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div className="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="p-4 border-b border-slate-800 flex items-center justify-between">
                    <div className="flex items-center gap-2.5">
                        <div className="w-9 h-9 rounded-xl bg-rose-500/15 border border-rose-500/30 flex items-center justify-center text-rose-400">
                            <AlertTriangle className="w-5 h-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-white tracking-tight">Report Delivery Issue</h2>
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

                    {/* Failure Reason */}
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Reason for Exception <span className="text-rose-400">*</span>
                        </label>
                        <select
                            value={failureReason}
                            onChange={(e) => setFailureReason(e.target.value)}
                            className="w-full bg-slate-950 border border-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 rounded-xl px-3 py-2.5 text-sm text-white outline-hidden transition-all"
                            required
                        >
                            {FAILURE_REASONS.map((r) => (
                                <option key={r.value} value={r.value} className="bg-slate-900 text-white">
                                    {r.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Driver Explanation Notes */}
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Driver Explanation Notes <span className="text-rose-400">*</span>
                        </label>
                        <textarea
                            rows={4}
                            value={driverNotes}
                            onChange={(e) => setDriverNotes(e.target.value)}
                            placeholder="Describe why delivery could not be completed (e.g., Gate locked, customer not responding to calls)..."
                            className="w-full bg-slate-950 border border-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 rounded-xl p-3 text-sm text-white placeholder-slate-500 outline-hidden transition-all resize-none"
                            required
                        />
                        <p className="text-[11px] text-slate-400 mt-1">
                            Minimum 5 characters. This explanation will be audited by dispatch operations.
                        </p>
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
                            disabled={isSubmitting || !driverNotes.trim() || driverNotes.trim().length < 5}
                            className="flex-2 min-h-[44px] rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs flex items-center justify-center gap-2 active:scale-98 transition-all disabled:opacity-50"
                        >
                            <AlertTriangle className="w-4 h-4" />
                            <span>{isSubmitting ? 'Reporting...' : 'Record Failure'}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
