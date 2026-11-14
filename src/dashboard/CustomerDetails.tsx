import {
    useCallback,
    useEffect,
    useMemo,
    useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import {
    DataViews,
    DateTimeHtml,
    DokanBadge,
    PriceHtml,
} from '@dokan/components';
import { Card } from '@getdokan/dokan-ui';
import { fetchPaginated } from './api';
import type {
    CustomerItem,
    CustomerOrderItem,
    RouterInjectedProps,
} from './types';

declare global {
    interface Window {
        dokan?: {
            urls?: {
                ordersUrl?: string;
                legacyOrdersUrl?: string;
            };
            nonces?: {
                viewOrder?: string;
            };
            orderStatuses?: Array< {
                value: string;
                label: string;
            } >;
        };
    }
}

const ORDER_FIELDS = [ 'order', 'date_created', 'status', 'total', 'earning' ];

const ORDER_LAYOUT = {
    styles: {
        order: { width: '20%' },
        date_created: { width: '25%' },
        status: { width: '20%' },
        total: { width: '20%' },
        earning: { width: '15%' },
    },
};

function getStatusBadgeVariant( status: string ) {
    switch ( status ) {
        case 'completed':
            return 'success';
        case 'processing':
            return 'info';
        case 'on-hold':
            return 'warning';
        case 'pending':
        case 'failed':
            return 'danger';
        case 'cancelled':
        case 'refunded':
        default:
            return 'secondary';
    }
}

function getStatusLabel( status: string ): string {
    const statuses = window?.dokan?.orderStatuses;

    if ( Array.isArray( statuses ) ) {
        const prefixed = `wc-${ status }`;
        const found = statuses.find(
            ( item ) => item.value === prefixed || item.value === status
        );
        if ( found ) {
            return found.label;
        }
    }

    if ( ! status ) {
        return '\u2013';
    }

    return status.charAt( 0 ).toUpperCase() + status.slice( 1 );
}

export function getOrderDetailsUrl( orderId: number ): string {
    const baseUrl =
        window?.dokan?.urls?.legacyOrdersUrl ||
        window?.dokan?.urls?.ordersUrl ||
        '';
    const nonce = window?.dokan?.nonces?.viewOrder || '';

    if ( ! baseUrl ) {
        return `#/orders/${ orderId }`;
    }

    try {
        const url = new URL( baseUrl, window.location.origin );
        url.searchParams.set( 'order_id', String( orderId ) );

        if ( nonce ) {
            url.searchParams.set( '_wpnonce', nonce );
        }

        return url.toString();
    } catch {
        return `#/orders/${ orderId }`;
    }
}

function openOrderDetails( orderId: number ): void {
    const url = getOrderDetailsUrl( orderId );
    if ( url && url !== '#' ) {
        window.location.href = url;
    }
}

function getCustomerIdFromPropsOrHash( paramId?: string ): number {
    if ( paramId ) {
        const parsed = parseInt( paramId, 10 );
        if ( Number.isFinite( parsed ) && parsed > 0 ) {
            return parsed;
        }
    }

    if ( typeof window !== 'undefined' ) {
        const match = ( window.location.hash || '' ).match(
            /customers\/(\d+)/
        );
        if ( match && match[ 1 ] ) {
            return parseInt( match[ 1 ], 10 );
        }
    }

    return 0;
}

interface CustomerOrdersTableProps {
    customerId: number;
    statusFilter: 'paid' | 'other';
    namespace: string;
    heading: string;
    headingStatuses: string;
    emptyTitle: string;
    emptyDescription: string;
    fields: Array< Record< string, unknown > >;
}

function CustomerOrdersTable( {
    customerId,
    statusFilter,
    namespace,
    heading,
    headingStatuses,
    emptyTitle,
    emptyDescription,
    fields,
}: CustomerOrdersTableProps ) {
    const [ orders, setOrders ] = useState< CustomerOrderItem[] >( [] );
    const [ isLoading, setIsLoading ] = useState< boolean >( true );
    const [ totalItems, setTotalItems ] = useState< number >( 0 );
    const [ totalPages, setTotalPages ] = useState< number >( 0 );
    const [ view, setView ] = useState( {
        perPage: 10,
        page: 1,
        search: '',
        type: 'table' as const,
        fields: ORDER_FIELDS,
        layout: ORDER_LAYOUT,
    } );

    useEffect( () => {
        let cancelled = false;
        setIsLoading( true );

        const path = addQueryArgs( '/dokan/v1/orders', {
            customer_id: String( customerId ),
            status: statusFilter,
            page: view.page,
            per_page: view.perPage,
        } );

        fetchPaginated< CustomerOrderItem >( path )
            .then( ( result ) => {
                if ( ! cancelled ) {
                    setOrders( result.items );
                    setTotalItems( result.totalItems );
                    setTotalPages( result.totalPages );
                }
            } )
            .catch( () => {
                if ( ! cancelled ) {
                    setOrders( [] );
                    setTotalItems( 0 );
                    setTotalPages( 0 );
                }
            } )
            .finally( () => {
                if ( ! cancelled ) {
                    setIsLoading( false );
                }
            } );

        return () => {
            cancelled = true;
        };
    }, [ customerId, statusFilter, view.page, view.perPage ] );

    return (
        <div className="dokan-customers-orders-section">
            <h3 className="dokan-customers-orders-heading">
                { heading }{ ' ' }
                <span className="dokan-customers-orders-heading-statuses">
                    { headingStatuses }
                </span>
            </h3>

            <DataViews
                namespace={ namespace }
                data={ orders }
                fields={ fields }
                view={ view }
                onChangeView={ ( nextView: typeof view ) =>
                    setView( ( prev ) => ( {
                        ...prev,
                        ...nextView,
                    } ) )
                }
                getItemId={ ( item: CustomerOrderItem ) => String( item.id ) }
                isLoading={ isLoading }
                paginationInfo={ {
                    totalItems,
                    totalPages,
                } }
                search={ false }
                onClickItem={ ( item: CustomerOrderItem ) =>
                    openOrderDetails( item.id )
                }
                isItemClickable={ () => true }
                emptyTitle={ emptyTitle }
                emptyDescription={ emptyDescription }
            />
        </div>
    );
}

export default function CustomerDetails( {
    navigate,
    params,
}: RouterInjectedProps ) {
    const customerId = useMemo(
        () => getCustomerIdFromPropsOrHash( params?.id ),
        [ params?.id ]
    );

    const [ customer, setCustomer ] = useState< CustomerItem | null >( null );
    const [ isCustomerLoading, setIsCustomerLoading ] =
        useState< boolean >( true );
    const [ notFound, setNotFound ] = useState< boolean >( false );

    const handleBack = useCallback(
        ( e?: React.MouseEvent< HTMLAnchorElement > ) => {
            if ( e ) {
                e.preventDefault();
            }
            if ( typeof navigate === 'function' ) {
                navigate( '/customers' );
            } else {
                window.location.hash = '#/customers';
            }
        },
        [ navigate ]
    );

    useEffect( () => {
        if ( ! customerId ) {
            setIsCustomerLoading( false );
            setNotFound( true );
            return;
        }

        let cancelled = false;
        setIsCustomerLoading( true );
        setNotFound( false );

        apiFetch< CustomerItem >( {
            path: `/dokan-customers/v1/customers/${ customerId }`,
        } )
            .then( ( result ) => {
                if ( ! cancelled ) {
                    setCustomer( result );
                    setNotFound( false );
                }
            } )
            .catch( () => {
                if ( ! cancelled ) {
                    setCustomer( null );
                    setNotFound( true );
                }
            } )
            .finally( () => {
                if ( ! cancelled ) {
                    setIsCustomerLoading( false );
                }
            } );

        return () => {
            cancelled = true;
        };
    }, [ customerId ] );

    const fields = useMemo(
        () => [
            {
                id: 'order',
                label: __( 'Order #', 'dokan-customers' ),
                enableSorting: false,
                render: ( { item }: { item: CustomerOrderItem } ) => {
                    const orderUrl = getOrderDetailsUrl( item.id );
                    return (
                        <a
                            href={ orderUrl }
                            className="dokan-customers-order-link"
                            onClick={ ( e ) => {
                                e.preventDefault();
                                e.stopPropagation();
                                openOrderDetails( item.id );
                            } }
                        >
                            { `#${ item.number || item.id }` }
                        </a>
                    );
                },
            },
            {
                id: 'date_created',
                label: __( 'Date', 'dokan-customers' ),
                enableSorting: false,
                render: ( { item }: { item: CustomerOrderItem } ) => (
                    <DateTimeHtml.Date date={ item.date_created } />
                ),
            },
            {
                id: 'status',
                label: __( 'Status', 'dokan-customers' ),
                enableSorting: false,
                render: ( { item }: { item: CustomerOrderItem } ) => (
                    <DokanBadge
                        variant={ getStatusBadgeVariant( item.status ) }
                        label={ getStatusLabel( item.status ) }
                    />
                ),
            },
            {
                id: 'total',
                label: __( 'Total', 'dokan-customers' ),
                enableSorting: false,
                render: ( { item }: { item: CustomerOrderItem } ) => (
                    <PriceHtml price={ item.total } />
                ),
            },
            {
                id: 'earning',
                label: __( 'Earning', 'dokan-customers' ),
                enableSorting: false,
                render: ( { item }: { item: CustomerOrderItem } ) =>
                    item.earning !== undefined && item.earning !== null ? (
                        <PriceHtml price={ item.earning } />
                    ) : (
                        <span>{ '\u2013' }</span>
                    ),
            },
        ],
        []
    );

    let bodyContent: JSX.Element;

    if ( isCustomerLoading ) {
        bodyContent = (
            <Card className="dokan-customers-summary-card">
                <Card.Body>
                    <p className="dokan-customers-loading-text">
                        { __( 'Loading customer details…', 'dokan-customers' ) }
                    </p>
                </Card.Body>
            </Card>
        );
    } else if ( notFound || ! customer ) {
        bodyContent = (
            <Card className="dokan-customers-summary-card dokan-customers-not-found">
                <Card.Header>
                    <Card.Title className="dokan-customers-summary-name">
                        { __( 'Customer not found', 'dokan-customers' ) }
                    </Card.Title>
                </Card.Header>
                <Card.Body>
                    <p className="dokan-customers-not-found-text">
                        { __(
                            'This customer does not exist or has not placed any orders with your store.',
                            'dokan-customers'
                        ) }
                    </p>
                </Card.Body>
            </Card>
        );
    } else {
        bodyContent = (
            <>
                <Card className="dokan-customers-summary-card">
                    <Card.Header className="dokan-customers-summary-header">
                        <Card.Title className="dokan-customers-summary-name">
                            { customer.name }
                        </Card.Title>
                    </Card.Header>

                    <Card.Body>
                        <dl className="dokan-customers-summary-grid">
                            <div className="dokan-customers-summary-item">
                                <dt>{ __( 'Email', 'dokan-customers' ) }</dt>
                                <dd>
                                    { customer.email ? (
                                        <a
                                            href={ `mailto:${ customer.email }` }
                                            className="dokan-customers-email-link"
                                        >
                                            { customer.email }
                                        </a>
                                    ) : (
                                        '\u2013'
                                    ) }
                                </dd>
                            </div>

                            <div className="dokan-customers-summary-item">
                                <dt>{ __( 'Phone', 'dokan-customers' ) }</dt>
                                <dd>{ customer.phone || '\u2013' }</dd>
                            </div>

                            <div className="dokan-customers-summary-item">
                                <dt>
                                    { __( 'Registered At', 'dokan-customers' ) }
                                </dt>
                                <dd>
                                    <DateTimeHtml.Date
                                        date={ customer.date_registered }
                                    />
                                </dd>
                            </div>

                            <div className="dokan-customers-summary-item">
                                <dt>
                                    { __( 'Paid Orders', 'dokan-customers' ) }
                                </dt>
                                <dd className="dokan-customers-summary-orders-count dokan-customers-summary-metric">
                                    { customer.orders_count }
                                </dd>
                            </div>

                            <div className="dokan-customers-summary-item">
                                <dt>
                                    { __( 'Total Spend', 'dokan-customers' ) }
                                </dt>
                                <dd className="dokan-customers-summary-total-spent dokan-customers-summary-metric">
                                    <PriceHtml price={ customer.total_spent } />
                                </dd>
                            </div>

                            <div className="dokan-customers-summary-item dokan-customers-summary-item-wide">
                                <dt>
                                    { __(
                                        'Billing Address',
                                        'dokan-customers'
                                    ) }
                                </dt>
                                <dd>{ customer.billing_address || '\u2013' }</dd>
                            </div>
                        </dl>
                    </Card.Body>
                </Card>

                <CustomerOrdersTable
                    customerId={ customer.id }
                    statusFilter="paid"
                    namespace="dokan-customers-paid-orders-data-view"
                    heading={ __( 'Paid Orders', 'dokan-customers' ) }
                    headingStatuses={ __(
                        '(Processing, Completed, On hold)',
                        'dokan-customers'
                    ) }
                    emptyTitle={ __( 'No paid orders found', 'dokan-customers' ) }
                    emptyDescription={ __(
                        'No processing, completed, or on-hold orders found for this customer.',
                        'dokan-customers'
                    ) }
                    fields={ fields }
                />

                <CustomerOrdersTable
                    customerId={ customer.id }
                    statusFilter="other"
                    namespace="dokan-customers-other-orders-data-view"
                    heading={ __( 'Other Orders', 'dokan-customers' ) }
                    headingStatuses={ __(
                        '(Pending payment, Failed, Cancelled, Refunded)',
                        'dokan-customers'
                    ) }
                    emptyTitle={ __( 'No other orders found', 'dokan-customers' ) }
                    emptyDescription={ __(
                        'No pending, failed, cancelled, or refunded orders found for this customer.',
                        'dokan-customers'
                    ) }
                    fields={ fields }
                />
            </>
        );
    }

    return (
        <div className="dokan-customers-details-page">
            <div className="dokan-customers-back-bar">
                <a
                    href="#/customers"
                    className="dokan-customers-back-link"
                    onClick={ handleBack }
                >
                    { __( '← Back to Customers', 'dokan-customers' ) }
                </a>
            </div>
            { bodyContent }
        </div>
    );
}
