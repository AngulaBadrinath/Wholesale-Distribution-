import React, { useState, useEffect, useCallback } from 'react';
import { Minus, Plus } from 'lucide-react';

export interface QuantityStepperProps {
    value: number;
    min?: number;
    max?: number;
    step?: number;
    disabled?: boolean;
    size?: 'sm' | 'md';
    className?: string;
    ariaLabel?: string;
    onChange: (newQuantity: number) => void;
}

export const QuantityStepper: React.FC<QuantityStepperProps> = ({
    value,
    min = 1,
    max = 999999,
    step = 1,
    disabled = false,
    size = 'sm',
    className = '',
    ariaLabel = 'Item quantity',
    onChange,
}) => {
    const [localInput, setLocalInput] = useState<string>(String(value || min));
    const [isFocused, setIsFocused] = useState<boolean>(false);

    // Synchronize local input state when value changes externally (e.g. cart reset or draft load)
    useEffect(() => {
        if (!isFocused) {
            setLocalInput(String(value ?? min));
        }
    }, [value, min, isFocused]);

    const commitQuantity = useCallback(
        (rawValue: number) => {
            const safeMin = Math.max(1, min);
            const safeMax = Math.min(999999, max);
            const clamped = Math.max(safeMin, Math.min(safeMax, Math.floor(rawValue)));
            setLocalInput(String(clamped));
            if (clamped !== value) {
                onChange(clamped);
            }
        },
        [min, max, value, onChange]
    );

    const handleIncrement = () => {
        if (disabled || value >= max) return;
        const next = Math.min(max, value + step);
        commitQuantity(next);
    };

    const handleDecrement = () => {
        if (disabled || value <= min) return;
        const next = Math.max(min, value - step);
        commitQuantity(next);
    };

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        // Allow digits only; strips non-numeric characters immediately
        const cleaned = e.target.value.replace(/[^0-9]/g, '');
        setLocalInput(cleaned);
    };

    const handleInputBlur = () => {
        setIsFocused(false);
        if (localInput === '' || isNaN(parseInt(localInput, 10))) {
            commitQuantity(min);
            return;
        }
        const parsed = parseInt(localInput, 10);
        commitQuantity(parsed);
    };

    const handleInputFocus = () => {
        setIsFocused(true);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            handleIncrement();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            handleDecrement();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            (e.target as HTMLInputElement).blur();
        }
    };

    const isDecrementDisabled = disabled || value <= min;
    const isIncrementDisabled = disabled || value >= max;

    // Size-specific dimensions
    const containerHeight = size === 'md' ? 'h-9' : 'h-8';
    const buttonWidth = size === 'md' ? 'w-9' : 'w-8';
    const iconSize = size === 'md' ? 'h-4 w-4' : 'h-3.5 w-3.5';
    const inputWidth = size === 'md' ? 'w-14' : 'w-12';
    const textSize = size === 'md' ? 'text-sm' : 'text-xs';

    return (
        <div
            className={`inline-flex items-stretch border rounded-md bg-background shadow-xs transition-colors focus-within:ring-1 focus-within:ring-primary focus-within:border-primary ${
                disabled ? 'opacity-60 cursor-not-allowed bg-muted/40' : ''
            } ${containerHeight} ${className}`}
        >
            {/* Decrement Button */}
            <button
                type="button"
                onClick={handleDecrement}
                disabled={isDecrementDisabled}
                aria-label={`Decrease ${ariaLabel}`}
                className={`relative flex items-center justify-center text-muted-foreground transition-colors select-none rounded-l-[5px] hover:bg-muted hover:text-foreground active:bg-muted/80 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-muted-foreground ${buttonWidth} after:absolute after:inset-[-6px] sm:after:inset-0`}
            >
                <Minus className={iconSize} />
            </button>

            {/* Numeric Input */}
            <input
                type="text"
                inputMode="numeric"
                pattern="[0-9]*"
                autoComplete="off"
                disabled={disabled}
                value={localInput}
                onChange={handleInputChange}
                onBlur={handleInputBlur}
                onFocus={handleInputFocus}
                onKeyDown={handleKeyDown}
                role="spinbutton"
                aria-valuenow={value}
                aria-valuemin={min}
                aria-valuemax={max}
                aria-label={ariaLabel}
                className={`border-0 bg-transparent text-center font-mono font-bold text-foreground focus:outline-none focus:ring-0 p-0 select-all ${inputWidth} ${textSize}`}
            />

            {/* Increment Button */}
            <button
                type="button"
                onClick={handleIncrement}
                disabled={isIncrementDisabled}
                aria-label={`Increase ${ariaLabel}`}
                className={`relative flex items-center justify-center text-muted-foreground transition-colors select-none rounded-r-[5px] hover:bg-muted hover:text-foreground active:bg-muted/80 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-muted-foreground ${buttonWidth} after:absolute after:inset-[-6px] sm:after:inset-0`}
            >
                <Plus className={iconSize} />
            </button>
        </div>
    );
};
