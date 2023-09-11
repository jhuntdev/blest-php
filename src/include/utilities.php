<?php

$route_regex = '/^[a-zA-Z][a-zA-Z0-9_\-\/]*[a-zA-Z0-9]$/';

function validate_route($route) {
    global $route_regex;

    if (empty($route)) {
        return 'Route is required';
    } elseif (!preg_match($route_regex, $route)) {
        $routeLength = strlen($route);
        if ($routeLength < 2) {
            return 'Route should be at least two characters long';
        } elseif ($route[$routeLength - 1] === '/') {
            return 'Route should not end in a forward slash';
        } elseif (!preg_match('/[a-zA-Z]/', $route[0])) {
            return 'Route should start with a letter';
        } elseif (!preg_match('/[a-zA-Z0-9]/', $route[$routeLength - 1])) {
            return 'Route should end with a letter or a number';
        } else {
            return 'Route should contain only letters, numbers, dashes, underscores, and forward slashes';
        }
    } elseif (preg_match('/\/[^a-zA-Z]/', $route)) {
        return 'Sub-routes should start with a letter';
    } elseif (preg_match('/[^a-zA-Z0-9]\//', $route)) {
        return 'Sub-routes should end with a letter or a number';
    } elseif (preg_match('/\/[a-zA-Z0-9_\-]{0,1}\//', $route)) {
        return 'Sub-routes should be at least two characters long';
    } elseif (preg_match('/\/[a-zA-Z0-9_\-]$/', $route)) {
        return 'Sub-routes should be at least two characters long';
    } elseif (preg_match('/^[a-zA-Z0-9_\-]\//', $route)) {
        return 'Sub-routes should be at least two characters long';
    }
    
    return false;
}

function filter_object(array $obj, array $arr): array {
    if (is_array($arr)) {
        $filtered_obj = array();
        foreach ($arr as $key) {
            if (is_string($key)) {
                if (array_key_exists($key, $obj)) {
                    $filtered_obj[$key] = $obj[$key];
                }
            } elseif (is_array($key)) {
                $nested_obj = $obj[$key[0]];
                $nested_arr = $key[1];
                if (is_list($nested_obj)) {
                    $filtered_arr = array();
                    foreach ($nested_obj as $nested) {
                        $filtered_nested_obj = filter_object($nested, $nested_arr);
                        if (count($filtered_nested_obj) > 0) {
                            $filtered_arr[] = $filtered_nested_obj;
                        }
                    }
                    if (count($filtered_arr) > 0) {
                        $filtered_obj[$key[0]] = $filtered_arr;
                    }
                } elseif (is_array($nested_obj) && $nested_obj !== null) {
                    $filtered_nested_obj = filter_object($nested_obj, $nested_arr);
                    if (count($filtered_nested_obj) > 0) {
                        $filtered_obj[$key[0]] = $filtered_nested_obj;
                    }
                }
            }
        }
        return $filtered_obj;
    }
    return array();
}



// function filterObject($obj, $arr) {
//     if (is_array($arr)) {
//         $filtered_obj = [];
//         foreach ($arr as $key) {
//             if (is_string($key) && array_key_exists($key, $obj)) {
//                 $filtered_obj[$key] = $obj[$key];
//             } elseif (is_array($key)) {
//                 $nested_obj = $obj[$key[0]];
//                 $nested_arr = $key[1];
//                 if (is_array($nested_obj)) {
//                     $filtered_arr = [];
//                     foreach ($nested_obj as $item) {
//                         $filteredNestedObj = filterObject($item, $nested_arr);
//                         if (!empty($filteredNestedObj)) {
//                             $filtered_arr[] = $filteredNestedObj;
//                         }
//                     }
//                     if (!empty($filtered_arr)) {
//                         $filtered_obj[$key[0]] = $filtered_arr;
//                     }
//                 } elseif (is_array($nested_obj) && $nested_obj !== null) {
//                     $filteredNestedObj = filterObject($nested_obj, $nested_arr);
//                     if (!empty($filteredNestedObj)) {
//                         $filtered_obj[$key[0]] = $filteredNestedObj;
//                     }
//                 }
//             }
//         }
//         return $filtered_obj;
//     }
//     return $obj;
// }

// function cloneDeep($value) {
//     if (!is_array($value) && !is_object($value)) {
//         return $value;
//     }

//     if (is_array($value)) {
//         $clonedValue = [];
//         foreach ($value as $key => $item) {
//             $clonedValue[$key] = cloneDeep($item);
//         }
//     } else {
//         $clonedValue = new stdClass();
//         foreach ($value as $key => $item) {
//             $clonedValue->$key = cloneDeep($item);
//         }
//     }

//     return $clonedValue;
// }
// ?>
