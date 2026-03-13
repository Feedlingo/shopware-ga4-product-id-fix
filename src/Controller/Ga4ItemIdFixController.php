<?php declare(strict_types=1);

namespace Ga4ItemIdFix\Controller;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class Ga4ItemIdFixController extends AbstractController
{
    private EntityRepository $productRepository;
    private EntityRepository $orderRepository;

    public function __construct(EntityRepository $productRepository, EntityRepository $orderRepository)
    {
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
    }

    #[Route(
        path: '/ga4-itemid-fix/map',
        name: 'frontend.ga4_itemid_fix.map',
        methods: ['GET','POST'],
        defaults: ['_routeScope' => ['storefront'], '_csrf_protected' => false]
    )]
    public function map(Request $request, Context $context): JsonResponse
    {
        $ids = [];

        // POST JSON: {"ids":[...]}
        $data = json_decode((string) $request->getContent(), true);
        if (is_array($data) && isset($data['ids']) && is_array($data['ids'])) {
            $ids = $data['ids'];
        }

        // GET: ?ids=a,b,c
        $q = (string) $request->query->get('ids', '');
        if ($q !== '') {
            $parts = array_filter(array_map('trim', explode(',', $q)));
            $ids = array_merge($ids, $parts);
        }

        $ids = array_values(array_unique(array_filter($ids, static fn ($v) => is_string($v) && $v !== '')));

        if (!$ids) {
            return new JsonResponse(['ok' => true, 'map' => []]);
        }

        // Normalize IDs: accept 32-hex (no dashes) and dashed UUID
        $hex = [];
        $hyphen = [];

        foreach ($ids as $id) {
            $c = strtolower(str_replace('-', '', $id));
            if (!preg_match('/^[0-9a-f]{32}$/', $c)) {
                continue;
            }
            $hex[] = $c;
            $hyphen[] = substr($c, 0, 8) . '-' . substr($c, 8, 4) . '-' . substr($c, 12, 4) . '-' . substr($c, 16, 4) . '-' . substr($c, 20, 12);
        }

        $hex = array_values(array_unique($hex));
        $hyphen = array_values(array_unique($hyphen));

        if (!$hex) {
            return new JsonResponse(['ok' => true, 'map' => []]);
        }

        // Fast path: Criteria with IDs
        $map = $this->lookup($hex, $context);

        // Fallback: hyphenated
        if (!$map && $hyphen) {
            $map = $this->lookup($hyphen, $context);
        }

        // Fallback: explicit filter (in case Criteria normalization differs)
        if (!$map) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('id', $hex));
            $res = $this->productRepository->search($criteria, $context);
            $map = $this->toMap($res->getEntities());
        }

        if (!$map && $hyphen) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('id', $hyphen));
            $res = $this->productRepository->search($criteria, $context);
            $map = $this->toMap($res->getEntities());
        }

        return new JsonResponse(['ok' => true, 'map' => $map]);
    }


    #[Route(
        path: '/ga4-itemid-fix/order-items',
        name: 'frontend.ga4_itemid_fix.order_items',
        methods: ['GET'],
        defaults: ['_routeScope' => ['storefront'], '_csrf_protected' => false]
    )]
    public function orderItems(Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $orderId = (string) $request->query->get('orderId', '');
        $orderId = strtolower(str_replace('-', '', trim($orderId)));
        if (!preg_match('/^[0-9a-f]{32}$/', $orderId)) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_order_id'], 400);
        }

        $deepLinkCode = (string) $request->query->get('deepLinkCode', '');
        $customer = $salesChannelContext->getCustomer();

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('lineItems.product');

        $ctx = $salesChannelContext->getContext();
        $order = $this->orderRepository->search($criteria, $ctx)->first();

        if (!$order instanceof OrderEntity) {
            return new JsonResponse(['ok' => false, 'error' => 'order_not_found'], 404);
        }

        // Access check:
        // - If customer is logged in: require order belongs to customer
        // - Else: allow only with matching deepLinkCode (guest checkout / direct finish access)
        if ($customer) {
            if ($order->getOrderCustomer() && $order->getOrderCustomer()->getCustomerId()) {
                if ($order->getOrderCustomer()->getCustomerId() !== $customer->getId()) {
                    return new JsonResponse(['ok' => false, 'error' => 'forbidden'], 403);
                }
            } elseif ($order->getOrderCustomerId() && $order->getOrderCustomerId() !== $customer->getId()) {
                return new JsonResponse(['ok' => false, 'error' => 'forbidden'], 403);
            }
        } else {
            $dl = method_exists($order, 'getDeepLinkCode') ? (string) $order->getDeepLinkCode() : '';
            if ($deepLinkCode === '' || $dl === '' || !hash_equals($dl, $deepLinkCode)) {
                return new JsonResponse(['ok' => false, 'error' => 'forbidden'], 403);
            }
        }

        $map = [];

        $lineItems = $order->getLineItems();
        if ($lineItems) {
            /** @var OrderLineItemEntity $li */
            foreach ($lineItems as $li) {
                if (!$li instanceof OrderLineItemEntity) {
                    continue;
                }
                // Prefer product line items, but keep mapping for any referencedId that has a productNumber
                $sku = null;
                $payload = $li->getPayload() ?? [];

                if (is_array($payload) && isset($payload['productNumber']) && is_string($payload['productNumber']) && trim($payload['productNumber']) !== '') {
                    $sku = trim($payload['productNumber']);
                }

                if (!$sku && $li->getProduct() instanceof ProductEntity) {
                    $sku = trim((string) $li->getProduct()->getProductNumber());
                }

                if (!$sku) {
                    continue;
                }

                $keys = [];
                if ($li->getId()) $keys[] = $li->getId();
                if ($li->getReferencedId()) $keys[] = $li->getReferencedId();
                if (is_array($payload) && isset($payload['productId']) && is_string($payload['productId'])) $keys[] = $payload['productId'];

                foreach ($keys as $k) {
                    $k = strtolower(str_replace('-', '', trim((string) $k)));
                    if ($k === '' || !preg_match('/^[0-9a-f]{32}$/', $k)) {
                        continue;
                    }
                    $map[$k] = $sku;
                }
            }
        }

        return new JsonResponse(['ok' => true, 'map' => $map, 'count' => count($map)]);
    }


    private function lookup(array $ids, Context $context): array
    {
        $criteria = new Criteria($ids);
        $res = $this->productRepository->search($criteria, $context);
        return $this->toMap($res->getEntities());
    }

    private function toMap(iterable $entities): array
    {
        $map = [];

        foreach ($entities as $p) {
            if (!$p instanceof ProductEntity) {
                continue;
            }

            $id = $p->getId();
            $sku = $p->getProductNumber();

            if (!$id || !$sku) {
                continue;
            }

            // IMPORTANT: Keep variant SKU (e.g. size suffix) because Merchant Center treats variants as separate products.
            $map[$id] = trim((string) $sku);
        }

        return $map;
    }
}
