<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Katana_Settings extends WC_Settings_Page {

    public function __construct() {
        $this->id    = 'katana';
        $this->label = __( 'Katana', 'katana-magic' );

        parent::__construct();
    }

    public function get_settings() {
        return [
            [
                'title' => __( 'Katana Settings', 'katana-magic' ),
                'type'  => 'title',
                'desc'  => __( 'Configure the Katana API integration.', 'katana-magic' ),
                'id'    => 'katana_settings',
            ],
            [
                'title'    => __( 'API Key', 'katana-magic' ),
                'id'       => 'katana_api_key',
                'type'     => 'text',
                'desc'     => __( 'Enter your Katana API key here.', 'katana-magic' ),
                'desc_tip' => true,
            ],
            [
                'title'   => __( 'Enable Logging', 'katana-magic' ),
                'id'      => 'katana_enable_logging',
                'type'    => 'checkbox',
                'default' => false,
                'desc'    => __( 'Enable logging of product creation attempts.', 'katana-magic' ),
            ],
            [
                'title'   => __( 'Default: Is Sellable', 'katana-magic' ),
                'id'      => 'katana_is_sellable',
                'type'    => 'checkbox',
                'default' => true,
                'desc'    => __( 'Enable this to mark products as sellable by default.', 'katana-magic' ),
            ],
            [
                'title'   => __( 'Default: Is Producible', 'katana-magic' ),
                'id'      => 'katana_is_producible',
                'type'    => 'checkbox',
                'default' => false,
                'desc'    => __( 'Enable this to mark products as producible by default.', 'katana-magic' ),
            ],
            [
                'title'   => __( 'Default: Is Purchasable', 'katana-magic' ),
                'id'      => 'katana_is_purchasable',
                'type'    => 'checkbox',
                'default' => true,
                'desc'    => __( 'Enable this to mark products as purchasable by default.', 'katana-magic' ),
            ],
            [
                'title'   => __( 'Default: Is Auto Assembly', 'katana-magic' ),
                'id'      => 'katana_is_auto_assembly',
                'type'    => 'checkbox',
                'default' => false,
                'desc'    => __( 'Enable this to mark products as auto-assembly by default.', 'katana-magic' ),
            ],
            [
                'type' => 'sectionend',
                'id'   => 'katana_settings',
            ],
        ];
    }
}

return new Katana_Settings();
