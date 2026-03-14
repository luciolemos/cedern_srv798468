<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;
use Throwable;

class HttpErrorHandler extends SlimErrorHandler
{
    private function expectsJson(): bool
    {
        $request = $this->request;
        $accept = strtolower($request->getHeaderLine('Accept'));
        $path = $request->getUri()->getPath();

        if (strpos($path, '/api/') === 0) {
            return true;
        }

        return strpos($accept, 'application/json') !== false
            && strpos($accept, 'text/html') === false;
    }

    private function buildHtmlErrorPage(int $statusCode, string $description): string
    {
        $safeDescription = htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $template =
            '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Erro %d</title></head><body><main '
            . 'style="max-width:720px;margin:3rem auto;padding:0 1rem;'
            . 'font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif">'
            . '<h1>Erro %d</h1><p>%s</p></main></body></html>';

        return sprintf(
            $template,
            $statusCode,
            $statusCode,
            $safeDescription
        );
    }

    /**
     * @inheritdoc
     */
    protected function respond(): Response
    {
        $exception = $this->exception;
        $statusCode = 500;
        $error = new ActionError(
            ActionError::SERVER_ERROR,
            'An internal error has occurred while processing your request.'
        );

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
            $error->setDescription($exception->getMessage());

            if ($exception instanceof HttpNotFoundException) {
                $error->setType(ActionError::RESOURCE_NOT_FOUND);
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $error->setType(ActionError::NOT_ALLOWED);
            } elseif ($exception instanceof HttpUnauthorizedException) {
                $error->setType(ActionError::UNAUTHENTICATED);
            } elseif ($exception instanceof HttpForbiddenException) {
                $error->setType(ActionError::INSUFFICIENT_PRIVILEGES);
            } elseif ($exception instanceof HttpBadRequestException) {
                $error->setType(ActionError::BAD_REQUEST);
            } elseif ($exception instanceof HttpNotImplementedException) {
                $error->setType(ActionError::NOT_IMPLEMENTED);
            }
        }

        if (
            !($exception instanceof HttpException)
            && $exception instanceof Throwable
            && $this->displayErrorDetails
        ) {
            $error->setDescription($exception->getMessage());
        }

        $response = $this->responseFactory->createResponse($statusCode);

        if ($this->expectsJson()) {
            $payload = new ActionPayload($statusCode, null, $error);
            $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT);
            $response->getBody()->write($encodedPayload);

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($this->buildHtmlErrorPage($statusCode, $error->getDescription()));

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
