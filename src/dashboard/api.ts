import apiFetch from '@wordpress/api-fetch';

export interface PaginatedResult< T > {
    items: T[];
    totalItems: number;
    totalPages: number;
}

export async function fetchPaginated< T >(
    path: string
): Promise< PaginatedResult< T > > {
    const response = await apiFetch< Response >( {
        path,
        parse: false,
    } );

    const total = parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 );
    const pages = parseInt(
        response.headers.get( 'X-WP-TotalPages' ) || '0',
        10
    );
    const items = ( await response.json() ) as T[];

    return {
        items: Array.isArray( items ) ? items : [],
        totalItems: Number.isFinite( total ) ? total : 0,
        totalPages: Number.isFinite( pages ) ? pages : 0,
    };
}
