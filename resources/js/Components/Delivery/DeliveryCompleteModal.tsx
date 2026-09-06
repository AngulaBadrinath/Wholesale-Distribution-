import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { 
    X, 
    CheckCircle2, 
    Camera, 
    Upload, 
    User, 
    FileText,
    AlertCircle
} from 'lucide-react';

interface DeliveryCompleteModalProps {
    isOpen: boolean;
    onClose: () => void;
    deliveryId: number;
    deliveryNumber: string;
    defaultRecipientName?: string;
}

export default function DeliveryCompleteModal({
    isOpen,
    onClose,
    deliveryId,
    deliveryNumber,
    defaultRecipientName = ''
}: DeliveryCompleteModalProps) {
    const [recipientName, setRecipientName] = useState(defaultRecipientName);
    const [podNotes, setPodNotes] = useState('');
    const [podEvidence, setPodEvidence] = useState<File | null>(null);
    const [recipientSignature, setRecipientSignature] = useState<File | null>(null);
    const [evidencePreview, setEvidencePreview] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    if (!isOpen) return null;

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setPodEvidence(file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setEvidencePreview(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!recipientName.trim()) {
            setErrorMessage('Recipient name is strictly required.');
            return;
        }

        setIsSubmitting(true);
        setErrorMessage(null);

        const formData = new FormData();
        formData.append('recipient_name', recipientName.trim());
        if (podNotes.trim()) formData.append('pod_notes', podNotes.trim());
        if (podEvidence) formData.append('pod_evidence', podEvidence);
        if (recipientSignature) formData.append('recipient_signature', recipientSignature);

        router.post(`/delivery/${deliveryId}/complete`, formData, {
            forceFormData: true,
            onSuccess: () => {
                setIsSubmitting(false);
                onClose();
            },
            onError: (errors) => {
                setIsSubmitting(false);
                setErrorMessage(Object.values(errors)[0] as string || 'Failed to complete delivery.');
            },
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div className="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="p-4 border-b border-slate-800 flex items-center justify-between">
                    <div className="flex items-center gap-2.5">
                        <div className="w-9 h-9 rounded-xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                            <CheckCircle2 className="w-5 h-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-white tracking-tight">Complete Delivery</h2>
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

                    {/* Recipient Name */}
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Recipient Name <span className="text-rose-400">*</span>
                        </label>
                        <div className="relative">
                            <User className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="text"
                                value={recipientName}
                                onChange={(e) => setRecipientName(e.target.value)}
                                placeholder="Full name of person receiving goods"
                                className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded-xl pl-9 pr-3 py-2.5 text-sm text-white placeholder-slate-500 outline-hidden transition-all"
                                required
                            />
                        </div>
                    </div>

                    {/* POD Photo Upload */}
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Proof of Delivery Photo (Optional)
                        </label>
                        <div className="space-y-2">
                            <label className="flex flex-col items-center justify-center border-2 border-dashed border-slate-800 hover:border-slate-700 rounded-xl p-4 cursor-pointer bg-slate-950/50 transition-colors">
                                <Camera className="w-6 h-6 text-slate-400 mb-1" />
                                <span className="text-xs font-medium text-slate-300">Take Photo or Upload POD</span>
                                <span className="text-[10px] text-slate-500 mt-0.5">JPEG or PNG (Max 5MB)</span>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png"
                                    capture="environment"
                                    onChange={handleFileChange}
                                    className="hidden"
                                />
                            </label>

                            {evidencePreview && (
                                <div className="relative rounded-xl overflow-hidden border border-slate-800 h-32 bg-slate-950">
                                    <img 
                                        src={evidencePreview} 
                                        alt="POD Preview" 
                                        className="w-full h-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => { setPodEvidence(null); setEvidencePreview(null); }}
                                        className="absolute top-2 right-2 bg-slate-900/80 hover:bg-slate-900 text-white rounded-lg p-1"
                                    >
                                        <X className="w-4 h-4" />
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* POD Notes */}
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Delivery Notes (Optional)
                        </label>
                        <textarea
                            rows={2}
                            value={podNotes}
                            onChange={(e) => setPodNotes(e.target.value)}
                            placeholder="e.g., Left at front desk with receptionist"
                            className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded-xl p-3 text-sm text-white placeholder-slate-500 outline-hidden transition-all resize-none"
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
                            disabled={isSubmitting || !recipientName.trim()}
                            className="flex-2 min-h-[44px] rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center justify-center gap-2 active:scale-98 transition-all disabled:opacity-50"
                        >
                            <CheckCircle2 className="w-4 h-4" />
                            <span>{isSubmitting ? 'Completing...' : 'Confirm Delivery'}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
