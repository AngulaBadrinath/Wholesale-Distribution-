import React, { useState } from 'react';
import { CatalogProduct, CartLineItem, CustomerSummary } from '@/types/order';
import { ProductOrderCard } from './ProductOrderCard';
import { OrderSummaryPanel } from './OrderSummaryPanel';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Search, Filter, ShoppingCart, ArrowRight, PackageSearch, X } from 'lucide-react';

interface ProductCatalogStepProps {
    customer: CustomerSummary | null;
    products: {
        data: CatalogProduct[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    categories: Array<{ id: number; name: string; code: string }>;
    cart: CartLineItem[];
    filters: { search: string; category_id: string };
    onFilterChange: (filters: { search?: string; category_id?: string; page?: number }) => void;
    onUpdateCart: (product: CatalogProduct, quantity: number, unitPrice: string) => void;
    onRemoveFromCart: (productId: number) => void;
    onProceed: () => void;
}

export const ProductCatalogStep: React.FC<ProductCatalogStepProps> = ({
    customer,
    products,
    categories,
    cart,
    filters,
    onFilterChange,
    onUpdateCart,
    onRemoveFromCart,
    onProceed,
}) => {
    const [searchLocal, setSearchLocal] = useState(filters.search || '');

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onFilterChange({ search: searchLocal, page: 1 });
    };

    const handleCategoryClick = (catId: string) => {
        const nextCat = filters.category_id === catId ? '' : catId;
        onFilterChange({ category_id: nextCat, page: 1 });
    };

    const totalCartItems = cart.reduce((sum, item) => sum + item.quantity, 0);

    return (
        <div className="space-y-6">
            {/* Top Workspace Header */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 className="text-xl font-bold tracking-tight text-foreground">Product Catalogue</h2>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Browse active products, adjust ordered quantities, and set authorized customer selling prices.
                    </p>
                </div>
                {cart.length > 0 && (
                    <Button onClick={onProceed} className="shrink-0 gap-2 font-semibold shadow-xs">
                        <ShoppingCart className="h-4 w-4" />
                        <span>Review Order ({totalCartItems})</span>
                        <ArrowRight className="h-4 w-4" />
                    </Button>
                )}
            </div>

            {/* Main Split Grid (Desktop: Left Catalogue, Right Persistent Summary Panel) */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {/* Left Catalogue Section (lg:col-span-8 or 12 if no cart) */}
                <div className="lg:col-span-8 space-y-4">
                    {/* Search & Category Filter Controls */}
                    <div className="space-y-2.5 bg-card border border-border/80 rounded-xl p-3.5 shadow-2xs">
                        <form onSubmit={handleSearchSubmit} className="flex gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder="Search by SKU, product name..."
                                    value={searchLocal}
                                    onChange={(e) => setSearchLocal(e.target.value)}
                                    className="pl-9 text-xs h-9"
                                />
                                {searchLocal && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearchLocal('');
                                            onFilterChange({ search: '', page: 1 });
                                        }}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-0.5"
                                        aria-label="Clear search"
                                    >
                                        <X className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>
                            <Button type="submit" variant="secondary" size="sm" className="h-9 text-xs">
                                Search
                            </Button>
                            {(filters.search || filters.category_id) && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-9 text-xs text-muted-foreground hover:text-foreground"
                                    onClick={() => {
                                        setSearchLocal('');
                                        onFilterChange({ search: '', category_id: '', page: 1 });
                                    }}
                                >
                                    Reset
                                </Button>
                            )}
                        </form>

                        {/* Category Chips with Horizontal Scroll Support on Mobile */}
                        {categories.length > 0 && (
                            <div className="flex items-center gap-1.5 overflow-x-auto pb-1 pt-0.5 scrollbar-none">
                                <span className="text-xs text-muted-foreground shrink-0 flex items-center gap-1 font-medium">
                                    <Filter className="h-3 w-3" />
                                </span>
                                <Badge
                                    variant={!filters.category_id ? 'default' : 'outline'}
                                    className="cursor-pointer text-xs shrink-0 select-none transition-all py-1 px-2.5"
                                    onClick={() => handleCategoryClick('')}
                                >
                                    All ({products.total})
                                </Badge>
                                {categories.map((cat) => {
                                    const isSelected = filters.category_id === String(cat.id);
                                    return (
                                        <Badge
                                            key={cat.id}
                                            variant={isSelected ? 'default' : 'outline'}
                                            className="cursor-pointer text-xs shrink-0 select-none transition-all py-1 px-2.5"
                                            onClick={() => handleCategoryClick(String(cat.id))}
                                        >
                                            {cat.name}
                                        </Badge>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* Product Grid: 1-col on Mobile (<640px), 2-col on Tablet (640-1023px), 2/3-col on Desktop (1024px+) */}
                    {products.data.length === 0 ? (
                        <div className="border border-dashed border-border rounded-xl py-16 text-center space-y-3 bg-card/40">
                            <PackageSearch className="mx-auto h-10 w-10 text-muted-foreground/60 stroke-[1.5]" />
                            <h3 className="text-sm font-semibold text-foreground">No products found</h3>
                            <p className="text-xs text-muted-foreground max-w-sm mx-auto leading-relaxed">
                                No active products matched your search or category criteria. Try resetting your filters.
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                            {products.data.map((product) => {
                                const cartItem = cart.find((item) => item.product.id === product.id);

                                return (
                                    <ProductOrderCard
                                        key={product.id}
                                        product={product}
                                        cartItem={cartItem}
                                        onUpdateCart={onUpdateCart}
                                        onRemoveFromCart={onRemoveFromCart}
                                    />
                                );
                            })}
                        </div>
                    )}

                    {/* Server-Driven Pagination Controls */}
                    {products.last_page > 1 && (
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-border text-xs text-muted-foreground">
                            <div>
                                Page <strong className="text-foreground font-semibold">{products.current_page}</strong> of{' '}
                                <strong className="text-foreground font-semibold">{products.last_page}</strong> ({products.total} products)
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={products.current_page <= 1}
                                    onClick={() => onFilterChange({ page: products.current_page - 1 })}
                                    className="h-8 text-xs"
                                >
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={products.current_page >= products.last_page}
                                    onClick={() => onFilterChange({ page: products.current_page + 1 })}
                                    className="h-8 text-xs"
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Right Desktop Anchored Order Summary Panel (>=1024px) */}
                <div className="hidden lg:block lg:col-span-4 sticky top-20">
                    <OrderSummaryPanel
                        customer={customer}
                        cart={cart}
                        onProceedToReview={onProceed}
                        onRemoveItem={onRemoveFromCart}
                        onUpdateQuantity={(productId, qty) => {
                            const product = products.data.find((p) => p.id === productId);
                            if (product) {
                                const existing = cart.find((c) => c.product.id === productId);
                                onUpdateCart(product, qty, existing?.unit_price || product.default_selling_price.toFixed(2));
                            }
                        }}
                    />
                </div>
            </div>
        </div>
    );
};
