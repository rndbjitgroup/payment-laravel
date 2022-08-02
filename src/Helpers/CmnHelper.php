<?php 

namespace Bjit\Payment\Helpers;

use ReflectionClass;

abstract class CmnHelper 
{
    public static function jsonEncodePrivate($obj)
    {
        function extract_props($obj) 
        {
            $public = [];
            $i = 0;
            $reflection = new ReflectionClass(get_class($obj));
            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($obj);
                $name = $property->getName();
                if ($name != "_values") {
                    continue;
                }

                if (is_array($value)) {
                    $public = [];
                    foreach ($value as $iKey => $item) {
                        if (is_object($item)) {
                            $itemArray = extract_props($item);
                            $public[$iKey] = $itemArray;
                        } else {
                            $public[$iKey] = $item;
                        }
                    }
                } else if (is_object($value)) {
                    $public[$i] = extract_props($value);
                } else {
                    $public[$i] = $value;
                }
                ++$i;
            }
            return $public;
        }
    
        return json_encode(extract_props($obj));
    }
    
}