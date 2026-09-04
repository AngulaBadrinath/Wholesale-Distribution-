import React, { useState } from 'react';
import { CatalogProduct, CartLineItem } from '@/types/order';
import { ProductOrderCard } from './ProductOrderCard';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Search, Filter, ShoppingCart, ArrowRight, PackageSearch } from 'lucide-react';

interface ProductCatalogStepProps {
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
            {/* Header & Cart Context */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 className="text-xl font-bold tracking-tight text-foreground">Product Catalogue</h2>
                    <p className="text-sm text-muted-foreground">
                        Browse active products, adjust ordered quantities, and set authorized selling prices.
                    </p>
                </div>
                {cart.length > 0 && (
                    <Button onClick={onProceed} className="shrink-0 gap-2">
                        <ShoppingCart className="h-4 w-4" />
                        <span>Review Order ({totalCartItems})</span>
                        <ArrowRight className="h-4 w-4" />
                    </Button>
                )}
            </div>

            {/* Search & Category Filter Controls */}
            <div className="space-y-3">
                <form onSubmit={handleSearchSubmit} className="flex gap-2 max-w-md">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Search by SKU, product name..."
                            value={searchLocal}
                            onChange={(e) => setSearchLocal(e.target.value)}
                            className="pl-9 text-sm"
                        />
                    </div>
                    <Button type="submit" variant="secondary" size="sm">
                        Search
                    </Button>
                    {(filters.search || filters.category_id) && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearchLocal('');
                                onFilterChange({ search: '', category_id: '', page: 1 });
                            }}
                        >
                            Reset
                        </Button>
                    )}
                </form>

                {/* Category Chips */}
                {categories.length > 0 && (
                    <div className="flex flex-wrap items-center gap-1.5 pt-1">
                        <span className="text-xs text-muted-foreground mr-1 flex items-center gap-1">
                            <Filter className="h-3 w-3" /> Categories:
                        </span>
                        <Badge
                            variant={!filters.category_id ? 'default' : 'outline'}
                            className="cursor-pointer text-xs"
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
                                    className="cursor-pointer text-xs transition-colors"
                                    onClick={() => handleCategoryClick(String(cat.id))}
                                >
                                    {cat.name}
                                </Badge>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Product Grid */}
            {products.data.length === 0 ? (
                <div className="border border-dashed rounded-lg py-16 text-center space-y-3">
                    <PackageSearch className="mx-auto h-10 w-10 text-muted-foreground/60" />
                    <h3 className="text-base font-semibold text-foreground">No products found</h3>
                    <p className="text-sm text-muted-foreground max-w-sm mx-auto">
                        No active products matched your search or category criteria. Try resetting filters.
                    </p>
                </div>
            ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
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

            {/* Pagination Controls */}
            {products.last_page > 1 && (
                <div className="flex items-center justify-between pt-4 border-t text-sm text-muted-foreground">
                    <div>
                        Page {products.current_page} of {products.last_page} ({products.total} products)
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={products.current_page <= 1}
                            onClick={() => onFilterChange({ page: products.current_page - 1 })}
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={products.current_page >= products.last_page}
                            onClick={() => onFilterChange({ page: products.current_page + 1 })}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
};
