<?php
defined('PHPFOX') or exit('NO DICE!');

/**
 * Class User_Component_Block_Tooltip
 */
class User_Component_Block_Tooltip extends Phpfox_Component
{
    /**
     * Controller
     */
    public function process()
    {
        $oUser = Phpfox::getService('user');

        $userName = $this->request()->get('user_name');
        $aUser = $oUser->getByUserName($userName);
        if (!$aUser) {
            $aUser = $oUser->getByUserId($userName);
        }

        if (!$aUser) {
            return false;
        }

        $bIsPage = ($aUser['profile_page_id'] > 0 ? true : false);
        if ($bIsPage) {
            $pageId = $aUser['profile_page_id'];
            $pageType = $oUser->getPageType($pageId);
            if ($pageType == 0 && Phpfox::isAppActive('Core_Pages')) { // is Page
                $aUser['page'] = Phpfox::getService('pages')->getPage($pageId);
            } else if ($pageType == 1 && Phpfox::isAppActive('PHPfox_Groups')) { // is Group
                $aUser['page'] = Phpfox::getService('groups')->getPage($pageId);
            }
        }
        if ($bIsPage && isset($aUser['page'])) {
            $aUser['cover_photo'] = $aUser['page']['cover_photo_id'];
        }

        $aUser['birthday_time_stamp'] = $aUser['birthday'];
        $aUser['birthday'] = $oUser->age($aUser['birthday']);
        $aUser['location'] = $aUser['gender_name'] = $aUser['birthdate_display'] = $aUser['relationship'] = '';
        $iMaxInfo = 2;
        if (Phpfox::getService('user.privacy')->hasAccess($aUser['user_id'], 'profile.view_location') && $sLocation = Phpfox::getPhraseT(Phpfox::getService('core.country')->getCountry($aUser['country_iso']), 'country')) {
            if (!empty($aUser['city_location'])) {
                $aUser['location'] = $aUser['city_location'] . ', ' . $sLocation;
            } else {
                $aUser['location'] = $sLocation;
            }
            $iMaxInfo--;
        }
        if ($iMaxInfo && $sGender = $oUser->gender($aUser['gender'])) {
            $aUser['gender_name'] = $sGender;
            $iMaxInfo--;
        }
        if ($iMaxInfo && $sBirthdayDisplay = $oUser->getProfileBirthDate($aUser)) {
            $aUser['birthdate_display'] = $sBirthdayDisplay;
            $iMaxInfo--;
        }
        if ($iMaxInfo && $sRelationship = Phpfox::getService('custom')->getRelationshipPhrase($aUser)) {
            $aUser['relationship'] = $sRelationship;
            $iMaxInfo--;
        }
        if (!$iMaxInfo) {
            unset($aUser['joined']);
        }

        if (isset($aUser['country_child_id']) && $aUser['country_child_id'] > 0) {
            $aUser['location_child'] = Phpfox::getService('core.country')->getChild($aUser['country_child_id']);
        }

        $aCoverPhoto = Phpfox::getService('photo')->getCoverPhoto($aUser['cover_photo']);
        if (!empty($aCoverPhoto)) {
            $aUser['cover_photo_link'] = Phpfox::getLib('image.helper')->display([
                'server_id'  => $aCoverPhoto['server_id'],
                'path'       => 'photo.url_photo',
                'file'       => $aCoverPhoto['destination'],
                'suffix'     => '_500',
                'return_url' => true
            ]);
        } else {
            $aUser['cover_photo_link'] = flavor()->active->default_photo('user_cover_default', true);
        }

        $aUser['bRelationshipHeader'] = true;
        $aUser['is_friend'] = false;
        $iTotal = 0;
        $aMutual = [];
        if (!$bIsPage && $aUser['user_id'] != Phpfox::getUserId() && Phpfox::isModule('friend')) {
            if (Phpfox::isUser()) {
                $aUser['is_friend'] = Phpfox::getService('friend')->isFriend(Phpfox::getUserId(), $aUser['user_id']);
                if (!$aUser['is_friend']) {
                    $aUser['is_friend'] = (Phpfox::getService('friend.request')->isRequested(Phpfox::getUserId(), $aUser['user_id']) ? 2 : false);
                }
            }

            list($iTotal, $aMutual) = Phpfox::getService('friend')->getMutualFriends($aUser['user_id'], 4);
        }

        $bShowBDayInput = false;
        if (!empty($aUser['birthday_time_stamp'])) {
            $iDays = Phpfox::getLib('date')->daysToDate($aUser['birthday_time_stamp'], null, false);
        } else {
            $iDays = 999;
        }

        if ($iDays < 1 && $iDays > 0) {
            $bShowBDayInput = true;
        }

        if (empty($aUser['dob_setting'])) {
            switch (Phpfox::getParam('user.default_privacy_brithdate')) {
                case 'month_day':
                    $aUser['dob_setting'] = '1';
                    break;
                case 'show_age':
                    $aUser['dob_setting'] = '2';
                    break;
                case 'hide':
                    $aUser['dob_setting'] = '3';
                    break;
            }
        }
        $bLoginAsPage = (Phpfox::getUserBy('profile_page_id') > 0 ? true : false);

        $userId = $aUser['user_id'];
        $isFriend = isset($aUser['is_friend']) && $aUser['is_friend'] === true;
        $bCanSendMessage = Phpfox::getService('user')->canSendMessage($userId, $isFriend);

        (($sPlugin = Phpfox_Plugin::get('user.component_block_tooltip_1')) ? eval($sPlugin) : false);

        $this->template()->assign([
                'bIsPage'         => $bIsPage,
                'bCanSendMessage' => $bCanSendMessage,
                'bLoginAsPage'    => $bLoginAsPage,
                'aUser'           => $aUser,
                'iMutualTotal'    => $iTotal,
                'aMutualFriends'  => $aMutual,
                'bShowBDay'       => $bShowBDayInput,
                'iRemainFriends'  => $iTotal - count($aMutual),
                'iInfoCount'      => !$iMaxInfo
            ]
        );
    }

    /**
     * Garbage collector. Is executed after this class has completed
     * its job and the template has also been displayed.
     */
    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('user.component_block_tooltip_clean')) ? eval($sPlugin) : false);
    }
}
