<?php

namespace PluginizeLab\DokanCustomers;

/**
 * DokanCustomers class
 *
 * @class DokanCustomers The class that holds the entire DokanCustomers plugin
 */
final class DokanCustomers {

    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '2.0.0';

    /**
     * Minimum Dokan Lite version required
     *
     * @var string
     */
    const MIN_DOKAN_VERSION = '5.0.0';

    /**
     * Instance of self
     *
     * @var DokanCustomers
     */
    private static $instance = null;

    /**
     * Holds various class instances
     *
     * @since 2.6.10
     *
     * @var array
     */
    private $container = [];

    /**
     * Constructor for the DokanCustomers class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     */
    private function __construct() {
        $this->define_constants();

        register_activation_hook( DOKAN_CUSTOMERS_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( DOKAN_CUSTOMERS_FILE, [ $this, 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
    }

    /**
     * Initializes the DokanCustomers() class
     *
     * Checks for an existing DokanCustomers instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Magic getter to bypass referencing objects
     *
     * @since 2.6.10
     *
     * @param string $prop
     *
     * @return mixed
     */
    public function __get( $prop ) {
        if ( array_key_exists( $prop, $this->container ) ) {
            return $this->container[ $prop ];
        }
    }

    /**
     * Placeholder for activation function
     *
     * Nothing being called here yet.
     */
    public function activate() {
        if ( ! $this->has_met_requirements() ) {
            return;
        }

        $this->flush_rewrite_rules();
    }

    /**
     * Flush rewrite rules after DOKAN_CUSTOMERS is activated or woocommerce is activated
     *
     * @since 3.2.8
     */
    public function flush_rewrite_rules() {
        if ( ! isset( $this->container['manage_customers'] ) ) {
            $this->container['manage_customers'] = new ManageCustomers();
        }

        add_filter( 'dokan_query_var_filter', [ $this->container['manage_customers'], 'register_customers_query_var' ] );

        if ( function_exists( 'dokan' ) && isset( dokan()->rewrite ) ) {
            dokan()->rewrite->register_rule();
        }

        flush_rewrite_rules( true );
    }

    /**
     * Placeholder for deactivation function
     *
     * Nothing being called here yet.
     */
    public function deactivate() {}

    /**
     * Define all constants
     *
     * @return void
     */
    public function define_constants() {
        $this->define( 'DOKAN_CUSTOMERS_PLUGIN_VERSION', $this->version );
        $this->define( 'DOKAN_CUSTOMERS_DIR', dirname( DOKAN_CUSTOMERS_FILE ) );
        $this->define( 'DOKAN_CUSTOMERS_INC_DIR', DOKAN_CUSTOMERS_DIR . '/includes' );
        $this->define( 'DOKAN_CUSTOMERS_PLUGIN_ASSET', plugins_url( 'assets', DOKAN_CUSTOMERS_FILE ) );
    }

    /**
     * Define constant if not already defined
     *
     * @param string      $name
     * @param string|bool $value
     *
     * @return void
     */
    private function define( $name, $value ) {
        if ( ! defined( $name ) ) {
            define( $name, $value );
        }
    }

    /**
     * Load the plugin after dependencies are loaded
     *
     * @return void
     */
    public function init_plugin() {
        if ( ! $this->has_met_requirements() ) {
            return;
        }

        $this->includes();
        $this->init_hooks();

        do_action( 'dokan_customers_loaded' );
    }

    /**
     * Check whether required plugins and minimum Dokan Lite version are active,
     * registering the appropriate admin notice when a requirement is not met.
     *
     * @return bool
     */
    protected function has_met_requirements() {
        if ( ! $this->check_dependencies() ) {
            add_action( 'admin_notices', [ $this, 'admin_error_notice_for_dependency_missing' ] );
            return false;
        }

        if ( ! $this->is_supported_dokan_version() ) {
            add_action( 'admin_notices', [ $this, 'admin_error_notice_for_dokan_version' ] );
            return false;
        }

        return true;
    }

    /**
     * Initialize the actions
     *
     * @return void
     */
    public function init_hooks() {
        // initialize the classes
        add_action( 'init', [ $this, 'init_classes' ], 4 );
        add_action( 'plugins_loaded', [ $this, 'after_plugins_loaded' ] );
        add_action( 'woocommerce_flush_rewrite_rules', [ $this, 'flush_rewrite_rules' ] );
        add_filter( 'dokan_rest_api_class_map', [ $this, 'rest_api_class_map' ] );
    }

    /**
     * Register REST API controllers with Dokan.
     *
     * @param array<string, string> $class_map Existing controller class map.
     * @return array<string, string>
     */
    public function rest_api_class_map( $class_map ) {
        if ( ! $this->is_supported_dokan_version() ) {
            return $class_map;
        }

        $class_map[ DOKAN_CUSTOMERS_INC_DIR . '/REST/CustomersController.php' ] = REST\CustomersController::class;

        return $class_map;
    }

    /**
     * Include all the required files
     *
     * @return void
     */
    public function includes() {
        // include_once STUB_PLUGIN_DIR . '/functions.php';
    }

    /**
     * Init all the classes
     *
     * @return void
     */
    public function init_classes() {
        if ( ! $this->is_supported_dokan_version() ) {
            return;
        }

        $this->container['scripts']          = new Assets();
        $this->container['manage_customers'] = new ManageCustomers();
    }

    /**
     * Executed after all plugins are loaded
     *
     * @since 2.8.7
     *
     * @return void
     */
    public function after_plugins_loaded() {
        // Initiate background processes and other tasks
    }

    /**
     * Check plugin dependencies
     *
     * @return boolean
     */
    public function check_dependencies() {
        if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WeDevs_Dokan' ) ) {
            return false;
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! is_plugin_active( 'dokan-lite/dokan.php' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Check if the installed Dokan Lite version meets the minimum requirement
     *
     * @return boolean
     */
    public function is_supported_dokan_version() {
        $dokan_version = '0.0.0';

        if ( function_exists( 'dokan' ) && isset( dokan()->version ) ) {
            $dokan_version = dokan()->version;
        } elseif ( defined( 'DOKAN_PLUGIN_VERSION' ) ) {
            $dokan_version = DOKAN_PLUGIN_VERSION;
        }

        return version_compare( $dokan_version, self::MIN_DOKAN_VERSION, '>=' );
    }

    /**
     * Dependency error message
     *
     * @return string
     */
    protected function get_dependency_message() {
        return __( 'Dokan customer plugin is enabled but not effective. It requires WooCommerce & Dokan Lite(Free) plugins to work.', 'dokan-customers' );
    }

    /**
     * Unsupported Dokan Lite version error message
     *
     * @return string
     */
    protected function get_dokan_version_message() {
        return sprintf(
            /* translators: %s: Minimum required Dokan Lite version. */
            __( 'Customer Management for Dokan requires Dokan Lite %s or newer. Please update Dokan Lite to use the Customers page.', 'dokan-customers' ),
            self::MIN_DOKAN_VERSION
        );
    }

    /**
     * Admin error notice for missing dependency plugins
     *
     * @return void
     */
    public function admin_error_notice_for_dependency_missing() {
        $class = 'notice notice-error';
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $this->get_dependency_message() ) );
    }

    /**
     * Admin error notice for unsupported Dokan Lite version
     *
     * @return void
     */
    public function admin_error_notice_for_dokan_version() {
        $class = 'notice notice-error';
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $this->get_dokan_version_message() ) );
    }
}
