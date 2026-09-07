import React, { useState, useEffect } from 'react';
import { CatalogProduct, CartLineItem } from '@/types/order';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Package, Plus, Check, AlertCircle } from 'lucide-react';
import { QuantityStepper } from '@/Components/Salesman/QuantityStepper';

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
        setQuantity(newQty);
        if (cartItem) {
            onUpdateCart(product, newQty, unitPrice);
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
        <Card
            className={`overflow-hidden transition-all duration-200 rounded-xl border ${
                isInCart
                    ? 'border-primary/80 ring-1 ring-primary/20 bg-primary/[0.02]'
                    : 'border-border/80 hover:border-border hover:shadow-xs'
            }`}
        >
            <div className="flex flex-col h-full">
                {/* Product Image & Badges */}
                <div className="relative aspect-[16/10] bg-muted/40 flex items-center justify-center overflow-hidden border-b border-border/70">
                    {product.primary_image_url ? (
                        <img
                            src={product.primary_image_url}
                            alt={product.name}
                            className="object-cover w-full h-full"
                            loading="lazy"
                        />
                    ) : (
                        <div className="flex flex-col items-center justify-center text-muted-foreground/50">
                            <Package className="h-8 w-8 stroke-[1.5]" />
                            <span className="text-[10px] mt-1 font-mono uppercase tracking-wider">No Image</span>
                        </div>
                    )}
                    <div className="absolute top-2 left-2 flex flex-wrap gap-1">
                        <Badge variant="secondary" className="font-mono text-[10px] bg-background/90 backdrop-blur-xs shadow-2xs">
                            {product.sku}
                        </Badge>
                        {product.category && (
                            <Badge variant="outline" className="text-[10px] bg-background/80 backdrop-blur-xs shadow-2xs">
                                {product.category.name}
                            </Badge>
                        )}
                    </div>
                    {isInCart && (
                        <div className="absolute top-2 right-2">
                            <Badge className="bg-primary text-primary-foreground text-[10px] gap-1 shadow-2xs">
                                <Check className="h-3 w-3" /> In Cart ({cartItem.quantity})
                            </Badge>
                        </div>
                    )}
                </div>

                {/* Product Details */}
                <CardContent className="p-3.5 flex-1 flex flex-col justify-between space-y-3">
                    <div>
                        <h3 className="font-semibold text-xs line-clamp-2 text-foreground tracking-tight" title={product.name}>
                            {product.name}
                        </h3>
                        <p className="text-[11px] text-muted-foreground mt-0.5 font-mono">
                            Unit: <span className="font-sans font-medium text-foreground">{product.unit}</span>
                        </p>
                    </div>

                    {/* Pricing Display & Boundary Indicator */}
                    <div className="space-y-1.5 pt-2 border-t border-border/60">
                        <div className="flex items-baseline justify-between">
                            <span className="text-xs text-muted-foreground">Price:</span>
                            <div className="text-right font-mono">
                                <span className="text-sm font-bold text-foreground">
                                    ${parseFloat(unitPrice || '0').toFixed(2)}
                                </span>
                                <span className="text-[10px] text-muted-foreground ml-1.5 line-through">
                                    ${product.mrp.toFixed(2)}
                                </span>
                            </div>
                        </div>

                        {/* Allowed Price Bound Info */}
                        <div className="flex items-center justify-between text-[10px] text-muted-foreground bg-muted/30 px-2 py-1 rounded">
                            <span>Min: ${product.minimum_allowed_price.toFixed(2)}</span>
                            <span>List: ${product.mrp.toFixed(2)}</span>
                            {product.tax_profile && (
                                <span className="text-emerald-600 dark:text-emerald-400 font-medium">
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
                                <QuantityStepper
                                    value={quantity}
                                    min={1}
                                    max={999999}
                                    onChange={handleQuantityChange}
                                    ariaLabel={`quantity for ${product.name}`}
                                    className="w-full justify-between h-8"
                                />
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
                                    className="text-xs h-8 text-destructive hover:bg-destructive/10 shrink-0 px-2"
                                    onClick={() => onRemoveFromCart(product.id)}
                                    aria-label={`Remove ${product.name} from cart`}
                                >
                                    Remove
                                </Button>
                            </div>
                        ) : (
                            <Button
                                type="button"
                                size="sm"
                                className="w-full text-xs h-8 gap-1.5 cursor-pointer"
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
