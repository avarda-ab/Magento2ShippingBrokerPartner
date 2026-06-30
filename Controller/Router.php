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
 * Routes the partner endpoints, accepted both scoped under the frontName and
 * bare at the host root (e.g. /create-session). Widget endpoints are
 * frontName-only (/widget/state/{id}, /widget/select/{id}).
 */
class Router implements RouterInterface
{
    public const FRONT_NAME = 'avarda_shipping_broker_partner';

    /**
     * action => [controller, HTTP method, expects path id]
     *
     * @var array<string, array{0: class-string, 1: string, 2: bool}>
     */
    protected const ROUTES = [
        'create-session'   => [CreateSession::class,   'POST', false],
        'update-session'   => [UpdateSession::class,   'PUT',  true],
        'complete-session' => [CompleteSession::class, 'PUT',  true],
        'get-session'      => [GetSession::class,      'GET',  true],
    ];

    protected ActionFactory $actionFactory;

    public function __construct(
        ActionFactory $actionFactory
    ) {
        $this->actionFactory = $actionFactory;
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

        // Bare form: /create-session etc. (no widget paths).
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
