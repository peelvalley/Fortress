<?php

namespace PeelValley\Fortress;

use Carbon\Carbon;
use  UserFrosting\Fortress\RequestDataTransformer as CoreRequestDataTransformer;
use \UserFrosting\Sprinkle\Core\Facades\Debug;


class RequestDataTransformer extends CoreRequestDataTransformer
{
    public function transformField($name, $value)
    {
        $debug = \UserFrosting\Sprinkle\Core\Facades\Config::get('debug.fortress.transformer');

        if($debug) {
            Debug::debug("Field name: $name Value: $value");
        }
        $schemaFields = $this->schema->all();

        $fieldParameters = $schemaFields[$name];

        if (!isset($fieldParameters['transformations']) || empty($fieldParameters['transformations'])) {
            return $value;
        } else {
            // Field exists in schema, so apply sequence of transformations
            $transformedValue = $value;

            foreach ($fieldParameters['transformations'] as $transformation) {
                if($debug) {
                    Debug::debug("Transformation: $transformation Before: " . print_r($transformedValue, TRUE));
                }
                switch (strtolower($transformation)) {
                    case 'parse_json':          if(is_string($transformedValue)) $transformedValue = json_decode($transformedValue); break;
                    case 'parse_json_assoc':    if(is_string($transformedValue)) $transformedValue = json_decode($transformedValue, TRUE); break;
                    case 'integer':             $transformedValue = intval($transformedValue); break;
                    case 'boolean':             $transformedValue = $this->booleanValue($transformedValue); break;
                    case 'date':
                        if(isset($fieldParameters['allowEmpty'])) {
                            $transformedValue = $this->toCarbon($fieldParameters['dateFormat'] ?? 'd M Y', $transformedValue, $fieldParameters['allowEmpty']);
                            break;
                        }

                        $transformedValue = $this->toCarbon($fieldParameters['dateFormat'] ?? 'd M Y', $transformedValue);
                        break;
                    case 'datetime':
                        if(isset($fieldParameters['allowEmpty'])) {
                            $transformedValue = $this->toCarbon($fieldParameters['dateTimeFormat'] ?? 'd M Y H:i', $transformedValue, $fieldParameters['allowEmpty']);
                            break;
                        }

                        $transformedValue = $this->toCarbon($fieldParameters['dateTimeFormat'] ?? 'd M Y H:i', $transformedValue);
                        break;
                    case 'from_timestamp':      $transformedValue = Carbon::createFromTimestamp($transformedValue); break;
                    case 'to_null':             $transformedValue = $this->isNullValue($transformedValue)? NULL: $transformedValue; break;
                    case 'lowercase':           $transformedValue = strtolower($transformedValue); break;
                    case 'uppercase':           $transformedValue = strtoupper($transformedValue); break;
                    case 'remove_spaces':       $transformedValue = str_replace(' ', '', $transformedValue); break;
                    case 'array_push':          array_push($transformedValue, $fieldParameters['pushValue'] ); break;

                    default:                    $transformedValue = parent::transformField($name, $value);
                }
                if($debug) {
                    Debug::debug("Transformation: $transformation Result: " . print_r($transformedValue, TRUE));
                }
            }
            return $transformedValue;
        }
    }

    protected function toCarbon($dtFormat, $value, $allowEmpty = False) {
        if($allowEmpty && (is_null($value) || $value === "")) {
            return Null;
        }

        try {
           return Carbon::createFromFormat($dtFormat, $value);
        } catch (\Exception $e) {
            $example = Carbon::now()->format($dtFormat);
            Debug::debug("Format: '{$dtFormat}' value: '{$value}' example: '{$example}'");
            throw $e;
        }
    }

    protected function booleanValue ($value) {
        if($value === TRUE || $value === FALSE) return $value;
        if(strtolower($value) === 'true') return TRUE;
        if(strtolower($value) === 'yes') return TRUE;
        if(strtolower($value) === 'on') return TRUE;
        if($value === '1') return TRUE;
        if($value === 1) return TRUE;
        if(strtolower($value) === 'false') return FALSE;
        if(strtolower($value) === 'no') return FALSE;
        if(strtolower($value) === 'off') return FALSE;
        if($value === '0') return FALSE;
        if($value === 0) return FALSE;
        throw new \Exception("Unable to convert value to boolean");
    }

    protected function isNullValue ($value) {
        if ($value === NULL) return TRUE;
        if ($value === '') return TRUE;
        if ($value === -1) return TRUE;
    }
}
