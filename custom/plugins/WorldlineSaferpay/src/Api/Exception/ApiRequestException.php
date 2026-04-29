<?php declare(strict_types=1);

namespace Worldline\Saferpay\Api\Exception;

class ApiRequestException extends \Exception
{
    const ERROR_NAME_TRANSACTION_ABORTED = 'TRANSACTION_ABORTED';
    const ERROR_NAME_TRANSACTION_ALREADY_CAPTURED = 'TRANSACTION_ALREADY_CAPTURED';

    /** @noinspection PhpUnused */
    const ERROR_NAME_INTERNAL_ERROR = 'INTERNAL_ERROR';

    /** @noinspection PhpUnused */
    const ERROR_NAME_ACTION_NOT_SUPPORTED = 'ACTION_NOT_SUPPORTED';

    /** @noinspection PhpUnused */
    const BEHAVIOR_RETRY_LATER = 'RETRY_LATER';
    const BEHAVIOR_DO_NOT_RETRY = 'DO_NOT_RETRY';

    private string $requestUri;
    private string $responseBody;
    private array $responseHeaders;
    private ?object $responseObject = null;

    public function __construct(string $requestUri, array $responseHeaders, string $responseBody, int $code)
    {
        parent::__construct(
            'Saferpay API request failed (URI: '
            . $requestUri
            . '). The response was: '
            . implode("\n", $responseHeaders)
            . "\n\n"
            . $responseBody,
            $code
        );

        $this->requestUri = $requestUri;
        $this->responseHeaders = $responseHeaders;
        $this->responseBody = $responseBody;

        $responseObject = @json_decode($responseBody);
        if (is_object($responseObject)) {
            $this->responseObject = $responseObject;
        }
    }

    /**
     * @noinspection PhpUnused
     */
    public function getRequestUri(): string
    {
        return $this->requestUri;
    }

    /**
     * @noinspection PhpUnused
     */
    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    public function getErrorName(): string
    {
        if ($this->responseObject && isset($this->responseObject->ErrorName) && is_string($this->responseObject->ErrorName)) {
            return $this->responseObject->ErrorName;
        }

        return '';
    }

    public function getBehaviour(): string
    {
        if ($this->responseObject && isset($this->responseObject->Behavior) && is_string($this->responseObject->Behavior)) {
            return $this->responseObject->Behavior;
        }

        return '';
    }

    /**
     * @noinspection PhpUnused
     */
    public function getResponseObject(): ?object
    {
        return $this->responseObject;
    }
}
