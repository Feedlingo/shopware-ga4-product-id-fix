# Feedlingo GA4 Product ID Fix

Fix incorrect Shopware UUID product IDs in Google Analytics 4 and in many Google Ads remarketing setups.

Developed by **Feedlingo**  
Website: **https://www.feedlingo.de**

---

## New in version 2.0.0

Version 2.0.0 is a major update.

It includes a fundamental revision of the purchase / checkout handling. In previous releases, there was a critical issue in which the purchase event was no longer processed correctly in the affected flow. As a result, the actual item ID fix could no longer reliably apply on purchase.

### Version 2.0.0 highlights

- major internal rework of purchase / checkout handling
- purchase event processing restored in the affected flow
- item ID fix applies reliably again during purchase
- improved stability in order and line-item handling
- updated plugin branding / icon

---

## Why this plugin exists

Many Shopware 6 tracking setups send internal **Shopware UUID product IDs** instead of the real product numbers used in feeds and merchant systems.

That mismatch can lead to:

- incorrect GA4 ecommerce item IDs
- broken or unreliable product reporting
- poor matching between tracking data and product feeds
- remarketing issues when product IDs must match Merchant Center IDs

**Feedlingo GA4 Product ID Fix** replaces Shopware UUID-based item IDs with the real **product numbers / SKUs** already used in the shop.

This helps align tracked item IDs with the identifiers commonly used in:

- Google Analytics 4 (GA4)
- Google Tag Manager (GTM)
- Google Merchant Center
- Google Shopping feeds
- many Google Ads remarketing and dynamic remarketing implementations that rely on GA4 or dataLayer ecommerce data

---

## What the plugin does

- replaces Shopware UUID product IDs in storefront tracking output
- uses the real Shopware **productNumber / SKU** instead
- keeps the existing plugin logic focused on safe ID mapping only
- supports typical Shopware storefront ecommerce tracking flows
- also covers the order-finish mapping logic already included in the plugin

---

## Important Google Ads note

This plugin is highly useful for **Google Ads** when your Ads setup uses:

- GA4 ecommerce events
- Google Tag Manager variables reading ecommerce/dataLayer events
- remarketing implementations that depend on product IDs matching Merchant Center feed IDs

However, Google Ads compatibility always depends on the individual tracking setup.

If a shop uses a completely separate Google Ads implementation that sends product IDs independently from the corrected Shopware / GA4 / dataLayer events, this plugin cannot automatically fix that separate implementation.

So the correct and legally safer statement is:

> This plugin fixes Shopware UUID item IDs in GA4- and dataLayer-based ecommerce tracking and is therefore suitable for many Google Ads and dynamic remarketing setups, but exact Google Ads behavior depends on the shop's tagging implementation.

---

## Features

- fixes Shopware UUID item IDs
- sends real product numbers (**SKU / productNumber**)
- compatible with **Shopware 6.5, 6.6 and 6.7**
- works with **Shopware storefront tracking output**
- suitable for **GA4**
- compatible with **GTM-based setups**
- useful for many **Google Ads remarketing scenarios**
- lightweight
- no configuration required for the core fix
- designed to be safe and non-destructive

---

## Safety

This plugin was built with a strong focus on operational safety.

It:

- does **not** modify products
- does **not** modify orders
- does **not** write to the database for the tracking fix itself
- does **not** change catalog content
- does **not** overwrite shop configuration
- only adjusts tracking-related item IDs in the relevant storefront output

In other words:

> The plugin is intended to improve tracking consistency without putting productive shop data at risk.

---

## Installation

1. Download the plugin ZIP.
2. Log in to your **Shopware Admin**.
3. Open **Extensions → My Extensions**.
4. Use **Upload extension**.
5. Upload the ZIP file.
6. Install and activate the plugin.
7. Clear the shop cache.

The plugin works automatically after activation.

---

## How to verify the fix

A practical check is:

1. open your shop with your existing tracking setup
2. use GA4 DebugView, Tag Assistant or your GTM/dataLayer inspection
3. trigger ecommerce events such as product view, add to cart and purchase
4. verify that the affected item IDs are product numbers / SKUs instead of Shopware UUIDs

For version 2.0.0, it is especially recommended to verify the purchase flow once after updating.

---

## Backend language support

The plugin includes localized Shopware metadata/snippets for:

- **German (de-DE)**
- **English (en-GB)**

That means the plugin description in the Shopware backend can be displayed according to the backend language configuration.

---

## Compatibility

- Shopware **6.5**
- Shopware **6.6**
- Shopware **6.7**

---

## Recommended use cases

This plugin is especially useful if you want to improve:

- Shopware GA4 ecommerce tracking
- product ID consistency between Shopware and Merchant Center
- dynamic remarketing prerequisites
- reporting quality for item-level ecommerce data
- feed-based advertising setups using SKU/product-number matching

---

## Changelog

### 2.0.0
- fundamental revision of purchase / checkout handling
- purchase event processing restored in the affected flow
- item ID fix applies reliably again during purchase
- improved stability in order / line-item processing
- updated plugin icon / branding

---

## Developer

**Feedlingo**  
https://www.feedlingo.de

---

## Support and project website

Documentation, updates and project information are available at:

**https://www.feedlingo.de**
