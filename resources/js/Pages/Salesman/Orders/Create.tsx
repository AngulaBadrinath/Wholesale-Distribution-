import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { CustomerSummary, CatalogProduct, CartLineItem } from '@/types/order';
import { CustomerSelectStep } from '@/Components/Salesman/CustomerSelectStep';
import { ProductCatalogStep } from '@/Components/Salesman/ProductCatalogStep';
import { OrderReviewStep } from '@/Components/Salesman/OrderReviewStep';
import { CartDrawer } from '@/Components/Salesman/CartDrawer';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { ShoppingBag, ArrowRight, Check } from 'lucide-react';

interface CreateOrderPageProps {
    customers: CustomerSummary[];
    selectedCustomerId: number | null;
    categories: Array<{ id: number; name: string; code: string }>;
    products: {
        data: CatalogProduct[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    filters: {
        search: string;
        category_id: string;
    };
}

const DRAFT_STORAGE_KEY = 'salesman_order_builder_draft_v1';

export default function CreateOrder({
    customers,
    selectedCustomerId,
    categories,
    products,
    filters,
}: CreateOrderPageProps) {
    const [step, setStep] = useState<'customer' | 'catalog' | 'review'>('customer');
    const [selectedCustomer, setSelectedCustomer] = useState<CustomerSummary | null>(null);
    const [cart, setCart] = useState<CartLineItem[]>([]);
    const [notes, setNotes] = useState<string>('');
    const [idempotencyKey, setIdempotencyKey] = useState<string>('');
    const [isCartDrawerOpen, setIsCartDrawerOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    // Generate or restore idempotency key and local draft on initial load
    useEffect(() => {
        // Initialize UUID v4 idempotency key
        const generatedKey = typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `idemp-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
        setIdempotencyKey(generatedKey);

        // Pre-select customer from props if provided (e.g. from customer profile action)
        if (selectedCustomerId) {
            const matched = customers.find((c) => c.id === selectedCustomerId);
            if (matched) {
                setSelectedCustomer(matched);
                setStep('catalog');
                return;
            }
        }

        // Restore draft from localStorage if available
        try {
            const rawDraft = localStorage.getItem(DRAFT_STORAGE_KEY);
            if (rawDraft) {
                const parsed = JSON.parse(rawDraft);
                if (parsed.customerId) {
                    const matched = customers.find((c) => c.id === parsed.customerId);
                    if (matched) {
                        setSelectedCustomer(matched);
                        setStep('catalog');
                    }
                }
                if (Array.isArray(parsed.cart)) {
                    setCart(parsed.cart);
                }
                if (typeof parsed.notes === 'string') {
                    setNotes(parsed.notes);
                }
            }
        } catch {
            // Silently ignore corrupted local storage
        }
    }, [selectedCustomerId, customers]);

    // Save active draft to LocalStorage
    useEffect(() => {
        try {
            const draft = {
                customerId: selectedCustomer?.id || null,
                cart,
                notes,
            };
            localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draft));
        } catch {
            // Silently handle quota issues
        }
    }, [selectedCustomer, cart, notes]);

    const handleSelectCustomer = (customer: CustomerSummary) => {
        setSelectedCustomer(customer);
    };

    const handleProceedToCatalog = () => {
        if (!selectedCustomer) return;
        setStep('catalog');
    };

    const handleFilterChange = (newFilters: { search?: string; category_id?: string; page?: number }) => {
        router.get(
            '/salesman/orders/create',
            {
                search: newFilters.search !== undefined ? newFilters.search : filters.search,
                category_id: newFilters.category_id !== undefined ? newFilters.category_id : filters.category_id,
                page: newFilters.page || 1,
                customer_id: selectedCustomer?.id,
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['products', 'filters'],
            }
        );
    };

    const handleUpdateCart = (product: CatalogProduct, quantity: number, unitPrice: string) => {
        setCart((prev) => {
            const existingIndex = prev.findIndex((item) => item.product.id === product.id);
            if (existingIndex >= 0) {
                const updated = [...prev];
                updated[existingIndex] = {
                    ...updated[existingIndex],
                    quantity,
                    unit_price: unitPrice,
                    is_custom_price: parseFloat(unitPrice) !== product.default_selling_price,
                };
                return updated;
            } else {
                return [
                    ...prev,
                    {
                        product,
                        quantity,
                        unit_price: unitPrice,
                        is_custom_price: parseFloat(unitPrice) !== product.default_selling_price,
                    },
                ];
            }
        });
    };

    const handleRemoveFromCart = (productId: number) => {
        setCart((prev) => prev.filter((item) => item.product.id !== productId));
    };

    const handleUpdateQuantityInReview = (productId: number, quantity: number) => {
        if (quantity <= 0) {
            handleRemoveFromCart(productId);
            return;
        }
        setCart((prev) =>
            prev.map((item) =>
                item.product.id === productId ? { ...item, quantity: Math.min(999999, quantity) } : item
            )
        );
    };

    const handleSubmitOrder = () => {
        if (!selectedCustomer) {
            setErrorMessage('Please select a customer before submitting.');
            return;
        }

        if (cart.length === 0) {
            setErrorMessage('Your cart is empty. Please add at least one product.');
            return;
        }

        setIsSubmitting(true);
        setErrorMessage(null);

        const payload = {
            customer_id: selectedCustomer.id,
            idempotency_key: idempotencyKey,
            notes: notes || null,
            items: cart.map((item) => ({
                product_id: item.product.id,
                quantity: item.quantity,
                unit_price: item.unit_price,
            })),
        };

        router.post('/salesman/orders', payload, {
            onSuccess: () => {
                // Clear local storage draft after successful order commit
                try {
                    localStorage.removeItem(DRAFT_STORAGE_KEY);
                } catch {
                    // Ignore
                }
            },
            onError: (errors) => {
                setIsSubmitting(false);
                const firstError = Object.values(errors)[0] as string;
                setErrorMessage(firstError || 'An error occurred while placing the order. Please review line items.');
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const totalCartItems = cart.reduce((acc, item) => acc + item.quantity, 0);
    const estimatedSubtotal = cart.reduce(
        (acc, item) => acc + (parseFloat(item.unit_price) || 0) * item.quantity,
        0
    );

    return (
        <AppLayout title="New Sales Order">
            <Head title="New Sales Order — Wholesale Distribution" />

            <div className="max-w-7xl mx-auto space-y-6 pb-24">
                {/* Stepper Navigation Bar */}
                <div className="bg-card border rounded-lg p-4 shadow-sm">
                    <div className="flex items-center justify-between gap-2 overflow-x-auto">
                        {/* Step 1 */}
                        <button
                            type="button"
                            onClick={() => setStep('customer')}
                            className={`flex items-center gap-2.5 text-xs font-semibold px-3 py-2 rounded-md transition-colors ${
                                step === 'customer'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : selectedCustomer
                                    ? 'text-foreground hover:bg-muted'
                                    : 'text-muted-foreground'
                            }`}
                        >
                            <span className="h-5 w-5 rounded-full border border-current flex items-center justify-center text-[10px]">
                                {selectedCustomer ? <Check className="h-3 w-3" /> : '1'}
                            </span>
                            <span>Customer</span>
                            {selectedCustomer && (
                                <Badge variant="secondary" className="hidden sm:inline-flex text-[10px] py-0">
                                    {selectedCustomer.code}
                                </Badge>
                            )}
                        </button>

                        <ArrowRight className="h-4 w-4 text-muted-foreground/50 shrink-0" />

                        {/* Step 2 */}
                        <button
                            type="button"
                            onClick={() => {
                                if (selectedCustomer) setStep('catalog');
                            }}
                            disabled={!selectedCustomer}
                            className={`flex items-center gap-2.5 text-xs font-semibold px-3 py-2 rounded-md transition-colors ${
                                step === 'catalog'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : cart.length > 0
                                    ? 'text-foreground hover:bg-muted'
                                    : 'text-muted-foreground opacity-60 cursor-not-allowed'
                            }`}
                        >
                            <span className="h-5 w-5 rounded-full border border-current flex items-center justify-center text-[10px]">
                                {cart.length > 0 ? <Check className="h-3 w-3" /> : '2'}
                            </span>
                            <span>Catalogue & Cart</span>
                            {cart.length > 0 && (
                                <Badge variant="secondary" className="hidden sm:inline-flex text-[10px] py-0">
                                    {totalCartItems} units
                                </Badge>
                            )}
                        </button>

                        <ArrowRight className="h-4 w-4 text-muted-foreground/50 shrink-0" />

                        {/* Step 3 */}
                        <button
                            type="button"
                            onClick={() => {
                                if (selectedCustomer && cart.length > 0) setStep('review');
                            }}
                            disabled={!selectedCustomer || cart.length === 0}
                            className={`flex items-center gap-2.5 text-xs font-semibold px-3 py-2 rounded-md transition-colors ${
                                step === 'review'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground opacity-60 cursor-not-allowed'
                            }`}
                        >
                            <span className="h-5 w-5 rounded-full border border-current flex items-center justify-center text-[10px]">
                                3
                            </span>
                            <span>Review & Submit</span>
                        </button>
                    </div>
                </div>

                {/* Step Content */}
                {step === 'customer' && (
                    <CustomerSelectStep
                        customers={customers}
                        selectedCustomer={selectedCustomer}
                        onSelectCustomer={handleSelectCustomer}
                        onProceed={handleProceedToCatalog}
                    />
                )}

                {step === 'catalog' && (
                    <ProductCatalogStep
                        products={products}
                        categories={categories}
                        cart={cart}
                        filters={filters}
                        onFilterChange={handleFilterChange}
                        onUpdateCart={handleUpdateCart}
                        onRemoveFromCart={handleRemoveFromCart}
                        onProceed={() => setStep('review')}
                    />
                )}

                {step === 'review' && selectedCustomer && (
                    <OrderReviewStep
                        customer={selectedCustomer}
                        cart={cart}
                        notes={notes}
                        onNotesChange={setNotes}
                        onUpdateQuantity={handleUpdateQuantityInReview}
                        onRemoveItem={handleRemoveFromCart}
                        onBackToCatalog={() => setStep('catalog')}
                        onSubmitOrder={handleSubmitOrder}
                        isSubmitting={isSubmitting}
                        errorMessage={errorMessage}
                    />
                )}

                {/* Floating Bottom Sticky Bar for Mobile and Fast Order Review */}
                {step === 'catalog' && cart.length > 0 && (
                    <div className="fixed bottom-0 left-0 right-0 z-40 bg-background/95 backdrop-blur border-t p-3 shadow-lg">
                        <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsCartDrawerOpen(true)}
                                    className="gap-2 text-xs"
                                >
                                    <ShoppingBag className="h-4 w-4" />
                                    <span>Cart ({totalCartItems})</span>
                                </Button>
                                <div className="text-xs">
                                    <span className="text-muted-foreground">Est. Subtotal: </span>
                                    <span className="font-bold font-mono text-sm text-foreground">
                                        ${estimatedSubtotal.toFixed(2)}
                                    </span>
                                </div>
                            </div>

                            <Button
                                size="sm"
                                onClick={() => setStep('review')}
                                className="gap-2 font-semibold text-xs"
                            >
                                <span>Review Order</span>
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}

                {/* Slide-over Cart Drawer */}
                <CartDrawer
                    open={isCartDrawerOpen}
                    onClose={() => setIsCartDrawerOpen(false)}
                    cart={cart}
                    onUpdateQuantity={handleUpdateQuantityInReview}
                    onRemoveItem={handleRemoveFromCart}
                    onProceedToReview={() => setStep('review')}
                />
            </div>
        </AppLayout>
    );
}
