<?php

namespace App\Services\Logging;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Registra incidentes de acesso (401, 403, 429, 404) no canal de log dedicado.
 */
class AccessIncidentLogger
{
    /**
     * Registra o incidente de acesso se aplicável.
     *
     * Retorna true se o incidente foi registrado, false se foi ignorado
     * (ex.: exceção não mapeada ou 404 de visitante anônimo).
     *
     * @param  Throwable  $e        Exceção capturada.
     * @param  Request    $request  Requisição HTTP atual.
     * @return bool
     */
    public function log(Throwable $e, Request $request): bool
    {
        $status = $this->resolveStatus($e);

        if ($status === null) {
            return false;
        }

        if ($status === 404 && $request->user() === null) {
            return false;
        }

        Log::channel('access')->warning('access.denied', [
            'status'     => $status,
            'method'     => $request->method(),
            'url'        => $request->fullUrl(),
            'ip'         => $request->ip(),
            'user_id'    => $request->user()?->id,
            'user_email' => $request->user()?->email,
            'route'      => $request->route()?->getName(),
            'ua'         => $request->userAgent(),
        ]);

        return true;
    }

    /**
     * Resolve o código de status HTTP a partir do tipo da exceção.
     *
     * @param  Throwable  $e  Exceção capturada.
     * @return ?int  Código HTTP ou null se a exceção não for um incidente de acesso.
     */
    private function resolveStatus(Throwable $e): ?int
    {
        if ($e instanceof AuthenticationException) {
            return 401;
        }
        if ($e instanceof AuthorizationException && ! $e instanceof HttpExceptionInterface) {
            return 403;
        }
        if ($e instanceof ThrottleRequestsException) {
            return 429;
        }
        if ($e instanceof NotFoundHttpException) {
            return 404;
        }
        if ($e instanceof HttpExceptionInterface) {
            $code = $e->getStatusCode();
            if (in_array($code, [401, 403, 429], true)) {
                return $code;
            }
        }
        return null;
    }
}
