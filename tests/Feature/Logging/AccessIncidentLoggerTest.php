<?php

use App\Services\Logging\AccessIncidentLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
});

test('returns false and does not log for unrelated exceptions', function () {
    $logger = app(AccessIncidentLogger::class);
    $request = Request::create('/whatever', 'GET');

    $handled = $logger->log(new \RuntimeException('boom'), $request);

    expect($handled)->toBeFalse();
    Log::shouldNotHaveReceived('channel');
});

test('logs 403 AccessDeniedHttpException with full context', function () {
    $channel = Mockery::mock();
    $channel->shouldReceive('warning')
        ->once()
        ->with('access.denied', Mockery::on(function ($ctx) {
            return $ctx['status'] === 403
                && $ctx['method'] === 'GET'
                && $ctx['ip'] === '203.0.113.42'
                && $ctx['user_id'] === null
                && $ctx['user_email'] === null
                && array_key_exists('url', $ctx)
                && array_key_exists('route', $ctx)
                && array_key_exists('ua', $ctx);
        }));
    Log::shouldReceive('channel')->with('access')->once()->andReturn($channel);

    $request = Request::create('/admin', 'GET', server: ['REMOTE_ADDR' => '203.0.113.42']);
    $logger = app(AccessIncidentLogger::class);

    $handled = $logger->log(
        new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('nope'),
        $request
    );

    expect($handled)->toBeTrue();
});

test('logs 401 AuthenticationException', function () {
    $channel = Mockery::mock();
    $channel->shouldReceive('warning')
        ->once()
        ->with('access.denied', Mockery::on(fn ($ctx) => $ctx['status'] === 401));
    Log::shouldReceive('channel')->with('access')->once()->andReturn($channel);

    $logger = app(AccessIncidentLogger::class);
    $handled = $logger->log(
        new \Illuminate\Auth\AuthenticationException(),
        Request::create('/admin', 'GET')
    );

    expect($handled)->toBeTrue();
});

test('logs 429 ThrottleRequestsException', function () {
    $channel = Mockery::mock();
    $channel->shouldReceive('warning')
        ->once()
        ->with('access.denied', Mockery::on(fn ($ctx) => $ctx['status'] === 429));
    Log::shouldReceive('channel')->with('access')->once()->andReturn($channel);

    $logger = app(AccessIncidentLogger::class);
    $handled = $logger->log(
        new \Illuminate\Http\Exceptions\ThrottleRequestsException('slow down'),
        Request::create('/login', 'POST')
    );

    expect($handled)->toBeTrue();
});

test('ignores 404 for anonymous requests', function () {
    Log::shouldReceive('channel')->never();

    $logger = app(AccessIncidentLogger::class);
    $handled = $logger->log(
        new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(),
        Request::create('/missing', 'GET')
    );

    expect($handled)->toBeFalse();
});

test('end-to-end: 403 from a real protected route is written to access channel', function () {
    $teacher = \App\Models\User::factory()->teacher()->create();

    // Stub para que Log::channel('access')->warning(...) seja capturado
    $captured = [];
    $channel = new class($captured) {
        public function __construct(public array &$captured) {}
        public function warning($message, array $context = []): void
        {
            $this->captured[] = ['message' => $message, 'context' => $context];
        }
    };
    Log::shouldReceive('channel')->with('access')->andReturn($channel);
    // Permitir outros canais (debug, error log do framework) sem quebrar
    Log::shouldReceive('channel')->withAnyArgs()->andReturnUsing(fn () => $channel);

    $this->actingAs($teacher)->get('/question-scripts')->assertForbidden();

    expect($captured)->not->toBeEmpty()
        ->and($captured[0]['message'])->toBe('access.denied')
        ->and($captured[0]['context']['status'])->toBe(403)
        ->and($captured[0]['context']['user_id'])->toBe($teacher->id);
});

test('logs 404 for authenticated requests with user context', function () {
    $user = \App\Models\User::factory()->create();

    $channel = Mockery::mock();
    $channel->shouldReceive('warning')
        ->once()
        ->with('access.denied', Mockery::on(function ($ctx) use ($user) {
            return $ctx['status'] === 404
                && $ctx['user_id'] === $user->id
                && $ctx['user_email'] === $user->email;
        }));
    Log::shouldReceive('channel')->with('access')->once()->andReturn($channel);

    $request = Request::create('/missing', 'GET');
    $request->setUserResolver(fn () => $user);

    $logger = app(AccessIncidentLogger::class);
    $handled = $logger->log(
        new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(),
        $request
    );

    expect($handled)->toBeTrue();
});
