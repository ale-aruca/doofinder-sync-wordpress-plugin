<?php
/**
 * Plugin Name: Doofinder Sync
 * Description: Dynamically injects product meta for Doofinder and provides a debug interface.
 * Version: 2.2
 * Author: Ale Aruca, Muhammad Adeel
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DSYNC_PREFIX', 'dsync_' );

/**
 * Dynamic metadata configuration
 */
function dsync_dynamic_meta_config() {
    return [
        '_category_slugs'     => ['tax' => 'product_cat',   'hier' => true],
        '_brand_slugs'        => ['tax' => 'product_brand', 'hier' => true],
        '_tag_slugs'          => ['tax' => 'product_tag',   'hier' => true],
        '_manufacturer_slugs' => ['cb' => DSYNC_PREFIX . 'get_manufacturer_slugs_for_product'],
        '_discount_codes'     => ['cb' => DSYNC_PREFIX . 'get_discount_price_for_product'],
        '_pewc_has_extra_fields' => ['cb' => 'pewc_has_extra_fields'],
        '_product_class'      => ['cb' => DSYNC_PREFIX . 'get_product_class_for_product'], 
    ];
}

// Enable unescaped slashes in JSON output
add_filter('wp_json_encode_options', function() {
    return JSON_UNESCAPED_SLASHES;
});

/**
 * Add dynamic metadata to REST API responses
 */
add_filter('woocommerce_rest_prepare_product_object', DSYNC_PREFIX . 'add_dynamic_meta_to_rest', 10, 3);
function dsync_add_dynamic_meta_to_rest($response, $product, $request) {
    if (! $product instanceof WC_Product) {
        return $response;
    }
    
    $product_id = $product->get_id();
    foreach (dsync_dynamic_meta_config() as $meta_key => $opts) {
        $response->data[$meta_key] = dsync_get_dynamic_meta_value($product_id, $opts);
    }
    
    if (!empty($response->data['_discount_codes'])) {
        $response->data['on_sale'] = true;
    }
    
    return $response;
}

/**
 * Inject dynamic metadata through WordPress meta system
 */
add_filter('get_post_metadata', DSYNC_PREFIX . 'inject_dynamic_meta', 10, 4);
function dsync_inject_dynamic_meta($value, $post_id, $meta_key, $single) {
    if (get_post_type($post_id) !== 'product') {
        return $value;
    }
    $cfg = dsync_dynamic_meta_config();
    if (isset($cfg[$meta_key])) {
        $val = dsync_get_dynamic_meta_value($post_id, $cfg[$meta_key]);
        return $single ? $val : [$val];
    }
    return $value;
}

/**
 * Get dynamic metadata value based on configuration
 */
function dsync_get_dynamic_meta_value($product_id, $opt) {
    if (!empty($opt['tax'])) {
        return dsync_get_taxonomy_slugs($product_id, $opt['tax'], !empty($opt['hier']));
    }
    if (!empty($opt['cb']) && is_callable($opt['cb'])) {
        return call_user_func($opt['cb'], $product_id);
    }
    return '';
}

/**
 * Get taxonomy slugs for a product
 */
function dsync_get_taxonomy_slugs($product_id, $tax, $hier = false) {
    $terms = get_the_terms($product_id, $tax);
    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }

    $collected_paths = [];
    foreach ($terms as $term) {
        if ($hier) {
            $paths_for_term = dsync_get_taxonomy_paths($term->term_id, $tax);
            $collected_paths = array_merge($collected_paths, $paths_for_term);
        } else {
            $collected_paths[] = $term->slug;
        }
    }

    $unique_paths = array_unique($collected_paths);
    if (empty($unique_paths)) {
        return '';
    }
    
    $processed_paths = [];
    foreach ($unique_paths as $current_path) {
        if ($hier) {
            if (substr($current_path, -1) !== '/') {
                $processed_paths[] = $current_path . '/';
            } else {
                $processed_paths[] = $current_path;
            }
        } else {
            $processed_paths[] = $current_path;
        }
    }

    return implode(' ', $processed_paths);
}

/**
 * Get hierarchical taxonomy paths
 */
function dsync_get_taxonomy_paths($term_id, $tax) {
    $paths = [];
    $slugs = [];

    $ancestor_ids = array_reverse(get_ancestors($term_id, $tax));
    $ancestor_ids[] = $term_id;

    foreach ($ancestor_ids as $ancestor_id) {
        $ancestor = get_term($ancestor_id, $tax);
        if (!is_wp_error($ancestor) && $ancestor) {
            $slugs[] = $ancestor->slug;
            $paths[] = implode('/', $slugs);
        }
    }

    return $paths;
}

/**
 * Get manufacturer slugs from multiple sources
 */
function dsync_get_manufacturer_slugs_for_product($product_id) {
    // Try manufacturer taxonomy first
    $terms = get_the_terms($product_id, 'manufacturer');
    if ($terms && !is_wp_error($terms)) {
        $slugs = [];
        foreach ($terms as $t) {
            $slugs[] = $t->slug;
        }
        return implode(' ', array_unique($slugs));
    }
    
    $product = wc_get_product($product_id);
    if ($product) {
        // Try pa_manufacturer attribute
        $val = $product->get_attribute('pa_manufacturer');
        if (!empty($val)) {
            return dsync_sanitize_manufacturer_slug($val);
        }
        
        // Try other attribute variations
        $attr_names = ['manufacturer', 'Manufacturer', 'MANUFACTURER'];
        foreach ($attr_names as $attr) {
            $val = $product->get_attribute($attr);
            if (!empty($val)) {
                return dsync_sanitize_manufacturer_slug($val);
            }
        }
        
        // Try custom field
        $cf = get_post_meta($product_id, 'Manufacturer', true);
        if (!empty($cf)) {
            return dsync_sanitize_manufacturer_slug($cf);
        }
    }

    return '';
}

/**
 * Sanitize manufacturer name to create clean slug
 */
function dsync_sanitize_manufacturer_slug($value) {
    // Decode HTML entities
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Replace problematic characters
    $value = str_replace(['/', ',', '&', '  '], ['-', '-', '-', ' '], $value);
    
    // Create slug
    $slug = sanitize_title($value);
    
    // Clean multiple hyphens
    $slug = preg_replace('/-+/', '-', $slug);
    
    return trim($slug, '-');
}

/**
 * Calculate discount price from various sources
 */
function dsync_get_discount_price_for_product($product_id) {
    $p = wc_get_product($product_id);
    if (! $p) {
        return '';
    }

    $final_discounted_price_string = '';

    // Check WooCommerce Discount Rules plugin
    if (class_exists('\Wdr\App\Controllers\ManageDiscount') && method_exists('\Wdr\App\Controllers\ManageDiscount', 'getDiscountDetailsOfAProduct')) {
        $plugin_discount_details = \Wdr\App\Controllers\ManageDiscount::getDiscountDetailsOfAProduct(false, $p, 1, 0);

        if ($plugin_discount_details && isset($plugin_discount_details['discounted_price']) && isset($plugin_discount_details['initial_price'])) {
            $plugin_calculated_discounted_price = floatval($plugin_discount_details['discounted_price']);
            $price_plugin_started_with = floatval($plugin_discount_details['initial_price']);

            if ($plugin_calculated_discounted_price < $price_plugin_started_with) {
                $regular_price = floatval($p->get_regular_price());
                if ($plugin_calculated_discounted_price < $regular_price) {
                    $rounded_price = ceil($plugin_calculated_discounted_price * 100) / 100;
                    $final_discounted_price_string = number_format($rounded_price, 2, '.', '');
                }
            }
        }
    }

    // Fallback to WooCommerce sale price
    if ($final_discounted_price_string === '') {
        $wc_sale_price = floatval($p->get_sale_price());
        $wc_regular_price = floatval($p->get_regular_price());
        if ($wc_sale_price > 0 && $wc_sale_price < $wc_regular_price) {
            $rounded_price = ceil($wc_sale_price * 100) / 100;
            $final_discounted_price_string = number_format($rounded_price, 2, '.', '');
        }
    }

    return $final_discounted_price_string;
}

/**
 * Get product class from custom field
 */
function dsync_get_product_class_for_product($product_id) {
    $product_class = get_post_meta($product_id, 'product_class', true);
    return !empty($product_class) ? sanitize_text_field($product_class) : '';
}

/**
 * Check if product is on sale including discount codes
 */
add_filter('woocommerce_product_is_on_sale', DSYNC_PREFIX . 'check_discount_for_on_sale', 10, 2);
function dsync_check_discount_for_on_sale($on_sale, $product) {
    if ($on_sale) {
        return $on_sale;
    }
    
    $discount_price = get_post_meta($product->get_id(), '_discount_codes', true);
    if (!empty($discount_price)) {
        return true;
    }
    
    return $on_sale;
}

/**
 * Add admin menu page
 */
add_action('admin_menu', DSYNC_PREFIX . 'add_debug_menu_page');
function dsync_add_debug_menu_page(){
    add_menu_page(
        'Doofinder Sync Debug',
        'Doofinder Sync',
        'manage_options',
        'doofinder-sync-debug',
        DSYNC_PREFIX . 'render_debug_page',
        'dashicons-search',          
        30
    );
}

/**
 * Render debug page
 */
function dsync_render_debug_page() {
    ?>
    <div class="wrap">
      <h1>Doofinder Sync - Meta Debug</h1>
      <p>Use this tool to inspect the dynamic metadata values generated for your products.</p>

      <h2>Doofinder Field Mapping Reference</h2>
      <p>Use these field names when configuring your Doofinder plugin mapping:</p>
      <table class="widefat fixed" cellspacing="0" style="margin-bottom: 20px; max-width: 600px;">
          <thead>
              <tr>
                  <th>Doofinder Attribute</th>
                  <th>Doofinder Field Name</th>
              </tr>
          </thead>
          <tbody>
              <tr>
                  <td><code>_category_slugs</code></td>
                  <td><code>category_slugs</code></td>
              </tr>
              <tr>
                  <td><code>_tag_slugs</code></td>
                  <td><code>tag_slugs</code></td>
              </tr>
              <tr>
                  <td><code>_manufacturer_slugs</code></td>
                  <td><code>manufacturer_slugs</code></td>
              </tr>
              <tr>
                  <td><code>_brand_slugs</code></td>
                  <td><code>brand_slugs</code></td>
              </tr>
              <tr>
                  <td><code>_discount_codes</code></td> 
                  <td><code>discount_price</code></td>
              </tr>
              <tr>
                  <td><code>_pewc_has_extra_fields</code></td> 
                  <td><code>pewc_has_extra_fields</code></td>
              </tr>
              <tr>
                  <td><code>_product_class</code></td> 
                  <td><code>product_class</code></td>
              </tr>
          </tbody>
      </table>

      <hr>

      <h2>Inspect Product Meta</h2>
      <form method="get">
        <input type="hidden" name="page" value="doofinder-sync-debug">
        <label for="dsync_pid">Product ID: </label> <input type="number" id="dsync_pid" name="pid" value="<?php echo esc_attr(isset($_GET['pid']) ? intval($_GET['pid']) : ''); ?>" style="width:80px;">
        <?php submit_button('Inspect', 'primary', 'dsync_submit_inspect'); ?>
      </form>
      <?php
      if (isset($_GET['pid']) && !empty($_GET['pid'])) {
          $pid = intval($_GET['pid']);
          $product = wc_get_product($pid);
          if ($product) {
              echo '<h3>Results for Product #'. esc_html($pid) .': ' . esc_html($product->get_name()) . '</h3>'; 
              echo '<table class="widefat fixed" cellspacing="0"><thead>
                      <tr><th style="width: 30%;">Meta Key (Internal WordPress)</th><th>Computed Value</th></tr>
                    </thead><tbody>'; 
              foreach (dsync_dynamic_meta_config() as $meta_key => $opts) {
                  $val = dsync_get_dynamic_meta_value($pid, $opts);
                  echo '<tr><td>'. esc_html($meta_key) .'</td>
                            <td>';
                  if (is_bool($val)) {
                      echo $val ? 'true' : 'false';
                  } elseif (is_array($val)) {
                      echo nl2br(esc_html(print_r($val, true)));
                  } else {
                      echo nl2br(esc_html((string)$val));
                  }
                  echo '</td></tr>';
              }
              echo '</tbody></table>';
          } else {
              echo '<p><strong>Product not found with ID: ' . esc_html($pid) . '</strong></p>'; 
          }
      }
      ?>
    </div>
    <?php
}

/**
 * Plugin activation check
 */
function dsync_activate_plugin() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Doofinder Sync requires WooCommerce to be installed and active. The plugin has been deactivated.'); 
    }
}
register_activation_hook(__FILE__, DSYNC_PREFIX . 'activate_plugin');

/**
 * Fix price structure with JavaScript
 */
function dsync_add_price_structure_fix() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('.price').each(function() {
            var $price = $(this);
            var $del = $price.find('del');
            
            if ($del.find('ins').length > 0) {
                var originalPriceHTML = $del.find('ins .woocommerce-Price-amount').parent().html();
                var currentPriceHTML = $price.find('del + ins .woocommerce-Price-amount').parent().html();
                var originalPriceText = $del.find('ins .woocommerce-Price-amount').text();
                var currentPriceText = $price.find('del + ins .woocommerce-Price-amount').text();
                
                var newHtml = '<del aria-hidden="true">' + originalPriceHTML + '</del> ';
                newHtml += '<span class="screen-reader-text">Original price was: ' + originalPriceText + '.</span>';
                newHtml += '<ins>' + currentPriceHTML + '</ins>';
                newHtml += '<span class="screen-reader-text">Current price is: ' + currentPriceText + '.</span>';
                
                $price.html(newHtml);
            }
        });
    });
    </script>
    <?php
}

add_action('wp_footer', 'dsync_add_price_structure_fix');