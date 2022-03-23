<?php


namespace PeelValley\Fortress;

use  UserFrosting\Fortress\ServerSideValidator as CoreServerSideValidator;

class ServerSideValidator extends CoreServerSideValidator
{

     /**
     * {@inheritdoc}
     */
    public function validate(array $data = [])
    {

        $this->generateCustomSchemaRules();   // Build custom validator rules from the schema.
        return parent::validate();      // Validate!
    }


    /**
     * Get the custom set of available rules, can be overridden to provide additional rules if required
     */
    protected function getCustomSchemaRules(MessageTranslator $translator)
    {

        return [
            'array_keys' => function ($validator, $messageSet, $fieldName) {
                if (isset($validator['keys'])) {
                    $this->customRuleWithMessage('arrayKeys', $messageSet, $fieldName, $validator['keys']);
                }
            },
            'array_values' => function ($validator, $messageSet, $fieldName) {
                if (isset($validator['values'])) {
                    $this->customRuleWithMessage('arrayValues', $messageSet, $fieldName, $validator['values']);
                }
            },
            'array_value_type' => function ($validator, $messageSet, $fieldName) {
                if (isset($validator['type']) && is_callable($validator['type'])) {
                    $this->customRuleWithMessage('arrayValuesType', $messageSet, $fieldName, $validator['type']);
                }
            }
        ];
    }

    /**
     * Validate that an array field contains only specific values
     *
     * @param string $field
     * @param mixed  $value
     * @param array  $params
     *
     * @return bool
     */
    protected function validateArrayKeys($field, $value, $params)
    {
        foreach ($value as $arrayKey=>$arrayVal) {
            if (! in_array($arrayKey, $params[0])) {
                return false;
            }
        }
        return true;
    }


    /**
     * Validate that an array field contains only specific values
     *
     * @param string $field
     * @param mixed  $value
     * @param array  $params
     *
     * @return bool
     */
    protected function validateArrayValues($field, $value, $params)
    {
        foreach ($value as $arrayKey=>$arrayVal) {
            if (! in_array($arrayVal, $params[0])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate that an array field contains only values of a specific type
     *
     * @param string $field
     * @param mixed  $value
     * @param array  $params
     *
     * @return bool
     */
    protected function validateArrayValuesType($field, $value, $params)
    {
        $typeCheck =  $params[0];
        foreach ($value as $arrayKey=>$arrayVal) {
            if (! call_user_func($typeCheck, $arrayVal)) {
                return false;
            }
        }
        return true;
    }

    protected function customRuleWithMessage($rule, $messageSet) //TODO: Remove after release 4.4 and use superclass ruleWithMessage function
    {
        // Weird way to adapt with Valitron's funky interface
        $params = array_merge([$rule], array_slice(func_get_args(), 2));
        call_user_func_array([$this, 'rule'], $params);

        // Set message.  Use Valitron's default message if not specified in the schema.
        if (!$messageSet) {
            $message = (isset(static::$_ruleMessages[$rule])) ? static::$_ruleMessages[$rule] : null;
            $message = vsprintf($message, array_slice(func_get_args(), 3));
            $messageSet = "'{$params[1]}' $message";
        }

        $this->message($messageSet);
    }

    /**
     * Generate and add rules from the schema.
     */
    private function generateCustomSchemaRules()
    {
        $customRules = $this->getCustomSchemaRules();

        foreach ($this->schema->all() as $fieldName => $field) {
            if (!isset($field['validators'])) {
                continue;
            }

            $validators = $field['validators'];
            foreach ($validators as $validatorName => $validator) {
                // Skip messages that are for client-side use only
                if (isset($validator['domain']) && $validator['domain'] == 'client') {
                    continue;
                }

                // Generate translated message
                if (isset($validator['message'])) {
                    $params = array_merge(['self' => $fieldName], $validator);
                    $messageSet = $this->translator->translate($validator['message'], $params);
                } else {
                    $messageSet = null;
                }

               if(in_array($validatorName, $customRules)) {
                   $ruleFunc = $customRules[$validatorName];
                   if(is_callable ($ruleFunc)) {
                    call_user_func($ruleFunc, $validator, $messageSet, $fieldName);
                   }
               }
            }
        }
    }
}
