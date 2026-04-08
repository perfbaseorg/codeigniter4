<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Support;

final class UserIdentifier
{
    public static function fromValue(mixed $value): ?string
    {
        if (is_scalar($value)) {
            $identifier = trim((string) $value);

            return $identifier === '' ? null : $identifier;
        }

        if (!is_object($value)) {
            return null;
        }

        foreach (['getId', 'id', 'getIdentifier', 'getAuthIdentifier'] as $method) {
            if (!method_exists($value, $method)) {
                continue;
            }

            $resolved = $value->{$method}();
            if (is_scalar($resolved)) {
                $identifier = trim((string) $resolved);

                return $identifier === '' ? null : $identifier;
            }
        }

        if (isset($value->id) && is_scalar($value->id)) {
            $identifier = trim((string) $value->id);

            return $identifier === '' ? null : $identifier;
        }

        return null;
    }
}
