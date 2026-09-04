import React, { useState, useEffect } from 'react';
import { CatalogProduct, CartLineItem } from '@/types/order';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Package, Plus, Minus, Check, AlertCircle } from 'lucide-react';

interface ProductOrderCardProps {
    product: CatalogProduct;
    cartItem?: CartLineItem;
    onUpdateCart: (product: CatalogProduct, quantity: number, unitPrice: string) => void;
    onRemoveFromCart: (productId: number) => void;
}

export const ProductOrderCard: React.FC<ProductOrderCardProps> = ({
    product,
    cartItem,
    onUpdateCart,
    onRemoveFromCart,
}) => {
    const [quantity, setQuantity] = useState<number>(cartItem?.quantity || 1);
    const [unitPrice, setUnitPrice] = useState<string>(
        cartItem?.unit_price || product.default_selling_price.toFixed(2)
    );
    const [priceError, setPriceError] = useState<string | null>(null);

    useEffect(() => {
        if (cartItem) {
            setQuantity(cartItem.quantity);
            setUnitPrice(cartItem.unit_price);
        }
    }, [cartItem]);

    const handleQuantityChange = (newQty: number) => {
        const clampedQty = Math.max(1, Math.min(999999, Math.floor(newQty || 1)));
        setQuantity(clampedQty);
        if (cartItem) {
            onUpdateCart(product, clampedQty, unitPrice);
        }
    };

    const handlePriceBlur = () => {
        const numPrice = parseFloat(unitPrice);
        if (isNaN(numPrice)) {
            setUnitPrice(product.default_selling_price.toFixed(2));
            setPriceError(null);
            if (cartItem) {
                onUpdateCart(product, quantity, product.default_selling_price.toFixed(2));
            }
            return;
        }

        if (numPrice < product.minimum_allowed_price) {
            setPriceError(`Min: $${product.minimum_allowed_price.toFixed(2)}`);
            setUnitPrice(product.minimum_allowed_price.toFixed(2));
            if (cartItem) {
                onUpdateCart(product, quantity, product.minimum_allowed_price.toFixed(2));
            }
            return;
        }

        if (numPrice > product.mrp) {
            setPriceError(`Max MRP: $${product.mrp.toFixed(2)}`);
            setUnitPrice(product.mrp.toFixed(2));
            if (cartItem) {
                onUpdateCart(product, quantity, product.mrp.toFixed(2));
            }
            return;
        }

        setPriceError(null);
        const formatted = numPrice.toFixed(2);
        setUnitPrice(formatted);
        if (cartItem) {
            onUpdateCart(product, quantity, formatted);
        }
    };

    const handleAddToCart = () => {
        const numPrice = parseFloat(unitPrice);
        const validPrice = isNaN(numPrice) ? product.default_selling_price : numPrice;
        const clampedPrice = Math.max(product.minimum_allowed_price, Math.min(product.mrp, validPrice));
        const formattedPrice = clampedPrice.toFixed(2);
        setUnitPrice(formattedPrice);
        onUpdateCart(product, quantity, formattedPrice);
    };

    const isInCart = !!cartItem;

    return (
        <Card className={`overflow-hidden transition-all duration-200 ${isInCart ? 'border-primary/80 ring-1 ring-primary/20 bg-primary/[0.02]' : 'hover:border-muted-foreground/30'}`}>
            <div className="flex flex-col h-full">
                {/* Product Image & Badges */}
                <div className="relative aspect-[4/3] bg-muted/40 flex items-center justify-center overflow-hidden border-b">
                    {product.primary_image_url ? (
                        <img
                            src={product.primary_image_url}
                            alt={product.name}
                            className="object-cover w-full h-full"
                            loading="lazy"
                        />
                    ) : (
                        <div className="flex flex-col items-center justify-center text-muted-foreground/50">
                            <Package className="h-10 w-10 stroke-[1.5]" />
                            <span className="text-[10px] mt-1 font-mono uppercase tracking-wider">No Image</span>
                        </div>
                    )}
                    <div className="absolute top-2 left-2 flex flex-wrap gap-1">
                        <Badge variant="secondary" className="font-mono text-[10px] bg-background/90 backdrop-blur-sm shadow-sm">
                            {product.sku}
                        </Badge>
                        {product.category && (
                            <Badge variant="outline" className="text-[10px] bg-background/80 backdrop-blur-sm shadow-sm">
                                {product.category.name}
                            </Badge>
                        )}
                    </div>
                    {isInCart && (
                        <div className="absolute top-2 right-2">
                            <Badge className="bg-primary text-primary-foreground text-[10px] gap-1 shadow-sm">
                                <Check className="h-3 w-3" /> In Cart ({cartItem.quantity})
                            </Badge>
                        </div>
                    )}
                </div>

                {/* Product Details */}
                <CardContent className="p-4 flex-1 flex flex-col justify-between space-y-3">
                    <div>
                        <div className="flex items-start justify-between gap-2">
                            <h3 className="font-semibold text-sm line-clamp-2 text-foreground" title={product.name}>
                                {product.name}
                            </h3>
                        </div>
                        <p className="text-xs text-muted-foreground mt-0.5 font-mono">
                            Unit: <span className="font-sans font-medium text-foreground">{product.unit}</span>
                        </p>
                    </div>

                    {/* Pricing Display & Boundary Indicator */}
                    <div className="space-y-1.5 pt-2 border-t">
                        <div className="flex items-baseline justify-between">
                            <span className="text-xs text-muted-foreground">Price / {product.unit}:</span>
                            <div className="text-right">
                                <span className="text-base font-bold text-foreground font-mono">
                                    ${parseFloat(unitPrice || '0').toFixed(2)}
                                </span>
                                <span className="text-[10px] text-muted-foreground ml-1 line-through font-mono">
                                    MRP ${product.mrp.toFixed(2)}
                                </span>
                            </div>
                        </div>

                        {/* Allowed Price Bound Info */}
                        <div className="flex items-center justify-between text-[10px] text-muted-foreground bg-muted/30 px-2 py-1 rounded">
                            <span>Min: ${product.minimum_allowed_price.toFixed(2)}</span>
                            <span>List: ${product.mrp.toFixed(2)}</span>
                            {product.tax_profile && (
                                <span className="text-emerald-700 dark:text-emerald-400 font-medium">
                                    Tax: {product.tax_profile.formatted_rate}
                                </span>
                            )}
                        </div>

                        {priceError && (
                            <div className="flex items-center gap-1 text-[11px] text-destructive">
                                <AlertCircle className="h-3 w-3 shrink-0" />
                                <span>{priceError}</span>
                            </div>
                        )}
                    </div>

                    {/* Quantity & Action Controls */}
                    <div className="pt-2 space-y-2">
                        <div className="grid grid-cols-2 gap-2">
                            {/* Quantity Stepper */}
                            <div>
                                <label className="text-[10px] font-medium text-muted-foreground block mb-1">
                                    Quantity
                                </label>
                                <div className="flex items-center border rounded-md bg-background">
                                    <button
                                        type="button"
                                        onClick={() => handleQuantityChange(quantity - 1)}
                                        className="h-8 w-8 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-l transition-colors"
                                        aria-label="Decrease quantity"
                                    >
                                        <Minus className="h-3.5 w-3.5" />
                                    </button>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={999999}
                                        value={quantity}
                                        onChange={(e) => handleQuantityChange(parseInt(e.target.value, 10))}
                                        className="h-8 border-0 text-center font-mono text-xs focus-visible:ring-0 p-0"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => handleQuantityChange(quantity + 1)}
                                        className="h-8 w-8 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-r transition-colors"
                                        aria-label="Increase quantity"
                                    >
                                        <Plus className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            {/* Unit Price Customizer */}
                            <div>
                                <label className="text-[10px] font-medium text-muted-foreground block mb-1">
                                    Selling Price ($)
                                </label>
                                <Input
                                    type="text"
                                    value={unitPrice}
                                    onChange={(e) => setUnitPrice(e.target.value)}
                                    onBlur={handlePriceBlur}
                                    placeholder={product.default_selling_price.toFixed(2)}
                                    className="h-8 text-right font-mono text-xs"
                                />
                            </div>
                        </div>

                        {/* Add / Update / Remove Button */}
                        {isInCart ? (
                            <div className="flex gap-1.5">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    className="w-full text-xs h-8"
                                    onClick={handleAddToCart}
                                >
                                    Update Cart
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="text-xs h-8 text-destructive hover:bg-destructive/10"
                                    onClick={() => onRemoveFromCart(product.id)}
                                >
                                    Remove
                                </Button>
                            </div>
                        ) : (
                            <Button
                                type="button"
                                size="sm"
                                className="w-full text-xs h-8 gap-1.5"
                                onClick={handleAddToCart}
                            >
                                <Plus className="h-3.5 w-3.5" />
                                <span>Add to Cart</span>
                            </Button>
                        )}
                    </div>
                </CardContent>
            </div>
        </Card>
    );
};
