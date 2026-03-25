# Feedlingo GA4 Product ID Fix for Shopware

🔥 Fix incorrect Shopware UUID product IDs in GA4, GTM and Google Ads tracking.

Many Shopware 6 stores send internal UUIDs instead of real product numbers in ecommerce tracking.  
This plugin replaces those UUIDs with real product IDs (SKU / productNumber).

---

## 🚀 New in version 2.0.0

- Purchase event handling fixed  
- Item ID fix now works reliably during checkout  
- Improved stability for order and line-item processing  

👉 Download:  
https://www.feedlingo.de/ga4-product-id-fix-fur-shopware/

---

## The Problem

In many Shopware 6 setups, ecommerce tracking sends internal **Shopware UUIDs** instead of real product identifiers.

These UUIDs do not match the product IDs used in:

- your Shopware catalog  
- Google Merchant Center  
- product feeds  
- Google Ads / remarketing setups  

This typically leads to:

- incorrect GA4 product reports  
- difficult data analysis  
- mismatches between tracking data and product feeds  
- unreliable remarketing setups  

---

## The Solution

The plugin **Feedlingo GA4 Product ID Fix** automatically replaces Shopware UUIDs with real product numbers (**productNumber / SKU**).

This ensures:

- consistent product IDs across tracking and feeds  
- correct GA4 ecommerce reporting  
- clean data for analysis and marketing  

---

## What the Plugin Does

The plugin operates only on ecommerce tracking output in the storefront.

- replaces UUID → real product number  
- corrects item IDs in tracking events  
- works automatically (no configuration required)  
- does not interfere with shop logic  

---

## Google Ads Relevance

Correct product IDs are essential for many marketing setups.

This includes:

- GA4-based reporting  
- GTM-based tracking  
- remarketing setups  
- product-based campaign analysis  

👉 Important:  
The plugin ensures correct IDs in tracking data.  
How Google Ads uses that data depends on your specific implementation.

---

## Safety

The plugin is designed to be safe and non-destructive.

It:

- does **not** modify products  
- does **not** modify orders  
- does **not** write to the database for the tracking fix  
- does **not** change shop configuration  

👉 It only adjusts product IDs in tracking output.

---

## Compatibility

Compatible with:

- Shopware 6.5  
- Shopware 6.6  
- Shopware 6.7  

Works with:

- Google Analytics 4 (GA4)  
- Google Tag Manager (GTM)  
- dataLayer-based ecommerce tracking  

---

## Installation

1. Download the plugin ZIP  
2. Log in to your Shopware Admin  
3. Go to **Extensions → My Extensions**  
4. Click **Upload extension**  
5. Upload the ZIP file  
6. Install and activate the plugin  
7. Clear the shop cache  

👉 The plugin works automatically after activation.

---

## How to Verify

1. Open your shop with your tracking setup  
2. Use GA4 DebugView, Tag Assistant or GTM preview  
3. Trigger ecommerce events (view item, add to cart, purchase)  
4. Check that item IDs are **product numbers (SKU)** instead of UUIDs  

---

## Changelog

### 2.0.0

- purchase event handling fixed  
- item ID fix now works reliably during checkout  
- improved stability for order and line-item processing  
- internal refactoring  

---

## Developer

**Feedlingo**  
https://www.feedlingo.de

---

## ⭐ Support

If this plugin helps you, consider giving it a star on GitHub.

👉 https://github.com/Feedlingo/shopware-ga4-product-id-fix
