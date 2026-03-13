# Ga4ItemIdFix v1.4.0

Zenith Stratus 5.5.4 kompatibel (theme-unabhängig):
- Injiziert Script per Kernel Response-Subscriber in jede Storefront-HTML Seite (vor </head>)
- Patcht dataLayer.push und ersetzt UUIDs durch SKU (Artikelnummer)
- Holt SKU bei Bedarf per Endpoint:
  POST /ga4-itemid-fix/map  { "ids": ["<32hex>", ...] } -> { ok: true, map: { "<id>":"<sku>" } }

Check:
- Produktseite hard reload (Strg+F5)
- Konsole: window.__ga4ItemIdFixInlineLoaded === true


## Changelog v1.4.1
- Fix: routes.xml hinzugefügt, damit der Storefront-Endpoint registriert ist.
- Fix: Response-Injector nutzt feste Endpoint-URL, um 500er bei Routing-Problemen zu vermeiden.


## Changelog v1.4.2
- Fix: Storefront-Endpoint ist CSRF-unprotected (POST/GET), damit fetch nicht mit 403 scheitert.
- Debug: window.__ga4ItemIdFixLastFetch zeigt den letzten Endpoint-Status.


## Changelog v1.4.3
- Debug: window.__ga4ItemIdFixVersion + Response-Header X-GA4-ItemId-Fix-Version
- Fix: dataLayer wrapper unterstützt gtag() Arguments-Objekte.
- Cache-Control no-cache Header bei injizierten Seiten.


## Changelog v1.4.4
- Fix: Injection wird nicht mehr durch vorhandenes altes Marker-Script blockiert (Storefront HTTP Cache). Version-Guard verhindert doppelte Ausführung.


## Changelog v1.4.6
- Fix: Parse Error behoben (Controller sauber neu geschrieben).
- Fix: Endpoint map() akzeptiert GET/POST ids, normalisiert 32-hex + hyphenated und nutzt Fallback-Suche.


## Changelog v1.4.7
- Fix: Injection wird jetzt VOR Shopware Analytics Bootstrap platziert (Suche nach window.gtagActive/gtagURL/dataLayer), damit dataLayer vor den ersten Events gepatcht ist.


## Changelog v1.4.8
- Fix: Injection-Position ist wieder sicher (direkt nach <head>-Tag), verhindert SyntaxErrors durch Einfügen innerhalb bestehender <script>-Tags.


## Changelog v1.4.9
- Fix: Wenn UUID->SKU bereits im Cache ist, wird add_to_cart etc. jetzt synchron umgeschrieben (kein erneuter Fetch nötig).


## Changelog v1.5.0
- Verbesserung: Item-Erkennung jetzt checkout-sicher durch Deep-Scan nach items-Arrays (zusätzlich zu den bekannten GA4-Strukturen).


## Changelog v1.5.2
- Fix: Parent-SKU Mapping für Varianten (Schuhgröße etc.).
- Fix: Plugin basiert wieder auf stabilem 1.5.0 Stand; kein Syntax-Fehler mehr.


## Changelog v1.5.5
- Fix: Zurück zum stabilen Event-Patching (dataLayer.push Wrapper wie v1.4.9), damit Tag Assistant/GA4 Hits nicht ausfallen.
- Feature: Parent-SKU Mapping für Varianten bleibt aktiv (wie v1.5.2).


## Changelog v1.5.6
- Fix: Bereits vorhandene dataLayer-Einträge werden nachträglich umgeschrieben (falls Events vor unserem Wrapper gefeuert wurden).


## Changelog v1.5.7
- Entscheidung: Varianten-SKU beibehalten (größenabhängig) für konsistente IDs mit Merchant Center/Benchmarks.
- Endpoint liefert immer Product.productNumber (keine Parent-SKU-Zusammenfassung).
- Enthält weiterhin Deep-Scan + Retroactive Fix für frühe Events (Checkout-sicher).
