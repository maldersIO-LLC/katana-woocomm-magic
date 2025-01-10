# Katana Magic for WooCommerce

**Katana Magic** is a WordPress plugin that integrates WooCommerce with Katana MRP, enabling seamless synchronization of product data, inventory, and stock updates.

---

## Features
- Sync WooCommerce products and variations to Katana MRP.
- Automatically fetch inventory data from Katana and update WooCommerce stock levels.
- Log all operations for troubleshooting.
- Fully customizable settings for product sync behavior.

---

## Requirements

- **WordPress**: 5.5 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Katana API Key**: A valid API key from your Katana MRP account.

---

## Installation

1. **Download the Plugin**:
   - Clone or download the plugin repository as a `.zip` file.

2. **Upload the Plugin**:
   - In your WordPress admin dashboard, go to `Plugins > Add New > Upload Plugin`.
   - Choose the `.zip` file and click `Install Now`.

3. **Activate the Plugin**:
   - After installation, activate the plugin through the `Plugins` menu in WordPress.

---

## Activation Instructions

1. **Obtain Katana API Key**:
   - Log in to your Katana MRP account and generate an API key from the developer section.

2. **Configure Settings**:
   - Navigate to `WooCommerce > Settings > Katana`.
   - Enter your API key in the **API Key** field.
   - Configure the default product settings for `Is Sellable`, `Is Producible`, `Is Purchasable`, and `Is Auto Assembly`.
   - Enable or disable logging as needed.

3. **Save Settings**:
   - Click `Save Changes` to apply your configuration.

---

## Settings Options

### **General Settings**

1. **API Key**:
   - Enter your Katana API key to enable the integration.

2. **Enable Logging**:
   - Toggle to enable or disable logging of all plugin actions to WooCommerce logs.

### **Default Product Settings**

1. **Is Sellable**:
   - Set whether products are sellable by default.

2. **Is Producible**:
   - Set whether products are producible by default.

3. **Is Purchasable**:
   - Set whether products are purchasable by default.

4. **Is Auto Assembly**:
   - Set whether products are auto-assembled by default.

---

## Usage

### Sync WooCommerce Products to Katana

- Navigate to a WooCommerce product edit page.
- Click the **Create in Katana** button in the product meta box under the publish section.
- The product will be synced to Katana MRP.
- For variable products, each variation will also be created as a variant in Katana.

### Inventory Sync

- The plugin will automatically sync inventory data hourly via a scheduled task.
- WooCommerce stock quantities will be updated to match the quantities in Katana.

---

## Troubleshooting

1. **API Key Missing**:
   - Ensure you have entered a valid API key in the settings.

2. **Logging Issues**:
   - Check WooCommerce logs (`WooCommerce > Status > Logs`) for Katana-related entries.

3. **Product Sync Errors**:
   - Ensure SKUs are set for all products and variations in WooCommerce.
   - Check Katana API logs for validation errors.

---

## Support
For support or inquiries, please visit [malders.io](https://malders.io) or contact the plugin author.

---

## License
This project is licensed under the MIT License - see the LICENSE file for details.

---
