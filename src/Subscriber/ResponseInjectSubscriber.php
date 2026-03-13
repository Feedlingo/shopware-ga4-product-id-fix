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

        $endpoint = '/ga4-itemid-fix/map'; // avoid router exception if routes not warmed yet
        $script = $this->buildInlineScript($endpoint);

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
        $response->headers->set('X-GA4-ItemId-Fix-Version', '1.5.19');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
    }

    private function isHtmlResponse(Response $response): bool
    {
        $ct = (string) $response->headers->get('Content-Type', '');
        return stripos($ct, 'text/html') !== false || $ct === '';
    }

    private function buildInlineScript(string $endpoint): string
    {
        $endpointJs = json_encode($endpoint, JSON_UNESCAPED_SLASHES);

        return <<<HTML
<script>
(function(){
  'use strict';
  // Always allow injection (HTTP cache may contain older injected script). Guard by version.
  if (window.__ga4ItemIdFixVersion === '1.5.19') return;


  window.__ga4ItemIdFixVersion = '1.5.19';
  window.__ga4ItemIdFixLastFetch = null;

  var MAP_ENDPOINT = {$endpointJs};
  var ORDER_ITEMS_ENDPOINT = '/ga4-itemid-fix/order-items';

  function getOrderCtx(){
    try {
      var sp = new URLSearchParams(window.location.search || '');
      var oid = cleanHex(sp.get('orderId'));
      if(!oid) return null;
      var dlc = (sp.get('deepLinkCode')||'').trim();
      return {orderId: oid, deepLinkCode: dlc};
    } catch(e){ return null; }
  }

  function fetchOrderItemsMap(){
    var ctx = getOrderCtx();
    if(!ctx || !ctx.orderId) return Promise.resolve(false);
    var url = ORDER_ITEMS_ENDPOINT + '?orderId=' + encodeURIComponent(ctx.orderId);
    if(ctx.deepLinkCode) url += '&deepLinkCode=' + encodeURIComponent(ctx.deepLinkCode);
    return fetch(url, {method:'GET', credentials:'include'})
      .then(function(r){ window.__ga4ItemIdFixLastFetch = {status:r.status, ok:r.ok, orderMap:true}; return r; })
      .then(function(r){ return r.json().catch(function(){return null;}); })
      .then(function(j){
        if(j && j.ok && j.map){
          for (var k in j.map) if (Object.prototype.hasOwnProperty.call(j.map,k)){
            var hk = cleanHex(k);
            if(hk) cache[hk] = String(j.map[k]);
          }
          return true;
        }
        return false;
      })
      .catch(function(){ return false; });
  }

  function isPurchaseEvent(obj){
    try {
      if (obj && typeof obj === 'object' && typeof obj.length === 'number') {
        return (obj[0] === 'event' && String(obj[1]||'').toLowerCase() === 'purchase');
      }
      if (obj && typeof obj === 'object') {
        return String(obj.event||'').toLowerCase() === 'purchase';
      }
    } catch(e){}
    return false;
  }

  function hasUnmappedIds(obj){
    try {
      var ids = collectAll(obj);
      for (var i=0;i<ids.length;i++) if(!cache[ids[i]]) return true;
    } catch(e){}
    return false;
  }


  function cleanHex(v){
    if(!v||typeof v!=='string') return null;
    var c=v.replace(/-/g,'').toLowerCase().trim();
    if(c.length!==32||!/^[0-9a-f]+$/.test(c)) return null;
    return c;
  }
  function normalizeSku(s){ try{ return (typeof s==='string')?s.trim():s; }catch(e){ return s; } }

  var cache = {};
  window.__ga4ItemIdFixLastFetch = null;

  function extractPayload(obj){
    // gtag() pushes an Arguments object into dataLayer: ['event', 'view_item', {..}]
    try {
      if (obj && typeof obj === 'object' && typeof obj.length === 'number' && obj[0] === 'event' && obj[2] && typeof obj[2] === 'object') {
        return obj[2];
      }
    } catch(e) {}
    return obj;
  }

  function findAllItemArrays(obj){
    var payload = extractPayload(obj);
    var r=[]; if(!payload||typeof payload!=='object') return r;
    if(payload.items && Array.isArray(payload.items)) r.push(payload.items);
    if(payload.eventModel && payload.eventModel.items && Array.isArray(payload.eventModel.items)) r.push(payload.eventModel.items);
    if(payload.ecommerce && payload.ecommerce.items && Array.isArray(payload.ecommerce.items)) r.push(payload.ecommerce.items);
    return r;
  }
  function collectAll(obj){
    var all=[]; var arrs=findAllItemArrays(obj);
    for(var a=0;a<arrs.length;a++){ var items=arrs[a];
      for(var i=0;i<items.length;i++){ var it=items[i]; if(!it||typeof it!=='object') continue;
        var h1=cleanHex(it.id); if(h1) all.push(h1);
        var h2=cleanHex(it.item_id); if(h2) all.push(h2);
      }
    }
    return Array.from(new Set(all));
  }

  function collectNeed(obj){
    var all = collectAll(obj);
    var need=[];
    for (var j=0;j<all.length;j++) if(!cache[all[j]]) need.push(all[j]);
    return {all: all, need: need};
  }
function toNumber(v){
  if (v === null || v === undefined) return null;
  if (typeof v === 'number') return isFinite(v) ? v : null;
  if (typeof v === 'string') {
    var s = v.trim();
    if (s === '') return 0;
    // Replace comma decimals
    s = s.replace(',', '.');
    var n = parseFloat(s);
    return isFinite(n) ? n : null;
  }
  return null;
}

function normalizeTotals(obj){
  // Only touch purchase events (or objects that clearly look like purchase payloads)
  try {
    var isPurchase = false;
    if (obj && typeof obj === 'object' && typeof obj.length === 'number') {
      // gtag arguments object: ['event', 'purchase', {...}]
      isPurchase = (obj[0] === 'event' && String(obj[1] || '').toLowerCase() === 'purchase');
    } else if (obj && typeof obj === 'object') {
      var ev = (obj.event || obj.name || obj.event_name);
      if (typeof ev === 'string' && ev.toLowerCase() === 'purchase') isPurchase = true;
    }
    if (!isPurchase) return;

    var payload = extractPayload(obj);
    if (!payload || typeof payload !== 'object') return;

    var targets = [];
    if (payload.eventModel && typeof payload.eventModel === 'object') targets.push(payload.eventModel);
    if (payload.ecommerce && typeof payload.ecommerce === 'object') targets.push(payload.ecommerce);
    if (payload.transaction && typeof payload.transaction === 'object') targets.push(payload.transaction);
    // Some implementations put totals on root payload
    targets.push(payload);

    for (var t=0;t<targets.length;t++){
      var p = targets[t];
      if (!p || typeof p !== 'object') continue;

      // Normalize known numeric fields
      if (p.shipping === '') p.shipping = 0;
      if (p.tax === '') p.tax = 0;
      if (p.value === '') p.value = 0;

      var ship = toNumber(p.shipping); if (ship !== null) p.shipping = ship;
      var tax  = toNumber(p.tax);      if (tax !== null)  p.tax = tax;
      var val  = toNumber(p.value);    if (val !== null)  p.value = val;

      // If value is missing/invalid but items exist, compute conservative sum(items.price * qty)
      if ((!isFinite(p.value) || p.value === null) && p.items && Array.isArray(p.items)) {
        var sum = 0;
        for (var i=0;i<p.items.length;i++){
          var it = p.items[i];
          if (!it || typeof it !== 'object') continue;
          var pr = toNumber(it.price);
          var qty = toNumber(it.quantity);
          if (pr === null) pr = toNumber(it.item_price);
          if (qty === null) qty = 1;
          if (isFinite(pr) && isFinite(qty)) sum += pr * qty;
        }
        if (isFinite(sum)) p.value = sum;
      }
    }
  } catch(e) {}
}

  function applyFix(obj){
    var changed = 0;
    var arrs=findAllItemArrays(obj);
    for(var a=0;a<arrs.length;a++){ var items=arrs[a];
      for(var i=0;i<items.length;i++){ var it=items[i]; if(!it||typeof it!=='object') continue;

        var h=cleanHex(it.id);
        if(h && cache[h]){
          var sku = normalizeSku(cache[h]);
          if(it.id !== sku){
            it.item_id_sw_uuid = it.item_id_sw_uuid || it.id;
            it.id = sku;
            it.item_id = sku;
            changed++;
          }
        }

        var h2=cleanHex(it.item_id);
        if(h2 && cache[h2]){
          var sku2 = normalizeSku(cache[h2]);
          if(it.item_id !== sku2 || it.id !== sku2){
            it.item_id_sw_uuid = it.item_id_sw_uuid || it.item_id;
            it.item_id = sku2;
            it.id = sku2;
            changed++;
          }
        }
      }
    }
    // Keep purchase totals numeric and consistent
    normalizeTotals(obj);

    return changed;
  }
  function fetchMap(ids){
    function handle(r){ window.__ga4ItemIdFixLastFetch = {status: r && r.status, ok: r && r.ok}; return r; }
    return fetch(MAP_ENDPOINT, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ids:ids})
    }).then(handle)
    .then(function(r){
      if (r && (r.status===403 || r.status===400 || r.status===401)) {
        // fallback GET (some setups block POST in storefront)
        var qs = '?ids=' + encodeURIComponent(ids.join(','));
        return fetch(MAP_ENDPOINT + qs, {method:'GET'}).then(handle);
      }
      return r;
    })
    .then(function(r){ return r.json().catch(function(){return null;}); })
    .then(function(j){
      if(j && j.ok && j.map){
        for (var k in j.map) if (Object.prototype.hasOwnProperty.call(j.map,k)){
          var hk = cleanHex(k);
          if(hk) cache[hk] = String(j.map[k]);
        }
      }
    })
    .catch(function(){});
  }

  function makeBlockingPush(targetArray){
    var wrapper = function(){
      // Resolve forward target at CALL TIME (not at wrap time).
      // At wrap time our script runs in <head> before GTM loads.
      // GTM's assignment (dl.push = gtmFn) is intercepted by our
      // Object.defineProperty setter and stored in __ga4ItemIdFixOriginalPush.
      // By the time any real event fires, GTM is loaded and the reference is correct.
      var forwardPush = (typeof targetArray.__ga4ItemIdFixOriginalPush === 'function')
        ? targetArray.__ga4ItemIdFixOriginalPush
        : Array.prototype.push;

      var args=[].slice.call(arguments);
      var pushNow=[]; var queued=[]; var needAll=[];
      for(var i=0;i<args.length;i++){
        var obj=args[i];
        if(!obj||typeof obj!=='object'||obj.__ga4Fixed){ pushNow.push(obj); continue; }
        var c=collectNeed(obj);
        if(c.all.length && !c.need.length){
          var ch = applyFix(obj);
          try{ if (ch>0 || !collectAll(obj).length) obj.__ga4Fixed=true; }catch(e){}
          pushNow.push(obj);
          continue;
        }
        if(!c.need.length){ pushNow.push(obj); continue; }
        queued.push(obj);
        needAll = needAll.concat(c.need);
      }
      needAll = Array.from(new Set(needAll));

      var result = targetArray.length;
      if(pushNow.length) result = forwardPush.apply(targetArray, pushNow);

      if(queued.length){
        fetchMap(needAll).then(function(){
          // If this is a purchase on /checkout/finish, product-id mapping may not be enough.
          // Try order-based mapping for line item IDs.
          var needOrderMap = false;
          try {
            if (getOrderCtx()) {
              for (var qi=0; qi<queued.length; qi++) {
                if (isPurchaseEvent(queued[qi]) && hasUnmappedIds(queued[qi])) { needOrderMap = true; break; }
              }
            }
          } catch(e){}
          if (needOrderMap) return fetchOrderItemsMap();
        }).then(function(){
          // Resolve AGAIN at async-callback time.
          // GTM is guaranteed to be fully loaded at this point.
          var fwdNow = (typeof targetArray.__ga4ItemIdFixOriginalPush === 'function')
            ? targetArray.__ga4ItemIdFixOriginalPush
            : Array.prototype.push;
          for(var q=0;q<queued.length;q++){
            var chq = applyFix(queued[q]);
            try{ if (chq>0 || !collectAll(queued[q]).length) queued[q].__ga4Fixed=true; }catch(e){}
            fwdNow.call(targetArray, queued[q]);
          }
        });
      }
      return result;
    };
    return wrapper;
  }


  function wrapDataLayer(dl){
    if(!Array.isArray(dl)) return dl;
    if(dl.__ga4ItemIdFixWrapped && dl.__ga4ItemIdFixWrappedVersion === '1.5.19') return dl;

    // Capture current push as forward target (may be GTM/gtag's processor function)
    var existingPush = dl.push;
    if (typeof existingPush === 'function') {
      dl.__ga4ItemIdFixOriginalPush = existingPush;
    }

    var ourPush = makeBlockingPush(dl);

    // Simple assignment (no accessor trap) to avoid recursion with gtag internals.
    // If something overwrites dl.push later, gtag wrapping below still ensures events are fixed.
    dl.push = ourPush;

    dl.__ga4ItemIdFixWrapped = true;
    dl.__ga4ItemIdFixWrappedVersion = '1.5.19';
    return dl;
  }

  function wrapGtag(){
    try {
      if (typeof window.gtag !== 'function') return;
      if (window.gtag.__ga4ItemIdFixWrapped) return;

      var orig = window.gtag;
      window.__ga4ItemIdFixOriginalGtag = orig;

      var gtagWrap = function(){
        try {
          var args = arguments;
          // gtag('event', name, params)
          if (args && args.length >= 3 && args[0] === 'event' && args[2] && typeof args[2] === 'object') {
            var payload = args[2];
            if (!payload.__ga4Fixed) {
              var c = collectNeed(payload);
              if (c && c.all && c.all.length && (!c.need || !c.need.length)) {
                var chp = applyFix(payload);
                try { if (chp>0 || !collectAll(payload).length) payload.__ga4Fixed = true; } catch(e) {}
                return orig.apply(this, args);
              }
              if (c && c.need && c.need.length) {
                // Block send until map is ready, then forward the original call.
                fetchMap(c.need).then(function(){
                  var didOrder=false;
                  try { if (isPurchaseEvent(['event','purchase',payload]) && getOrderCtx() && hasUnmappedIds(payload)) { didOrder=true; } } catch(e){}
                  var p1 = didOrder ? fetchOrderItemsMap() : Promise.resolve(false);
                  return p1.then(function(){
                    try { var ch = applyFix(payload); if (ch>0 || !collectAll(payload).length) payload.__ga4Fixed = true; } catch(e) {}
                    try { orig.apply(window, args); } catch(e) {}
                  });
                });
                return;
              }
            }
          }
        } catch(e) {}
        return orig.apply(this, arguments);
      };
      gtagWrap.__ga4ItemIdFixWrapped = true;
      window.gtag = gtagWrap;

      // If some script replaces gtag later, re-wrap quickly.
      try {
        var _gtag = window.gtag;
        Object.defineProperty(window, 'gtag', {
          configurable: true,
          get: function(){ return _gtag; },
          set: function(fn){
            _gtag = fn;
            try {
              if (typeof fn === 'function' && !fn.__ga4ItemIdFixWrapped) {
                // restore then wrap
                _gtag = fn;
                // temporarily remove defineProperty to avoid trapping our own assignment
                try { delete window.gtag; } catch(e) {}
                window.gtag = fn;
                wrapGtag();
              }
            } catch(e) {}
          }
        });
      } catch(e) {}
    } catch(e) {}
  }

  try {
    var initial = window.dataLayer;
    if(!initial) initial = [];
    window.dataLayer = wrapDataLayer(initial);
  } catch(e) {}

  // Wrap gtag so events are fixed even if dataLayer.push is overwritten later.
  wrapGtag();
// Retroactive fix: process already-pushed entries (if events fired before our wrapper attached)
(function(){
  try {
    var dl = window.dataLayer;
    if (!Array.isArray(dl) || !dl.length) return;

    // Collect ids from existing entries
    var needAll = [];
    var objs = [];
    for (var i=0;i<dl.length;i++){
      var obj = dl[i];
      if(!obj || typeof obj !== 'object') continue;
      if(obj.__ga4Fixed) continue;
      var c = collectNeed(obj);
      if (c && c.all && c.all.length && (!c.need || !c.need.length)) {
        var chr = applyFix(obj);
        try{ if (chr>0 || !collectAll(obj).length) obj.__ga4Fixed=true; }catch(e){}
      } else if (c && c.need && c.need.length) {
        objs.push(obj);
        needAll = needAll.concat(c.need);
      }
    }
    needAll = Array.from(new Set(needAll));
    if (!needAll.length) return;

    fetchMap(needAll).then(function(){
      for (var j=0;j<objs.length;j++){
        try{ var chrr = applyFix(objs[j]); try{ if (chrr>0 || !collectAll(objs[j]).length) objs[j].__ga4Fixed=true; }catch(e){} }catch(e){}
      }
    });
  } catch(e) {}
})();

  window.__ga4ItemIdFixInlineLoaded = true;
})();
</script>
HTML;
    }
}
