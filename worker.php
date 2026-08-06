<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use Pizzeria\Kernel;
use Pizzeria\Kitchen\Infrastructure\Consumer\JobDispatcher;
use Psr\Container\ContainerInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

require __DIR__ . '/vendor/autoload.php';

/**
 * One image, one entrypoint (doc/09_architecture.md §3) — differentiated
 * only by RoadRunner's own RR_MODE env var, set automatically per worker
 * pool ("http" for the http plugin, "jobs" for the jobs plugin, regardless
 * of which pipeline/broker a job came from — see .rr/rr-kitchen.yaml). This is the
 * literal mechanism this whole pass exists to exercise.
 */

function runHttpMode(Kernel $kernel): void
{
    $psr17Factory = new Psr17Factory();
    $psr7Worker = new PSR7Worker(RoadRunnerWorker::create(), $psr17Factory, $psr17Factory, $psr17Factory);
    $httpFoundationFactory = new HttpFoundationFactory();
    $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

    while (true) {
        try {
            $psrRequest = $psr7Worker->waitRequest();
        } catch (\Throwable $e) {
            $psr7Worker->getWorker()->error((string) $e);
            continue;
        }

        if ($psrRequest === null) {
            break; // Graceful stop signal from RoadRunner.
        }

        try {
            $symfonyRequest = $httpFoundationFactory->createRequest($psrRequest);
            $symfonyResponse = $kernel->handle($symfonyRequest);
            $psr7Worker->respond($psrHttpFactory->createResponse($symfonyResponse));
            $kernel->terminate($symfonyRequest, $symfonyResponse);
        } catch (\Throwable $e) {
            $psr7Worker->respond(new Psr7Response(500, [], 'Internal Server Error'));
            $psr7Worker->getWorker()->error((string) $e);
        }
    }
}

function runJobsMode(ContainerInterface $container): void
{
    $consumer = new Consumer();
    /** @var JobDispatcher $dispatcher */
    $dispatcher = $container->get(JobDispatcher::class);

    while ($task = $consumer->waitTask()) {
        try {
            $dispatcher->dispatch($task->getPayload());
            $task->complete();
        } catch (\Throwable $e) {
            $task->fail($e);
        }
    }
}

$appEnv = getenv('APP_ENV') ?: 'prod';
// Symfony convention: debug follows env unless explicitly overridden —
// APP_ENV=dev alone should turn debug on, not require APP_DEBUG too.
$appDebug = getenv('APP_DEBUG') !== false
    ? (bool) getenv('APP_DEBUG')
    : $appEnv !== 'prod';

$kernel = new Kernel($appEnv, $appDebug);
$kernel->boot();

$mode = $_SERVER['RR_MODE'] ?? null;

match ($mode) {
    'http' => runHttpMode($kernel),
    'jobs' => runJobsMode($kernel->getContainer()),
    default => throw new \RuntimeException(sprintf('Unknown or missing RR_MODE: %s', var_export($mode, true))),
};
