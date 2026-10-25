<?php

use App\Modules\Platform\Tenancy\ResolveTenant;
use App\Modules\Schools\Services\ConflictException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias(['tenant' => ResolveTenant::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (ConflictException $e) => response()->json(['message' => $e->getMessage()], 409));

        // Unicidade (23505), exclusão (23P01) e chave estrangeira (23503) violadas: conflito de dados.
        $exceptions->render(function (QueryException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match ($e->errorInfo[0] ?? null) {
                '23505', '23P01' => response()->json(['message' => 'Já existe um registo igual ou com dados sobrepostos.'], 409),
                '23503' => response()->json(['message' => 'O registo está associado a outros dados.'], 409),
                '23514' => response()->json(['message' => 'Os dados não cumprem as regras de integridade.'], 422),
                default => null,
            };
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
