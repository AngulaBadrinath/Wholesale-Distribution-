import React from 'react';
import { cn } from '@/lib/utils';

export interface RadioOption {
    label: React.ReactNode;
    value: string | number;
    description?: React.ReactNode;
    disabled?: boolean;
    badge?: React.ReactNode;
}

export interface RadioGroupProps {
    name: string;
    value: string | number;
    onChange: (value: string | number) => void;
    options: RadioOption[];
    disabled?: boolean;
    orientation?: 'vertical' | 'horizontal';
    className?: string;
    variant?: 'default' | 'cards';
}

export function RadioGroup({
    name,
    value,
    onChange,
    options,
    disabled = false,
    orientation = 'vertical',
    className,
    variant = 'default',
}: RadioGroupProps) {
    return (
        <div
            role="radiogroup"
            className={cn(
                'gap-2',
                orientation === 'horizontal' ? 'flex flex-wrap items-center' : 'space-y-2',
                className
            )}
        >
            {options.map((opt) => {
                const isSelected = String(value) === String(opt.value);
                const isOptDisabled = disabled || opt.disabled;
                const id = `${name}-${String(opt.value)}`;

                if (variant === 'cards') {
                    return (
                        <label
                            key={String(opt.value)}
                            htmlFor={id}
                            className={cn(
                                'flex items-start gap-3 p-3 rounded-xl border transition-all text-xs select-none',
                                isSelected
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                    : 'border-border bg-card hover:bg-muted/40 hover:border-border/80',
                                isOptDisabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                            )}
                        >
                            <input
                                id={id}
                                type="radio"
                                name={name}
                                value={opt.value}
                                checked={isSelected}
                                disabled={isOptDisabled}
                                onChange={() => onChange(opt.value)}
                                className="h-4 w-4 mt-0.5 border-input text-primary focus:ring-primary/20 cursor-pointer"
                            />
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="font-semibold text-foreground tracking-tight">
                                        {opt.label}
                                    </span>
                                    {opt.badge && <div className="shrink-0">{opt.badge}</div>}
                                </div>
                                {opt.description && (
                                    <p className="text-[11px] text-muted-foreground mt-0.5 leading-relaxed">
                                        {opt.description}
                                    </p>
                                )}
                            </div>
                        </label>
                    );
                }

                return (
                    <label
                        key={String(opt.value)}
                        htmlFor={id}
                        className={cn(
                            'flex items-start gap-2.5 text-xs select-none',
                            isOptDisabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                        )}
                    >
                        <input
                            id={id}
                            type="radio"
                            name={name}
                            value={opt.value}
                            checked={isSelected}
                            disabled={isOptDisabled}
                            onChange={() => onChange(opt.value)}
                            className="h-4 w-4 mt-0.5 border-input text-primary focus:ring-primary/20 cursor-pointer"
                        />
                        <div className="flex-1 min-w-0">
                            <span className="font-medium text-foreground block">
                                {opt.label}
                            </span>
                            {opt.description && (
                                <span className="text-[11px] text-muted-foreground block mt-0.5">
                                    {opt.description}
                                </span>
                            )}
                        </div>
                    </label>
                );
            })}
        </div>
    );
}
