<?php

namespace VanDmade\Hookamatic\Inbound\Concerns;

trait ExtractsHeaderValue
{

    /**
     * Normalizes a header value, whether it's a plain string or Symfony's
     * HeaderBag::all() shape (an array of values per header name).
     */
    protected function headerValue(array $headers, string $name): ?string
    {
        $value = $headers[$name] ?? null;
        if (is_array($value)) {
            return $value[0] ?? null;
        }
        return $value;
    }

}
