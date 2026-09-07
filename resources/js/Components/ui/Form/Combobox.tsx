import React, { useState, useRef, useEffect } from 'react';
import { Search, ChevronDown, Check, X, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface ComboboxOption {
    label: string;
    value: string | number;
    description?: string;
    badge?: string;
    disabled?: boolean;
}

export interface ComboboxProps {
    options: ComboboxOption[];
    value?: string | number | null;
    onChange: (value: string | number) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
    isLoading?: boolean;
    error?: boolean | string | string[] | null;
    id?: string;
    className?: string;
    onSearch?: (query: string) => void;
}

export function Combobox({
    options = [],
    value,
    onChange,
    placeholder = 'Select an option...',
    searchPlaceholder = 'Search...',
    emptyText = 'No matching options found',
    disabled = false,
    isLoading = false,
    error,
    id,
    className,
    onSearch,
}: ComboboxProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const hasError = Boolean(error);

    const selectedOption = options.find((opt) => String(opt.value) === String(value));

    const filteredOptions = onSearch
        ? options
        : options.filter((opt) =>
              opt.label.toLowerCase().includes(query.toLowerCase()) ||
              (opt.description && opt.description.toLowerCase().includes(query.toLowerCase()))
          );

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        };

        const handleEscape = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setIsOpen(false);
            }
        };

        if (isOpen) {
            document.addEventListener('mousedown', handleClickOutside);
            document.addEventListener('keydown', handleEscape);
        }
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [isOpen]);

    const handleSelect = (val: string | number) => {
        onChange(val);
        setIsOpen(false);
        setQuery('');
    };

    const handleQueryChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const text = e.target.value;
        setQuery(text);
        if (onSearch) {
            onSearch(text);
        }
    };

    return (
        <div className={cn('relative', className)} ref={containerRef}>
            {/* Trigger Button */}
            <button
                id={id}
                type="button"
                disabled={disabled || isLoading}
                onClick={() => {
                    if (!disabled && !isLoading) {
                        setIsOpen(!isOpen);
                        setTimeout(() => inputRef.current?.focus(), 50);
                    }
                }}
                aria-haspopup="listbox"
                aria-expanded={isOpen}
                aria-invalid={hasError ? 'true' : undefined}
                className={cn(
                    'flex h-10 w-full items-center justify-between rounded-lg border bg-background px-3 py-2 text-sm text-left transition-colors',
                    'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
                    hasError
                        ? 'border-rose-500 focus-visible:ring-rose-500/30'
                        : 'border-input hover:border-input/80',
                    disabled ? 'cursor-not-allowed opacity-50 bg-muted/50 select-none' : 'cursor-pointer'
                )}
            >
                <span className={cn('truncate', !selectedOption ? 'text-muted-foreground' : 'text-foreground font-medium')}>
                    {selectedOption ? selectedOption.label : placeholder}
                </span>
                <span className="ml-2 flex items-center text-muted-foreground pointer-events-none">
                    {isLoading ? (
                        <Loader2 className="w-4 h-4 animate-spin" />
                    ) : (
                        <ChevronDown className="w-4 h-4 opacity-70" />
                    )}
                </span>
            </button>

            {/* Dropdown Overlay */}
            {isOpen && (
                <div className="absolute z-50 mt-1.5 w-full rounded-xl border border-border bg-card shadow-xl overflow-hidden animate-in fade-in-50 zoom-in-95 duration-100">
                    {/* Search Field */}
                    <div className="flex items-center border-b border-border px-3 py-2 bg-muted/20">
                        <Search className="w-4 h-4 text-muted-foreground mr-2 shrink-0" />
                        <input
                            ref={inputRef}
                            type="text"
                            value={query}
                            onChange={handleQueryChange}
                            placeholder={searchPlaceholder}
                            className="w-full bg-transparent text-xs text-foreground placeholder:text-muted-foreground focus:outline-hidden"
                        />
                        {query && (
                            <button
                                type="button"
                                onClick={() => {
                                    setQuery('');
                                    if (onSearch) onSearch('');
                                }}
                                className="p-0.5 text-muted-foreground hover:text-foreground cursor-pointer"
                            >
                                <X className="w-3.5 h-3.5" />
                            </button>
                        )}
                    </div>

                    {/* Options List */}
                    <div className="max-h-60 overflow-y-auto p-1 divide-y divide-border/40" role="listbox">
                        {isLoading && options.length === 0 ? (
                            <div className="p-4 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
                                <Loader2 className="w-4 h-4 animate-spin" />
                                <span>Searching...</span>
                            </div>
                        ) : filteredOptions.length === 0 ? (
                            <div className="p-4 text-center text-xs text-muted-foreground">
                                {emptyText}
                            </div>
                        ) : (
                            filteredOptions.map((opt) => {
                                const isSelected = String(opt.value) === String(value);

                                return (
                                    <button
                                        key={String(opt.value)}
                                        type="button"
                                        disabled={opt.disabled}
                                        onClick={() => handleSelect(opt.value)}
                                        role="option"
                                        aria-selected={isSelected}
                                        className={cn(
                                            'w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs text-left transition-colors',
                                            isSelected
                                                ? 'bg-primary/10 text-primary font-semibold'
                                                : 'text-foreground hover:bg-muted/60',
                                            opt.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
                                        )}
                                    >
                                        <div className="flex-1 min-w-0 pr-2">
                                            <div className="flex items-center gap-2">
                                                <span className="truncate">{opt.label}</span>
                                                {opt.badge && (
                                                    <span className="text-[10px] bg-muted px-1.5 py-0.5 rounded font-mono text-muted-foreground">
                                                        {opt.badge}
                                                    </span>
                                                )}
                                            </div>
                                            {opt.description && (
                                                <div className="text-[11px] text-muted-foreground truncate mt-0.5">
                                                    {opt.description}
                                                </div>
                                            )}
                                        </div>
                                        {isSelected && (
                                            <Check className="w-4 h-4 text-primary shrink-0" />
                                        )}
                                    </button>
                                );
                            })
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
