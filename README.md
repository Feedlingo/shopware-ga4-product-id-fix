# Feedlingo GA4 Product ID Fix for Shopware

Free Shopware 6 plugin that replaces internal Shopware UUID product IDs with real product numbers (productNumber / SKU) in Google Analytics 4 ecommerce tracking.

Developed by **Feedlingo**  
https://www.feedlingo.de

---

## Download

The plugin can be downloaded from the official Feedlingo website:

https://www.feedlingo.de/ga4-product-id-fix-fur-shopware/?lang=en

---

## Problem

Many Shopware 6 stores send internal Shopware UUID product IDs in ecommerce tracking events.

These UUIDs do not match the product IDs used in product feeds, Google Merchant Center or external systems. As a result, ecommerce tracking reports in Google Analytics 4 can contain product IDs that cannot easily be matched with real products in the shop.

This makes analysis of ecommerce data more difficult and can cause inconsistencies between tracking data and product feeds.

---

## Solution

Feedlingo GA4 Product ID Fix replaces the internal Shopware UUID with the actual product number (productNumber / SKU) inside ecommerce tracking events.

Instead of sending a Shopware UUID as the product identifier, the plugin automatically sends the real product number used in the shop.

This ensures that product IDs in tracking data correspond to the actual product numbers used in the shop and in product feeds.

---

## Features

- Fixes incorrect product IDs in GA4 ecommerce tracking
- Replaces Shopware UUIDs with real product numbers (SKU / productNumber)
- Works with Google Analytics 4
- Compatible with Google Tag Manager
- Lightweight implementation
- No database changes
- No modification of shop data

---

## Compatibility

The plugin is compatible with:

- Shopware 6.5  
- Shopware 6.6  
- Shopware 6.7  

---

## Installation

1. Download the plugin from the Feedlingo website  
2. Log in to the Shopware administration panel  
3. Go to **Extensions → My Extensions**  
4. Upload the plugin ZIP file  
5. Install and activate the plugin  
6. Clear the shop cache  

After activation the plugin works automatically.

---

## Safety

The plugin was designed to be completely non-destructive.

It does not modify:

- products
- orders
- database records

The plugin only replaces product IDs inside ecommerce tracking events in the storefront.

---

## Website

More tools and updates are available at:

https://www.feedlingo.de

---

## License

MIT License
