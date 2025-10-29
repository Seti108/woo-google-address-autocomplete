<?php
/**
 * Plugin Name: WooCommerce Google Address Autocomplete (Accessible)
 * Plugin URI: https://srhdesign.co.uk/
 * Description: Adds Google Places Autocomplete to WooCommerce checkout with session tokens, multi-region support, Consent Mode fallback, and WCAG AAA accessibility. Automatically registers Google Places as an address autocomplete provider and loads async for performance.
 * Author: Simon Harper (SRH Design)
 * Version: 1.3
 * License: GPL2+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ✅ Register Google Places as an autocomplete provider
 */
add_filter( 'woocommerce_address_autocomplete_providers', function( $providers ) {
	$providers['google_places'] = [
		'label'       => __( 'Google Places', 'woo-google-address-autocomplete' ),
		'description' => __( 'Use the Google Places API for address autocompletion.', 'woo-google-address-autocomplete' ),
		'callback'    => 'woo_google_address_autocomplete_provider',
	];
	return $providers;
});
function woo_google_address_autocomplete_provider( $address, $args = [] ) { return $address; }

/**
 * ✅ Enqueue async Google API + main JS/CSS
 */
add_action( 'wp_enqueue_scripts', function() {

	if ( ! ( is_checkout() || is_account_page() ) ) return;

	$google_api_key = 'YOUR-API-KEY_HERE';

	wp_enqueue_script(
		'google-places-api',
		'https://maps.googleapis.com/maps/api/js?key=' . $google_api_key . '&libraries=places&loading=async',
		[],
		null,
		true
	);

	add_filter( 'script_loader_tag', function( $tag, $handle ) {
		if ( 'google-places-api' === $handle ) $tag = str_replace( ' src', ' async defer src', $tag );
		return $tag;
	}, 10, 2 );

	wp_add_inline_script( 'google-places-api', <<<JS
	(function($) {
		'use strict';

		let billingToken, shippingToken, billingAuto, shippingAuto;
		let liveRegion;

		function googleReady() {
			return typeof google !== 'undefined' && google.maps && google.maps.places;
		}

		function createLiveRegion() {
			if ($('#address-autocomplete-status').length) return;
			liveRegion = $('<div>', {
				id: 'address-autocomplete-status',
				role: 'status',
				'aria-live': 'polite',
				class: 'sr-only',
				text: ''
			}).appendTo('body');
		}

		function updateLiveRegion(count) {
			if (!liveRegion) createLiveRegion();
			const msg = count > 0
				? count + ' address suggestions available. Use arrow keys to navigate.'
				: 'No address suggestions.';
			$('#address-autocomplete-status').text(msg);
		}

		function getCountryCode(type) {
			const el = document.getElementById(type + '_country');
			if (el) return (el.value || el.options[el.selectedIndex]?.value || 'gb').toLowerCase();
			return 'gb';
		}

		function initAutocomplete() {
			createLiveRegion();

			const billingInput  = document.getElementById('billing_address_1');
			const shippingInput = document.getElementById('shipping_address_1');
			if (!billingInput && !shippingInput) return;

			const fields = ['address_components', 'geometry'];

			if (billingInput) {
				billingToken = new google.maps.places.AutocompleteSessionToken();
				billingAuto = new google.maps.places.Autocomplete(billingInput, {
					types: ['address'],
					componentRestrictions: { country: getCountryCode('billing') },
					sessionToken: billingToken
				});
				billingAuto.setFields(fields);
				billingAuto.addListener('place_changed', function() {
					fillAddress(billingAuto, 'billing');
					billingToken = new google.maps.places.AutocompleteSessionToken();
				});
				addLiveCountListener(billingInput);
			}

			if (shippingInput) {
				shippingToken = new google.maps.places.AutocompleteSessionToken();
				shippingAuto = new google.maps.places.Autocomplete(shippingInput, {
					types: ['address'],
					componentRestrictions: { country: getCountryCode('shipping') },
					sessionToken: shippingToken
				});
				shippingAuto.setFields(fields);
				shippingAuto.addListener('place_changed', function() {
					fillAddress(shippingAuto, 'shipping');
					shippingToken = new google.maps.places.AutocompleteSessionToken();
				});
				addLiveCountListener(shippingInput);
			}

			$('#billing_country, #shipping_country').on('change', function() {
				if (billingAuto && $(this).attr('id') === 'billing_country') {
					billingAuto.setComponentRestrictions({ country: getCountryCode('billing') });
				}
				if (shippingAuto && $(this).attr('id') === 'shipping_country') {
					shippingAuto.setComponentRestrictions({ country: getCountryCode('shipping') });
				}
			});
		}

		function addLiveCountListener(input) {
			let lastCount = 0;
			$(input).on('input', function() {
				setTimeout(function() {
					const count = document.querySelectorAll('.pac-item').length;
					if (count !== lastCount) {
						updateLiveRegion(count);
						lastCount = count;
					}
				}, 250);
			});
		}

		// ✅ UK/IE-aware with Address 2 (subpremise/unit)
		function fillAddress(autocomplete, type) {
			const place = autocomplete.getPlace();
			if (!place || !place.address_components) return;

			const comp = {};
			place.address_components.forEach(c => c.types.forEach(t => { if (!comp[t]) comp[t] = c; }));

			const getLong = (...keys) => keys.find(k => comp[k]?.long_name) ? comp[keys.find(k => comp[k]?.long_name)].long_name : '';
			const getShort = (...keys) => keys.find(k => comp[k]?.short_name) ? comp[keys.find(k => comp[k]?.short_name)].short_name : '';

			const streetNumber = getLong('street_number');
			const route = getLong('route');
			const address1 = [streetNumber, route].filter(Boolean).join(' ').trim();
			const address2 = getLong('subpremise'); // flat/unit

			const postcode = getLong('postal_code');
			const countryCode = getShort('country');
			const city = getLong('locality','postal_town','sublocality','sublocality_level_1','administrative_area_level_3');

			let region = getLong('administrative_area_level_1');
			if (!region || countryCode === 'GB' || countryCode === 'IE') {
				region = getLong('administrative_area_level_2') || region;
			}

			$('#' + type + '_address_1').val(address1);
			if (address2) $('#' + type + '_address_2').val(address2);
			$('#' + type + '_city').val(city).trigger('change');
			$('#' + type + '_postcode').val(postcode).trigger('change');
			$('#' + type + '_country').val(countryCode).trigger('change');
			$('#' + type + '_state').val(region).trigger('change');
		}

		function enableManualEntry() {
			console.warn('Google Autocomplete unavailable or consent not granted. Manual entry mode active.');
			$('.woocommerce-address-fields__field-wrapper input').attr('autocomplete', 'on');
		}

		function tryInit() { googleReady() ? initAutocomplete() : enableManualEntry(); }

		$(window).on('load', () => setTimeout(tryInit, 800));

		window.addEventListener('consent', function(e) {
			const g = e.detail;
			const granted = g?.ad_storage==='granted'||g?.functionality_storage==='granted'||g?.analytics_storage==='granted';
			if (granted && googleReady()) initAutocomplete();
		});
	})(jQuery);
JS);
/*
	wp_add_inline_style( 'wp-block-library', <<<CSS
	.pac-container {
		z-index: 10000 !important;
		font-size: 16px;
		border-radius: 8px;
		box-shadow: 0 4px 12px rgba(0,0,0,0.12);
		background-color: #fff;
	}
	.pac-item { font-family: inherit; padding: 10px 14px; line-height: 1.4; cursor: pointer; }
	.pac-item:hover, .pac-item:focus { background-color: #f5f5f5; outline: none; }
	.pac-item-selected, .pac-item.pac-item-selected:focus {
		background-color: #e8f0fe; border-left: 4px solid #1a73e8;
		outline: 2px solid #1a73e8; outline-offset: -2px;
	}
	.pac-item-query { font-weight: 600; color: #111; }
	.pac-container:focus-within { border: 2px solid #1a73e8; }
	@media (hover:none){.pac-item{padding:14px 16px;font-size:18px}}
	@media (prefers-color-scheme:dark){
		.pac-container{background-color:#1f1f1f;color:#f1f1f1}
		.pac-item:hover,.pac-item:focus{background-color:#333}
		.pac-item-selected{background-color:#2b4d9e;border-left-color:#7ba5ff}
	}
	.sr-only{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
CSS); */
});
