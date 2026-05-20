<?php

/**
 * @author Avarda Team
 * @copyright Copyright © Avarda. All rights reserved.
 */

declare(strict_types=1);

namespace Avarda\ShippingBrokerPartner\Controller;

use Avarda\ShippingBrokerPartner\Controller\Partner\CompleteSession;
use Avarda\ShippingBrokerPartner\Controller\Partner\CreateSession;
use Avarda\ShippingBrokerPartner\Controller\Partner\GetSession;
use Avarda\ShippingBrokerPartner\Controller\Partner\UpdateSession;
use Avarda\ShippingBrokerPartner\Controller\Widget\Select;
use Avarda\ShippingBrokerPartner\Controller\Widget\State;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RouterInterface;

/**
 * Maps the partner-shipping URL space onto Magento controllers.
 *
 * Two URL forms are accepted for the four partner-side actions:
 *
 *   Scoped (preferred — implementor base URL = `https://<host>/avarda_shipping_broker_partner`):
 *     POST /avarda_shipping_broker_partner/create-session
 *     PUT  /avarda_shipping_broker_partner/update-session/{id}
 *     PUT  /avarda_shipping_broker_partner/complete-session/{id}
 *     GET  /avarda_shipping_broker_partner/get-session/{id}
 *
 *   Bare (fallback — implementor base URL = `https://<host>`):
 *     POST /create-session
 *     PUT  /update-session/{id}
 *     PUT  /complete-session/{id}
 *     GET  /get-session/{id}
 *
 * Widget endpoints are intentionally only exposed under the frontName since
 * only our own JS calls them:
 *
 *     GET  /avarda_shipping_broker_partner/widget/state/{id}
 *     POST /avarda_shipping_broker_partner/widget/select/{id}
 */
class Router implements RouterInterface
{
    public const string FRONT_NAME = 'avarda_shipping_broker_partner';

    /**
     * action segment => [controller class, expected HTTP method, expects path id]
     *
     * @var array<string, array{0: class-string, 1: string, 2: bool}>
     */
    private const array ROUTES = [
        'create-session'   => [CreateSession::class,   'POST', false],
        'update-session'   => [UpdateSession::class,   'PUT',  true],
        'complete-session' => [CompleteSession::class, 'PUT',  true],
        'get-session'      => [GetSession::class,      'GET',  true],
    ];

    public function __construct(
        private readonly ActionFactory $actionFactory
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        $path = trim((string) $request->getPathInfo(), '/');
        if ($path === '') {
            return null;
        }
        $segments = explode('/', $path);

        // Scoped form: /avarda_shipping_broker_partner/...
        if (($segments[0] ?? '') === self::FRONT_NAME) {
            return $this->matchScoped($request, array_slice($segments, 1));
        }

        // Bare form: /create-session etc., when the merchant portal's
        // implementor base URL is just the host. Widget paths are not exposed
        // here.
        return $this->matchPartnerAction($request, $segments[0] ?? '', array_slice($segments, 1));
    }

    private function matchScoped(RequestInterface $request, array $tail): ?ActionInterface
    {
        $action = $tail[0] ?? '';
        $rest = array_slice($tail, 1);

        if ($action === 'widget' && ($rest[0] ?? '') === 'state' && isset($rest[1])) {
            return $this->dispatch($request, State::class, 'widget-state', $rest[1], 'GET');
        }
        if ($action === 'widget' && ($rest[0] ?? '') === 'select' && isset($rest[1])) {
            return $this->dispatch($request, Select::class, 'widget-select', $rest[1], 'POST');
        }

        return $this->matchPartnerAction($request, $action, $rest);
    }

    private function matchPartnerAction(RequestInterface $request, string $action, array $rest): ?ActionInterface
    {
        if (!isset(self::ROUTES[$action])) {
            return null;
        }
        [$class, $method, $expectsId] = self::ROUTES[$action];
        $id = $expectsId ? ($rest[0] ?? null) : null;
        if ($expectsId && ($id === null || $id === '')) {
            return null;
        }
        return $this->dispatch($request, $class, $action, $id, $method);
    }

    private function dispatch(
        RequestInterface $request,
        string $class,
        string $actionName,
        ?string $id,
        string $expectedHttpMethod
    ): ?ActionInterface {
        if ($request instanceof HttpRequest && strtoupper($request->getMethod()) !== $expectedHttpMethod) {
            return null;
        }
        if ($id !== null) {
            $request->setParam('id', $id);
        }
        $request->setModuleName(self::FRONT_NAME)
            ->setControllerName('partner')
            ->setActionName($actionName);
        return $this->actionFactory->create($class);
    }
}
