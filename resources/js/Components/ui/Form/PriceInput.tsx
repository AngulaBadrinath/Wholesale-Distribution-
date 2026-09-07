import React from 'react';
import { AlertTriangle, ShieldAlert } from 'lucide-react';
import { cn } from '@/lib/utils';
import { CurrencyInput, CurrencyInputProps } from './CurrencyInput';

export interface PriceInputProps extends CurrencyInputProps {
    minAllowedPrice?: number;
    listPrice?: number;
    requiresOverride?: boolean;
    overrideReason?: string;
    onOverrideReasonChange?: (reason: string) => void;
}

export const PriceInput = React.forwardRef<HTMLInputElement, PriceInputProps>(
    (
        {
            value,
            onChange,
            minAllowedPrice,
            listPrice,
            requiresOverride,
            overrideReason,
            onOverrideReasonChange,
            error,
            className,
            ...props
        },
        ref
    ) => {
        const numValue = typeof value === 'number' ? value : parseFloat(String(value)) || 0;
        const isBelowMin = minAllowedPrice !== undefined && numValue > 0 && numValue < minAllowedPrice;
        const isAboveList = listPrice !== undefined && numValue > 0 && numValue > listPrice;
        const isBoundaryViolated = isBelowMin || isAboveList;

        return (
            <div className="space-y-1.5">
                <CurrencyInput
                    ref={ref}
                    value={value}
                    onChange={onChange}
                    error={error || (isBoundaryViolated && !requiresOverride)}
                    className={cn(
                        isBoundaryViolated
                            ? 'border-amber-500/80 dark:border-amber-500/60 bg-amber-500/5'
                            : '',
                        className
                    )}
                    {...props}
                />

                {/* Price bounds helper hint */}
                {(minAllowedPrice !== undefined || listPrice !== undefined) && (
                    <div className="flex items-center justify-between text-[11px] text-muted-foreground px-0.5">
                        {minAllowedPrice !== undefined && (
                            <span>
                                Min: <strong className="font-mono text-foreground">${minAllowedPrice.toFixed(2)}</strong>
                            </span>
                        )}
                        {listPrice !== undefined && (
                            <span>
                                List: <strong className="font-mono text-foreground">${listPrice.toFixed(2)}</strong>
                            </span>
                        )}
                    </div>
                )}

                {/* Price boundary override warning */}
                {isBelowMin && (
                    <div className="p-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200 text-xs flex items-start gap-2">
                        <AlertTriangle className="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                        <div className="flex-1 min-w-0">
                            <span className="font-semibold block">Price below minimum allowed threshold</span>
                            <span className="text-[11px] opacity-90 block mt-0.5">
                                This transaction will require supervisor authorization before order processing.
                            </span>
                            {onOverrideReasonChange && (
                                <input
                                    type="text"
                                    value={overrideReason || ''}
                                    onChange={(e) => onOverrideReasonChange(e.target.value)}
                                    placeholder="Enter authorization justification / reason..."
                                    className="mt-2 w-full px-2.5 py-1.5 text-xs rounded-md border border-amber-500/40 bg-background text-foreground placeholder:text-muted-foreground focus:outline-hidden focus:ring-1 focus:ring-amber-500"
                                />
                            )}
                        </div>
                    </div>
                )}
            </div>
        );
    }
);

PriceInput.displayName = 'PriceInput';
