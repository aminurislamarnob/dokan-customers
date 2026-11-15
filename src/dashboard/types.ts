export interface CustomerItem {
    id: number;
    name: string;
    email: string;
    phone: string;
    orders_count: number;
    total_spent: number;
    total_spent_formatted: string;
    date_registered: string;
    billing_address: string;
}

export interface CustomerOrderItem {
    id: number;
    number?: string;
    status: string;
    date_created: string;
    total: string;
    earning?: string | number;
    refunds?: Array< {
        id: number;
        refund?: string;
        total: string;
    } >;
}

export interface RouterInjectedProps {
    navigate?: ( to: string ) => void;
    params?: {
        id?: string;
    };
    location?: {
        pathname?: string;
        search?: string;
        hash?: string;
    };
}
