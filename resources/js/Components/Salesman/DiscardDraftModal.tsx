import React from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { AlertTriangle, Loader2 } from 'lucide-react';

interface DiscardDraftModalProps {
    isOpen: boolean;
    draftId: number | null;
    customerName?: string;
    onClose: () => void;
}

export function DiscardDraftModal({
    isOpen,
    draftId,
    customerName,
    onClose,
}: DiscardDraftModalProps) {
    const [isDiscarding, setIsDiscarding] = React.useState(false);

    if (!isOpen || !draftId) return null;

    const handleConfirmDiscard = () => {
        setIsDiscarding(true);
        router.delete(`/salesman/orders/drafts/${draftId}`, {
            onFinish: () => {
                setIsDiscarding(false);
                onClose();
            },
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm animate-in fade-in-0">
            <div className="w-full max-w-md p-6 bg-card border rounded-xl shadow-lg space-y-4">
                <div className="flex items-center gap-3 text-destructive">
                    <div className="p-2 bg-destructive/10 rounded-full">
                        <AlertTriangle className="h-5 w-5" />
                    </div>
                    <h3 className="text-lg font-semibold text-foreground">Discard Draft Order?</h3>
                </div>

                <p className="text-sm text-muted-foreground leading-relaxed">
                    Are you sure you want to permanently discard this draft order
                    {customerName ? ` for ${customerName}` : ''}? This action cannot be undone.
                </p>

                <div className="flex items-center justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={isDiscarding}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleConfirmDiscard}
                        disabled={isDiscarding}
                    >
                        {isDiscarding ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Discarding...
                            </>
                        ) : (
                            'Discard Draft'
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
