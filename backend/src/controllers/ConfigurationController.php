<?php

declare(strict_types=1);

namespace Xestify\controllers;

use Throwable;
use Xestify\core\Request;
use Xestify\core\RequestFactory;
use Xestify\core\Response;
use Xestify\core\RuntimePathNormalizer;
use Xestify\repositories\ConfigurationRepository;

class ConfigurationController
{
    private const SUPPORTED_KEYS = ['ui-preferences'];
    private const MSG_AUTH_REQUIRED = 'Se requiere autenticación.';
    private const MSG_ADMIN_REQUIRED = 'Se requiere rol de administrador.';
    private const MSG_KEY_NOT_FOUND = 'Configuración no soportada.';
    private const MSG_VALUE_INVALID = 'El campo value debe ser un objeto JSON.';
    private const MSG_ERROR_PREFIX = 'Error: ';

    public function __construct(
        private ConfigurationRepository $repository,
        private ?RequestFactory $requestFactory = null
    ) {
    }

    public function index(array $params = [], ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        try {
            Response::make()->json([
                'configurations' => $this->repository->all(),
            ]);
        } catch (Throwable $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    public function show(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        if (!is_array($request->user())) {
            Response::make()->unauthorized(self::MSG_AUTH_REQUIRED);
            return;
        }

        $key = (string) ($params['key'] ?? '');
        if (!$this->supportsKey($key)) {
            Response::make()->notFound(self::MSG_KEY_NOT_FOUND);
            return;
        }

        try {
            $config = $this->repository->find($key);
            Response::make()->json([
                'key' => $key,
                'value' => is_array($config['value'] ?? null) ? $config['value'] : [],
                'updated_at' => $config['updated_at'] ?? null,
            ]);
        } catch (Throwable $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    public function update(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        $key = (string) ($params['key'] ?? '');
        if (!$this->supportsKey($key)) {
            Response::make()->notFound(self::MSG_KEY_NOT_FOUND);
            return;
        }

        $body = $request->allBody();
        $value = $body['value'] ?? null;
        if (!is_array($value)) {
            Response::make()->unprocessable(self::MSG_VALUE_INVALID, [
                'value' => [self::MSG_VALUE_INVALID],
            ]);
            return;
        }

        try {
            $saved = $this->repository->save($key, $value);
            Response::make()->json($saved);
        } catch (Throwable $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    private function supportsKey(string $key): bool
    {
        return in_array($key, self::SUPPORTED_KEYS, true);
    }

    private function requestFactory(): RequestFactory
    {
        if ($this->requestFactory instanceof RequestFactory) {
            return $this->requestFactory;
        }

        return new RequestFactory(new RuntimePathNormalizer());
    }
}

