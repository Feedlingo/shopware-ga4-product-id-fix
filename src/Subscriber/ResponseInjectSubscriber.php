<?php declare(strict_types=1);

namespace Ga4ItemIdFix\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class ResponseInjectSubscriber implements EventSubscriberInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 2000],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$this->isHtmlResponse($response)) {
            return;
        }

        if ($request->getMethod() !== 'GET') {
            return;
        }

        $scopes = $request->attributes->get('_routeScope');
        if (is_array($scopes) && !in_array('storefront', $scopes, true)) {
            return;
        }

        $content = $response->getContent();
        if (!is_string($content) || $content === '') {
            return;
        }

        if (strpos($content, '__ga4ItemIdFixInlineLoaded') !== false) {
            return;
        }

        // Generate endpoints via the Symfony router so they work correctly
        // behind reverse proxies and in subfolder installations.
        // Fall back to hardcoded paths only if the router throws (e.g. routes not warmed).
        try {
            $mapEndpoint        = $this->router->generate('frontend.ga4_itemid_fix.map');
            $orderItemsEndpoint = $this->router->generate('frontend.ga4_itemid_fix.order_items');
        } catch (\Throwable $e) {
            $mapEndpoint        = '/ga4-itemid-fix/map';
            $orderItemsEndpoint = '/ga4-itemid-fix/order-items';
        }
        $script = $this->buildInlineScript($mapEndpoint, $orderItemsEndpoint);

        // Safe injection point: right after the opening <head ...> tag.
// This is early enough (before Shopware's analytics bootstrap script) and cannot break existing <script> tags.
$pos = null;
if (preg_match('/<head\b[^>]*>/i', $content, $m, PREG_OFFSET_CAPTURE)) {
    $pos = $m[0][1] + strlen($m[0][0]);
}

if (!is_int($pos)) {
    // Fallbacks
    $pos = stripos($content, '</head>');
    if ($pos === false) {
        $pos = stripos($content, '</body>');
    }
    if ($pos === false) {
        return;
    }
}



        $content = substr($content, 0, $pos) . $script . substr($content, $pos);
        $response->setContent($content);

        // Help debugging caches: expose version in headers
        $response->headers->set('X-GA4-ItemId-Fix-Version', '2.0.0');
    }

    private function isHtmlResponse(Response $response): bool
    {
        $ct = (string) $response->headers->get('Content-Type', '');
        return stripos($ct, 'text/html') !== false || $ct === '';
    }

    private function buildInlineScript(string $mapEndpoint, string $orderItemsEndpoint): string
    {
        $mapEndpointJs        = json_encode($mapEndpoint, JSON_UNESCAPED_SLASHES);
        $orderItemsEndpointJs = json_encode($orderItemsEndpoint, JSON_UNESCAPED_SLASHES);

        return <<<HTML
<script>
(function(){
  'use strict';
  if (window.__ga4ItemIdFixVersion === '2.0.0') return;
  window.__ga4ItemIdFixVersion = '2.0.0';

  // ── Endpoints ────────────────────────────────────────────────────────────────
  // MAP_ENDPOINT is injected by PHP using the Symfony router so it works
  // correctly behind reverse proxies and in subfolder installations.
  var MAP_ENDPOINT         = {$mapEndpointJs};
  var ORDER_ITEMS_ENDPOINT = {$orderItemsEndpointJs};

  // ── Consent guard ────────────────────────────────────────────────────────────
  // Only run mapping fetches when analytics consent is granted.
  // We check the standard Consent Mode v2 flag. If the CMP has not set it yet
  // we assume consent is present (opt-out model) so we do not break shops that
  // do not use Consent Mode. A shop using strict opt-in simply does not call
  // gtag('event', ...) until consent is given, so our wrapper never fires anyway.
  function hasAnalyticsConsent(){
    try {
      // Google Consent Mode v2: window.google_tag_data.ics.entries
      var gtd = window.google_tag_data;
      if (gtd && gtd.ics && typeof gtd.ics.entries === 'object') {
        var e = gtd.ics.entries;
        if (e.analytics_storage && e.analytics_storage.update === 'denied') return false;
      }
    } catch(e){}
    return true; // default allow — CMP will have blocked gtag() before us if denied
  }

  // ── Helpers ──────────────────────────────────────────────────────────────────
  var cache = {};

  function cleanHex(v){
    if (!v || typeof v !== 'string') return null;
    var c = v.replace(/-/g, '').toLowerCase().trim();
    if (c.length !== 32 || !/^[0-9a-f]+$/.test(c)) return null;
    return c;
  }

  function normalizeSku(s){ return (typeof s === 'string') ? s.trim() : s; }

  function toNumber(v){
    if (v === null || v === undefined) return null;
    if (typeof v === 'number') return isFinite(v) ? v : null;
    if (typeof v === 'string') {
      var s = v.trim();
      if (s === '') return 0;
      var n = parseFloat(s.replace(',', '.'));
      return isFinite(n) ? n : null;
    }
    return null;
  }

  function getOrderCtx(){
    try {
      var sp  = new URLSearchParams(window.location.search || '');
      var oid = cleanHex(sp.get('orderId'));
      if (!oid) return null;
      return { orderId: oid, deepLinkCode: (sp.get('deepLinkCode') || '').trim() };
    } catch(e){ return null; }
  }

  function extractPayload(obj){
    try {
      if (obj && typeof obj === 'object' && typeof obj.length === 'number'
          && obj[0] === 'event' && obj[2] && typeof obj[2] === 'object') {
        return obj[2];
      }
    } catch(e){}
    return obj;
  }

  function findAllItemArrays(obj){
    var payload = extractPayload(obj);
    var r = [];
    if (!payload || typeof payload !== 'object') return r;
    if (payload.items && Array.isArray(payload.items)) r.push(payload.items);
    if (payload.eventModel && payload.eventModel.items && Array.isArray(payload.eventModel.items)) r.push(payload.eventModel.items);
    if (payload.ecommerce && payload.ecommerce.items && Array.isArray(payload.ecommerce.items)) r.push(payload.ecommerce.items);
    return r;
  }

  function collectAll(obj){
    var all = [];
    var arrs = findAllItemArrays(obj);
    for (var a = 0; a < arrs.length; a++) {
      var items = arrs[a];
      for (var i = 0; i < items.length; i++) {
        var it = items[i];
        if (!it || typeof it !== 'object') continue;
        var h1 = cleanHex(it.id);      if (h1) all.push(h1);
        var h2 = cleanHex(it.item_id); if (h2) all.push(h2);
      }
    }
    return Array.from(new Set(all));
  }

  function collectNeed(obj){
    var all = collectAll(obj), need = [];
    for (var j = 0; j < all.length; j++) if (!cache[all[j]]) need.push(all[j]);
    return { all: all, need: need };
  }

  function isPurchaseEvent(obj){
    try {
      if (obj && typeof obj === 'object' && typeof obj.length === 'number')
        return (obj[0] === 'event' && String(obj[1] || '').toLowerCase() === 'purchase');
      if (obj && typeof obj === 'object')
        return String(obj.event || '').toLowerCase() === 'purchase';
    } catch(e){}
    return false;
  }

  function hasUnmappedIds(obj){
    var ids = collectAll(obj);
    for (var i = 0; i < ids.length; i++) if (!cache[ids[i]]) return true;
    return false;
  }

  // ── Numeric normalisation ────────────────────────────────────────────────────
  // Shopware sends value/shipping/tax as strings. GA4 requires numbers.
  // Also normalises item-level price and quantity.
  function normalizeTotals(payload){
    if (!payload || typeof payload !== 'object') return;

    // Top-level containers that may hold totals.
    var targets = [payload];
    if (payload.eventModel  && typeof payload.eventModel  === 'object') targets.push(payload.eventModel);
    if (payload.ecommerce   && typeof payload.ecommerce   === 'object') targets.push(payload.ecommerce);
    if (payload.transaction && typeof payload.transaction === 'object') targets.push(payload.transaction);

    for (var t = 0; t < targets.length; t++) {
      var p = targets[t];
      if (!p || typeof p !== 'object') continue;

      // Cast known numeric totals.
      var ship = toNumber(p.shipping); if (ship !== null) p.shipping = ship;
      var tax  = toNumber(p.tax);      if (tax  !== null) p.tax      = tax;
      var val  = toNumber(p.value);    if (val  !== null) p.value    = val;

      // Normalise item-level fields.
      if (p.items && Array.isArray(p.items)) {
        for (var i = 0; i < p.items.length; i++) {
          var it = p.items[i];
          if (!it || typeof it !== 'object') continue;
          var pr  = toNumber(it.price);    if (pr  !== null) it.price    = pr;
          var qty = toNumber(it.quantity); if (qty !== null) it.quantity  = qty;
          var disc = toNumber(it.discount); if (disc !== null) it.discount = disc;
        }
      }

      // If value is still missing, compute from items as a fallback.
      if ((p.value === null || p.value === undefined || !isFinite(p.value)) && p.items && Array.isArray(p.items)) {
        var sum = 0;
        for (var ii = 0; ii < p.items.length; ii++) {
          var iti = p.items[ii];
          if (!iti || typeof iti !== 'object') continue;
          var pri  = (typeof iti.price    === 'number') ? iti.price    : toNumber(iti.price);
          var qtyi = (typeof iti.quantity === 'number') ? iti.quantity : toNumber(iti.quantity);
          if (pri === null) pri  = toNumber(iti.item_price);
          if (qtyi === null) qtyi = 1;
          if (isFinite(pri) && isFinite(qtyi)) sum += pri * qtyi;
        }
        if (isFinite(sum)) p.value = sum;
      }
    }
  }

  // ── ID fix ───────────────────────────────────────────────────────────────────
  function applyFix(payload){
    var arrs = findAllItemArrays(payload);
    for (var a = 0; a < arrs.length; a++) {
      var items = arrs[a];
      for (var i = 0; i < items.length; i++) {
        var it = items[i];
        if (!it || typeof it !== 'object') continue;

        var h = cleanHex(it.id);
        if (h && cache[h]) {
          var sku = normalizeSku(cache[h]);
          it.item_id_sw_uuid = it.item_id_sw_uuid || it.id;
          it.id      = sku;
          it.item_id = sku;
        }

        var h2 = cleanHex(it.item_id);
        if (h2 && cache[h2]) {
          var sku2 = normalizeSku(cache[h2]);
          it.item_id_sw_uuid = it.item_id_sw_uuid || it.item_id;
          it.item_id = sku2;
          it.id      = sku2;
        }
      }
    }
    // Always normalise numeric fields on purchase events.
    if (isPurchaseEvent(payload) || (payload.event && String(payload.event).toLowerCase() === 'purchase')) {
      normalizeTotals(payload);
    }
  }

  // ── Fetch helpers ─────────────────────────────────────────────────────────────
  // fetchWithTimeout: wraps fetch() with an AbortController timeout.
  // If the request does not complete within `ms` milliseconds it is aborted
  // and the returned promise rejects — triggering our .catch() safety net so
  // the event is still forwarded rather than held indefinitely.
  function fetchWithTimeout(url, options, ms){
    ms = ms || 4000;
    var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    var timer = controller ? setTimeout(function(){ controller.abort(); }, ms) : null;
    var opts = controller ? Object.assign({}, options, { signal: controller.signal }) : options;
    return fetch(url, opts).then(function(r){
      if (timer) clearTimeout(timer);
      return r;
    }, function(err){
      if (timer) clearTimeout(timer);
      throw err;
    });
  }

  function fetchMap(ids){
    if (!hasAnalyticsConsent()) return Promise.resolve(false);
    return fetchWithTimeout(MAP_ENDPOINT, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ ids: ids })
    }, 4000)
    .then(function(r){
      window.__ga4ItemIdFixLastFetch = { status: r.status, ok: r.ok };
      // Fallback to GET if POST is blocked.
      if (r.status === 400 || r.status === 401 || r.status === 403) {
        return fetchWithTimeout(MAP_ENDPOINT + '?ids=' + encodeURIComponent(ids.join(',')), { method: 'GET' }, 4000)
          .then(function(r2){ window.__ga4ItemIdFixLastFetch = { status: r2.status, ok: r2.ok }; return r2; });
      }
      return r;
    })
    .then(function(r){ return r.json().catch(function(){ return null; }); })
    .then(function(j){
      if (j && j.ok && j.map) {
        for (var k in j.map) if (Object.prototype.hasOwnProperty.call(j.map, k)) {
          var hk = cleanHex(k);
          if (hk) cache[hk] = String(j.map[k]);
        }
      }
      return true;
    })
    .catch(function(){ return false; });
  }

  function fetchOrderItemsMap(){
    if (!hasAnalyticsConsent()) return Promise.resolve(false);
    var ctx = getOrderCtx();
    if (!ctx || !ctx.orderId) return Promise.resolve(false);
    var url = ORDER_ITEMS_ENDPOINT + '?orderId=' + encodeURIComponent(ctx.orderId);
    if (ctx.deepLinkCode) url += '&deepLinkCode=' + encodeURIComponent(ctx.deepLinkCode);
    return fetchWithTimeout(url, { method: 'GET', credentials: 'include' }, 4000)
      .then(function(r){ window.__ga4ItemIdFixLastFetch = { status: r.status, ok: r.ok, orderMap: true }; return r; })
      .then(function(r){ return r.json().catch(function(){ return null; }); })
      .then(function(j){
        if (j && j.ok && j.map) {
          for (var k in j.map) if (Object.prototype.hasOwnProperty.call(j.map, k)) {
            var hk = cleanHex(k);
            if (hk) cache[hk] = String(j.map[k]);
          }
          return true;
        }
        return false;
      })
      .catch(function(){ return false; });
  }

  // ── gtag() wrapper ───────────────────────────────────────────────────────────
  // We wrap ONLY window.gtag and leave dataLayer.push untouched to avoid
  // re-entry loops (orig calls dataLayer.push which would re-trigger our wrapper).
  function wrapGtag(){
    try {
      if (typeof window.gtag !== 'function') return;
      if (window.gtag.__ga4ItemIdFixWrapped) return;

      var orig = window.gtag;
      window.__ga4ItemIdFixOriginalGtag = orig;

      window.gtag = function ga4Fix(){
        try {
          var args = arguments;
          if (args.length >= 3 && args[0] === 'event' && args[2] && typeof args[2] === 'object') {
            var payload = args[2];
            var c = collectNeed(payload);

            // All IDs in cache — fix and send synchronously.
            if (c.all.length && !c.need.length) {
              applyFix(payload);
              return orig.apply(this, args);
            }

            // Unknown IDs — fetch mapping then send.
            if (c.need.length) {
              var savedThis = this;
              var savedArgs = args;
              fetchMap(c.need)
                .then(function(){
                  var didOrder = false;
                  try {
                    if (isPurchaseEvent(['event', 'purchase', payload]) && getOrderCtx() && hasUnmappedIds(payload)) {
                      didOrder = true;
                    }
                  } catch(e){}
                  return (didOrder
                    ? fetchOrderItemsMap().catch(function(){ return false; })
                    : Promise.resolve(false)
                  ).then(function(){
                    try { applyFix(payload); } catch(e){}
                    orig.apply(savedThis, savedArgs);
                  });
                })
                .catch(function(){
                  // Safety net: always send, even without mapping.
                  try { orig.apply(savedThis, savedArgs); } catch(e){}
                });
              return;
            }
          }
        } catch(e){}
        return orig.apply(this, arguments);
      };
      window.gtag.__ga4ItemIdFixWrapped = true;
    } catch(e){}
  }

  // Poll until gtag is available (loaded async by gtag.js).
  (function(){
    wrapGtag();
    var attempts = 0;
    function tryWrap(){ wrapGtag(); if (++attempts < 50) setTimeout(tryWrap, 200); }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function(){ setTimeout(tryWrap, 0); });
    } else {
      setTimeout(tryWrap, 0);
    }
  })();

  window.__ga4ItemIdFixInlineLoaded = true;
})();
</script>
HTML;
    }
}
