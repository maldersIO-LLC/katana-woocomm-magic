<?php
/**
 * Plugin Name: Katana Magic For WooCommerce
 * Plugin URI: https://malders.io
 * Description: Integrates Katana MRP with WooCommerce for product creation and management.
 * Version: 1.2.0
 * Author: maldersIO
 * Author URI: https://malders.io
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function katana_check_dependencies() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            __( 'WooCommerce is required for Katana Magic For WooCommerce to work. Please install and activate WooCommerce.', 'katana-magic' ),
            __( 'Dependency Check Failed', 'katana-magic' ),
            array( 'back_link' => true )
        );
    }
}
register_activation_hook( __FILE__, 'katana_check_dependencies' );

define( 'KATANA_MAGIC_VERSION', '1.2.0' );
define( 'KATANA_MAGIC_LOG_FILE', plugin_dir_path( __FILE__ ) . 'katana_log.txt' );

add_filter( 'woocommerce_get_settings_pages', 'katana_add_settings_tab' );
function katana_add_settings_tab( $settings ) {
    if ( class_exists( 'WC_Settings_Page' ) ) {
        $settings[] = include 'includes/class-katana-settings.php';
    }
    return $settings;
}

// Add the meta box for the "Create Katana Product" button
add_action( 'add_meta_boxes', 'katana_add_meta_box' );
function katana_add_meta_box() {
    add_meta_box(
        'katana_product_meta_box',
        __( 'Katana Integration', 'katana-magic' ),
        'katana_render_meta_box',
        'product',
        'side',
        'high'
    );
}

function katana_render_meta_box( $post ) {
    echo '<button id="katana_create_product" class="button button-primary">' . __( 'Create Katana Product', 'katana-magic' ) . '</button>';
    ?>
    <script>
        document.getElementById('katana_create_product').addEventListener('click', function(e) {
            e.preventDefault();
            const createProduct = confirm('<?php esc_html_e( 'Are you sure you want to create this product in Katana?', 'katana-magic' ); ?>');
            if (createProduct) {
                const url = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
                const data = new FormData();
                data.append('action', 'katana_create_product');
                data.append('product_id', '<?php echo $post->ID; ?>');
                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                }).then(response => response.json())
                  .then(data => alert(data.message));
            }
        });
    </script>
    <?php
}

add_action( 'wp_ajax_katana_create_product', 'katana_create_product_handler' );
function katana_create_product_handler() {
    $product_id = intval( $_POST['product_id'] );

    if ( ! $product_id ) {
        wp_send_json_error( [ 'message' => __( 'Invalid product ID.', 'katana-magic' ) ] );
    }

    $api_key = get_option( 'katana_api_key' );
    if ( ! $api_key ) {
        wp_send_json_error( [ 'message' => __( 'Katana API key is missing. Please set it in the settings.', 'katana-magic' ) ] );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        wp_send_json_error( [ 'message' => __( 'Invalid WooCommerce product.', 'katana-magic' ) ] );
    }

    // Read default values from settings
    $is_sellable = (bool) get_option( 'katana_is_sellable', true );
    $is_producible = (bool) get_option( 'katana_is_producible', false );
    $is_purchasable = (bool) get_option( 'katana_is_purchasable', true );
    $is_auto_assembly = (bool) get_option( 'katana_is_auto_assembly', false );

    // Prepare data for Katana product creation
    $katana_data = [
        'name' => $product->get_name(),
        'uom' => 'pcs',
        'category_name' => 'Default',
        'is_sellable' => $is_sellable,
        'is_producible' => $is_producible,
        'is_purchasable' => $is_purchasable,
        'is_auto_assembly' => $is_auto_assembly,
        'default_supplier_id' => null,
        'additional_info' => $product->get_description(),
        'batch_tracked' => false,
        'serial_tracked' => false,
        'operations_in_sequence' => true,
        'purchase_uom' => 'pcs',
        'purchase_uom_conversion_rate' => 1,
        'lead_time' => 0,
        'minimum_order_quantity' => 1,
        'configs' => [],
        'variants' => [],
    ];

    if ( $product->is_type( 'variable' ) ) {
        $variations = $product->get_children();
        foreach ( $variations as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( $variation ) {
                $katana_data['variants'][] = [
                    'sku' => $variation->get_sku(),
                    'purchase_price' => $variation->get_regular_price(),
                    'sales_price' => $variation->get_price(),
                    'config_attributes' => [
                        [
                            'config_name' => 'Size',
                            'config_value' => $variation->get_attribute( 'pa_size' ),
                        ],
                    ],
                    'internal_barcode' => '',
                    'registered_barcode' => '',
                    'supplier_item_codes' => [],
                    'custom_fields' => [],
                ];
            }
        }
    } else {
        $katana_data['variants'][] = [
            'sku' => $product->get_sku(),
            'purchase_price' => $product->get_regular_price(),
            'sales_price' => $product->get_price(),
            'config_attributes' => [],
            'internal_barcode' => '',
            'registered_barcode' => '',
            'supplier_item_codes' => [],
            'custom_fields' => [],
        ];
    }

    $url = 'https://api.katanamrp.com/v1/products';
    $response = wp_remote_post( $url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode( $katana_data ),
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => $response->get_error_message() ] );
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status_code === 201 ) {
        wp_send_json_success( [
            'message' => sprintf(
                __( '%s has been created in Katana successfully.', 'katana-magic' ),
                $product->get_name()
            ),
        ] );
    } else {
        wp_send_json_error( [
            'message' => $response_body['message'] ?? __( 'Unknown error.', 'katana-magic' ),
        ] );
    }
}
