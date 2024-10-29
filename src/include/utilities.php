<?php

function validateRoute($route, $system = false) {
    $routeRegex = '/^[a-zA-Z][a-zA-Z0-9_\-\/]*[a-zA-Z0-9]$/';
    $systemRouteRegex = '/^_[a-zA-Z][a-zA-Z0-9_\-\/]*[a-zA-Z0-9]$/';

    if (empty($route)) {
        return 'Route is required';
    } elseif ($system && !preg_match($systemRouteRegex, $route)) {
        $routeLength = strlen($route);
        if ($routeLength < 3) {
            return 'System route should be at least three characters long';
        } elseif ($route[0] !== '_') {
            return 'System route should start with an underscore';
        } elseif (!preg_match('/[a-zA-Z0-9]/', $route[$routeLength - 1])) {
            return 'System route should end with a letter or a number';
        } else {
            return 'System route should contain only letters, numbers, dashes, underscores, and forward slashes';
        }
    } elseif (!$system && !preg_match($routeRegex, $route)) {
        $routeLength = strlen($route);
        if ($routeLength < 2) {
            return 'Route should be at least two characters long';
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

function filterObject(array $obj, array $arr): array {
    if (is_array($arr)) {
        $filteredObj = array();
        foreach ($arr as $key) {
            if (is_string($key)) {
                if (array_key_exists($key, $obj)) {
                    $filteredObj[$key] = $obj[$key];
                }
            } elseif (is_array($key)) {
                $nestedObj = $obj[$key[0]];
                $nestedArr = $key[1];
                if (is_list($nestedObj)) {
                    $filteredArr = array();
                    foreach ($nestedObj as $nested) {
                        $filteredNestedObj = filterObject($nested, $nestedArr);
                        if (count($filteredNestedObj) > 0) {
                            $filteredArr[] = $filteredNestedObj;
                        }
                    }
                    if (count($filteredArr) > 0) {
                        $filteredObj[$key[0]] = $filteredArr;
                    }
                } elseif (is_array($nestedObj) && $nestedObj !== null) {
                    $filteredNestedObj = filterObject($nestedObj, $nestedArr);
                    if (count($filteredNestedObj) > 0) {
                        $filteredObj[$key[0]] = $filteredNestedObj;
                    }
                }
            }
        }
        return $filteredObj;
    }
    return array();
}

function generateUUIDv1() {
    // $node = getNodeIdentifier(); // Get MAC address
    $timeLow = round(microtime(true) * 10000) & 0xFFFFFFF; // Get lower 32 bits of current timestamp
    $timeMid = round(microtime(true) * 10000) >> 4 & 0xFFFF; // Get middle 16 bits of current timestamp
    $timeHiAndVersion = round(microtime(true) * 10000) >> 12 | 1 << 12; // Get higher 16 bits of current timestamp with version number 1
    return sprintf(
        '%08x-%04x-%04x-%04x-%012x',
        $timeLow,
        $timeMid,
        $timeHiAndVersion,
        random_int(pow(16, 12), pow(16, 13) - 1), // $node,
        random_int(pow(16, 12), pow(16, 13) - 1) // Generate random 48-bit number
    );
}

// function getNodeIdentifier() {
//     $file = '/sys/class/net/eth0/address';
//     $macAddress = '';
//     if (file_exists($file)) {
//         $macAddress = file_get_contents($file);
//     } else {
//         $networkInterfaces = network_interfaces();
//         foreach ($networkInterfaces as $networkInterface) {
//             if ($networkInterface['flags'] & IFF_BROADCAST) {
//             $macAddress = $networkInterface['mac'];
//             break;
//             }
//         }
//     }
//     return hexdec(str_replace(':', '', $macAddress)) & 0xFFFFFFFFFFFF;
// }

// function filterObject($obj, $arr) {
//     if (is_array($arr)) {
//         $filteredObj = [];
//         foreach ($arr as $key) {
//             if (is_string($key) && array_key_exists($key, $obj)) {
//                 $filteredObj[$key] = $obj[$key];
//             } elseif (is_array($key)) {
//                 $nestedObj = $obj[$key[0]];
//                 $nestedArr = $key[1];
//                 if (is_array($nestedObj)) {
//                     $filteredArr = [];
//                     foreach ($nestedObj as $item) {
//                         $filteredNestedObj = filterObject($item, $nestedArr);
//                         if (!empty($filteredNestedObj)) {
//                             $filteredArr[] = $filteredNestedObj;
//                         }
//                     }
//                     if (!empty($filteredArr)) {
//                         $filteredObj[$key[0]] = $filteredArr;
//                     }
//                 } elseif (is_array($nestedObj) && $nestedObj !== null) {
//                     $filteredNestedObj = filterObject($nestedObj, $nestedArr);
//                     if (!empty($filteredNestedObj)) {
//                         $filteredObj[$key[0]] = $filteredNestedObj;
//                     }
//                 }
//             }
//         }
//         return $filteredObj;
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
// 

?>
