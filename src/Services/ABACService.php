<?php

namespace AuroraWebSoftware\AAuth\Services;

use AuroraWebSoftware\AAuth\Enums\ABACCondition;
use Exception;
use Illuminate\Support\Facades\Validator;

/**
 * Organization Data Service
 */
class ABACService
{
    public static function getQueryBuilderfromJson()
    {
    }

    /**
     * @param array $abacRules
     * @return void
     * @throws Exception
     */
    public static function validateAbacRuleArray(array $abacRules): void
    {
        $validationRules = [
            '&&' => 'array',
            '||' => 'array',
        ];

        foreach (ABACCondition::cases() as $condition) {
            // todo requeired if ile kontrol
            $validationRules[$condition->value] = ['array'];
            // $validationRules['*.' . $condition->value . '.attribute'] = ['string'];
            // $validationRules['*.' . $condition->value . '.value'] = 'string';

            if (array_key_exists($condition->value, $abacRules)) {
                $validationRules[$condition->value . '.attribute'] = ['string', 'required'];
            }
        }



        $validation = Validator::make($abacRules, $validationRules);

        if ($validation->fails()) {
            throw new Exception($validation->messages());
        }

        foreach ($abacRules as $abacRule) {
            if (is_array($abacRule)) {
                ABACService::validateAbacRuleArray($abacRule);
            }
        }
    }

    public static function validateAbacRuleJson(string $rule)
    {
    }
}
