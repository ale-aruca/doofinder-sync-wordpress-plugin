# Doofinder Sync - WordPress Plugin

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-3.0+-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2+-red.svg)](http://www.gnu.org/licenses/gpl-2.0.html)

WordPress plugin for Doofinder integration with WooCommerce. Dynamically injects product metadata for enhanced search functionality.

## Features

- **Dynamic Meta Injection**: Generates metadata on-the-fly without database storage
- **REST API Integration**: Extends WooCommerce REST API responses
- **Debug Interface**: WordPress admin tool for testing and inspection
- **Multi-source Data**: Detects manufacturers from taxonomy, attributes, and custom fields
- **Discount Integration**: Works with WooCommerce Discount Rules plugin
- **Hierarchical Taxonomies**: Full category/brand tree paths

## Supported Fields

| Field | Description | Source |
|-------|-------------|--------|
| `_category_slugs` | Hierarchical category paths | `product_cat` taxonomy |
| `_brand_slugs` | Brand information | `product_brand` taxonomy |
| `_tag_slugs` | Product tags | `product_tag` taxonomy |
| `_manufacturer_slugs` | Manufacturer data | Multiple sources |
| `_discount_codes` | Calculated discount prices | WC + discount plugins |
| `_product_class` | Product classification | Custom field |
| `_pewc_has_extra_fields` | PEWC compatibility | PEWC plugin |

## Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.4+

## Installation

1. Download plugin files
2. Upload to `/wp-content/plugins/doofinder-sync/`
3. Activate through WordPress admin
4. Configure Doofinder plugin mapping

## Usage

### Debug Interface

1. Go to **WordPress Admin → Doofinder Sync**
2. Enter Product ID and click **Inspect**
3. Review generated metadata values

### REST API

```bash
curl -u "key:secret" "https://yoursite.com/wp-json/wc/v3/products/123"
```

### Programmatic Access

```php
$category_slugs = get_post_meta($product_id, '_category_slugs', true);
$manufacturer = get_post_meta($product_id, '_manufacturer_slugs', true);
```

### Doofinder Configuration

Map these fields in your Doofinder plugin:

- `category_slugs` → Category filtering
- `manufacturer_slugs` → Manufacturer filtering
- `brand_slugs` → Brand filtering
- `tag_slugs` → Tag filtering
- `discount_price` → Discount pricing

## Extending

```php
// Add custom metadata field
add_filter('dsync_dynamic_meta_config', function($config) {
    $config['_custom_field'] = ['cb' => 'my_custom_callback'];
    return $config;
});

function my_custom_callback($product_id) {
    return 'custom_value';
}
```

## Manufacturer Detection

The plugin checks these sources in order:
1. `manufacturer` taxonomy
2. `pa_manufacturer` product attribute
3. Standard `manufacturer` attributes
4. `Manufacturer` custom field

## License

GPLv2 or later

## Authors

- Ale Aruca
- Muhammad Adeel