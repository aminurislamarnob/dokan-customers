import {
    useCallback,
    useEffect,
    useMemo,
    useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Fill } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import {
    DataViews,
    DateTimeHtml,
    DokanButton,
    PriceHtml,
} from '@dokan/components';
import { fetchPaginated } from './api';
import type { CustomerItem, RouterInjectedProps } from './types';

const DEFAULT_FIELDS = [
    'name',
    'email',
    'phone',
    'orders_count',
    'total_spent',
    'date_registered',
    'billing_address',
];

function getInitialQueryParams( locationSearch?: string ): {
    page: number;
    search: string;
} {
    let rawSearch = locationSearch || '';
    if ( ! rawSearch && typeof window !== 'undefined' ) {
        const hash = window.location.hash || '';
        const qIndex = hash.indexOf( '?' );
        if ( qIndex !== -1 ) {
            rawSearch = hash.slice( qIndex );
        }
    }

    const params = new URLSearchParams( rawSearch );
    const pageParam = parseInt(
        params.get( 'page' ) || params.get( 'pagenum' ) || '1',
        10
    );
    const searchParam = params.get( 'search' ) || params.get( 's' ) || '';

    return {
        page: Number.isFinite( pageParam ) && pageParam > 0 ? pageParam : 1,
        search: searchParam,
    };
}

function escapeCsvCell( value: string | number ): string {
    const str = String( value ?? '' );
    if ( /[",\n\r]/.test( str ) ) {
        return `"${ str.replace( /"/g, '""' ) }"`;
    }
    return str;
}

function downloadCustomersCsv( items: CustomerItem[] ): void {
    const headers = [
        __( 'Name', 'dokan-customers' ),
        __( 'Email', 'dokan-customers' ),
        __( 'Phone', 'dokan-customers' ),
        __( 'Orders', 'dokan-customers' ),
        __( 'Total Spend', 'dokan-customers' ),
        __( 'Registered At', 'dokan-customers' ),
        __( 'Billing Address', 'dokan-customers' ),
    ];

    const rows = items.map( ( item ) =>
        [
            item.name,
            item.email,
            item.phone,
            item.orders_count,
            item.total_spent_formatted || item.total_spent,
            item.date_registered,
            item.billing_address,
        ]
            .map( escapeCsvCell )
            .join( ',' )
    );

    const csv = [ headers.map( escapeCsvCell ).join( ',' ), ...rows ].join(
        '\r\n'
    );
    const blob = new Blob( [ '\uFEFF' + csv ], {
        type: 'text/csv;charset=utf-8;',
    } );
    const url = URL.createObjectURL( blob );
    const link = document.createElement( 'a' );
    const date = new Date().toISOString().slice( 0, 10 );

    link.href = url;
    link.download = `dokan-customers-${ date }.csv`;
    document.body.appendChild( link );
    link.click();
    document.body.removeChild( link );
    URL.revokeObjectURL( url );
}

export default function CustomerList( {
    navigate,
    location,
}: RouterInjectedProps ) {
    const initial = useMemo(
        () => getInitialQueryParams( location?.search ),
        [ location?.search ]
    );

    const [ view, setView ] = useState( {
        perPage: 10,
        page: initial.page,
        search: initial.search,
        type: 'table' as const,
        sort: {
            field: 'total_spent',
            direction: 'desc' as 'asc' | 'desc',
        },
        fields: DEFAULT_FIELDS,
        layout: {
            styles: {
                name: { width: '18%' },
                email: { width: '20%' },
                phone: { width: '12%' },
                orders_count: { width: '8%' },
                total_spent: { width: '12%' },
                date_registered: { width: '12%' },
                billing_address: { width: '18%' },
            },
        },
    } );

    const [ data, setData ] = useState< CustomerItem[] >( [] );
    const [ isLoading, setIsLoading ] = useState< boolean >( true );
    const [ isExporting, setIsExporting ] = useState< boolean >( false );
    const [ exportOpen, setExportOpen ] = useState< boolean >( false );
    const [ totalItems, setTotalItems ] = useState< number >( 0 );
    const [ totalPages, setTotalPages ] = useState< number >( 0 );

    const navigateToCustomer = useCallback(
        ( customerId: number ) => {
            if ( typeof navigate === 'function' ) {
                navigate( `/customers/${ customerId }` );
            } else {
                window.location.hash = `#/customers/${ customerId }`;
            }
        },
        [ navigate ]
    );

    const fetchCustomers = useCallback( async () => {
        setIsLoading( true );

        try {
            const path = addQueryArgs( '/dokan-customers/v1/customers', {
                page: view.page,
                per_page: view.perPage,
                search: view.search || '',
                orderby: view.sort?.field || 'total_spent',
                order: view.sort?.direction || 'desc',
            } );

            const result = await fetchPaginated< CustomerItem >( path );
            setData( result.items );
            setTotalItems( result.totalItems );
            setTotalPages( result.totalPages );
        } catch {
            setData( [] );
            setTotalItems( 0 );
            setTotalPages( 0 );
        } finally {
            setIsLoading( false );
        }
    }, [
        view.page,
        view.perPage,
        view.search,
        view.sort?.field,
        view.sort?.direction,
    ] );

    useEffect( () => {
        fetchCustomers();
    }, [ fetchCustomers ] );

    const fields = useMemo(
        () => [
            {
                id: 'name',
                label: __( 'Name', 'dokan-customers' ),
                enableSorting: true,
                enableHiding: false,
                render: ( { item }: { item: CustomerItem } ) => (
                    <a
                        href={ `#/customers/${ item.id }` }
                        className="dokan-customers-name-link"
                        onClick={ ( e ) => {
                            e.preventDefault();
                            e.stopPropagation();
                            navigateToCustomer( item.id );
                        } }
                    >
                        { item.name }
                    </a>
                ),
            },
            {
                id: 'email',
                label: __( 'Email', 'dokan-customers' ),
                enableSorting: false,
                render: ( { item }: { item: CustomerItem } ) =>
                    item.email ? (
                        <a
                            href={ `mailto:${ item.email }` }
                            className="dokan-customers-email-link"
                            onClick={ ( e ) => e.stopPropagation() }
                        >
                            { item.email }
                        </a>
                    ) : (
                        <span>{ '\u2013' }</span>
                    ),
            },
            {
                id: 'phone',
                label: __( 'Phone', 'dokan-customers' ),
                enableSorting: false,
                render: ( { item }: { item: CustomerItem } ) => (
                    <span>{ item.phone || '\u2013' }</span>
                ),
            },
            {
                id: 'orders_count',
                label: __( 'Orders', 'dokan-customers' ),
                enableSorting: true,
                render: ( { item }: { item: CustomerItem } ) => (
                    <span>{ item.orders_count }</span>
                ),
            },
            {
                id: 'total_spent',
                label: __( 'Total Spend', 'dokan-customers' ),
                enableSorting: true,
                render: ( { item }: { item: CustomerItem } ) => (
                    <PriceHtml price={ item.total_spent } />
                ),
            },
            {
                id: 'date_registered',
                label: __( 'Registered At', 'dokan-customers' ),
                enableSorting: true,
                render: ( { item }: { item: CustomerItem } ) => (
                    <DateTimeHtml.Date date={ item.date_registered } />
                ),
            },
            {
                id: 'billing_address',
                label: __( 'Billing Address', 'dokan-customers' ),
                enableSorting: false,
                render: ( { item }: { item: CustomerItem } ) => (
                    <span>{ item.billing_address || '\u2013' }</span>
                ),
            },
        ],
        [ navigateToCustomer ]
    );

    const actions = useMemo(
        () => [
            {
                id: 'view-orders',
                label: __( 'View Orders', 'dokan-customers' ),
                callback: ( items: CustomerItem[] ) => {
                    const item = items[ 0 ];
                    if ( item?.id ) {
                        navigateToCustomer( item.id );
                    }
                },
            },
        ],
        [ navigateToCustomer ]
    );

    const handleViewChange = useCallback( ( nextView: typeof view ) => {
        setView( ( prev ) => {
            const filterChanged =
                ( nextView.search || '' ) !== ( prev.search || '' ) ||
                nextView.perPage !== prev.perPage ||
                nextView.sort?.field !== prev.sort?.field ||
                nextView.sort?.direction !== prev.sort?.direction;

            return {
                ...prev,
                ...nextView,
                page: filterChanged ? 1 : nextView.page || 1,
                sort: nextView.sort || {
                    field: 'total_spent',
                    direction: 'desc',
                },
            };
        } );
    }, [] );

    const handleExport = useCallback(
        async ( filtered: boolean ) => {
            setExportOpen( false );
            setIsExporting( true );

            try {
                const allItems: CustomerItem[] = [];
                let currentPage = 1;
                let pages = 1;

                do {
                    const path = addQueryArgs(
                        '/dokan-customers/v1/customers',
                        {
                            page: currentPage,
                            per_page: 100,
                            search: filtered ? view.search || '' : '',
                            orderby: view.sort?.field || 'total_spent',
                            order: view.sort?.direction || 'desc',
                        }
                    );

                    const result =
                        await fetchPaginated< CustomerItem >( path );
                    allItems.push( ...result.items );
                    pages = result.totalPages || 1;
                    currentPage += 1;
                } while ( currentPage <= pages );

                downloadCustomersCsv( allItems );
            } finally {
                setIsExporting( false );
            }
        },
        [ view.search, view.sort?.field, view.sort?.direction ]
    );

    const hasActiveSearch = Boolean( view.search && view.search.trim() );

    return (
        <div className="dokan-customers-dashboard-page">
            <Fill name="dokan-header-actions">
                <div className="dokan-customers-export-wrapper">
                    <DokanButton
                        variant="secondary"
                        disabled={ isExporting }
                        onClick={ () =>
                            setExportOpen( ( prev ) => ! prev )
                        }
                    >
                        { isExporting
                            ? __( 'Exporting…', 'dokan-customers' )
                            : __( 'Export', 'dokan-customers' ) }
                    </DokanButton>
                    { exportOpen && (
                        <>
                            <div
                                className="dokan-customers-export-backdrop"
                                onClick={ () => setExportOpen( false ) }
                                aria-hidden="true"
                            />
                            <div className="dokan-customers-export-menu">
                                <button
                                    type="button"
                                    className="dokan-customers-export-item"
                                    onClick={ () => handleExport( false ) }
                                >
                                    { __( 'Export All', 'dokan-customers' ) }
                                </button>
                                <button
                                    type="button"
                                    className="dokan-customers-export-item"
                                    onClick={ () => handleExport( true ) }
                                >
                                    { __(
                                        'Export Filtered',
                                        'dokan-customers'
                                    ) }
                                </button>
                            </div>
                        </>
                    ) }
                </div>
            </Fill>

            <DataViews
                namespace="dokan-customers-data-view"
                data={ data }
                fields={ fields }
                view={ view }
                onChangeView={ handleViewChange }
                getItemId={ ( item: CustomerItem ) => String( item.id ) }
                isLoading={ isLoading }
                paginationInfo={ {
                    totalItems,
                    totalPages,
                } }
                search={ true }
                searchLabel={ __( 'Search customers', 'dokan-customers' ) }
                searchPlaceholder={ __(
                    'Search by name or email…',
                    'dokan-customers'
                ) }
                actions={ actions }
                emptyTitle={
                    hasActiveSearch
                        ? __( 'No customers found', 'dokan-customers' )
                        : __( 'No customers yet', 'dokan-customers' )
                }
                emptyDescription={
                    hasActiveSearch
                        ? __(
                              'No customers matched your search. Try a different name or email.',
                              'dokan-customers'
                          )
                        : __(
                              'Registered customers who place orders with your store will appear here.',
                              'dokan-customers'
                          )
                }
            />
        </div>
    );
}
