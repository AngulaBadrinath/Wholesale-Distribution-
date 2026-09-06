import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { 
    X, 
    Calendar, 
    Clock, 
    FileText,
    AlertCircle
} from 'lucide-react';

interface DeliveryRescheduleModalProps {
    isOpen: boolean;
    onClose: () => void;
    deliveryId: number;
    deliveryNumber: string;
    currentScheduledDate?: string;
}

export default function DeliveryRescheduleModal({
    isOpen,
    onClose,
    deliveryId,
    deliveryNumber,
    currentScheduledDate = '',
}: DeliveryRescheduleModalProps) {
    const todayStr = new Date().toISOString().split('T')[0];
    const [scheduledDate, setScheduledDate] = useState(currentScheduledDate || todayStr);
    const [deliveryWindow, setDeliveryWindow] = useState('Morning (09:00 - 13:00)');
    const [notes, setNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    if (!isOpen) return null;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!scheduledDate) {
            setErrorMessage('Please select a rescheduled delivery date.');
            return;
        }

        setIsSubmitting(true);
        setErrorMessage(null);

        router.post(`/delivery/${deliveryId}/reschedule`, {
            scheduled_date: scheduledDate,
            delivery_window: deliveryWindow,
            notes: notes.trim() || undefined,
        }, {
            onSuccess: () => {
                setIsSubmitting(false);
                onClose();
            },
            onError: (errors) => {
                setIsSubmitting(false);
                setErrorMessage(Object.values(errors)[0] as string || 'Failed to reschedule delivery.');
            },
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div className="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="p-4 border-b border-slate-800 flex items-center justify-between">
                    <div className="flex items-center gap-2.5">
                        <div className="w-9 h-9 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400">
                            <Calendar className="w-5 h-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-white tracking-tight">Reschedule Delivery</h2>
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

                    {/* New Scheduled Date */}
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            New Scheduled Date <span className="text-amber-400">*</span>
                        </label>
                        <div className="relative">
                            <Calendar className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="date"
                                min={todayStr}
                                value={scheduledDate}
                                onChange={(e) => setScheduledDate(e.target.value)}
                                className="w-full bg-slate-950 border border-slate-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-9 pr-3 py-2.5 text-sm text-white outline-hidden transition-all"
                                required
                            />
                        </div>
                    </div>

                    {/* Delivery Window */}
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Delivery Window
                        </label>
                        <div className="relative">
                            <Clock className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <select
                                value={deliveryWindow}
                                onChange={(e) => setDeliveryWindow(e.target.value)}
                                className="w-full bg-slate-950 border border-slate-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-9 pr-3 py-2.5 text-sm text-white outline-hidden transition-all"
                            >
                                <option value="Morning (09:00 - 13:00)">Morning (09:00 - 13:00)</option>
                                <option value="Afternoon (13:00 - 17:00)">Afternoon (13:00 - 17:00)</option>
                                <option value="Evening (17:00 - 20:00)">Evening (17:00 - 20:00)</option>
                                <option value="All Day (09:00 - 18:00)">All Day (09:00 - 18:00)</option>
                            </select>
                        </div>
                    </div>

                    {/* Reschedule Notes */}
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Reschedule Reason / Notes (Optional)
                        </label>
                        <textarea
                            rows={3}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="e.g., Customer requested next-day morning delivery slot..."
                            className="w-full bg-slate-950 border border-slate-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl p-3 text-sm text-white placeholder-slate-500 outline-hidden transition-all resize-none"
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
                            disabled={isSubmitting || !scheduledDate}
                            className="flex-2 min-h-[44px] rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs flex items-center justify-center gap-2 active:scale-98 transition-all disabled:opacity-50"
                        >
                            <Calendar className="w-4 h-4" />
                            <span>{isSubmitting ? 'Rescheduling...' : 'Confirm Reschedule'}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
