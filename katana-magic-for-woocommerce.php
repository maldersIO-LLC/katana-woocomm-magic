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

define( 'KATANA_MAGIC_VERSION', '1.0.0' );

/**
 * Enqueue admin notices if set.
 */
add_action( 'admin_notices', 'katana_display_admin_notices' );
function katana_display_admin_notices() {
    if ( $notice = get_transient( 'katana_admin_notice' ) ) {
        echo '<div class="' . esc_attr( $notice['class'] ) . '"><p>' . esc_html( $notice['message'] ) . '</p></div>';
        delete_transient( 'katana_admin_notice' );
    }
}


/**
 * Add the meta box for the "Create Katana Product" button.
 */
add_action( 'add_meta_boxes', 'katana_add_meta_box' );
function katana_add_meta_box() {
    add_meta_box(
        'katana_product_meta_box',
        __( 'Katana', 'katana-magic' ),
        'katana_render_meta_box',
        'product',
        'side',
        'high'
    );
}

function katana_render_meta_box( $post ) {
    echo '<form id="katana-create-product-form" method="POST">';
    wp_nonce_field( 'katana_create_product_action', 'katana_create_product_nonce' );
    echo '<button id="katana_create_product" class="button button-primary" style="background-color:#eeff38;color:black;min-width:100%;font-weight:600;padding:5px;font-size:1.5em;" data-product-id="' . esc_attr($post->ID) . '">' . __('Create Katana Product', 'katana-magic') . '</button>';
    echo '</form>';
}


add_action('admin_enqueue_scripts', 'katana_enqueue_admin_scripts');
function katana_enqueue_admin_scripts() {
    wp_enqueue_script('katana-admin', plugin_dir_url(__FILE__) . 'includes/katana-admin.js', ['jquery'], '1.0.0', true);
    wp_localize_script('katana-admin', 'katana_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('katana_create_product_action'),
    ]);
}

add_filter('woocommerce_get_settings_pages', 'katana_add_settings_tab');
function katana_add_settings_tab($settings) {
    if (class_exists('WC_Settings_Page')) {
        $settings[] = include 'includes/class-katana-settings.php';
    }
    return $settings;
}
/**
 * Handle product creation AJAX request.
 */
add_action('wp_ajax_katana_create_product', 'katana_create_or_update_product_handler');
function katana_create_or_update_product_handler() {
    // Check nonce for security
    check_ajax_referer('katana_create_product_action', 'security');

    $product_id = intval($_POST['product_id']);
    if (!$product_id) {
        wp_send_json_error([
            'message' => __('Invalid product ID.', 'katana-magic'),
        ]);
        return;
    }

    $api_key = get_option('katana_api_key');
    if (!$api_key) {
        wp_send_json_error([
            'message' => __('Katana API key is missing. Please set it in the settings.', 'katana-magic'),
        ]);
        return;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error([
            'message' => __('Invalid WooCommerce product.', 'katana-magic'),
        ]);
        return;
    }

    // Prepare common product data
    $name = $product->get_name();
    $description = $product->get_description();
    $sku = $product->get_sku();
    if (empty($sku)) {
        wp_send_json_error([
            'message' => __('Product SKU is missing. Please ensure the product has an SKU.', 'katana-magic'),
        ]);
        return;
    }

    $terms = get_the_terms($product_id, 'product_cat');
    $category_name = 'Default'; // Fallback if no category is found
    if (!is_wp_error($terms) && !empty($terms)) {
        $category_name = $terms[0]->name; // Get the first category name
    }

    // Fetch settings and convert yes/no to true/false
    $is_sellable = get_option('katana_is_sellable', 'yes') === 'yes' ? true : false;
    $is_producible = get_option('katana_is_producible', 'no') === 'yes' ? true : false;
    $is_purchasable = get_option('katana_is_purchasable', 'yes') === 'yes' ? true : false;
    $is_auto_assembly = get_option('katana_is_auto_assembly', 'no') === 'yes' ? true : false;

    // Step 1: Check for existing variants in Katana
    $existing_variants = katana_check_existing_variants($sku, $api_key);
    if ($existing_variants['error']) {
        wp_send_json_error([
            'message' => __('Error checking existing variants: ' . $existing_variants['message'], 'katana-magic'),
        ]);
        return;
    }

    // Handle simple products
    if ($product->is_type('simple')) {
        if (!empty($existing_variants['variants'])) {
            wp_send_json_error([
                'message' => __('Product already exists in Katana with variants: ' . implode(', ', array_column($existing_variants['variants'], 'sku')), 'katana-magic'),
            ]);
            return;
        }

        // Create a simple product
        katana_create_simple_product($product, $category_name, $api_key, $is_sellable, $is_producible, $is_purchasable, $is_auto_assembly, $description);
        return;
    }

    // Handle variable products
    if ($product->is_type('variable')) {
        // Check if the parent product or any of its variants exist
        $variations = $product->get_children(); // Get all variation IDs
        $existing_skus = array_column($existing_variants['variants'], 'sku');
        $variants_to_create = [];
        $configs = [];

        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation || !$variation->get_sku()) {
                continue;
            }

            $variation_sku = $variation->get_sku();
            if (in_array($variation_sku, $existing_skus)) {
                continue; // Skip creating this variant since it already exists
            }

            $variation_attributes = $variation->get_attributes();
            foreach ($variation_attributes as $attribute_name => $attribute_value) {
                $attribute_label = wc_attribute_label($attribute_name);
                if (!isset($configs[$attribute_label])) {
                    $configs[$attribute_label] = [
                        'name' => $attribute_label,
                        'values' => [],
                    ];
                }

                if (!in_array($attribute_value, $configs[$attribute_label]['values'])) {
                    $configs[$attribute_label]['values'][] = $attribute_value;
                }
            }

            $variants_to_create[] = [
                'sku' => $variation_sku,
                'sales_price' => (float) $variation->get_price(),
                'config_attributes' => array_map(function ($attribute_name, $attribute_value) {
                    return [
                        'config_name' => wc_attribute_label($attribute_name),
                        'config_value' => $attribute_value,
                    ];
                }, array_keys($variation_attributes), $variation_attributes),
            ];
        }

        // Flatten configs
        $configs = array_values($configs);

        // If no new variants to create, stop
        if (empty($variants_to_create)) {
            wp_send_json_error([
                'message' => __('All variants already exist in Katana.', 'katana-magic'),
            ]);
            return;
        }

        // Create the parent product and variants
        katana_create_variable_product($product, $category_name, $configs, $variants_to_create, $api_key, $is_sellable, $is_producible, $is_purchasable, $is_auto_assembly, $description);
        return;
    }

    wp_send_json_error(['message' => __('Invalid product type or no valid SKUs found.', 'katana-magic')]);
}

/**
 * Check if variants already exist for a given product SKU in Katana.
 */
function katana_check_existing_variants($sku, $api_key) {
    $url = 'https://api.katanamrp.com/v1/variants?sku=' . urlencode($sku);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        katana_log('Error checking variants: ' . $curl_error, 'error');
        return [
            'error' => true,
            'message' => $curl_error,
        ];
    }

    $response_decoded = json_decode($response, true);
    if ($http_code === 200) {
        return [
            'error' => false,
            'variants' => $response_decoded['data'],
        ];
    } else {
        return [
            'error' => true,
            'message' => $response_decoded['message'] ?? __('Unknown error while checking variants.', 'katana-magic'),
        ];
    }
}

/**
 * Utility function to log messages to WooCommerce logs.
 *
 * @param string $message The log message.
 * @param string $log_level The log level ('error' or 'info').
 */
function katana_log($message, $log_level = 'error') {
    $logger = wc_get_logger();
    $logger->$log_level($message, ['source' => 'katana']);
}

/**
 * Create a simple product in Katana.
 */
function katana_create_simple_product($product, $category_name, $api_key, $is_sellable, $is_producible, $is_purchasable, $is_auto_assembly, $description) {
    $name = $product->get_name();
    $sku = $product->get_sku();
    $price = (float) $product->get_price();

    $katana_create_data = [
        'name' => $name,
        'uom' => 'pcs',
        'category_name' => $category_name,
        'is_sellable' => $is_sellable,
        'is_producible' => $is_producible,
        'is_purchasable' => $is_purchasable,
        'is_auto_assembly' => $is_auto_assembly,
        'additional_info' => $description,
        'variants' => [[
            'sku' => $sku,
            'sales_price' => $price,
        ]],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.katanamrp.com/v1/products');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($katana_create_data));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        katana_log('Error creating simple product: ' . $curl_error, 'error');
        wp_send_json_error([
            'message' => __('Error creating new product: ' . $curl_error, 'katana-magic'),
        ]);
        return;
    }

    if ($http_code === 200) {
        katana_log('Simple product successfully created: ' . $name, 'info');
        wp_send_json_success([
            'message' => sprintf(__('Simple product "%s" has been created in Katana.', 'katana-magic'), $name),
        ]);
    } else {
        katana_log('Failed to create simple product in Katana. HTTP Code: ' . $http_code . '. Response: ' . $response, 'error');
        wp_send_json_error([
            'message' => sprintf(__('Failed to create product in Katana (HTTP %d).', 'katana-magic'), $http_code),
        ]);
    }
}

/**
 * Create a variable product in Katana.
 */
function katana_create_variable_product($product, $category_name, $configs, $variants_to_create, $api_key, $is_sellable, $is_producible, $is_purchasable, $is_auto_assembly, $description) {
    $name = $product->get_name();
    $sku = $product->get_sku();

    $katana_create_data = [
        'name' => $name,
        'uom' => 'pcs',
        'category_name' => $category_name,
        'is_sellable' => $is_sellable,
        'is_producible' => $is_producible,
        'is_purchasable' => $is_purchasable,
        'is_auto_assembly' => $is_auto_assembly,
        'additional_info' => $description,
        'configs' => $configs,
        'variants' => $variants_to_create,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.katanamrp.com/v1/products');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($katana_create_data));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        katana_log('Error creating variable product: ' . $curl_error, 'error');
        wp_send_json_error([
            'message' => __('Error creating variable product: ' . $curl_error, 'katana-magic'),
        ]);
        return;
    }

    if ($http_code === 200) {
        katana_log('Variable parent product and variable products successfully created: ' . $name, 'info');
        wp_send_json_success([
            'message' => __('Variable product has been created in Katana.', 'katana-magic'),
        ]);
    } else {
        katana_log('Failed to create variable product in Katana. HTTP Code: ' . $http_code . '. Response: ' . $response, 'error');
        wp_send_json_error([
            'message' => sprintf(__('Failed to create variable product in Katana (HTTP %d).', 'katana-magic'), $http_code),
        ]);
    }
} 


//______________________________________________________________________________
// All About Updates

//  Begin Version Control | Auto Update Checker
require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
// ***IMPORTANT*** Update this path to New Github Repository Master Branch Path
	'https://github.com/maldersIO-LLC/katana-woocomm-magic',
	__FILE__,
// ***IMPORTANT*** Update this to New Repository Master Branch Path
	'katana-woocomm-magic'
);
//Enable Releases
$myUpdateChecker->getVcsApi()->enableReleaseAssets();
//Optional: If you're using a private repository, specify the access token like this:
//
//
//Future Update Note: Comment in these sections and add token and branch information once private git established
//
//
//$myUpdateChecker->setAuthentication('your-token-here');
//Optional: Set the branch that contains the stable release.
//$myUpdateChecker->setBranch('stable-branch-name');

//______________________________________________________________________________
/* Katana Magic for WooCommerce End */
?>
