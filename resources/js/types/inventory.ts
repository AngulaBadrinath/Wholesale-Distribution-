export interface Warehouse {
    id: number;
    code: string;
    name: string;
    is_default: boolean;
    is_active: boolean;
}

export type StockStatusType = 'IN_STOCK' | 'LOW_STOCK' | 'OUT_OF_STOCK';

export interface InventoryBalanceItem {
    id: number;
    warehouse_id: number;
    warehouse_code: string;
    warehouse_name: string;
    product_id: number;
    product_name: string;
    sku: string;
    unit: string;
    product_status: string;
    category_name: string | null;
    bin_location: string | null;
    reorder_point: number;
    safety_stock: number;
    on_hand_quantity: number;
    reserved_quantity: number;
    available_quantity: number;
    damaged_quantity: number;
    stock_status: StockStatusType;
    stock_status_label: string;
    stock_status_badge_variant: string;
    is_active: boolean;
    version: number;
    last_counted_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface InventorySummaryCounts {
    all_items: number;
    in_stock_items: number;
    low_stock_items: number;
    out_of_stock_items: number;
}

export interface InventoryFilters {
    search?: string;
    warehouse_id?: number | null;
    stock_status?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedInventoryBalances {
    data: InventoryBalanceItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}
