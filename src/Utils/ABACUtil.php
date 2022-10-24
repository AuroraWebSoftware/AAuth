<?php

namespace AuroraWebSoftware\AAuth\Utils;

use AuroraWebSoftware\AAuth\Enums\ABACCondition;
use Exception;
use Illuminate\Support\Facades\Validator;

class ABACUtil
{
    /**
     * @param array $abacRules
     * @return void
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
                $validationRules[$condition->value . '.attribute'] = ['string', 'required'];
                $validationRules[$condition->value . '.value'] = ['string', 'required'];
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
     * @param string $ruleJson
     * @return void
     * @throws Exception
     */
    public static function validateAbacRuleJson(string $ruleJson): void
    {
        ABACUtil::validateAbacRuleArray(json_decode($ruleJson));
    }
}
