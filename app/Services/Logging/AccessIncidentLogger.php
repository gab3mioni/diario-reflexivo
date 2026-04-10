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

class AccessIncidentLogger
{
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
