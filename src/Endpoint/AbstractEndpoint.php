<?php

declare(strict_types=1);

/**
 * This file is part of the Elastic OpenAPI PHP code generator.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ADS\OpenApi\Codegen\Endpoint;

use ADS\Util\ArrayUtil;
use ADS\ValueObjects\ValueObject;
use EventEngine\Data\ImmutableRecord;
use UnexpectedValueException;

use function array_diff;
use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function count;
use function implode;
use function in_array;
use function ltrim;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function strval;

use const ARRAY_FILTER_USE_KEY;

/**
 * Abstract endpoint implementation.
 */
abstract class AbstractEndpoint implements Endpoint
{
    protected string $method;
    protected string $uri;
    /** @var array<string>  */
    protected array $routeParams = [];
    /** @var array<string>  */
    protected array $paramWhitelist = [];
    /** @var array<string, string>  */
    protected array $params = [];
    /** @var array<string, mixed>|null  */
    protected array|null $body = null;
    /** @var array<string, mixed>|null  */
    protected array|null $formData     = null;
    protected bool $snakeCasedParams   = false;
    protected bool $snakeCasedBody     = false;
    protected bool $snakeCasedFormData = false;

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        $uri = $this->uri;

        foreach ($this->routeParams as $paramName) {
            $uri = str_replace(sprintf('{%s}', $paramName), strval($this->params[$paramName]), $uri);
        }

        return ltrim($uri, '/');
    }

    /** @return array<string> */
    private function paramWhitelist(): array
    {
        return array_map(
            static fn (string $param) => str_ends_with($param, '[]')
                ? rtrim($param, '[]')
                : $param,
            $this->paramWhitelist,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function params(): array
    {
        $paramWhiteList = $this->paramWhitelist();

        /** @var array<string> $result */
        $result = ArrayUtil::rejectNullValues(
            array_filter(
                $this->params,
                static fn (string $paramName) => in_array($paramName, $paramWhiteList),
                ARRAY_FILTER_USE_KEY,
            ),
        );

        return $result;
    }

    public function body(): array|null
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function setBody(array|null $body)
    {
        $this->body = $this->transformData($body);

        return $this;
    }

    public function formData(): array|null
    {
        return $this->formData;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormData(array|null $formData)
    {
        $this->formData = $this->transformData($formData);

        return $this;
    }

    /**
     * @param array<string, mixed>|null $data
     *
     * @return array<string, mixed>|null
     */
    private function transformData(array|null $data): array|null
    {
        if ($data === null) {
            return $data;
        }

        $data = ArrayUtil::rejectNullValues($data);
        $data = ArrayUtil::rejectEmptyArrayValues($data);
        $data = ArrayUtil::removePrefixFromKeys(
            $data,
            'prefixNumber',
        );

        if ($this->snakeCasedBody) {
            /** @var array<mixed> $data */
            $data = ArrayUtil::toSnakeCasedKeys($data);
        }

        if (! ArrayUtil::isAssociative($data)) {
            $data = array_map(
                static fn ($item) => $item instanceof ImmutableRecord ? $item->toArray() : $item,
                $data,
            );
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function setParams(array|null $params)
    {
        if ($params === null) {
            return $this;
        }

        if ($this->snakeCasedParams) {
            /** @var array<string, string> $params */
            $params = ArrayUtil::toSnakeCasedKeys($params);
        }

        $this->checkParams($params);

        /** @var array<string> $params */
        $params = array_map(
            static fn ($paramValue) => $paramValue instanceof ValueObject ? $paramValue->toValue() : $paramValue,
            $params,
        );

        $this->params = $params;

        return $this;
    }

    /**
     * Loop over the param to check all params are into the whitelist.
     *
     * @param array<string, mixed>|null $params
     *
     * @throws UnexpectedValueException
     */
    private function checkParams(array|null $params): void
    {
        if ($params === null) {
            return;
        }

        $whitelist     = array_merge($this->paramWhitelist(), $this->routeParams);
        $invalidParams = array_diff(array_keys($params), $whitelist);
        $countInvalid  = count($invalidParams);

        if ($countInvalid <= 0) {
            return;
        }

        $whitelist     = implode('", "', $whitelist);
        $invalidParams = implode('", "', $invalidParams);
        $message       = '"%s" is not a valid parameter. Allowed parameters are "%s".';
        if ($countInvalid > 1) {
            $message = '"%s" are not valid parameters. Allowed parameters are "%s".';
        }

        throw new UnexpectedValueException(
            sprintf($message, $invalidParams, $whitelist),
        );
    }

    /**
     * @return static
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public function setSnakeCasedParams(bool $snakeCasedParams)
    {
        $this->snakeCasedParams = $snakeCasedParams;

        return $this;
    }

    /**
     * @return static
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public function setSnakeCasedBody(bool $snakeCasedBody)
    {
        $this->snakeCasedBody = $snakeCasedBody;

        return $this;
    }

    /**
     * @return static
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public function setSnakeCasedFormData(bool $snakeCasedFormData)
    {
        $this->snakeCasedFormData = $snakeCasedFormData;

        return $this;
    }
}
