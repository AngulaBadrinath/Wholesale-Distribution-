import React, { useState, useEffect, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { CustomerSummary, CatalogProduct, CartLineItem, InitialDraftData } from '@/types/order';
import { CustomerSelectStep } from '@/Components/Salesman/CustomerSelectStep';
import { ProductCatalogStep } from '@/Components/Salesman/ProductCatalogStep';
import { OrderReviewStep } from '@/Components/Salesman/OrderReviewStep';
import { CartDrawer } from '@/Components/Salesman/CartDrawer';
import { DiscardDraftModal } from '@/Components/Salesman/DiscardDraftModal';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    ShoppingBag,
    ArrowRight,
    Check,
    Save,
    Trash2,
    FileText,
    AlertTriangle,
    Loader2,
    RefreshCw,
} from 'lucide-react';

interface CreateOrderPageProps {
    customers: CustomerSummary[];
    selectedCustomerId: number | null;
    initialDraft?: InitialDraftData | null;
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
    initialDraft,
    categories,
    products,
    filters,
}: CreateOrderPageProps) {
    const [step, setStep] = useState<'customer' | 'catalog' | 'review'>('customer');
    const [selectedCustomer, setSelectedCustomer] = useState<CustomerSummary | null>(null);
    const [cart, setCart] = useState<CartLineItem[]>([]);
    const [notes, setNotes] = useState<string>('');
    const [idempotencyKey, setIdempotencyKey] = useState<string>('');
    const [activeDraftId, setActiveDraftId] = useState<number | null>(initialDraft?.id || null);
    const [draftVersion, setDraftVersion] = useState<number>(initialDraft?.version || 1);
    const [draftToken, setDraftToken] = useState<string | null>(initialDraft?.draft_token || null);

    const [isCartDrawerOpen, setIsCartDrawerOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isSavingDraft, setIsSavingDraft] = useState(false);
    const [lastSavedTime, setLastSavedTime] = useState<string | null>(null);
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const [isDiscardModalOpen, setIsDiscardModalOpen] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [conflictError, setConflictError] = useState<string | null>(null);

    // Initial load: Setup from server initialDraft or local storage fallback
    useEffect(() => {
        if (initialDraft) {
            setActiveDraftId(initialDraft.id);
            setDraftVersion(initialDraft.version);
            setDraftToken(initialDraft.draft_token);
            setIdempotencyKey(initialDraft.idempotency_key);
            setNotes(initialDraft.notes || '');

            const matchedCustomer = customers.find((c) => c.id === initialDraft.customer_id);
            if (matchedCustomer) {
                setSelectedCustomer(matchedCustomer);
                setStep('catalog');
            }

            const initialCartItems: CartLineItem[] = initialDraft.items
                .filter((item) => item.product !== null)
                .map((item) => ({
                    product: item.product as CatalogProduct,
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                    is_custom_price: item.is_custom_price,
                }));

            setCart(initialCartItems);
            setLastSavedTime(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
            return;
        }

        // Initialize UUID v4 idempotency key for new order session
        const generatedKey = typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `idemp-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
        setIdempotencyKey(generatedKey);

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
    }, [initialDraft, selectedCustomerId, customers]);

    // Save active draft to LocalStorage as resilient client backup
    useEffect(() => {
        try {
            const draft = {
                serverDraftId: activeDraftId,
                customerId: selectedCustomer?.id || null,
                cart,
                notes,
            };
            localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draft));
        } catch {
            // Silently handle quota issues
        }
    }, [activeDraftId, selectedCustomer, cart, notes]);

    const handleSelectCustomer = (customer: CustomerSummary) => {
        setSelectedCustomer(customer);
        setHasUnsavedChanges(true);
    };

    const handleProceedToCatalog = () => {
        if (!selectedCustomer) return;
        setStep('catalog');
    };

    const handleFilterChange = (newFilters: { search?: string; category_id?: string; page?: number }) => {
        const url = activeDraftId
            ? `/salesman/orders/drafts/${activeDraftId}/edit`
            : '/salesman/orders/create';

        router.get(
            url,
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
        setHasUnsavedChanges(true);
    };

    const handleRemoveFromCart = (productId: number) => {
        setCart((prev) => prev.filter((item) => item.product.id !== productId));
        setHasUnsavedChanges(true);
    };

    const handleUpdateQuantityInReview = (productId: number, quantity: number) => {
        const safeQuantity = Math.max(1, Math.min(999999, Math.floor(quantity)));
        setCart((prev) =>
            prev.map((item) =>
                item.product.id === productId ? { ...item, quantity: safeQuantity } : item
            )
        );
        setHasUnsavedChanges(true);
    };

    // Save Draft to Server (Manual or Debounced)
    const handleSaveDraft = async () => {
        if (!selectedCustomer) {
            setErrorMessage('Please select a customer before saving a draft.');
            return;
        }

        setIsSavingDraft(true);
        setErrorMessage(null);
        setConflictError(null);

        const payload = {
            customer_id: selectedCustomer.id,
            notes: notes || null,
            expected_version: activeDraftId ? draftVersion : null,
            idempotency_key: idempotencyKey,
            items: cart.map((item) => ({
                product_id: item.product.id,
                quantity: item.quantity,
                unit_price: item.unit_price,
            })),
        };

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        try {
            const url = activeDraftId
                ? `/salesman/orders/drafts/${activeDraftId}`
                : '/salesman/orders/drafts';
            const method = activeDraftId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            if (response.status === 409) {
                const data = await response.json();
                setConflictError(data.message || 'Draft was modified in another session. Please reload to see the latest changes.');
                setIsSavingDraft(false);
                return;
            }

            if (!response.ok) {
                const data = await response.json();
                const firstErr = data.errors ? Object.values(data.errors)[0] : data.message;
                setErrorMessage(Array.isArray(firstErr) ? firstErr[0] : firstErr || 'Failed to save draft.');
                setIsSavingDraft(false);
                return;
            }

            const result = await response.json();
            if (result.success && result.draft) {
                setActiveDraftId(result.draft.id);
                setDraftVersion(result.draft.version);
                setDraftToken(result.draft.draft_token);
                setHasUnsavedChanges(false);
                setLastSavedTime(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
            }
        } catch {
            setErrorMessage('Network error while saving draft. Please try again.');
        } finally {
            setIsSavingDraft(false);
        }
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
        setConflictError(null);

        // If draft exists, submit draft; otherwise direct order submission
        if (activeDraftId) {
            // Synchronize and submit draft
            router.post(
                `/salesman/orders/drafts/${activeDraftId}/submit`,
                { idempotency_key: idempotencyKey },
                {
                    onSuccess: () => {
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
                }
            );
        } else {
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
        }
    };

    const totalCartItems = cart.reduce((acc, item) => acc + item.quantity, 0);
    const estimatedSubtotal = cart.reduce(
        (acc, item) => acc + (parseFloat(item.unit_price) || 0) * item.quantity,
        0
    );

    // Stale customer warning
    const isCustomerStale = initialDraft && !initialDraft.customer_is_active;

    // Inactive product in cart warnings
    const inactiveProductsInCart = cart.filter((item) => item.product && item.product.status === 'INACTIVE');

    return (
        <AppLayout title={activeDraftId ? 'Edit Draft Order' : 'New Sales Order'}>
            <Head title={`${activeDraftId ? 'Edit Draft' : 'New Sales Order'} — Wholesale Distribution`} />

            <div className="max-w-7xl mx-auto space-y-6 pb-24">
                {/* Top Action Bar & Status */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-card border rounded-lg p-4 shadow-sm">
                    <div className="flex items-center gap-3">
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold tracking-tight text-foreground">
                                    {activeDraftId ? 'Edit Draft Order' : 'New Sales Order'}
                                </h1>
                                {activeDraftId && (
                                    <Badge variant="secondary" className="font-mono text-xs">
                                        Draft #{activeDraftId} (v{draftVersion})
                                    </Badge>
                                )}
                            </div>
                            <div className="text-xs text-muted-foreground mt-0.5 flex items-center gap-2">
                                {isSavingDraft ? (
                                    <span className="flex items-center gap-1 text-primary">
                                        <Loader2 className="h-3 w-3 animate-spin" />
                                        Saving draft to server...
                                    </span>
                                ) : lastSavedTime ? (
                                    <span className="flex items-center gap-1">
                                        <Check className="h-3 w-3 text-emerald-500" />
                                        Draft saved at {lastSavedTime}
                                        {hasUnsavedChanges && ' (unsaved changes)'}
                                    </span>
                                ) : (
                                    <span>Working draft in progress</span>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 w-full sm:w-auto justify-between sm:justify-end">
                        <Link href="/salesman/orders/drafts">
                            <Button variant="ghost" size="sm" className="gap-1.5 text-xs">
                                <FileText className="h-4 w-4" />
                                <span>My Drafts</span>
                            </Button>
                        </Link>

                        {selectedCustomer && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleSaveDraft}
                                disabled={isSavingDraft || isSubmitting}
                                className="gap-1.5 text-xs"
                            >
                                {isSavingDraft ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Save className="h-4 w-4" />
                                )}
                                <span>Save Draft</span>
                            </Button>
                        )}

                        {activeDraftId && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setIsDiscardModalOpen(true)}
                                className="text-destructive hover:bg-destructive/10 text-xs px-2.5"
                            >
                                <Trash2 className="h-4 w-4" />
                                <span className="hidden sm:inline ml-1">Discard</span>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Conflict Alert (409) */}
                {conflictError && (
                    <div className="bg-destructive/10 border border-destructive/20 text-destructive p-4 rounded-lg flex items-center justify-between gap-4">
                        <div className="flex items-center gap-2 text-sm">
                            <AlertTriangle className="h-5 w-5 shrink-0" />
                            <span>{conflictError}</span>
                        </div>
                        <Button
                            variant="destructive"
                            size="sm"
                            onClick={() => window.location.reload()}
                            className="shrink-0 gap-1"
                        >
                            <RefreshCw className="h-3.5 w-3.5 mr-1" />
                            Reload Latest
                        </Button>
                    </div>
                )}

                {/* Stale Customer Warning Banner */}
                {isCustomerStale && (
                    <div className="bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 p-4 rounded-lg flex items-center gap-3 text-sm">
                        <AlertTriangle className="h-5 w-5 shrink-0" />
                        <div>
                            <span className="font-semibold">Customer Status Warning: </span>
                            This customer is currently {initialDraft?.customer_status}. You may view and edit the draft, but submission is blocked until the customer account is restored to Active.
                        </div>
                    </div>
                )}

                {/* Stale Inactive Product Warning Banner */}
                {inactiveProductsInCart.length > 0 && (
                    <div className="bg-destructive/10 border border-destructive/20 text-destructive p-4 rounded-lg flex items-center gap-3 text-sm">
                        <AlertTriangle className="h-5 w-5 shrink-0" />
                        <div>
                            <span className="font-semibold">Inactive Products in Cart: </span>
                            {inactiveProductsInCart.map((i) => i.product.name).join(', ')} is inactive in the catalog. Please remove inactive items before submitting.
                        </div>
                    </div>
                )}

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

                        <div className="h-px w-6 bg-border" />

                        {/* Step 2 */}
                        <button
                            type="button"
                            onClick={() => selectedCustomer && setStep('catalog')}
                            disabled={!selectedCustomer}
                            className={`flex items-center gap-2.5 text-xs font-semibold px-3 py-2 rounded-md transition-colors ${
                                step === 'catalog'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : cart.length > 0
                                    ? 'text-foreground hover:bg-muted'
                                    : 'text-muted-foreground opacity-60'
                            }`}
                        >
                            <span className="h-5 w-5 rounded-full border border-current flex items-center justify-center text-[10px]">
                                {cart.length > 0 ? <Check className="h-3 w-3" /> : '2'}
                            </span>
                            <span>Product Catalog</span>
                            {cart.length > 0 && (
                                <Badge variant="secondary" className="hidden sm:inline-flex text-[10px] py-0">
                                    {totalCartItems} {totalCartItems === 1 ? 'item' : 'items'}
                                </Badge>
                            )}
                        </button>

                        <div className="h-px w-6 bg-border" />

                        {/* Step 3 */}
                        <button
                            type="button"
                            onClick={() => selectedCustomer && cart.length > 0 && setStep('review')}
                            disabled={!selectedCustomer || cart.length === 0}
                            className={`flex items-center gap-2.5 text-xs font-semibold px-3 py-2 rounded-md transition-colors ${
                                step === 'review'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground opacity-60'
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

                {step === 'catalog' && selectedCustomer && (
                    <ProductCatalogStep
                        categories={categories}
                        products={products}
                        filters={filters}
                        cart={cart}
                        onUpdateCart={handleUpdateCart}
                        onRemoveFromCart={handleRemoveFromCart}
                        onFilterChange={handleFilterChange}
                        onProceed={() => setStep('review')}
                    />
                )}

                {step === 'review' && selectedCustomer && (
                    <OrderReviewStep
                        customer={selectedCustomer}
                        cart={cart}
                        notes={notes}
                        onNotesChange={(newNotes) => {
                            setNotes(newNotes);
                            setHasUnsavedChanges(true);
                        }}
                        onUpdateQuantity={handleUpdateQuantityInReview}
                        onRemoveItem={handleRemoveFromCart}
                        onBackToCatalog={() => setStep('catalog')}
                        onSubmitOrder={handleSubmitOrder}
                        isSubmitting={isSubmitting}
                        errorMessage={errorMessage}
                    />
                )}
            </div>

            {/* Persistent Mobile Bottom Bar */}
            {step === 'catalog' && (
                <div className="fixed bottom-0 left-0 right-0 z-40 bg-card/95 backdrop-blur border-t p-3 lg:hidden flex items-center justify-between shadow-lg">
                    <div>
                        <div className="text-xs text-muted-foreground">
                            {totalCartItems} {totalCartItems === 1 ? 'item' : 'items'}
                        </div>
                        <div className="text-sm font-bold font-mono text-foreground">
                            ${estimatedSubtotal.toFixed(2)}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setIsCartDrawerOpen(true)}
                            className="gap-1.5"
                        >
                            <ShoppingBag className="h-4 w-4" />
                            <span>Cart</span>
                        </Button>

                        <Button
                            size="sm"
                            disabled={cart.length === 0}
                            onClick={() => setStep('review')}
                            className="gap-1.5"
                        >
                            <span>Review</span>
                            <ArrowRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}

            {/* Cart Drawer for Mobile/Tablet */}
            <CartDrawer
                open={isCartDrawerOpen}
                cart={cart}
                onClose={() => setIsCartDrawerOpen(false)}
                onUpdateQuantity={handleUpdateQuantityInReview}
                onRemoveItem={handleRemoveFromCart}
                onProceedToReview={() => {
                    setIsCartDrawerOpen(false);
                    setStep('review');
                }}
            />

            {/* Discard Draft Modal */}
            <DiscardDraftModal
                isOpen={isDiscardModalOpen}
                draftId={activeDraftId}
                customerName={selectedCustomer?.name}
                onClose={() => setIsDiscardModalOpen(false)}
            />
        </AppLayout>
    );
}
