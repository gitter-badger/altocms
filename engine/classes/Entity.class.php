<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

/**
 * Абстрактный класс сущности
 *
 * При запросе к базе данных удобно возвращать не просто массив данных, а данные в виде специального объекта - Entity.
 * Основные методы такого объекта делятся на два вида: get-методы и set-методы.
 * Первые получают свойство объекта по его имени, а вторые устанавливают.
 * Сущности поддерживают "магические" методы set* и get* , например
 * <pre>
 * $oEntity->getMyProperty()
 * </pre> вернет данные по ключу/полю my_property
 *
 * @package engine
 * @since 1.0
 */
abstract class Entity extends LsObject {
    const EXP_KEY = '_expanded';
    const EXP_KEY_SET = '_set';
    const EXP_KEY_DEL = '_del';
    const EXP_KEY_MOD = '_mod';
    const EXP_STR_MAX = 250;

    /**
     * Данные сущности, на этот массив мапятся методы set* и get*
     *
     * @var array
     */
    protected $_aData = array();
    /**
     * Имя поля с первичным ключом в БД
     *
     * @var null|string
     */
    protected $sPrimaryKey = null;
    /**
     * Список правил валидации полей
     * @see ModuleValidate
     *
     * @var array
     */
    protected $aValidateRules = array();
    /**
     * Список ошибок валидации в разрезе полей, например
     * <pre>
     * array(
     *    'title' => array('error one','error two'),
     *    'name' => array('error one','error two'),
     * )
     * </pre>
     *
     * @var array
     */
    protected $aValidateErrors = array();
    /**
     * Сценарий валиадции полей
     * @see _setValidateScenario
     *
     * @var string
     */
    protected $sValidateScenario = '';


    /**
     * Если передать в конструктор ассоциативный массив свойств и их значений, то они автоматом загрузятся в сущность
     *
     * @param array|null $aParam    Ассоциативный массив данных сущности
     */
    public function __construct($aParam = null) {

        $this->_setData($aParam);
        $this->Init();
    }

    public function __wakeup() {

        $this->Init();
    }

    /**
     * Метод инициализации сущности, вызывается при её создании или при восстановлении из кеша
     */
    public function Init() {

        $this->aValidateRules = array();
    }

    /**
     * Sets property of entity
     *
     * @param   string $sKey
     * @param   mixed $xVal
     * @return  mixed|null
     */
    public function setProp($sKey, $xVal) {

        $xOldVal = $this->getProp($sKey);
        $this->_aData[$sKey] = $xVal;
        return $xOldVal;
    }

    /**
     * Gets property of entity
     *
     * @param   string $sKey
     * @param   mixed $xDefault
     * @return  mixed|null
     */
    public function getProp($sKey, $xDefault = null) {

        if ($this->isProp($sKey)) {
            return $this->_aData[$sKey];
        }
        return $xDefault;
    }

    /**
     * Gets language associated property of entity
     *
     * @param string $sKey
     * @param mixed  $xDefault
     * @param string $sLang
     *
     * @return mixed|null
     */
    public function getLangProp($sKey, $xDefault = null, $sLang = null) {

        if (is_null($sLang)) {
            $sLang = $this->Lang_GetLang();
        }
        $sResult = $this->getProp($sKey . '_' . $sLang);
        if (is_null($sResult)) {
            $sResult = $this->getProp($sKey . '_' . $this->Lang_GetDefaultLang());
            if (is_null($sResult) && !is_null($sVal = $this->getProp($sKey . '_en'))) {
                return $sVal;
            }
            if (is_null($sResult) && !is_null($sVal = $this->getProp($sKey . '_ru'))) {
                return $sVal;
            }
            if (is_null($sResult) && !is_null($sVal = $this->getProp($sKey))) {
                return $sVal;
            }
            return $xDefault;
        }
        return $sResult;
    }

    /**
     * Deletes property of entity
     *
     * @param   string  $sKey
     */
    public function delProp($sKey) {
        if ($this->isProp($sKey)) {
            unset($this->_aData[$sKey]);
        }
    }

    /**
     * Gets integer property by mask
     *
     * @param string          $sKey
     * @param int|string|null $xMask - integer or string mask like '01001110'
     *
     * @return int
     */
    public function getPropMask($sKey, $xMask = null) {

        $nVal = intval($this->getProp($sKey));
        if (is_null($xMask)) {
            return $nVal;
        }
        if (is_string($xMask) && strcspn($xMask, '01')) {
            $xMask = bindec($xMask);
        }
        return $nVal & $xMask;
    }

    /**
     * @param string     $sKey
     * @param int|string $xMask - integer or string mask like '01001110'
     * @param bool       $bAnd  - OR/AND operation
     */
    public function setPropMask($sKey, $xMask, $bAnd = false) {

        $nVal = intval($this->getProp($sKey));
        if (is_string($xMask) && strcspn($xMask, '01')) {
            $xMask = bindec($xMask);
        }
        if ($bAnd) {
            $this->setProp($sKey, $nVal & $xMask);
        } else {
            $this->setProp($sKey, $nVal | $xMask);
        }
    }

    protected function _getExpandedData($sSubKey = null) {

        $aData = $this->getProp(self::EXP_KEY);
        if (!is_array($aData)) {
            $aData = array(
                self::EXP_KEY_SET => array(),
                self::EXP_KEY_DEL => array(),
                self::EXP_KEY_MOD => array(),
            );
        } elseif (!isset($aData[self::EXP_KEY_SET])) {
            $aData[self::EXP_KEY_SET] = array();
        } elseif (!isset($aExpanded[self::EXP_KEY_DEL])) {
            $aData[self::EXP_KEY_DEL] = array();
        } elseif (!isset($aExpanded[self::EXP_KEY_MOD])) {
            $aData[self::EXP_KEY_MOD] = array();
        }
        if ($sSubKey && in_array($sSubKey, array(self::EXP_KEY_SET, self::EXP_KEY_DEL, self::EXP_KEY_MOD))) {
            return $aData[$sSubKey];
        }
        return $aData;
    }

    protected function _setExpandedData($aData) {

        $this->setProp(self::EXP_KEY, $aData);
    }

    protected function _propExpandedInclude($sExpIdx, $sKey, $xVal) {

        $aExpanded = $this->_getExpandedData();
        if (in_array($sExpIdx, array(self::EXP_KEY_SET, self::EXP_KEY_DEL, self::EXP_KEY_MOD))) {
            if (isset($aExpanded[$sExpIdx][$sKey])) {
                $xOld = $aExpanded[$sExpIdx][$sKey];
            } else {
                $xOld = null;
            }
            $aExpanded[$sExpIdx][$sKey] = $xVal;
            $this->_setExpandedData($aExpanded);
            return $xOld;
        }
        return null;
    }

    protected function _propExpandedExclude($sExpIdx, $sKey) {

        $aExpanded = $this->_getExpandedData();
        if (in_array($sExpIdx, array(self::EXP_KEY_SET, self::EXP_KEY_DEL, self::EXP_KEY_MOD))) {
            if (isset($aExpanded[$sExpIdx][$sKey])) {
                $xOld = $aExpanded[$sExpIdx][$sKey];
                unset($aExpanded[$sExpIdx][$sKey]);
                $this->_setExpandedData($aExpanded);
                return $xOld;
            }
        }
        return null;
    }

    protected function _propExpandedValue($sExpIdx, $sKey) {

        $aExpanded = $this->_getExpandedData();
        if (in_array($sExpIdx, array(self::EXP_KEY_SET, self::EXP_KEY_DEL, self::EXP_KEY_MOD))) {
            if (isset($aExpanded[$sExpIdx][$sKey])) {
                $xVal = $aExpanded[$sExpIdx][$sKey];
                return $xVal;
            }
        }
        return null;
    }

    public function setPropExpanded($sKey, $xVal, $sType) {
        // Задаем свойству новое значение
        $this->setProp($sKey, $xVal);
        $aData = array(
            'type' => $sType,
            'string' => (($sType != 'text') ? $xVal : null),
            'text' => (($sType == 'text') ? serialize($xVal) : null),
        );
        $xNew = serialize($aData);
        // Добавляем в expanded-набор, получаем старое значение
        $xOld = $this->_propExpandedInclude(self::EXP_KEY_SET, $sKey, $xNew);
        if ($xVal !== $xOld) {
            // Добавляем в список измененных
            $this->_propExpandedInclude(self::EXP_KEY_MOD, $sKey, $xOld);
        }
    }

    public function delPropExpanded($sKey) {
        // Удаляем свойство из общего набора
        $this->delProp($sKey);
        // Исключаем из expanded-набора
        $xOld = $this->_propExpandedExclude(self::EXP_KEY_SET, $sKey);
        // Добавляем в список удяляемых
        $this->_propExpandedInclude(self::EXP_KEY_DEL, $sKey, $xOld);
    }

    public function getPropExpanded($sKey) {
        // Получаем свойство из общего набора
        return $this->getProp($sKey);
    }

    public function _getExpandedForUpdate($bModifiedOnly = true) {

        $aResult = array();
        if (!$bModifiedOnly) {
            $aResult = $this->_getExpandedData(self::EXP_KEY_SET);
        } else {
            $aUpdatedFields = $this->_getExpandedData(self::EXP_KEY_SET);
            foreach ($this->_getExpandedData(self::EXP_KEY_MOD) as $sKey=>$xVal) {
                if (isset($aUpdatedFields[$sKey])) {
                    $aResult[$sKey] = $aUpdatedFields[$sKey];
                }
            }
        }
        foreach($aResult as $sKey => $xData) {
            $aResult[$sKey] = unserialize($xData);
        }
        return $aResult;
    }

    public function _getExpandedForDelete() {

        return $this->_getExpandedData(self::EXP_KEY_DEL);
    }

    /**
     * Returns TRUE if property exists
     *
     * @param   string $sKey
     * @return  mixed|null
     */
    public function isProp($sKey) {

        return array_key_exists($sKey, $this->_aData);
    }

    /**
     * Устанавливает данные сущности
     *
     * @param array $aData    Ассоциативный массив данных сущности
     */
    public function _setData($aData) {

        if (is_array($aData) && sizeof($aData)) {
            foreach ($aData as $sKey => $val) {
                $this->setProp($sKey, $val);
            }
        }
    }

    /**
     * Получает массив данных сущности
     *
     * @param array|null $aKeys    Список полей, данные по которым необходимо вернуть, если не передан, то возвращаются все данные
     * @return array
     */
    public function _getData($aKeys = array()) {

        if (!is_array($aKeys) || !count($aKeys)) return $this->_aData;

        $aReturn = array();
        foreach ($aKeys as $key) {
            if ($this->isProp($key)) {
                $aReturn[$key] = $this->getProp($key);
            }
        }
        return $aReturn;
    }

    /**
     * Возвращает данные по конкретному полю    // LS-compatible
     *
     * @param string $sKey    Название поля, например <pre>'my_property'</pre>
     * @return null|mixed
     */
    public function _getDataOne($sKey) {

        return $this->getProp($sKey);
    }

    /**
     * Рекурсивное преобразование объекта и вложенных объектов в массив
     *
     * @return array
     */
    public function _getDataArray() {

        $aResult = array();
        foreach ($this->_aData as $sKey => $sValue) {
            if (is_object($sValue) && $sValue instanceOf Entity) {
                $aResult[$sKey] = $sValue->_getDataArray();
            } else {
                $aResult[$sKey] = $sValue;
            }
        }
        return $aResult;
    }

    public function getEntityName() {

        $aInfo = Engine::getInstance()->GetClassInfo($this, Engine::CI_ENTITY);
        if (isset($aInfo[Engine::CI_ENTITY])) {
            return $aInfo[Engine::CI_ENTITY];
        }
        return get_class($this);
    }

    /**
     * Ставим хук на вызов неизвестного метода и считаем что хотели вызвать метод какого либо модуля
     * Также производит обработку методов set* и get*
     * @see Engine::_CallModule
     *
     * @param string $sName Имя метода
     * @param array $aArgs Аргументы
     * @return mixed
     */
    public function __call($sName, $aArgs) {

        $sType = strtolower(substr($sName, 0, 3));
        if (!strpos($sName, '_') && ($sType == 'get' || $sType == 'set')) {
            $sKey = F::StrUnderscore(substr($sName, 3));
            if ($sType == 'get') {
                if ($this->isProp($sKey)) {
                    return $this->getProp($sKey);
                } else {
                    if (preg_match('/Entity([^_]+)/', get_class($this), $sModulePrefix)) {
                        $sModulePrefix = F::StrUnderscore($sModulePrefix[1]) . '_';
                        if ($this->isProp($sModulePrefix . $sKey)) {
                            return $this->getProp($sModulePrefix . $sKey);
                        }
                    }
                }
                return null;
            } elseif ($sType == 'set' && array_key_exists(0, $aArgs)) {
                $this->setProp($sKey, $aArgs[0]);
            }
        } else {
            return Engine::getInstance()->_CallModule($sName, $aArgs);
        }
    }

    /**
     * Получение первичного ключа сущности (ключ, а не значение!)
     * @see _getPrimaryKeyValue
     *
     * @return null|string
     */
    public function _getPrimaryKey() {

        if (!$this->sPrimaryKey) {
            if (isset($this->_aData['id'])) {
                $this->sPrimaryKey = 'id';
            } else {
                // Получение primary_key из схемы бд (пока отсутствует)
                $this->sPrimaryKey = 'id';
            }
        }

        return $this->sPrimaryKey;
    }

    /**
     * Возвращает значение первичного ключа/поля
     *
     * @return mixed|null
     */
    public function _getPrimaryKeyValue() {

        return $this->_getDataOne($this->_getPrimaryKey());
    }

    /**
     * Выполняет валидацию данных сущности
     * Если $aFields=null, то выполняется валидация по всем полям из $this->aValidateRules, иначе по пересечению
     *
     * @param null|array $aFields    Список полей для валидации, если null то по всем полям
     * @param bool $bClearErrors    Очищать или нет стек ошибок перед валидацией
     *
     * @return bool
     */
    public function _Validate($aFields = null, $bClearErrors = true) {

        if ($bClearErrors) {
            $this->_clearValidateErrors();
        }
        foreach ($this->_getValidators() as $validator) {
            $validator->validateEntity($this, $aFields);
        }
        return !$this->_hasValidateErrors();
    }

    /**
     * Возвращает список валидаторов с учетом текущего сценария
     *
     * @param null|string $sField    Поле сущности для которого необходимо вернуть валидаторы, если нет, то возвращается для всех полей
     *
     * @return array
     */
    public function _getValidators($sField = null) {

        $aValidators = $this->_createValidators();

        $aValidatorsReturn = array();
        $sScenario = $this->_getValidateScenario();
        foreach ($aValidators as $oValidator) {
            /**
             * Проверка на текущий сценарий
             */
            if ($oValidator->applyTo($sScenario)) {
                if ($sField === null || in_array($sField, $oValidator->fields, true)) {
                    $aValidatorsReturn[] = $oValidator;
                }
            }
        }
        return $aValidatorsReturn;
    }

    /**
     * Создает и возвращает список валидаторов для сущности
     * @see ModuleValidate::CreateValidator
     *
     * @return array
     * @throws Exception
     */
    public function _createValidators() {

        $aValidators = array();
        foreach ($this->aValidateRules as $aRule) {
            if (isset($aRule[0], $aRule[1])) {
                $aValidators[] = $this->Validate_CreateValidator($aRule[1], $this, $aRule[0], array_slice($aRule, 2));
            } else {
                throw new Exception(get_class($this) . ' has an invalid validation rule');
            }
        }
        return $aValidators;
    }

    /**
     * Проверяет есть ли ошибки валидации
     *
     * @param null|string $sField    Поле сущности, если нет, то проверяется для всех полей
     *
     * @return bool
     */
    public function _hasValidateErrors($sField = null) {

        if ($sField === null) {
            return $this->aValidateErrors !== array();
        } else {
            return isset($this->aValidateErrors[$sField]);
        }
    }

    /**
     * Возвращает список ошибок для всех полей или одного поля
     *
     * @param null|string $sField    Поле сущности, если нет, то возвращается для всех полей
     *
     * @return array
     */
    public function _getValidateErrors($sField = null) {

        if ($sField === null) {
            return $this->aValidateErrors;
        } else {
            return isset($this->aValidateErrors[$sField]) ? $this->aValidateErrors[$sField] : array();
        }
    }

    /**
     * Возвращает первую ошибку для поля или среди всех полей
     *
     * @param null|string $sField    Поле сущности
     *
     * @return string|null
     */
    public function _getValidateError($sField = null) {

        if ($sField === null) {
            foreach ($this->_getValidateErrors() as $sFieldKey => $aErros) {
                return reset($aErros);
            }
        } else {
            return isset($this->aValidateErrors[$sField]) ? reset($this->aValidateErrors[$sField]) : null;
        }
    }

    /**
     * Добавляет для поля ошибку в список ошибок
     *
     * @param string $sField    Поле сущности
     * @param string $sError    Сообщение об ошибке
     */
    public function _addValidateError($sField, $sError) {

        $this->aValidateErrors[$sField][] = $sError;
    }

    /**
     * Очищает список всех ошибок или для конкретного поля
     *
     * @param null|string $sField    Поле сущности
     */
    public function _clearValidateErrors($sField = null) {

        if ($sField === null) {
            $this->aValidateErrors = array();
        } else {
            unset($this->aValidateErrors[$sField]);
        }
    }

    /**
     * Возвращает текущий сценарий валидации
     *
     * @return string
     */
    public function _getValidateScenario() {

        return $this->sValidateScenario;
    }

    /**
     * Устанавливает сценарий валидации
     * Если использовать валидацию без сценария, то будут использоваться только те правила, где нет никаких сценариев, либо указан пустой сценарий ''
     * Если указать сценарий, то проверка будет только по правилом, где в списке сценарией есть указанный
     *
     * @param string $sValue
     */
    public function _setValidateScenario($sValue) {

        $this->sValidateScenario = $sValue;
    }

    /**
     * Преобразует сущность в массив
     *
     * @param   array|null $aMethods
     * @param   string $sPrefix
     * @return  array
     */
    public function ToArray($aMethods = null, $sPrefix = '') {

        if (!is_array($aMethods)) {
            $aMethods = get_class_methods($this);
        }
        $aEntity = array();
        foreach ($aMethods as $sMethod) {
            if (!preg_match('#^get([a-z][a-z\d]*)$#i', $sMethod, $aMatch)) {
                continue;
            }
            $sProp = strtolower(preg_replace('#([a-z])([A-Z])#', '$1_$2', $aMatch[1]));
            $mValue = $this->$sMethod();
            $aEntity[$sPrefix . $sProp] = $mValue;
        }
        return $aEntity;
    }

    public function setExpanded($sName, $sValue, $sType = 'string') {

        if ($sType == 'number' && is_scalar($sValue)) {
            if (filter_var($sValue, FILTER_VALIDATE_FLOAT)) {
                $sValue = floatval($sValue);
            } else {
                $sValue = intval($sValue);
            }
        } elseif ($sType == 'string' && is_scalar($sValue)) {
            $sValue = (string)$sValue;
            if (sizeof($sValue) > self::EXP_STR_MAX) {
                $sValue = substr($sValue, 0, self::EXP_STR_MAX);
            }
        } else {
            $sType = 'text';
        }
        $this->setPropExpanded($sName, $sValue, $sType);
    }

    public function deleteExpanded($sName) {

        $this->delPropExpanded($sName);
    }

}

// EOF