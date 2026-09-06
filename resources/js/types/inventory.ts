export interface Warehouse {
    id: number;
    code: string;
    name: string;
    is_default: boolean;
    is_active: boolean;
}

export interface CategoryOption {
    id: number;
    code: string;
    name: string;
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
    category_id: number | null;
    category_name: string | null;
    bin_location: string | null;
    reorder_point: number;
    safety_stock: number;
    on_hand_quantity: number;
    reserved_quantity: number;
    available_quantity: number;
    damaged_quantity: number;
    commercial_allocated_quantity: number;
    commercial_unallocated_demand: number;
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

export interface InventorySummaryMetrics {
    total_skus: number;
    total_on_hand_units: number;
    total_reserved_units: number;
    total_available_units: number;
    total_allocated_units: number;
    total_damaged_units: number;
    in_stock_skus: number;
    low_stock_skus: number;
    out_of_stock_skus: number;
    all_items: number;
    in_stock_items: number;
    low_stock_items: number;
    out_of_stock_items: number;
}

export interface ActiveAllocationItem {
    id: number;
    allocation_number: string;
    order_id: number;
    order_number: string;
    customer_name: string;
    customer_code: string;
    order_status: string;
    order_status_label: string;
    allocated_quantity: number;
    reserved_quantity: number;
    picked_quantity: number;
    dispatched_quantity: number;
    delivered_quantity: number;
    status: string;
    status_label: string;
    status_badge_variant: string;
    allocated_by_name: string;
    allocated_at: string | null;
}

export interface CommercialSummary {
    allocated_quantity: number;
    unallocated_demand: number;
    net_coverage: number;
    is_surplus: boolean;
    coverage_status: 'SURPLUS' | 'DEFICIT';
}

export interface CompositionProportions {
    on_hand_total: number;
    available_percent: number;
    reserved_percent: number;
    damaged_percent: number;
}

export interface InventoryDetailPayload {
    balance: InventoryBalanceItem;
    commercial_summary: CommercialSummary;
    composition_proportions: CompositionProportions;
    active_allocations: ActiveAllocationItem[];
}

export interface InventoryFilters {
    search?: string;
    warehouse_id?: number | null;
    category_id?: number | null;
    has_damaged?: boolean;
    has_allocations?: boolean;
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
