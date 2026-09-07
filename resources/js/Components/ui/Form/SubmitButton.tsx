import React, { useState } from 'react';
import { Loader2, AlertTriangle } from 'lucide-react';
import { Button, ButtonProps } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

export interface SubmitButtonProps extends ButtonProps {
    isLoading?: boolean;
    loadingText?: string;
    requiresConfirmation?: boolean;
    confirmTitle?: string;
    confirmMessage?: string;
    confirmButtonText?: string;
    onConfirm?: () => void;
}

export const SubmitButton = React.forwardRef<HTMLButtonElement, SubmitButtonProps>(
    (
        {
            children,
            isLoading = false,
            loadingText = 'Processing...',
            requiresConfirmation = false,
            confirmTitle = 'Confirm Action',
            confirmMessage = 'Are you sure you want to proceed with this high-impact operation?',
            confirmButtonText = 'Confirm & Proceed',
            onConfirm,
            disabled,
            onClick,
            className,
            variant = 'default',
            type = 'submit',
            ...props
        },
        ref
    ) => {
        const [showConfirmModal, setShowConfirmModal] = useState(false);

        const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
            if (isLoading || disabled) {
                e.preventDefault();
                return;
            }

            if (requiresConfirmation && !showConfirmModal) {
                e.preventDefault();
                setShowConfirmModal(true);
                return;
            }

            if (onClick) {
                onClick(e);
            }
        };

        const handleProceed = () => {
            setShowConfirmModal(false);
            if (onConfirm) {
                onConfirm();
            }
        };

        return (
            <>
                <Button
                    ref={ref}
                    type={type}
                    variant={variant}
                    disabled={disabled || isLoading}
                    onClick={handleClick}
                    className={cn('relative gap-2 select-none cursor-pointer', className)}
                    {...props}
                >
                    {isLoading ? (
                        <>
                            <Loader2 className="w-4 h-4 animate-spin shrink-0" />
                            <span>{loadingText}</span>
                        </>
                    ) : (
                        children
                    )}
                </Button>

                {/* Confirmation Modal */}
                {showConfirmModal && (
                    <div
                        className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in-50 duration-150"
                        role="dialog"
                        aria-modal="true"
                    >
                        <div className="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-2xl space-y-4 animate-in zoom-in-95 duration-150">
                            <div className="flex items-start gap-3">
                                <div className="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                                    <AlertTriangle className="w-5 h-5" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-base font-semibold text-foreground tracking-tight">
                                        {confirmTitle}
                                    </h3>
                                    <p className="text-xs text-muted-foreground mt-1 leading-relaxed">
                                        {confirmMessage}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2 border-t border-border">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setShowConfirmModal(false)}
                                    disabled={isLoading}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="button"
                                    variant={variant}
                                    size="sm"
                                    onClick={handleProceed}
                                    disabled={isLoading}
                                    className="gap-1.5"
                                >
                                    {isLoading && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                                    <span>{confirmButtonText}</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
            </>
        );
    }
);

SubmitButton.displayName = 'SubmitButton';
