<?php
/**
 * Plugin Name: WooCommerce Google Address Autocomplete
 * Plugin URI: https://srhdesign.co.uk/
 * Description: Adds Google Places Autocomplete to WooCommerce checkout with the new Places API, session tokens, multi-region support. Works with both classic and block checkouts.
 * Author: Simon Harper (SRH Design)
 * Version: 2.0
 * License: GPL2+
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 10.3
 * WC tested up to: 10.4
 */

if ( ! defined( 'ABSPATH' ) )
  exit;

// Declare HPOS compatibility
add_action( 'before_woocommerce_init', function () {
  if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'address_autocomplete', __FILE__, true );
  }
} );

// Initialize on plugins_loaded
add_action( 'plugins_loaded', function () {

  // Make sure WooCommerce is active
  if ( ! class_exists( 'WooCommerce' ) ) {
    return;
  }

  /**
   * Google Places Integration Settings
   */
  class SRH_Google_Places_Integration extends WC_Integration {

    public function __construct() {
      $this->id = 'srh_google_places';
      $this->method_title = __( 'Google Places', 'woo-google-address-autocomplete' );
      $this->method_description = __( 'Configure Google Places API for accessible address autocomplete on checkout.', 'woo-google-address-autocomplete' );

      // Load settings
      $this->init_form_fields();
      $this->init_settings();

      // Save settings
      add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
          'title' => __( 'Enable/Disable', 'woo-google-address-autocomplete' ),
          'type' => 'checkbox',
          'label' => __( 'Enable Google address autocomplete', 'woo-google-address-autocomplete' ),
          'default' => 'yes',
        ),
        'api_key' => array(
          'title' => __( 'Google API Key', 'woo-google-address-autocomplete' ),
          'type' => 'password',
          'description' => sprintf(
            __( 'Get your API key from <a href="%s" target="_blank">Google Cloud Console</a>. Make sure to enable the Places API (New).', 'woo-google-address-autocomplete' ),
            'https://console.cloud.google.com/apis/credentials'
          ),
          'desc_tip' => false,
          'placeholder' => 'AIza...',
          'custom_attributes' => array(
            'autocomplete' => 'off',
          ),
        ),
        'countries' => array(
          'title' => __( 'Country Restrictions', 'woo-google-address-autocomplete' ),
          'type' => 'text',
          'description' => __( 'Limit autocomplete to specific countries. Use 2-letter country codes separated by commas (e.g., US,CA,GB). Leave empty for all countries.', 'woo-google-address-autocomplete' ),
          'desc_tip' => false,
          'placeholder' => 'AT,BE,BG,HR,CY,CZ,DK,EE,FI,FR,DE,GR,HU,IE,IT,LV,LT,LU,MT,NL,PL,PT,RO,SK,SI,ES,SE',
        ),
      );
    }

    /**
     * Generate the API key field with obfuscation
     */
    public function generate_password_html( $key, $data ) {
      $field_key = $this->get_field_key( $key );
      $defaults = array(
        'title' => '',
        'disabled' => false,
        'class' => '',
        'css' => '',
        'placeholder' => '',
        'type' => 'text',
        'desc_tip' => false,
        'description' => '',
        'custom_attributes' => array(),
      );

      $data = wp_parse_args( $data, $defaults );

      $value = $this->get_option( $key );

      // Obfuscate the API key if it exists
      if ( ! empty( $value ) ) {
        // Show first 8 and last 4 characters
        if ( strlen( $value ) > 12 ) {
          $display_value = substr( $value, 0, 8 ) . str_repeat( '•', strlen( $value ) - 12 ) . substr( $value, -4 );
        } else {
          $display_value = str_repeat( '•', strlen( $value ) );
        }
      } else {
        $display_value = '';
      }

      ob_start();
      ?>
      <tr valign="top">
        <th scope="row" class="titledesc">
          <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?>
            <?php echo $this->get_tooltip_html( $data ); ?></label>
        </th>
        <td class="forminp">
          <fieldset>
            <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
            <input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="password"
              name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>"
              style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $display_value ); ?>"
              placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?>
              <?php echo $this->get_custom_attribute_html( $data ); ?> />
            <?php if ( ! empty( $value ) ) : ?>
              <br>
              <label style="margin-top: 8px; display: inline-block;">
                <input type="checkbox" id="<?php echo esc_attr( $field_key ); ?>_change"
                  name="<?php echo esc_attr( $field_key ); ?>_change" value="1" style="width: auto;">
                <span
                  style="vertical-align: middle;"><?php esc_html_e( 'Change API key', 'woo-google-address-autocomplete' ); ?></span>
              </label>
              <script type="text/javascript">
                jQuery(document).ready(function ($) {
                  var $input = $('#<?php echo esc_js( $field_key ); ?>');
                  var $checkbox = $('#<?php echo esc_js( $field_key ); ?>_change');

                  $input.prop('readonly', true);

                  $checkbox.on('change', function () {
                    if ($(this).is(':checked')) {
                      $input.val('').prop('readonly', false).focus();
                    } else {
                      $input.val('<?php echo esc_js( $display_value ); ?>').prop('readonly', true);
                    }
                  });
                });
              </script>
            <?php endif; ?>
            <?php echo $this->get_description_html( $data ); ?>
          </fieldset>
        </td>
      </tr>
      <?php

      return ob_get_clean();
    }

    /**
     * Validate and save the API key
     */
    public function validate_password_field( $key, $value ) {
      // If "change" checkbox is not checked and we have an existing value, keep the existing value
      $change_key = $this->get_field_key( $key ) . '_change';
      if ( ! isset( $_POST[ $change_key ] ) || $_POST[ $change_key ] !== '1' ) {
        $existing_value = $this->get_option( $key );
        if ( ! empty( $existing_value ) ) {
          return $existing_value;
        }
      }

      // Otherwise, validate and save the new value
      return sanitize_text_field( $value );
    }

  }

  /**
   * Google Address Provider for WooCommerce
   */
  class SRH_Google_Address_Provider extends WC_Address_Provider {

    public $id = 'srh_google_places';
    public $name = 'Google Places (SRH)';

    private $settings;

    public function __construct() {
      $this->name = __( 'Google Places', 'woo-google-address-autocomplete' );
      $this->settings = new SRH_Google_Places_Integration();

      add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue scripts on checkout
     */
    public function enqueue_scripts() {
      // Only on checkout pages
      if ( ! is_checkout() && ! has_block( 'woocommerce/checkout' ) ) {
        return;
      }

      // Check if enabled
      if ( get_option( 'woocommerce_address_autocomplete_enabled' ) !== 'yes' || $this->settings->get_option( 'enabled' ) !== 'yes' ) {
        return;
      }

      $api_key = $this->settings->get_option( 'api_key' );
      if ( empty( $api_key ) ) {
        return;
      }

      // Pass config to JS first
      $countries = $this->settings->get_option( 'countries', '' );
      wp_add_inline_script(
        'wc-address-autocomplete',
        'window.srhGaaConfig = ' . wp_json_encode( array(
          'apiKey' => $api_key,
          'countries' => empty( $countries ) ? array() : array_map( 'trim', explode( ',', strtoupper( $countries ) ) ),
        ) ) . ';',
        'before'
      );

      // Add inline script with the new Places API implementation
      wp_add_inline_script( 'wc-address-autocomplete', $this->get_inline_script() );
    }

    /**
     * Get inline JavaScript
     */
    private function get_inline_script() {
      return <<<'JS'
(function () {
  "use strict";

  // Wait for dependencies
  if (
    typeof window.wc === "undefined" ||
    typeof window.wc.addressAutocomplete === "undefined"
  ) {
    console.error("WooCommerce address autocomplete not loaded");
    return;
  }

  // Check for API key
  if (typeof srhGaaConfig === "undefined" || !srhGaaConfig.apiKey) {
    console.error("Google API key not configured");
    return;
  }

  // Session token for billing optimization
  let sessionToken = generateSessionToken();

  /**
   * Generate a random session token (UUID v4)
   */
  function generateSessionToken() {
    if (typeof crypto !== "undefined" && crypto.randomUUID) {
      return crypto.randomUUID();
    }
    // Fallback for older browsers
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  /**
   * Google Places Provider (Accessible)
   */
  const srhGooglePlacesProvider = {
    id: "srh_google_places",
    name: "Google Places",

    /**
     * Check if we can search in the given country
     */
    canSearch: function (country) {
      // If no country restrictions, support all
      if (!srhGaaConfig.countries || srhGaaConfig.countries.length === 0) {
        return true;
      }
      return srhGaaConfig.countries.includes(country.toUpperCase());
    },

    /**
     * Search for addresses using Google Places API (New)
     */
    search: async function (query, country, type) {
      if (!query || query.length < 3) {
        return [];
      }

      try {
        // Build request body
        const requestBody = {
          input: query,
          languageCode: "en",
          sessionToken: sessionToken,
        };

        // Add country restriction if provided
        if (country) {
          requestBody.includedRegionCodes = [country.toLowerCase()];
        }

        // Call Google Places API (New)
        const response = await fetch(
          `https://places.googleapis.com/v1/places:autocomplete`,
          {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Goog-Api-Key": srhGaaConfig.apiKey,
            },
            body: JSON.stringify(requestBody),
          }
        );

        if (!response.ok) {
          const errorData = await response.json().catch(() => ({}));
          console.error("Autocomplete API error:", response.status, errorData);
          return [];
        }

        const data = await response.json();

        if (!data.suggestions || data.suggestions.length === 0) {
          return [];
        }

        // Format results for WooCommerce with accessibility enhancements
        const formattedSuggestions = data.suggestions.map((suggestion) => {
          const result = {
            id: suggestion.placePrediction.placeId,
            label: suggestion.placePrediction.text.text,
          };

          // Convert Google's matches format to WooCommerce's matchedSubstrings format
          if (
            suggestion.placePrediction.structuredFormat &&
            suggestion.placePrediction.structuredFormat.mainText &&
            suggestion.placePrediction.structuredFormat.mainText.matches
          ) {
            result.matchedSubstrings =
              suggestion.placePrediction.structuredFormat.mainText.matches.map(
                (match) => ({
                  offset: match.startOffset || 0,
                  length: match.endOffset - (match.startOffset || 0),
                })
              );
          }

          return result;
        });

        return formattedSuggestions;
      } catch (error) {
        console.error("Search error:", error);
        return [];
      }
    },

    /**
     * Get full address details using Google Places API (New)
     */
    select: async function (placeId) {
      try {
        // Call Google Places API (New) - Place Details
        const response = await fetch(
          `https://places.googleapis.com/v1/places/${encodeURIComponent(placeId)}?sessionToken=${encodeURIComponent(sessionToken)}`,
          {
            method: "GET",
            headers: {
              "Content-Type": "application/json",
              "X-Goog-Api-Key": srhGaaConfig.apiKey,
              "X-Goog-FieldMask": "addressComponents",
            },
          }
        );

        if (!response.ok) {
          const errorData = await response.json().catch(() => ({}));
          console.error("Place Details API error:", response.status, errorData);
          return null;
        }

        const data = await response.json();

        if (!data.addressComponents) {
          return null;
        }

        // Parse address components into WooCommerce format
        const address = parseAddressComponents(data.addressComponents);

        // Reset session token after place selection (session ends)
        sessionToken = generateSessionToken();

        return address;
      } catch (error) {
        console.error("Select error:", error);
        return null;
      }
    },
  };

  /**
   * Parse Google address components to WooCommerce format
   * Enhanced for UK/IE addresses with proper handling of counties
   */
  function parseAddressComponents(components) {
    const address = {
      address_1: "",
      address_2: "",
      city: "",
      state: "",
      postcode: "",
      country: "",
    };

    let streetNumber = "";
    let route = "";
    let countryCode = "";

    components.forEach((component) => {
      const types = component.types;

      if (types.includes("street_number")) {
        streetNumber = component.longText;
      } else if (types.includes("route")) {
        route = component.longText;
      } else if (types.includes("subpremise")) {
        // Flat/Unit number
        address.address_2 = component.longText;
      } else if (types.includes("locality")) {
        address.city = component.longText;
      } else if (types.includes("postal_town") && !address.city) {
        // UK postal towns
        address.city = component.longText;
      } else if (types.includes("administrative_area_level_1")) {
        address.state = component.shortText;
      } else if (types.includes("administrative_area_level_2") && !address.state) {
        // For UK/IE, use level 2 (county) if level 1 not available
        address.state = component.longText;
      } else if (types.includes("postal_code")) {
        address.postcode = component.longText;
      } else if (types.includes("country")) {
        address.country = component.shortText;
        countryCode = component.shortText;
      }
    });

    // UK/IE specific: prefer administrative_area_level_2 for state
    if (countryCode === "GB" || countryCode === "IE") {
      components.forEach((component) => {
        if (component.types.includes("administrative_area_level_2")) {
          address.state = component.longText;
        }
      });
    }

    // Combine street number and route for address_1
    if (streetNumber && route) {
      address.address_1 = streetNumber + " " + route;
    } else if (route) {
      address.address_1 = route;
    }

    return address;
  }

  // Register provider with WooCommerce
  window.wc.addressAutocomplete.registerAddressAutocompleteProvider(
    srhGooglePlacesProvider
  );
})();
JS;
    }

  }

  // Register integration
  add_filter( 'woocommerce_integrations', function ( $integrations ) {
    $integrations[] = 'SRH_Google_Places_Integration';
    return $integrations;
  } );

  // Register address provider
  add_filter( 'woocommerce_address_providers', function ( $providers ) {
    $providers[] = 'SRH_Google_Address_Provider';
    return $providers;
  } );

} );