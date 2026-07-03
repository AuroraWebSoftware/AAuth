<?php

namespace AuroraWebSoftware\AAuth\Utils;

use AuroraWebSoftware\AAuth\Enums\ABACCondition;
use Exception;
use Illuminate\Support\Facades\Validator;

class ABACUtil
{
    /**
     * @throws Exception
     */
    public static function validateAbacRuleArray(array $abacRules): void
    {
        // todo validation improvement needed

        $validationRules = [
            '&&' => 'array',
            '||' => 'array',
        ];

        foreach (ABACCondition::cases() as $condition) {
            $validationRules[$condition->value] = ['array'];
            if (array_key_exists($condition->value, $abacRules)) {
                // 'attribute' is interpolated into the column position of the query, so it
                // must be a bare column identifier — reject quotes, '->', spaces, etc.
                // (fail closed against SQL/column injection from stored rules).
                $validationRules[$condition->value.'.attribute'] = ['string', 'required', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'];
                $validationRules[$condition->value.'.value'] = ['string', 'required'];
            }
        }

        $validation = Validator::make($abacRules, $validationRules);

        if ($validation->fails()) {
            throw new Exception($validation->messages());
        }

        foreach ($abacRules as $abacRule) {
            if (is_array($abacRule)) {
                ABACUtil::validateAbacRuleArray($abacRule);
            }
        }
    }

    /**
     * @throws Exception
     */
    public static function validateAbacRuleJson(string $ruleJson): void
    {
        ABACUtil::validateAbacRuleArray(json_decode($ruleJson));
    }
}
