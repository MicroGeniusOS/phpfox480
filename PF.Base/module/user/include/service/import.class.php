<?php
defined('PHPFOX') or exit('NO DICE!');

/**
 * Class User_Service_Import
 */
class User_Service_Import extends Phpfox_Service
{
    /**
     * Parse text in csv file to array
     * @param null $hFile
     * @param null $iStart
     * @param null $iTotal
     * @return array|bool
     */
    public function parseTextToArray($hFile = null, $iStart = null, $iTotal = null)
    {
        if (empty($hFile)) {
            return false;
        }
        $iTotalLine = 0;
        $aHeaderText = '';
        $aTexts = [];


        while (!feof($hFile)) {
            $sText = fgets($hFile);
            if ($sText === false) {
                break;
            }

            if (empty($iStart)) {
                if ($iTotalLine == 0) {
                    $aHeaderText = str_replace(PHP_EOL, '', $sText);
                } else {
                    $aTexts[] = str_replace(PHP_EOL, '', $sText);
                }
            } elseif ($iTotalLine >= (int)$iStart && (($iTotalLine - (int)$iStart) < (int)$iTotal)) {
                $aTexts[] = str_replace(PHP_EOL, '', $sText);
            }

            $iTotalLine++;

            if (!empty($iTotal) && !empty($iStart) && ($iTotalLine > (int)$iStart) && (($iTotalLine - (int)$iStart) == (int)$iTotal)) {
                break;
            }
        }

        if (empty($iStart)) {
            $aHeader = str_getcsv($aHeaderText);
        }

        $aRows = [];
        foreach ($aTexts as $aText) {
            $aRows[] = str_getcsv($aText);
        }
        return !empty($iStart) ? $aRows : [$iTotalLine, $aHeader, $aRows];
    }

    /**
     * Validate for upload and import csv file
     * @param $aRow
     * @param $aInitCheckingData
     * @return array|bool
     */
    public function checkRowData($aRow, $aInitCheckingData)
    {
        $aSelectedFields = isset($aInitCheckingData['selected_field']) ? $aInitCheckingData['selected_field'] : null;
        $bIsImportProcess = !empty($aSelectedFields) ? true : false;
        $aValue = [];
        $iSelectedFieldCnt = 0;
        $aMerge = array_combine($aInitCheckingData['header'], $aRow);
        $aRowError = $this->_checkUploadedRequiredFieldData($aMerge);
        if (!empty($aRowError) && !$bIsImportProcess) {
            return false;
        }

        if (isset($aMerge['user_group_id'])) {
            $aSelectedFields[] = 'user_group_id';
        }

        foreach ($aMerge as $sRowKey => $sRowValue) {
            $sTempValue = null;

            if (!in_array($sRowKey, $aInitCheckingData['required_field'])) {
                $bIsValid = true;

                if (in_array($sRowKey, $aInitCheckingData['main_field']) && !empty($sRowValue)) {
                    //Note: input value with Gender Id if it belongs to system and text(seperated by "_". Exp: test_test123, abc_xyz)
                    if ($sRowKey == 'gender') {
                        if (!in_array($sRowValue, $aInitCheckingData['gender']) && !(is_string($sRowValue) && !empty(explode('_', $sRowValue)))) {
                            $bIsValid = false;
                        }

                        if ($bIsImportProcess && in_array($sRowKey, $aSelectedFields) && $bIsValid) {
                            if (is_numeric($sRowValue) && intval($sRowValue) > 0 && intval($sRowValue) == $sRowValue && in_array($sRowValue, $aInitCheckingData['gender'])) {
                                $sTempValue = $sRowValue;
                            } else if (!empty(explode('_', $sRowValue))) {
                                $sTempValue = explode('_', $sRowValue);
                            }
                        }
                    } elseif ($sRowKey == 'country_iso') {
                        if (!in_array($sRowValue, $aInitCheckingData['country_iso']['country_code']) && in_array($sRowValue, $aInitCheckingData['country_iso']['country_title'])) {
                            $bIsValid = false;
                        }

                        if ($bIsImportProcess && in_array($sRowKey, $aSelectedFields) && $bIsValid) {
                            $aInverse = array_combine($aInitCheckingData['country_iso']['country_title'], $aInitCheckingData['country_iso']['country_code']);
                            $sTempValue = in_array($sRowValue, $aInitCheckingData['country_iso']['country_code']) ? $sRowValue : $aInverse[$sRowValue];
                        }
                    } else if ($sRowKey == 'country_child_id') {
                        if (isset($aMerge['country_iso'])) {
                            $aState = Phpfox::getService('core.country')->getChildren($aMerge['country_iso']);
                            if (!in_array($sRowValue, array_keys($aState)) && !in_array($sRowValue, array_values($aState))) {
                                $bIsValid = false;
                            }
                            if ($bIsImportProcess && in_array($sRowKey, $aSelectedFields) && $bIsValid) {
                                $aInverse = array_combine(array_values($aState), array_keys($aState));
                                $sTempValue = in_array($sRowValue, array_keys($aState)) ? $sRowValue : $aInverse[$sRowValue];
                            }
                        } else {
                            $bIsValid = false;
                        }
                    } else if ($sRowKey == 'user_group_id') {
                        if (!in_array($sRowValue, $aInitCheckingData['group']['group_id']) && !in_array($sRowValue, $aInitCheckingData['group']['group_title'])) {
                            $bIsValid = false;
                        }
                        if ($bIsImportProcess && $bIsValid) {
                            $aInverse = array_combine($aInitCheckingData['group']['group_title'], $aInitCheckingData['group']['group_id']);
                            $sTempValue = in_array($sRowValue, $aInitCheckingData['group']['group_id']) ? $sRowValue : $aInverse[$sRowValue];
                        }
                    } else if ($sRowKey == 'city_location') {
                        if (!Phpfox_Validator::instance()->check($sRowValue, ['html', 'url'])) {
                            $bIsValid = false;
                        }
                        $sTempValue = $sRowValue;
                    } else {
                        $sTempValue = $sRowValue;
                    }
                } else {
                    if (in_array($sRowKey, $aInitCheckingData['custom_field_text'])) {
                        //Note: Admin need to check if this custom field is required and included to sign up or not
                        if ($aInitCheckingData['custom_field'][$sRowKey]['is_required'] && $aInitCheckingData['custom_field'][$sRowKey]['on_signup'] && empty($sRowValue)) {
                            $bIsValid = false;
                        }
                        if ($bIsValid && isset($sRowValue)) {
                            //Note: If option value for multi-select, checkbox, radio, select is number text ("1", "2",...), you must insert option value by option id
                            //Note: You must be insert option_id or option text following the custom field you want to import
                            switch ($aInitCheckingData['custom_field'][$sRowKey]['var_type']) {
                                case 'multiselect':
                                case 'checkbox':
                                    $sTempValue = $this->checkCustomData($aInitCheckingData['custom_field'][$sRowKey]['field_id'], $sRowValue, $bIsImportProcess);

                                    if ($sTempValue === false || (!is_array($sTempValue) && $bIsImportProcess)) {
                                        $bIsValid = false;
                                    }

                                    break;
                                case 'select':
                                case 'radio':
                                    if (((!is_numeric($sRowValue) || intval($sRowValue) == 0 || (intval($sRowValue) > 0 && (intval($sRowValue) != $sRowValue)))) && !is_string($sRowValue)) {
                                        $bIsValid = false;
                                    }

                                    if ($bIsImportProcess && $bIsValid) {
                                        $aField = Phpfox::getService('custom')->getForCustomEdit($aInitCheckingData['custom_field'][$sRowKey]['field_id']);
                                        if (empty($aField['option'])) {
                                            $bIsValid = false;
                                        }

                                        if (intval($sRowValue) > 0 && intval($sRowValue) == $sRowValue) {
                                            $sTempValue = $sRowValue;
                                        } else {
                                            $aOptionTexts = [];
                                            $sDefaultLanguage = Phpfox::getLib('locale')->getLangId();
                                            foreach ($aField['option'] as $iOptionId => $aOption) {
                                                foreach ($aOption as $aTempOption) {
                                                    $aOptionTexts[$aTempOption[$sDefaultLanguage]['text']] = $iOptionId;
                                                }
                                            }
                                            $sTempValue = $aOptionTexts[$sRowValue];
                                        }
                                    }
                                    break;
                                case 'text':
                                case 'textarea':
                                    $sInsertValue = Phpfox::getLib('parse.input')->prepare($sRowValue);
                                    $sReturn = Phpfox::getLib('parse.output')->parse($sInsertValue);
                                    if (empty($sReturn) && ($aInitCheckingData['custom_field'][$sRowKey]['is_required'] && $aInitCheckingData['custom_field'][$sRowKey]['on_signup'])) {
                                        $bIsValid = false;
                                    } else {
                                        $sTempValue = $sRowValue;
                                    }
                                    break;
                                default:
                                    $bIsValid = false;
                                    break;
                            }
                        }
                    }
                }

                if (!$bIsImportProcess && !$bIsValid) {
                    return false;
                } else if ($bIsImportProcess && (in_array($sRowKey, $aSelectedFields) || $sRowKey == 'user_group_id' || in_array($sRowKey, $aInitCheckingData['custom_field_text']))) {
                    if ($bIsValid) {
                        $aValue[$sRowKey] = $sTempValue;
                    } else {
                        $aRowError[$sRowKey]['error']['field_is_invalid_to_import'] = [
                            'field' => $sRowKey
                        ];
                    }
                    $iSelectedFieldCnt++;

                    if ($iSelectedFieldCnt == count($aSelectedFields)) {
                        break;
                    }
                }
            } else if ($bIsImportProcess) {
                $aValue[$sRowKey] = $sRowValue;
                $iSelectedFieldCnt++;

                if ($iSelectedFieldCnt == count($aSelectedFields)) {
                    break;
                }
            }
        }
        return $bIsImportProcess ? [$aValue, $aRowError] : true;
    }

    /**
     * Validate custom field data for upload and import csv file
     * @param $iFieldId
     * @param $sRowValue
     * @param bool $bIsImport
     * @return array|bool
     */
    public function checkCustomData($iFieldId, $sRowValue, $bIsImport = false)
    {
        $aTemp = explode('_', $sRowValue);
        if (!is_array($aTemp)) {
            return false;
        }

        $bAllValueIsId = true;
        $bAllValueIsText = true;

        foreach ($aTemp as $temp) {
            if (!is_numeric($temp) && (intval($temp) == 0 || (intval($temp) > 0 && (intval($temp) != $temp)))) {
                $bAllValueIsId = false;
            }
        }

        if (!$bAllValueIsId) {
            foreach ($aTemp as $temp) {
                if (!is_string($temp) || intval($temp) > 0) {
                    $bAllValueIsText = false;
                }
            }
        }

        if ((!$bAllValueIsId && !$bAllValueIsText) || count($aTemp) != count(array_unique($aTemp))) {
            return false;
        }

        if ($bIsImport) {
            $aTempField = Phpfox::getService('custom')->getForCustomEdit($iFieldId);

            if (empty($aTempField['option'])) {
                return false;
            }

            $aOptions = $aTempField['option'];

            $bRight = true;
            if ($bAllValueIsId) {
                $aOptionIds = array_keys($aOptions);
                foreach ($aTemp as $temp) {
                    if (!in_array($temp, $aOptionIds)) {
                        $bRight = false;
                        break;
                    }
                }
            }

            //Note: text must be follow default language (priority: user selected, site)
            if ($bAllValueIsText && ((!$bRight && $bAllValueIsId) || !$bAllValueIsId)) {
                $aOptionTexts = [];
                $sDefaultLanguage = Phpfox::getLib('locale')->getLangId();
                foreach ($aOptions as $aOption) {
                    foreach ($aOption as $aTempOption) {
                        $aOptionTexts[] = $aTempOption[$sDefaultLanguage]['text'];
                    }
                }

                foreach ($aTemp as $temp) {
                    if (!in_array($temp, $aOptionTexts)) {
                        return false;
                    }
                }
            }
        }
        return $bIsImport ? (!empty($aTemp) ? $aTemp : false) : true;
    }

    /**
     * Import user
     * @param $aRow
     * @param $aRowError
     * @return bool
     */
    public function importUser($aRow, $aRowError)
    {
        if (empty($aRow)) {
            return false;
        }

        $aFields = [
            'full_name',
            'user_name',
            'email',
            'user_group_id',
            'gender',
            'country_iso',
            'city_location',
            'postal_code',
            'country_child_id'
        ];

        $aCustomGroups = Phpfox::getService('custom')->getForListing();
        $aCustomFields = [];
        $aCustomFieldText = [];
        if (!empty($aCustomGroups)) {
            foreach ($aCustomGroups as $aCustomGroup) {
                foreach ($aCustomGroup['child'] as $aCustomField) {
                    $aCustomFields['cf_' . $aCustomField['field_name']] = $aCustomField;
                }
            }
        }

        if (!empty($aCustomFields)) {
            $aCustomFieldText = array_keys($aCustomFields);
        }

        $aInsert = [];

        foreach ($aRow as $sKey => $sValue) {
            if (in_array($sKey, $aFields)) {
                if (in_array($sKey, ['full_name', 'user_name', 'email'])) {
                    $tempError = $this->_validateRequiredField($sKey, $sValue);
                    if (!empty($tempError)) {
                        $aRowError[$sKey]['error'] = !empty($aRowError[$sKey]['error']) ? array_merge($aRowError[$sKey]['error'], $tempError) : $tempError;
                    } else {
                        $aInsert[$sKey] = $sValue;
                    }
                } else {
                    $aInsert[$sKey] = $sValue;
                }
            } else if (in_array($sKey, $aCustomFieldText)) {
                $aInsert['custom'][$aCustomFields[$sKey]['field_id']] = $sValue;
            }
        }
        if (empty($aRowError)) {
            $aInsert['password'] = $this->_randomPassword();
            Phpfox::getService('user.process')->importUser($aInsert, $aInsert['user_group_id']);
        }
        return $aRowError;
    }

    /**
     * Random password
     * @return string
     */
    private function _randomPassword()
    {
        $sSalt = '';
        for ($i = 0; $i < 6; $i++) {
            $sSalt .= chr(rand(97, 122));
        }

        return $sSalt;
    }

    /**
     * Get import information
     * @param $iImportId
     * @param bool $bGetUserInfo
     * @return array|int|string
     */
    public function getImport($iImportId, $bGetUserInfo = false)
    {
        $sSelect = 'ui.*';
        if ($bGetUserInfo) {
            $sSelect .= ', ' . Phpfox::getUserField();
            db()->join(Phpfox::getT('user'), 'u', 'u.user_id = ui.user_id');
        }
        return db()->select($sSelect)
            ->from(Phpfox::getT('user_import'), 'ui')
            ->where('ui.import_id = ' . (int)$iImportId)
            ->execute('getSlaveRow');
    }

    /**
     * Filter imports by conditions
     * @param array $aConds
     * @param int $iPage
     * @param int $iSize
     * @return array
     */
    public function getImportsByConditions($aConds = [], $iPage = 1, $iSize = 10)
    {
        if (empty($iPage)) {
            $iPage = 1;
        }
        $iCnt = db()->select('COUNT(*)')
            ->from(Phpfox::getT('user_import'), 'ui')
            ->join(Phpfox::getT('user'), 'u', 'u.user_id = ui.user_id')
            ->where($aConds)
            ->execute('getSlaveField');
        $aRows = [];
        if ($iCnt) {
            $aRows = db()->select('ui.*, ' . Phpfox::getUserField())
                ->from(Phpfox::getT('user_import'), 'ui')
                ->join(Phpfox::getT('user'), 'u', 'u.user_id = ui.user_id')
                ->where($aConds)
                ->order('ui.import_id DESC')
                ->limit($iPage, $iSize)
                ->execute('getSlaveRows');
        }

        return [$aRows, $iCnt];
    }

    /**
     * Stop import process
     * @param null $iImportId
     * @return bool
     */
    public function deleteProcessingImport($iImportId = null)
    {
        if (empty($iImportId)) {
            return false;
        }
        $aImport = $this->getImport($iImportId);
        if (empty($aImport)) {
            return false;
        }
        $aProcessJobIds = explode(',', $aImport['processing_job_id']);
        foreach ($aProcessJobIds as $iJobId) {
            Phpfox_Queue::instance()->deleteJob($iJobId);
        }
        db()->update(Phpfox::getT('user_import'), ['status' => 'stopped', 'processing_job_id' => ''], 'import_id = ' . (int)$iImportId);
        return true;
    }

    /**
     * Create array custom field text for title
     * @param bool $bGetOnlyPhraseText
     * @return array
     */
    public function createCustomFieldText($bGetOnlyPhraseText = false)
    {
        static $aCustomFields = [];
        static $aCustomFieldText = [];
        static $aCustomFieldPhraseText = [];
        if (empty($aCustomFields) && empty($aCustomFieldText)) {
            if (Phpfox::isModule('custom')) {
                $aCustomGroups = Phpfox::getService('custom')->getForListing();
                $aCustomFields = [];

                if (!empty($aCustomGroups)) {
                    foreach ($aCustomGroups as $aCustomGroup) {
                        foreach ($aCustomGroup['child'] as $aCustomField) {
                            $aCustomField['phrase_var_name_parsed'] = \Core\Lib::phrase()->isPhrase($aCustomField['phrase_var_name']) ? _p($aCustomField['phrase_var_name']) : $aCustomField['phrase_var_name'];
                            $aCustomFields['cf_' . $aCustomField['field_name']] = $aCustomField;
                        }
                    }
                }

                if (!empty($aCustomFields)) {
                    $aCustomFieldText = array_keys($aCustomFields);
                    if ($bGetOnlyPhraseText) {
                        $aCustomFieldPhraseText = array_combine(array_keys($aCustomFields), array_column($aCustomFields, 'phrase_var_name_parsed'));
                    }
                }
            }
        }

        return $bGetOnlyPhraseText ? (!empty($aCustomFieldPhraseText) ? $aCustomFieldPhraseText : []) : [$aCustomFields, $aCustomFieldText];
    }

    /**
     * Validate required fields( user_name, full_name, email )
     * @param $sType
     * @param $sValue
     * @return array
     */
    private function _validateRequiredField($sType, $sValue)
    {
        $aError = [];
        switch ($sType) {
            case 'email':
                {
                    $iCnt = $this->database()->select('COUNT(*)')
                        ->from(Phpfox::getT('user'))
                        ->where("email = '" . db()->escape($sValue) . "'")
                        ->execute('getSlaveField');
                    if ($iCnt) {
                        $aError['import_email_is_in_use'] = [
                            'email' => $sValue
                        ];
                    }

                    if (!Phpfox::getService('ban')->check('email', $sValue)) {
                        $aError['this_email_is_not_allowed_to_be_used'] = [];
                    }
                    break;
                }
            case 'user_name':
                {
                    $bIsInvalidUserName = false;
                    if (strpos($sValue, 'profile-') !== 0 && (Phpfox::getLib('parse.input')->allowTitle($sValue, _p('user_name_is_already_in_use'))) !== true) {
                        $aError['user_name_is_already_in_use'] = [];
                        Phpfox_Error::reset();
                    }

                    if (!Phpfox::getService('ban')->check('username', $sValue)) {
                        $aError['invalid_user_name'] = [];
                        $bIsInvalidUserName = true;
                    }

                    if (strpos($sValue, 'profile-') !== 0 && (Phpfox::getParam('user.disable_username_on_sign_up') != 'full_name') && !$bIsInvalidUserName) {
                        $sUser = Phpfox::getLib('parse.input')->clean($sValue);
                        /* Check if there is a page with the same url as the user name*/
                        $aPages = Phpfox::getService('page')->get();
                        foreach ($aPages as $aPage) {
                            if ($aPage['title_url'] == strtolower($sUser)) {
                                $aError['invalid_user_name'] = [];
                                break;
                            }
                        }
                    }
                    break;
                }
            case 'full_name':
                {
                    if (Phpfox::getLib('parse.format')->isEmpty($sValue)) {
                        $aError['import_user_check_field_null'] = [
                            'field' => 'full_name'
                        ];
                        break;
                    }


                    if (!Phpfox_Validator::instance()->check($sValue, ['html', 'url']) || ($sValue == '&#173;')) {
                        $aError['not_a_valid_name'] = [];
                    }
                    if (!Phpfox_Validator::instance()->check($sValue, ['special_characters'])) {
                        $aError['this_profile_name_has_certain_characters_the_arent_allowed'] = [];
                    }
                    if (Phpfox::getParam('user.maximum_length_for_full_name') > 0 && mb_strlen($sValue) > Phpfox::getParam('user.maximum_length_for_full_name')) {
                        $aChange = ['iMax' => Phpfox::getParam('user.maximum_length_for_full_name')];
                        $aError[Phpfox::getParam('user.display_or_full_name') == 'full_name' ? 'please_shorten_full_name' : 'please_shorten_display_name'] = $aChange;
                    }

                    if (!Phpfox::getService('ban')->check('display_name', $sValue)) {
                        $aError['this_display_name_is_not_allowed_to_be_used'] = [];
                    }
                    break;
                }
        }
        return $aError;
    }

    /**
     * Validate required fields for uploading csv file
     * @param $aVals
     * @return array
     */
    private function _checkUploadedRequiredFieldData($aVals)
    {
        $aRequiredFields = ['user_name', 'full_name', 'email'];
        $aError = [];
        foreach ($aRequiredFields as $sField) {
            if (empty($aVals[$sField])) {
                $aError[$sField]['error']['import_user_check_field_null'] = [
                    'field' => 'full_name'
                ];
            } else {
                if ($sField == 'full_name') {
                    if (!is_string($aVals[$sField]) || (is_string($aVals[$sField]) && Phpfox::getLib('parse.format')->isEmpty($aVals[$sField]))) {
                        $aError[$sField]['error']['field_is_invalid_to_import'] = [
                            'field' => 'full_name'
                        ];
                    }
                } elseif ($sField == 'user_name') {
                    $aPattern = [
                        'pattern' => '/^[a-zA-Z][a-zA-Z0-9\-_]{' . (Phpfox::getParam('user.min_length_for_username') - 1) . ',' . (Phpfox::getParam('user.max_length_for_username') - 1) . '}$/',
                    ];

                    if (!preg_match($aPattern['pattern'], $aVals[$sField])) {
                        $aError[$sField]['error']['provide_a_valid_user_name'] = [
                            'min' => Phpfox::getParam('user.min_length_for_username'),
                            'max' => Phpfox::getParam('user.max_length_for_username')
                        ];
                    }
                } elseif ($sField == 'email') {
                    if (function_exists('filter_var') && !empty($aVals[$sField]) && !filter_var($aVals[$sField], FILTER_VALIDATE_EMAIL)) {
                        $aError[$sField]['error']['provide_a_valid_email_address'] = [];
                    } else {
                        $aPattern = [
                            'pattern' => '/^[0-9a-zA-Z]([\-+.\w]*[0-9a-zA-Z]?)*@([0-9a-zA-Z\-.\w]*[0-9a-zA-Z]\.)+[a-zA-Z]{2,}$/',
                            'max_len' => 100
                        ];
                        if (!preg_match($aPattern['pattern'], $aVals[$sField])) {
                            $aError[$sField]['error']['provide_a_valid_email_address'] = [];
                        } elseif (strlen($aVals[$sField]) > $aPattern['max_len']) {
                            $aError[$sField]['error']['provide_a_valid_email_address'] = [];
                        }
                    }
                }
            }
        }
        return $aError;
    }

    /**
     * Update import
     * @param $iImportId
     * @param array $aUpdate
     * @return bool|resource
     */
    public function updateUserImport($iImportId, $aUpdate = [])
    {
        return db()->update(Phpfox::getT('user_import'), $aUpdate, 'import_id = ' . (int)$iImportId);
    }

    /**
     * Add import
     * @param $aInsert
     * @return int
     */
    public function addUserImport($aInsert)
    {
        return db()->insert(Phpfox::getT('user_import'), $aInsert);
    }
}