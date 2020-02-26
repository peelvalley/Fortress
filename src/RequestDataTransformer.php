<?php

namespace PeelValley\Fortress;

use Carbon\Carbon;
use  UserFrosting\Fortress\RequestDataTransformer as CoreRequestDataTransformer;

class RequestDataTransformer extends CoreRequestDataTransformer
{
    public function transformField($name, $value)
    {
        $debug = \UserFrosting\Sprinkle\Core\Facades\Config::get('debug.fortress.transformer');

        if($debug) {
            $this->debug("Field name: $name Value: $value");
        }
        $schemaFields = $this->schema->all();

        $fieldParameters = $schemaFields[$name];

        if (!isset($fieldParameters['transformations']) || empty($fieldParameters['transformations'])) {
            return $value;
        } else {
            // Field exists in schema, so apply sequence of transformations
            $transformedValue = $value;

            foreach ($fieldParameters['transformations'] as $transformation) {
                switch (strtolower($transformation)) {
                    case 'parse_json': $transformedValue = json_decode($transformedValue); break;
                    case 'integer': $transformedValue = intval($transformedValue); break;
                    case 'boolean': $transformedValue = $this->booleanValue($transformedValue); break;
                    case 'date': $transformedValue = $this->toCarbon( 'd M Y', $transformedValue); break;
                    case 'datetime': $transformedValue = $this->toCarbon('d M Y H:i', $transformedValue); break;
                    case 'from_timestamp': $transformedValue = Carbon::createFromTimestamp($transformedValue); break;
                    default: $transformedValue = parent::transformField($name, $value);
                }
                if($debug) {
                    $this->debug("Transformaation: $transformation Result: $transformedValue");
                }
            }
            return $transformedValue;
        }
    }

    protected function toCarbon($dtFormat, $value) {
        try {
           return Carbon::createFromFormat($dtForat, $value);
        } catch (\Exception $e) {
            $example = Carbon::now()->format($dtFormat);
            $this->debug("Format: {$dtFormat} value: {$value} example: {$example}");
            throw $e;
        }
    }

    protected function debug($message) {
        (\UserFrosting\System\Facade::getFacadeContainer())->debugLogger->debug($message); // Nasty workaround because userfrosting debug facade seems broken
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
}
