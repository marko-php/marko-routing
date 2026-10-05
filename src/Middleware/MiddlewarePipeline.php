<?php

declare(strict_types=1);

namespace Marko\Routing\Middleware;

use JsonException;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Routing\Http\ExceptionRenderer;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

readonly class MiddlewarePipeline
{
    public function __construct(
        private ContainerInterface $container,
        private ExceptionRenderer $exceptionRenderer = new ExceptionRenderer(),
    ) {}

    /**
     * Process the request through the middleware pipeline.
     *
     * An HttpExceptionInterface thrown by a middleware or the handler is
     * rendered into a response at the depth it was thrown, so every outer
     * middleware still runs on (and can decorate) the error response. Any
     * other throwable propagates unchanged.
     *
     * @param array<class-string<MiddlewareInterface>> $middlewareClasses
     * @param Request $request
     * @param callable(Request): Response $handler
     * @return Response
     * @throws JsonException
     */
    public function process(
        array $middlewareClasses,
        Request $request,
        callable $handler,
    ): Response {
        if (empty($middlewareClasses)) {
            try {
                return $handler($request);
            } catch (HttpExceptionInterface $exception) {
                return $this->exceptionRenderer->render($exception, $request);
            }
        }

        $middlewareClass = array_shift($middlewareClasses);
        /** @var MiddlewareInterface $middleware */
        $middleware = $this->container->get($middlewareClass);

        try {
            return $middleware->handle(
                $request,
                fn (Request $r) => $this->process($middlewareClasses, $r, $handler),
            );
        } catch (HttpExceptionInterface $exception) {
            return $this->exceptionRenderer->render($exception, $request);
        }
    }
}
