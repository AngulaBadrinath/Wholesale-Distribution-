import React, { useEffect, useRef } from 'react';
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
    const dialogRef = useRef<HTMLDivElement>(null);
    const previousActiveElement = useRef<HTMLElement | null>(null);

    useEffect(() => {
        if (isOpen) {
            previousActiveElement.current = document.activeElement as HTMLElement | null;

            // Handle Escape key and focus trapping
            const handleKeyDown = (e: KeyboardEvent) => {
                if (e.key === 'Escape' && !isDiscarding) {
                    e.preventDefault();
                    onClose();
                    return;
                }

                if (e.key === 'Tab' && dialogRef.current) {
                    const focusableElements = dialogRef.current.querySelectorAll<HTMLElement>(
                        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                    );
                    const firstElement = focusableElements[0];
                    const lastElement = focusableElements[focusableElements.length - 1];

                    if (!firstElement) return;

                    if (e.shiftKey) {
                        if (document.activeElement === firstElement) {
                            e.preventDefault();
                            lastElement?.focus();
                        }
                    } else {
                        if (document.activeElement === lastElement) {
                            e.preventDefault();
                            firstElement?.focus();
                        }
                    }
                }
            };

            window.addEventListener('keydown', handleKeyDown);

            // Focus first interactive element inside dialog
            const timeout = setTimeout(() => {
                const focusable = dialogRef.current?.querySelector<HTMLElement>('button:not([disabled])');
                focusable?.focus();
            }, 50);

            return () => {
                window.removeEventListener('keydown', handleKeyDown);
                clearTimeout(timeout);
                if (previousActiveElement.current && typeof previousActiveElement.current.focus === 'function') {
                    previousActiveElement.current.focus();
                }
            };
        }
    }, [isOpen, isDiscarding, onClose]);

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
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-xs animate-in fade-in-0"
            role="dialog"
            aria-modal="true"
            aria-labelledby="discard-draft-title"
            aria-describedby="discard-draft-description"
        >
            <div
                ref={dialogRef}
                className="w-full max-w-md p-6 bg-card border rounded-xl shadow-lg space-y-4 animate-in zoom-in-95 duration-150"
            >
                <div className="flex items-center gap-3 text-destructive">
                    <div className="p-2 bg-destructive/10 rounded-full">
                        <AlertTriangle className="h-5 w-5" />
                    </div>
                    <h3 id="discard-draft-title" className="text-lg font-semibold text-foreground">Discard Draft Order?</h3>
                </div>

                <p id="discard-draft-description" className="text-sm text-muted-foreground leading-relaxed">
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
