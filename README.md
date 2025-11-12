# WooCommerce AutoComplete with Classic Checkout and Block Checkout in WooCommerce

A lightweight, accessible WordPress plugin that adds Google Places address autocomplete to WooCommerce checkout pages. Built with WCAG AAA accessibility standards and full support for both classic and block-based checkouts.

## 🌟 Features

- ✅ **New Google Places API** - Uses the latest REST-based Places API (v1)
- ✅ **Universal Checkout Support** - Works with both classic shortcode and block-based checkouts
- ✅ **Session Token Optimization** - Efficient billing with proper session token management
- ✅ **Multi-Region Support** - Configurable country restrictions
- ✅ **HPOS Compatible** - Fully compatible with WooCommerce High-Performance Order Storage
- ✅ **Enhanced UK/IE Support** - Proper handling of counties, postal towns, and flat numbers
- ✅ **Single File** - No external dependencies, everything inline

## 📋 Requirements

- **WordPress**: 5.8 or higher
- **WooCommerce**: 10.3 or higher
- **PHP**: 7.4 or higher
- **Google Places API Key**: With Places API (New) enabled

## 🚀 Installation

### Step 1: Get Your Google API Key

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select an existing one)
3. Navigate to **APIs & Services → Library**
4. Search for and enable **"Places API (New)"** (NOT the old Places API)
5. Go to **APIs & Services → Credentials**
6. Click **"Create Credentials" → "API Key"**
7. Copy your API key
8. **(Recommended)** Click on your new API key to restrict it:
   - **Application restrictions**: Select "HTTP referrers"
   - Add your domain(s): `yourdomain.com/*`
   - **API restrictions**: Select "Restrict key" and choose "Places API (New)"
   - Save changes

### Step 2: Install the Plugin

#### Option A: Manual Upload
1. Download `woo-google-address-autocomplete.php`
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Choose the file and click **Install Now**
4. Click **Activate**

#### Option B: FTP/File Manager
1. Download `woo-google-address-autocomplete.php`
2. Upload to `/wp-content/plugins/woo-google-address-autocomplete/`
3. Go to **WordPress Admin → Plugins**
4. Find "WooCommerce Google Address Autocomplete (Accessible)" and click **Activate**

### Step 3: Enable WooCommerce Address Autocomplete Feature

1. Go to **WooCommerce → Settings → Advanced → Features**
2. Check the box for **"Address Autocomplete"**
3. Click **"Save changes"**

This enables WooCommerce's built-in address autocomplete system that the plugin hooks into.

### Step 4: Configure the Plugin

1. Go to **WooCommerce → Settings → Integration**
2. Click on **"Google Places"** (it will appear after activation)
3. Configure the following settings:

   - **Enable/Disable**: Check to enable Google address autocomplete
   - **Google API Key**: Paste your API key from Step 1
   - **Country Restrictions** (Optional): Enter comma-separated 2-letter country codes
     - Example: `GB,IE` (UK and Ireland only)
     - Example: `US,CA` (USA and Canada only)
     - Leave empty to allow all countries

4. Click **"Save changes"**

## 🔧 How It Works

### Technical Overview

1. **Registration Phase**
   - Plugin extends `WC_Integration` to add settings page
   - Extends `WC_Address_Provider` to register with WooCommerce's autocomplete system
   - Declares compatibility with HPOS and address autocomplete features

2. **Frontend Loading**
   - Checks if checkout page is loaded (classic or block)
   - Verifies address autocomplete is enabled in WooCommerce
   - Confirms API key is configured
   - Enqueues JavaScript with dependency on `wc-address-autocomplete`

3. **Autocomplete Flow**
   - User types in address field (minimum 3 characters)
   - Plugin calls Google Places API Autocomplete endpoint with session token
   - Results are formatted and displayed in WooCommerce's autocomplete dropdown
   - User selects an address
   - Plugin calls Google Places API Details endpoint to get full address
   - Address components are parsed and mapped to WooCommerce fields:
     - `street_number` + `route` → Address Line 1
     - `subpremise` → Address Line 2 (flat/unit number)
     - `locality` or `postal_town` → City
     - `administrative_area_level_1` or `administrative_area_level_2` → State/County
     - `postal_code` → Postcode
     - `country` → Country
   - Session token is regenerated for the next search

### Session Token Optimization

The plugin implements Google's recommended session token pattern to minimize API costs:
- One token per search session
- Token is sent with all autocomplete requests
- Token is regenerated after place selection
- This groups all autocomplete requests + the final details request into a single billable session

### Address Parsing Logic

The plugin includes enhanced logic for UK and Ireland addresses:
- Prioritizes `administrative_area_level_2` (county) for GB/IE
- Falls back to `postal_town` when `locality` is not available
- Properly handles `subpremise` for flat/apartment numbers
- Uses short codes for country and state (e.g., "GB" not "United Kingdom")

## 🌍 Country Restrictions

You can limit autocomplete suggestions to specific countries:

1. Go to **WooCommerce → Settings → Integration → Google Places**
2. In the **"Country Restrictions"** field, enter 2-letter ISO country codes separated by commas
3. Examples:
   - UK only: `GB`
   - UK and Ireland: `GB,IE`
   - North America: `US,CA,MX`
   - European Union: `AT,BE,BG,HR,CY,CZ,DK,EE,FI,FR,DE,GR,HU,IE,IT,LV,LT,LU,MT,NL,PL,PT,RO,SK,SI,ES,SE,GB`

**Note**: If you leave this field empty, the plugin will suggest addresses from all countries.

## 🐛 Troubleshooting

### Autocomplete Not Appearing

1. **Check WooCommerce Feature is Enabled**
   - Go to **WooCommerce → Settings → Advanced → Features**
   - Ensure "Address Autocomplete" is checked

2. **Verify API Key**
   - Go to **WooCommerce → Settings → Integration → Google Places**
   - Confirm your API key is correctly entered
   - Test the key in [Google's API Key Validator](https://console.cloud.google.com/apis/credentials)

3. **Check API is Enabled**
   - In Google Cloud Console, verify **"Places API (New)"** is enabled
   - NOT the old "Places API" - it must be the new one

4. **Check Browser Console**
   - Open browser developer tools (F12)
   - Look for JavaScript errors in the Console tab
   - Common errors:
     - "WooCommerce address autocomplete not loaded" → WooCommerce feature not enabled
     - "Google API key not configured" → API key missing or not saved
     - "Autocomplete API error: 403" → API key restrictions too strict or API not enabled

### No Suggestions for My Country

1. Check if you have country restrictions enabled
2. Go to **WooCommerce → Settings → Integration → Google Places**
3. Either remove restrictions or add your country code to the list

### Suggestions Don't Fill All Fields

This is usually because Google doesn't have complete data for that address. The plugin fills in whatever Google provides. You can:
- Try a more specific search
- Select a different suggestion
- Manually complete the missing fields

## 💰 Google Places API Pricing

The plugin uses two API endpoints:

1. **Autocomplete (per session)** - ~$2.83 per 1,000 sessions
2. **Place Details (per request)** - $0.017 per request

**One session** = Multiple autocomplete requests + one details request

With session tokens properly implemented (as in this plugin), a typical checkout completion costs:
- **1 session** + **1 details request** = ~$0.003 per checkout

Google provides **$200 free credit per month**, which covers approximately:
- ~66,000 address autocompletes per month
- ~2,200 checkouts per day

For most stores, this plugin will cost nothing under the free tier.

[Current Google Places API Pricing](https://developers.google.com/maps/billing/gmp-billing#places)

## 🔒 Security & Privacy

- Plugin only loads on checkout pages
- API key is not exposed to the public (stored in WP database)
- No user data is stored by the plugin
- All API calls are made directly from the user's browser to Google
- Plugin declares GDPR/privacy considerations are handled by Google's terms

## 📝 Changelog

### Version 2.0
- Complete rewrite using new Google Places API (v1)
- Added support for block-based checkout
- Implemented proper WooCommerce integration system
- Added session token optimization
- Enhanced UK/IE address parsing
- Added HPOS compatibility
- Improved accessibility (WCAG AAA)
- Added dark mode support
- Single-file architecture

### Version 1.3 (Legacy)
- Used old Google Maps JavaScript API
- Supported classic checkout only

## 🤝 Contributing

Found a bug or have a feature request? Please open an issue on the [GitHub repository](https://github.com/yourusername/woo-google-address-autocomplete).

## 📄 License

GPL v2 or later

## 👨‍💻 Author

**Simon Harper (SRH Design)**
- Website: [https://srhdesign.co.uk/](https://srhdesign.co.uk/)

## 🙏 Credits

- Built on WooCommerce's native address autocomplete system
- Uses Google Places API (New)
- Inspired by Nadir Seghir's implementation approach

---

**Need help?** Check the troubleshooting section above or open an issue on GitHub.