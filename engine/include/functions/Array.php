<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * Array functions for Alto CMS
 */
class AltoFunc_Array {
    /**
     * Рекурсивно изменяет элементы массива, добавляя ко всем значениям (кроме объектов) префикс и суффикс
     *
     * @param   array   $aData
     * @param   string  $sBefore
     * @param   string  $sAfter
     *
     * @return  array
     */
    static public function ChangeValues($aData, $sBefore = '', $sAfter = '') {

        foreach ($aData as $xKey => $xValue) {
            if (is_array($xValue)) {
                $array[$xKey] = self::ChangeValue($xValue, $sBefore, $sAfter);
            } elseif (!is_object($xValue)) {
                $aData[$xKey] = $sBefore . $aData[$xKey] . $sAfter;
            }
        }
        return $aData;
    }

    /**
     * Меняет числовые ключи массива на их значения
     * Т.е. если ключ - число, а значение элемента - строка, то они меняются местами
     *
     * @param   array   $aData
     * @param   mixed   $xDefValue
     *
     * @return  array
     */
    static public function FlipIntKeys($aData, $xDefValue = 1) {

        $aData = (array)$aData;
        foreach ($aData as $nKey => $sValue) {
            if (is_int($nKey) && is_string($sValue)) {
                unset($aData[$nKey]);
                if ($sValue) $aData[$sValue] = $xDefValue;
            }
        }
        return $aData;
    }

    /**
     * Сортировка массива данных по заданному массиву ключей
     *
     * @param   array   $aData
     * @param   array   $aKeys
     *
     * @return  array
     */
    static public function SortByKeysArray($aData, $aKeys) {

        $aResult = array();
        foreach ($aKeys as $xKey) {
            if (isset($aData[$xKey])) {
                $aResult[$xKey] = $aData[$xKey];
            }
        }
        return $aResult;
    }

    /**
     * Сливает рекурсивно два ассоциативных массива
     *
     * @param   array   $aData1
     * @param   array   $aData2
     *
     * @return  array
     */
    static public function Merge($aData1, $aData2) {

        $aData1 = (array)$aData1;
        if ($aData2) {
            foreach ($aData2 as $sKey => $xVal) {
                $bIsKeyInt = false;
                if (is_array($xVal)) {
                    foreach ($xVal as $k => $v) {
                        if (is_int($k)) {
                            $bIsKeyInt = true;
                            break;
                        }
                    }
                }
                if (is_array($xVal) && !$bIsKeyInt && isset($aData1[$sKey])) {
                    $aData1[$sKey] = self::Merge($aData1[$sKey], $xVal);
                } else {
                    $aData1[$sKey] = $xVal;
                }
            }
        }
        return $aData1;
    }

    /**
     * Рекурсивный вариант array_keys
     *
     * @param  array $aData     Массив
     *
     * @return array
     */
    static public function KeysRecursive($aData) {

        if (!is_array($aData)) {
            return false;
        } else {
            $aKeys = array_keys($aData);
            foreach ($aKeys as $k => $v) {
                if ($aAppend = F::Array_KeysRecursive($aData[$v])) {
                    unset($aKeys[$k]);
                    foreach ($aAppend as $sNewKey) {
                        $aKeys[] = $v . '.' . $sNewKey;
                    }
                }
            }
            return $aKeys;
        }
    }

    /**
     * Преобразует строку в массив
     *
     * @param   string|array    $sStr
     * @param   string          $sSeparator
     * @param   bool            $bSkipEmpty
     *
     * @return  array
     */
    static public function Str2Array($sStr, $sSeparator = ',', $bSkipEmpty = false) {

        if (!is_string($sStr) && !is_array($sStr)) {
            return (array)$sStr;
        }
        if (is_array($sStr)) {
            $aData = $sStr;
        } else {
            $aData = explode($sSeparator, $sStr);
        }

        $aResult = array();
        foreach ($aData as $xKey=>$sStr) {
            if ($sStr || !$bSkipEmpty) {
                $aResult[$xKey] = trim($sStr);
            }
        }
        return $aResult;
    }

    /**
     * Преобразует строку в массив целых чисел
     *
     * @param   string|array    $sStr
     * @param   string          $sSeparator
     * @param   bool            $bUnique
     *
     * @return  array
     */
    static public function Str2ArrayInt($sStr, $sSeparator = ',', $bUnique = true) {

        $aData = static::Str2Array($sStr, $sSeparator, $bUnique);
        $aResult = array();
        foreach ($aData as $sItem) {
            $aResult[] = intval($sItem);
        }
        if ($bUnique) {
            $aResult = array_unique($aResult);
        }
        return $aResult;
    }

    /**
     * Преобразует значение в массив
     * <pre>
     * Значение               Результат
     * ---------------------+--------------------
     *   null               | array()
     *   false              | array(false)
     *   array(true)        | array(true)
     *   array(true, false) | array(true, false)
     *   'a, b'             | array('a', 'b')
     *   array('a, b')      | array('a', 'b')
     *   array('a', 'b')    | array('a', 'b')
     * ---------------------+--------------------
     * </pre>
     */
    static public function Val2Array($xVal, $sSeparator = ',', $bSkipEmpty = false) {

        if (is_array($xVal) && (sizeof($xVal) == 1) && isset($xVal[0]) && strpos($xVal[0], ',')) {
            $aResult = F::Str2Array($xVal[0], $sSeparator, $bSkipEmpty);
        } elseif (is_array($xVal)) {
            $aResult = $xVal;
        } elseif (is_null($xVal)) {
            $aResult = array();
        } elseif (!is_string($xVal)) {
            $aResult = (array)$xVal;
        } else {
            $aResult = F::Str2Array($xVal, $sSeparator, $bSkipEmpty);
        }
        return $aResult;
    }

    /**
     * Returns the first key of array
     *
     * @param $aData
     *
     * @return mixed
     */
    static public function FirstKey($aData) {

        if (is_array($aData)) {
            $aKeys = array_keys($aData);
            return array_shift($aKeys);
        }
    }

    /**
     * Returns the last key of array
     *
     * @param $aData
     *
     * @return mixed
     */
    static public function LastKey($aData) {

        if (is_array($aData)) {
            $aKeys = array_keys($aData);
            return array_pop($aKeys);
        }
    }

    /**
     * Search string in array with case-insensitive string comparison
     *
     * @param $sStr
     * @param $aArray
     *
     * @return bool
     */
    static public function StrInArray($sStr, $aArray) {

        $sStr = mb_strtolower($sStr);
        foreach ($aArray as $sCompare) {
            $sCompare = mb_strtolower($sCompare);
            if (strcmp($sStr, mb_strtolower($sCompare)) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the values from a single column of the input array, identified by the columnKey
     *
     * @param      $aArray
     * @param      $sColumnKey
     * @param null $sIndexKey
     *
     * @return array|bool
     */
    static public function Column($aArray, $sColumnKey, $sIndexKey = null) {

        if (!is_array($aArray)) {
            return false;
        }
        if (function_exists('array_column')) {
            return array_column($aArray, $sColumnKey, $sIndexKey);
        }
        if ($sIndexKey === null) {
            foreach ($aArray as $nI => &$aIn) {
                if (is_array($aIn) && isset($aIn[$sColumnKey])) {
                    $aIn = $aIn[$sColumnKey];
                } else {
                    unset($aArray[$nI]);
                }
            }
        } else {
            $aResult = array();
            foreach ($aArray as $nI => $aIn) {
                if (is_array($aIn) && isset($aIn[$sColumnKey])) {
                    if (isset($aIn[$sIndexKey])) {
                        $aResult[$aIn[$sIndexKey]] = $aIn[$sColumnKey];
                    } else {
                        $aResult[] = $aIn[$sColumnKey];
                    }
                    unset($aArray[$nI]);
                }
            }
            $aArray = & $aResult;
        }
        return $aArray;
    }

}

// EOF