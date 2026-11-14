import { __ } from '@wordpress/i18n';
import CustomerList from './CustomerList';
import CustomerDetails from './CustomerDetails';
import './customers.css';

interface DokanRoute {
    id: string;
    title?: string;
    element: JSX.Element;
    path: string;
    exact?: boolean;
    capabilities?: string[];
    backUrl?: string;
}

declare global {
    interface Window {
        wp?: {
            hooks?: {
                addFilter: (
                    hookName: string,
                    namespace: string,
                    callback: ( routes: DokanRoute[] ) => DokanRoute[]
                ) => void;
            };
        };
    }
}

if ( window.wp?.hooks?.addFilter ) {
    window.wp.hooks.addFilter(
        'dokan-dashboard-routes',
        'dokan-customers/routes',
        ( routes: DokanRoute[] ) => {
            routes.push( {
                id: 'dokan-customers',
                title: __( 'Customers', 'dokan-customers' ),
                element: <CustomerList />,
                path: '/customers',
                exact: true,
                capabilities: [ 'dokan_view_order_menu' ],
            } );

            routes.push( {
                id: 'dokan-customers-details',
                title: __( 'Customer Details', 'dokan-customers' ),
                element: <CustomerDetails />,
                path: '/customers/:id',
                exact: true,
                capabilities: [ 'dokan_view_order_menu' ],
                backUrl: '/customers',
            } );

            return routes;
        }
    );
}
