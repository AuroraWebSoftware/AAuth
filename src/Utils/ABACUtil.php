<?php

namespace AuroraWebSoftware\AAuth\Utils;

use AuroraWebSoftware\AAuth\Enums\ABACCondition;
use Exception;
use Illuminate\Support\Facades\Validator;

class ABACUtil
{
    protected const MAX_DEPTH = 10;

    protected const LOGICAL_OPERATORS = ['&&', '||'];

    /**
     * @param  array  $abacRules
     * @param  int  $depth
     * @return void
     *
     * @throws Exception
     */
    public static function validateAbacRuleArray(array $abacRules, int $depth = 0): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new Exception('ABAC rule depth limit exceeded (max: '.self::MAX_DEPTH.')');
        }

        $allowedOperators = array_map(fn ($case) => $case->value, ABACCondition::cases());

        foreach ($abacRules as $operator => $value) {
            if (in_array($operator, self::LOGICAL_OPERATORS)) {
                if (! is_array($value)) {
                    throw new Exception("ABAC logical operator '{$operator}' must contain an array");
                }

                foreach ($value as $nestedRule) {
                    if (! is_array($nestedRule)) {
                        throw new Exception("ABAC logical operator '{$operator}' must contain array of rules");
                    }

                    self::validateAbacRuleArray($nestedRule, $depth + 1);
                }
            } elseif (in_array($operator, $allowedOperators)) {
                if (! is_array($value)) {
                    throw new Exception("ABAC condition operator '{$operator}' must contain an array with 'attribute' and 'value' keys");
                }

                if (! isset($value['attribute'])) {
                    throw new Exception("ABAC rule missing 'attribute' key for operator '{$operator}'");
                }

                if (! isset($value['value'])) {
                    throw new Exception("ABAC rule missing 'value' key for operator '{$operator}'");
                }

                self::validateAttributeName($value['attribute']);

                self::validateAttributeValue($value['value']);
            } else {
                throw new Exception("Unknown ABAC operator: '{$operator}'");
            }
        }
    }

    /**
     * @param  string  $ruleJson
     * @return void
     *
     * @throws Exception
     */
    public static function validateAbacRuleJson(string $ruleJson): void
    {
        $decoded = json_decode($ruleJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in ABAC rule: '.json_last_error_msg());
        }

        if (! is_array($decoded)) {
            throw new Exception('ABAC rule JSON must decode to an array');
        }

        self::validateAbacRuleArray($decoded);
    }

    /**
     * @param  mixed  $attribute
     * @return void
     *
     * @throws Exception
     */
    protected static function validateAttributeName($attribute): void
    {
        if (! is_string($attribute)) {
            throw new Exception('ABAC attribute name must be a string');
        }

        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $attribute)) {
            throw new Exception("Invalid ABAC attribute name: '{$attribute}'. Must match pattern: [a-zA-Z_][a-zA-Z0-9_]*(.[a-zA-Z_][a-zA-Z0-9_]*)*");
        }
    }

    /**
     * @param  mixed  $value
     * @return void
     *
     * @throws Exception
     */
    protected static function validateAttributeValue($value): void
    {
        if (is_string($value)) {
            if (str_starts_with($value, '@')) {
                $userAttr = substr($value, 1);

                if (! preg_match('/^user\.[a-zA-Z_][a-zA-Z0-9_]*$/', $userAttr)) {
                    throw new Exception("Invalid user attribute reference: '{$value}'. Must match pattern: @user.[attribute]");
                }
            }
        } elseif (! is_numeric($value) && ! is_bool($value) && ! is_null($value) && ! is_array($value)) {
            throw new Exception('ABAC attribute value must be string, numeric, boolean, null, or array');
        }
    }
}
