<?php
defined('PHPFOX') or exit('NO DICE!');

/**
 * Class User_Service_Process
 */
class User_Service_Process extends Phpfox_Service
{
    private $_iStatusId = 0;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->_sTable = Phpfox::getT('user');
    }

    /**
     * Clear friend cache of current user's friends to apply new full name
     *
     * @param $userId
     */
    public function clearFriendCacheOfFriends($userId = null)
    {
        if (Phpfox::isModule('friend')) {
            $aFriends = Phpfox::getService('friend')->getFromCache(false, false, false, $userId);
            foreach ($aFriends as $aFriend) {
                $this->cache()->remove('friend_build_cache_data_' . $aFriend['user_id']);
            }
        }
    }

    /**
     * Import user after validating successfully
     *
     * @param      $aVals
     * @param null $iUserGroupId
     *
     * @return bool|int
     */
    public function importUser($aVals, $iUserGroupId = null)
    {
        $oParseInput = Phpfox::getLib('parse.input');
        $sSalt = $this->_getSalt();
        $aCustom = $aVals['custom'];
        // The visitor's current language is...
        $sLangId = Phpfox_Locale::instance()->getLangId();

        $password = (new Core\Hash())->make($aVals['password']);

        $aInsert = [
            'user_group_id'   => ($iUserGroupId === null ? NORMAL_USER_ID : $iUserGroupId),
            'full_name'       => $oParseInput->clean($aVals['full_name'], 255),
            'password'        => $password,
            'password_salt'   => $sSalt,
            'email'           => $aVals['email'],
            'joined'          => PHPFOX_TIME,
            'gender'          => is_array($aVals['gender']) ? 127 : (!empty($aVals['gender']) ? $aVals['gender'] : 0),
            'birthday'        => null,
            'birthday_search' => 0,
            'country_iso'     => !empty($aVals['country_iso']) ? $aVals['country_iso'] : null,
            'language_id'     => $sLangId,
            'time_zone'       => null,
            'last_ip_address' => Phpfox::getIp(),
            'last_activity'   => PHPFOX_TIME,
            'feed_sort'       => (Phpfox::getParam('feed.default_sort_criterion_feed') == 'top_stories') ? 0 : 1,
            'custom_gender'   => is_array($aVals['gender']) ? serialize($aVals['gender']) : null
        ];
        $bIsGenerateUsername = true;
        if (strpos($aVals['user_name'], 'profile-') !== false) {
            $aInsert['user_name'] = $oParseInput->cleanTitle($oParseInput->clean($aVals['user_name']));
            $bIsGenerateUsername = false;
        }

        $iId = $this->database()->insert($this->_sTable, $aInsert);

        if ($bIsGenerateUsername) {
            db()->update($this->_sTable, ['user_name' => 'profile-' . $iId], 'user_id = ' . (int)$iId);
        }

        $aInsert['user_id'] = $iId;
        $aExtras = [
            'user_id' => $iId
        ];

        $this->database()->insert(Phpfox::getT('user_activity'), $aExtras);
        $this->database()->insert(Phpfox::getT('user_field'), (!empty($aVals['postal_code']) ? array_merge($aExtras, ['postal_code' => $aVals['postal_code']]) : $aExtras));
        $this->database()->insert(Phpfox::getT('user_space'), $aExtras);
        $this->database()->insert(Phpfox::getT('user_count'), $aExtras);

        if (!defined('PHPFOX_INSTALLER') && isset($aVals['city_location'])) {
            Phpfox::getService('user.field.process')->update($iId, 'city_location', Phpfox::getLib('parse.input')->clean($aVals['city_location'], 100));
        }

        if (isset($aVals['country_child_id'])) {
            Phpfox::getService('user.field.process')->update($iId, 'country_child_id', $aVals['country_child_id']);
        }

        if (!defined('PHPFOX_INSTALLER')) {
            $iFriendId = (int)Phpfox::getParam('user.on_signup_new_friend');
            if ($iFriendId > 0 && Phpfox::isModule('friend')) {
                $iCheckFriend = $this->database()->select('COUNT(*)')
                    ->from(Phpfox::getT('friend'))
                    ->where('user_id = ' . (int)$iId . ' AND friend_user_id = ' . (int)$iFriendId)
                    ->execute('getSlaveField');

                if (!$iCheckFriend) {
                    $this->database()->insert(Phpfox::getT('friend'), [
                            'list_id'        => 0,
                            'user_id'        => $iId,
                            'friend_user_id' => $iFriendId,
                            'time_stamp'     => PHPFOX_TIME
                        ]
                    );

                    $this->database()->insert(Phpfox::getT('friend'), [
                            'list_id'        => 0,
                            'user_id'        => $iFriendId,
                            'friend_user_id' => $iId,
                            'time_stamp'     => PHPFOX_TIME
                        ]
                    );

                    Phpfox::getService('friend.process')->updateFriendCount($iId, $iFriendId);
                    Phpfox::getService('friend.process')->updateFriendCount($iFriendId, $iId);
                }
            }

            switch (Phpfox::getParam('user.on_register_privacy_setting')) {
                case 'network':
                    $iPrivacySetting = '1';
                    break;
                case 'friends_only':
                    $iPrivacySetting = '2';
                    break;
                case 'no_one':
                    $iPrivacySetting = '4';
                    break;
                default:
                    break;
            }

            if (isset($iPrivacySetting)) {
                $aPrivacy = [
                    'feed.view_wall',
                    'friend.view_friend',
                    'photo.display_on_profile',
                    'profile.view_profile',
                    'profile.profile_info',
                    'rss.display_on_profile',
                    'track.display_on_profile',
                    'feed.share_on_wall',
                    'mail.send_message',
                    'poke.can_send_poke',
                    'profile.basic_info',
                    'profile.view_location',
                    'rss.can_subscribe_profile',
                    'user.can_i_be_tagged'
                ];
                foreach ($aPrivacy as $sPrivacy) {
                    $a = explode('.', $sPrivacy);
                    if (!isset($a[0]) || !Phpfox::isModule($a[0])) {
                        continue;
                    }
                    $this->database()->insert(Phpfox::getT('user_privacy'), [
                            'user_id'      => $iId,
                            'user_privacy' => $sPrivacy,
                            'user_value'   => $iPrivacySetting
                        ]
                    );
                }
            }
        }

        if (!empty($aCustom)) {
            if (!Phpfox::getService('custom.process')->updateFields($iId, $iId, $aCustom, true)) {
                return false;
            }
        }

        $this->database()->insert(Phpfox::getT('user_ip'), [
                'user_id'    => $iId,
                'type_id'    => 'register',
                'ip_address' => Phpfox::getIp(),
                'time_stamp' => PHPFOX_TIME
            ]
        );

        if (Phpfox::isAppActive('Core_Activity_Points')) {
            Phpfox::getService('activitypoint.process')->updatePoints($iId, 'user_signup');
        }

        //Send mail to imported user
        Phpfox::getLib('mail')
            ->to($iId)
            ->subject(['import_user_send_mail_title'])
            ->message([
                    'import_user_send_mail_content', [
                        'link'      => Phpfox::getParam('core.path'),
                        'user_name' => $aInsert['user_name'],
                        'email'     => $aInsert['email'],
                        'password'  => $aVals['password']
                    ]
                ]
            )
            ->send();

        return $iId;
    }

    public function saveData($aSaveData, $iUserId = null)
    {
        if ($iUserId === null) {
            if (!Phpfox::isUser()) {
                return;
            }

            $iUserId = Phpfox::getUserId();
        }

        $sCacheId = $this->cache()->set(['userdata', $iUserId]);

        $aCachedInfo = (array)$this->cache()->get($sCacheId);
        $aKey = array_keys($aSaveData);
        if (isset($aCachedInfo[$aKey[0]])) {
            unset($aCachedInfo[$aKey[0]]);
        }

        $this->cache()->save($sCacheId, $aSaveData);
        Phpfox::getLib('cache')->group('user', $sCacheId);
    }

    public function saveUserCache($iUserId)
    {
        $aUser = $this->database()->select('*')
            ->from(Phpfox::getT('user'))
            ->where('user_id = ' . (int)$iUserId)
            ->execute('getSlaveRow');

        if (isset($aUser['user_id'])) {
            $sCacheId = $this->cache()->set(['userjoin', $iUserId]);
            $this->cache()->save($sCacheId, [
                    'user_name'  => $aUser['user_name'],
                    'full_name'  => $aUser['full_name'],
                    'user_image' => $aUser['user_image'],
                    'server_id'  => $aUser['server_id']
                ]
            );
        }

    }

    public function updateFeedSort($sOrder)
    {
        $this->database()->update(Phpfox::getT('user'), ['feed_sort' => (int)$sOrder], 'user_id = ' . (int)Phpfox::getUserId());

        return true;
    }

    public function addPointsPurchase($iTotal, $iTotalUpgrade)
    {
        $iId = $this->database()->insert(Phpfox::getT('point_purchase'), [
                'user_id'     => Phpfox::getUserId(),
                'currency_id' => Phpfox::getService('core.currency')->getDefault(),
                'price'       => $iTotal,
                'status'      => '0',
                'time_stamp'  => PHPFOX_TIME,
                'total_point' => $iTotalUpgrade
            ]
        );

        return $iId;
    }

    public function removeLogo($iUserId = null)
    {
        if ($iUserId === null) {
            $iUserId = Phpfox::getUserId();
        }

        $this->database()->update(Phpfox::getT('user_field'), ['cover_photo' => '0', 'cover_photo_top' => null], 'user_id = ' . (int)$iUserId);
        return true;
    }

    public function removeProfilePic($iId)
    {
        $oFile = Phpfox_File::instance();

        $sUserImage = $this->database()->select('user_image')
            ->from(Phpfox::getT('user'))
            ->where('user_id = ' . (int)$iId)
            ->execute('getSlaveField');

        if (!empty($sUserImage)) {
            if (file_exists(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, ''))) {
                $oFile->unlink(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, ''));
                foreach (Phpfox::getService('user')->getUserThumbnailSizes() as $iSize) {
                    if (file_exists(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, '_' . $iSize))) {
                        $oFile->unlink(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, '_' . $iSize));
                    }

                    if (file_exists(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, '_' . $iSize . '_square'))) {
                        $oFile->unlink(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, '_' . $iSize . '_square'));
                    }
                }
            }

            $this->database()->update($this->_sTable, ['user_image' => null, 'server_id' => 0], 'user_id = ' . (int)$iId);
            $this->saveUserCache($iId);
        }
    }

    public function updateCoverPosition($sPosition, $iUserId = null)
    {
        if ($iUserId === null) {
            $iUserId = Phpfox::getUserId();
        }

        $this->database()->update(Phpfox::getT('user_field'), ['cover_photo_top' => $sPosition], 'user_id = ' . (int)$iUserId);
        return true;
    }

    /**
     * @param int $iPhotoId
     * @param int $iUserId
     *
     * @return bool
     */
    public function updateCoverPhoto($iPhotoId, $iUserId = null)
    {
        if ($iUserId === null) {
            $iUserId = Phpfox::getUserId();
        }
        //Create cover photo albums
        $iCoverAlbumId = $this->database()->select('album_id')
            ->from(Phpfox::getT('photo_album'))
            ->where('cover_id=' . (int)$iUserId)
            ->execute('getSlaveField');
        //Create Album if photo not exist
        if (empty($iCoverAlbumId)) {
            $iCoverAlbumId = $this->database()->insert(Phpfox::getT('photo_album'), [
                'privacy'         => '0',
                'privacy_comment' => '0',
                'user_id'         => $iUserId,
                'name'            => "{_p var='cover_photo'}",
                'time_stamp'      => PHPFOX_TIME,
                'cover_id'        => $iUserId,
                'total_photo'     => 0
            ]);
            $this->database()->insert(Phpfox::getT('photo_album_info'), ['album_id' => $iCoverAlbumId]);
        }
        //Update albums Id
        $this->database()->update(Phpfox::getT('photo'), ['is_cover' => 0,], 'album_id=' . (int)$iCoverAlbumId);
        $this->database()->update(Phpfox::getT('photo'), [
            'album_id'         => $iCoverAlbumId,
            'is_cover'         => 1,
            'is_cover_photo'   => 1,
            'is_profile_photo' => 0,
            'view_id'          => 0
        ], 'photo_id=' . (int)$iPhotoId);

        storage()->del('user/cover/' . $iUserId);
        storage()->set('user/cover/' . $iUserId, $iPhotoId);

        Phpfox::getService('photo.album.process')->updateCounter((int)$iCoverAlbumId, 'total_photo');
        //And update photo cover albums
        $this->database()->update(Phpfox::getT('user_field'), ['cover_photo' => $iPhotoId, 'cover_photo_top' => null], 'user_id = ' . (int)$iUserId);
        (Phpfox::isModule('feed') ? Phpfox::getService('feed.process')->add('user_cover', $iPhotoId, 0, 0, 0, (int)$iUserId) : null);

        Phpfox::getService('user.activity')->update($iUserId, 'photo', '+', 1, false);
        if (Phpfox::isAppActive('Core_Activity_Points')) {
            Phpfox::getService('activitypoint.process')->updatePoints($iUserId, 'user_uploadcoverphoto');
        }

        return true;
    }

    public function add($aVals, $iUserGroupId = null)
    {
        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('user.disable_username_on_sign_up') != 'username' && Phpfox::getParam('user.split_full_name')) {
            if (empty($aVals['first_name']) || empty($aVals['last_name'])) {
                Phpfox_Error::set(_p('please_fill_in_both_your_first_and_last_name'));
            }
        }

        if (!defined('PHPFOX_INSTALLER') && !Phpfox::getParam('user.allow_user_registration')) {
            return Phpfox_Error::display(_p('user_registration_has_been_disabled'));
        }
        $oParseInput = Phpfox::getLib('parse.input');
        $sSalt = $this->_getSalt();
        $aCustom = Phpfox_Request::instance()->getArray('custom');

        (($sPlugin = Phpfox_Plugin::get('user.service_process_add_1')) ? eval($sPlugin) : false);

        $aVals['email'] = strtolower($aVals['email']);
        // ban check
        $oBan = Phpfox::getService('ban');
        if (!$oBan->check('email', $aVals['email'])) {
            Phpfox_Error::set(_p('global_ban_message'));
        }
        if (!$oBan->check('ip', Phpfox_Request::instance()->getIp())) {
            Phpfox_Error::set(_p('not_allowed_ip_address'));
        }

        $aBanned = Phpfox::getService('ban')->isUserBanned($aVals);

        if ($aBanned['is_banned']) {
            if (isset($aBanned['reason']) && !empty($aBanned['reason'])) {
                $aBanned['reason'] = str_replace('&#039;', "'", Phpfox::getLib('parse.output')->parse($aBanned['reason']));
                $sReason = Phpfox::getLib('parse.output')->cleanPhrases($aBanned['reason']);
                Phpfox_Error::set($sReason);
            } else {
                Phpfox_Error::set(_p('global_ban_message'));
            }
        }
        //End check ban
        $aCustomFields = Phpfox::getService('custom')->getForEdit(['user_main', 'user_panel', 'profile_panel'], null, null, true);
        foreach ($aCustomFields as $aCustomField) {
            if ($aCustomField['on_signup'] && $aCustomField['is_required'] && empty($aCustom[$aCustomField['field_id']])) {
                Phpfox_Error::set(_p('the_field_field_is_required', ['field' => _p($aCustomField['phrase_var_name'])]));
            }
        }

        // The visitor's current language is...
        $sLangId = Phpfox_Locale::instance()->getLangId();

        /* Check if there should be a spam question answered */
        $aSpamQuestions = $this->database()->select('*')->from(Phpfox::getT('user_spam'))->execute('getSlaveRows');
        if (!defined('PHPFOX_INSTALLER') && !defined('PHPFOX_IS_FB_USER') && !empty($aSpamQuestions) && (isset($aVals['spam']))) {
            foreach ($aVals['spam'] as $iQuestionId => $sAnswer) {
                $aDbQuestion = $this->database()->select('us.*')
                    ->from(Phpfox::getT('user_spam'), 'us')
                    ->where('us.question_id = ' . (int)$iQuestionId)
                    ->execute('getSlaveRow');

                if (!isset($aDbQuestion['answers_phrases']) || empty($aDbQuestion['answers_phrases'])) {
                    Phpfox_Error::set(_p('that_question_does_not_exist_all_hack_attempts_are_forbidden_and_logged'));
                    break;
                }
                // now to compare the answers
                $aAnswers = json_decode($aDbQuestion['answers_phrases']);
                $bValidAnswer = false;

                foreach ($aAnswers as $sDbAnswer) {
                    if (preg_match('/phrase var=&#039;([a-z\._0-9]+)/', $sDbAnswer, $aMatch)) {
                        $sDbAnswer = _p($aMatch[1], [], $sLangId);
                        $sDbAnswer = html_entity_decode($sDbAnswer, null, 'UTF-8');
                    }
                    if (strcmp($sAnswer, $sDbAnswer) == 0) {
                        $bValidAnswer = true;
                        break;
                    }
                }

                if ($bValidAnswer == false) {
                    Phpfox_Error::set(_p('wrong_answer'));
                    break;
                }
            }
        } else if (!defined('PHPFOX_INSTALLER') && !defined('PHPFOX_IS_FB_USER') && !empty($aSpamQuestions) && !isset($aVals['spam'])) {
            Phpfox_Error::set(_p('you_forgot_to_answer_the_captcha_questions'));
        }
        if (!Phpfox_Error::isPassed()) {
            return false;
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('user.split_full_name') && setting('user.disable_username_on_sign_up') != 'username') {
            $aVals['full_name'] = $aVals['first_name'] . ' ' . $aVals['last_name'];
        }

        if (empty($aVals['full_name']) && (Phpfox::getParam('user.disable_username_on_sign_up') == 'username')) {
            $aVals['full_name'] = $aVals['user_name'];
        }

        if (isset($aVals['full_name']) && Phpfox::getParam('user.validate_full_name')) {
            if (!Phpfox_Validator::instance()->check($aVals['full_name'], ['html', 'url'])) {
                return Phpfox_Error::set(_p('not_a_valid_name'));
            }
            if (!Phpfox_Validator::instance()->check($aVals['full_name'], ['special_characters'])) {
                Phpfox_Error::set(_p('this_profile_name_has_certain_characters_the_arent_allowed'));
            }
            if (Phpfox::getParam('user.maximum_length_for_full_name') > 0 && mb_strlen($aVals['full_name']) > Phpfox::getParam('user.maximum_length_for_full_name')) {
                $aChange = ['iMax' => Phpfox::getParam('user.maximum_length_for_full_name')];
                $sPhrase = Phpfox::getParam('user.display_or_full_name') == 'full_name' ? _p('please_shorten_full_name', $aChange) : _p('please_shorten_display_name', $aChange);
                return Phpfox_Error::set($sPhrase);
            }
        }

        if (!defined('PHPFOX_INSTALLER') && $aVals['full_name'] == '&#173;') {
            return Phpfox_Error::set(_p('not_a_valid_name'));
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('core.city_in_registration') && isset($aVals['city_location']) && !Phpfox_Validator::instance()->check($aVals['city_location'], ['html', 'url'])) {
            return Phpfox_Error::set(_p('not_a_valid_city'));
        }

        if (!defined('PHPFOX_INSTALLER') && !Phpfox::getService('ban')->check('display_name', $aVals['full_name'])) {
            Phpfox_Error::set(_p('this_display_name_is_not_allowed_to_be_used'));
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::isAppActive('Core_Subscriptions') && Phpfox::getParam('subscribe.enable_subscription_packages') && Phpfox::getParam('subscribe.subscribe_is_required_on_sign_up') && empty($aVals['package_id'])) {
            $aPackages = Phpfox::getService('subscribe')->getPackages(true);

            if (count($aPackages)) {
                return Phpfox_Error::set(_p('select_a_membership_package'));
            }
        }

        if (!defined('PHPFOX_INSTALLER')) {
            if (!defined('PHPFOX_SKIP_EMAIL_INSERT')) {
                if (!Phpfox::getLib('mail')->checkEmail($aVals['email'])) {
                    return Phpfox_Error::set(_p('email_is_not_valid'));
                }
            }

            if (Phpfox::getLib('parse.format')->isEmpty($aVals['full_name'])) {
                Phpfox_Error::set(_p('provide_a_name_that_is_not_representing_an_empty_name'));
            }
        }

        $bHasImage = false;
        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('user.force_user_to_upload_on_sign_up')) {
            if (Phpfox::getParam('user.verify_email_at_signup')) {
                define('PHPFOX_FORCE_PHOTO_VERIFY_EMAIL', true);
            }
            if (!isset($_FILES['image']['name']) || empty($_FILES['image']['name'])) {
                Phpfox_Error::set(_p('please_upload_an_image_for_your_profile'));
            } else {
                $aImage = Phpfox_File::instance()->load('image', ['jpg', 'gif', 'png'], (Phpfox::getUserParam('user.max_upload_size_profile_photo') === 0 ? null : (Phpfox::getUserParam('user.max_upload_size_profile_photo') / 1024)));

                if ($aImage !== false) {
                    $bHasImage = true;
                }
            }
        }
        $month = isset($aVals['month']) ? (int)$aVals['month'] : 0;
        $day = isset($aVals['day']) ? (int)$aVals['day'] : 0;
        $year = isset($aVals['year']) ? (int)$aVals['year'] : 0;
        if ($year && $month && $day && !checkdate($month, $day, $year)) {
            return Phpfox_Error::set(_p('Not a valid date'));
        }
        $password = (new Core\Hash())->make($aVals['password']);

        $sLangId = ((!defined('PHPFOX_INSTALLER') && $sLangId) ? $sLangId : null);
        $aInsert = [
            'user_group_id'   => ($iUserGroupId === null ? NORMAL_USER_ID : $iUserGroupId),
            'full_name'       => $oParseInput->clean($aVals['full_name'], 255),
            'password'        => $password,
            'password_salt'   => $sSalt,
            'email'           => $aVals['email'],
            'joined'          => PHPFOX_TIME,
            'gender'          => ((!defined('PHPFOX_INSTALLER') && Phpfox::getParam('core.registration_enable_gender')) ? $aVals['gender'] : 0),
            'birthday'        => ((!defined('PHPFOX_INSTALLER') && Phpfox::getParam('core.registration_enable_dob')) ? Phpfox::getService('user')->buildAge($aVals['day'], $aVals['month'], $aVals['year']) : null),
            'birthday_search' => ((!defined('PHPFOX_INSTALLER') && Phpfox::getParam('core.registration_enable_dob')) ? Phpfox::getLib('date')->mktime(0, 0, 0, $aVals['month'], $aVals['day'], $aVals['year']) : 0),
            'country_iso'     => ((!defined('PHPFOX_INSTALLER') && Phpfox::getParam('core.registration_enable_location')) ? $aVals['country_iso'] : null),
            'language_id'     => $sLangId,
            'time_zone'       => (isset($aVals['time_zone']) && (defined('PHPFOX_INSTALLER') || (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('core.registration_enable_timezone'))) ? $aVals['time_zone'] : null),
            'last_ip_address' => Phpfox::getIp(),
            'last_activity'   => PHPFOX_TIME,
            'feed_sort'       => (Phpfox::getParam('feed.default_sort_criterion_feed') == 'top_stories') ? 0 : 1
        ];

        // check custom gender
        if ($aInsert['gender'] == 'custom') {
            if (Phpfox::getUserParam('user.can_add_custom_gender')) {
                if (!empty($aVals['custom_gender'])) {
                    $countValid = 0;
                    foreach ($aVals['custom_gender'] as $custom_gender) {
                        $custom_gender = trim($custom_gender);
                        if (!empty($custom_gender)) {
                            $countValid++;
                        }
                    }

                    if ($countValid == count($aVals['custom_gender'])) {
                        $aInsert['gender'] = 127;
                        $aInsert['custom_gender'] = serialize($aVals['custom_gender']);
                    } else {
                        $aInsert['gender'] = 0;
                    }
                } else {
                    $aInsert['custom_gender'] = '';
                }
            } else {
                $aInsert['gender'] = 0;
                $aInsert['custom_gender'] = '';
            }
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('user.invite_only_community') && !Phpfox::getService('invite')->isValidInvite($aVals['email'])) {
            // the isValidInvite runs Phpfox_Error::set so we don't have to do it here
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('core.registration_sms_enable')) {
            $aInsert['status_id'] = 1;// 1 = need to verify account using sms
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('user.verify_email_at_signup')) {
            $aInsert['status_id'] = 1;// 1 = need to verify email
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('user.approve_users')) {
            $aInsert['view_id'] = '1';// 1 = need to approve the user
        }

        if (!Phpfox::getParam('user.profile_use_id') && (Phpfox::getParam('user.disable_username_on_sign_up') != 'full_name')) {
            $aInsert['user_name'] = $oParseInput->clean($aVals['user_name']);
        }

        (($sPlugin = Phpfox_Plugin::get('user.service_process_add_start')) ? eval($sPlugin) : false);

        if (!Phpfox_Error::isPassed()) {
            return false;
        }
        $iId = $this->database()->insert($this->_sTable, $aInsert);
        $aInsert['user_id'] = $iId;
        $aExtras = [
            'user_id' => $iId
        ];

        (($sPlugin = Phpfox_Plugin::get('user.service_process_add_extra')) ? eval($sPlugin) : false);

        $tables = [
            'user_activity',
            'user_field',
            'user_space',
            'user_count'
        ];
        foreach ($tables as $table) {
            $this->database()->insert(':' . $table, $aExtras);
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('core.city_in_registration') && isset($aVals['city_location'])) {
            Phpfox::getService('user.field.process')->update($iId, 'city_location', Phpfox::getLib('parse.input')->clean($aVals['city_location'], 100));
        }
        if (Phpfox::getParam('user.profile_use_id') || (Phpfox::getParam('user.disable_username_on_sign_up') == 'full_name')) {
            //Check duplicate user_name first
            $iCnt = $this->database()->select('count(*)')
                ->from(':user')
                ->where('user_name="profile-' . $iId . '"')
                ->executeField();

            if ($iCnt) {
                $this->database()->update($this->_sTable, ['user_name' => 'profile-' . uniqid()], 'user_id = ' . $iId);
            } else {
                $this->database()->update($this->_sTable, ['user_name' => 'profile-' . $iId], 'user_id = ' . $iId);
            }
        }

        if ($bHasImage) {
            $this->uploadImage($iId, true, null, true);
        }

        if (Phpfox::isModule('invite') && (Phpfox::getCookie('invited_by_email') || Phpfox::getCookie('invited_by_user'))) {
            Phpfox::getService('invite.process')->registerInvited($iId);
        } else if (Phpfox::isModule('invite')) {
            Phpfox::getService('invite.process')->registerByEmail($aInsert);
        }

        (($sPlugin = Phpfox_Plugin::get('user.service_process_add_feed')) ? eval($sPlugin) : false);

        if (isset($aVals['country_child_id'])) {
            Phpfox::getService('user.field.process')->update($iId, 'country_child_id', $aVals['country_child_id']);
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('user.split_full_name')) {
            Phpfox::getService('user.field.process')->update($iId, 'first_name', (empty($aVals['first_name']) ? null : $aVals['first_name']));
            Phpfox::getService('user.field.process')->update($iId, 'last_name', (empty($aVals['last_name']) ? null : $aVals['last_name']));
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('core.registration_enable_dob')) {
            // Updating for the birthday range
            $this->database()->update(Phpfox::getT('user_field'), ['birthday_range' => '\'' . Phpfox::getService('user')->buildAge($aVals['day'], $aVals['month']) . '\''], 'user_id = ' . $iId, false);
        }

        if (!defined('PHPFOX_INSTALLER')) {
            $iFriendId = (int)Phpfox::getParam('user.on_signup_new_friend');
            if ($iFriendId > 0 && Phpfox::isModule('friend')) {
                $iCheckFriend = $this->database()->select('COUNT(*)')
                    ->from(Phpfox::getT('friend'))
                    ->where('user_id = ' . (int)$iId . ' AND friend_user_id = ' . (int)$iFriendId)
                    ->execute('getSlaveField');

                if (!$iCheckFriend) {
                    $this->database()->insert(Phpfox::getT('friend'), [
                            'list_id'        => 0,
                            'user_id'        => $iId,
                            'friend_user_id' => $iFriendId,
                            'time_stamp'     => PHPFOX_TIME
                        ]
                    );

                    $this->database()->insert(Phpfox::getT('friend'), [
                            'list_id'        => 0,
                            'user_id'        => $iFriendId,
                            'friend_user_id' => $iId,
                            'time_stamp'     => PHPFOX_TIME
                        ]
                    );

                    Phpfox::getService('friend.process')->updateFriendCount($iId, $iFriendId);
                    Phpfox::getService('friend.process')->updateFriendCount($iFriendId, $iId);
                }
            }
            if ($sPlugin = Phpfox_Plugin::get('user.service_process_add_check_1')) {
                eval($sPlugin);
            }

            // Allow to send an email even if verify email is disabled
            if ((!defined('PHPFOX_INSTALLER') && Phpfox::getParam('user.verify_email_at_signup') == false && !isset($bDoNotSendWelcomeEmail)) || isset($bSendWelcomeEmailPlg)) {
                Phpfox::getLib('mail')
                    ->to($iId)
                    ->subject(['core.welcome_email_subject', ['site' => Phpfox::getParam('core.site_title')]])
                    ->message(['core.welcome_email_content'])
                    ->send();
            }

            switch (Phpfox::getParam('user.on_register_privacy_setting')) {
                case 'network':
                    $iPrivacySetting = '1';
                    break;
                case 'friends_only':
                    $iPrivacySetting = '2';
                    break;
                case 'no_one':
                    $iPrivacySetting = '4';
                    break;
                default:
                    break;
            }

            if (isset($iPrivacySetting)) {
                $aPrivacy = [
                    'feed.view_wall',
                    'friend.view_friend',
                    'photo.display_on_profile',
                    'profile.view_profile',
                    'profile.profile_info',
                    'rss.display_on_profile',
                    'track.display_on_profile',
                    'feed.share_on_wall',
                    'mail.send_message',
                    'poke.can_send_poke',
                    'profile.basic_info',
                    'profile.view_location',
                    'rss.can_subscribe_profile',
                    'user.can_i_be_tagged'
                ];
                foreach ($aPrivacy as $sPrivacy) {
                    $a = explode('.', $sPrivacy);
                    if (!isset($a[0]) || !Phpfox::isModule($a[0])) {
                        continue;
                    }
                    $this->database()->insert(Phpfox::getT('user_privacy'), [
                            'user_id'      => $iId,
                            'user_privacy' => $sPrivacy,
                            'user_value'   => $iPrivacySetting
                        ]
                    );
                }
            }
        }

        (($sPlugin = Phpfox_Plugin::get('user.service_process_add_end')) ? eval($sPlugin) : false);

        if (!empty($aCustom)) {
            if (!Phpfox::getService('custom.process')->updateFields($iId, $iId, $aCustom, true)) {
                return false;
            }
        }

        $this->database()->insert(Phpfox::getT('user_ip'), [
                'user_id'    => $iId,
                'type_id'    => 'register',
                'ip_address' => Phpfox::getIp(),
                'time_stamp' => PHPFOX_TIME
            ]
        );

        if (!defined('PHPFOX_INSTALLER') && Phpfox::getParam('core.registration_sms_enable')) {

            $aVals['user_id'] = $iId;
            $sHash = Phpfox::getLib('phpfox.verify')->generateOneTimeTokenToSMS();
            Phpfox::getLib('session')->set('sms_verify_email', $aVals['email']);

            $this->database()->insert(Phpfox::getT('user_verify'), ['user_id' => $iId, 'hash_code' => $sHash, 'time_stamp' => Phpfox::getTime(), 'email' => $aVals['email']]);
        }

        if (!defined('PHPFOX_INSTALLER') && !Phpfox::getParam('core.registration_sms_enable') && Phpfox::getParam('user.verify_email_at_signup') && !isset($bSkipVerifyEmail)) {
            $aVals['user_id'] = $iId;
            $sHash = Phpfox::getService('user.verify')->getVerifyHash($aVals);
            $this->database()->insert(Phpfox::getT('user_verify'), ['user_id' => $iId, 'hash_code' => $sHash, 'time_stamp' => Phpfox::getTime(), 'email' => $aVals['email']]);
            // send email
            $sLink = Phpfox_Url::instance()->makeUrl('user.verify', ['link' => $sHash]);
            Phpfox::getLib('mail')
                ->to($iId)
                ->subject(['user.please_verify_your_email_for_site_title', ['site_title' => Phpfox::getParam('core.site_title')]])
                ->message([
                        'user.you_registered_an_account_on_site_title_before_being_able_to_use_your_account_you_need_to_verify_that_this_is_your_email_address_by_clicking_here_a_href_link_link_a', [
                            'site_title' => Phpfox::getParam('core.site_title'),
                            'link'       => $sLink
                        ]
                    ]
                )
                ->send(false, true);
        }

        if (!defined('PHPFOX_INSTALLER') && Phpfox::isAppActive('Core_Subscriptions') && Phpfox::getParam('subscribe.enable_subscription_packages') && !empty($aVals['package_id'])) {
            $aPackage = Phpfox::getService('subscribe')->getPackage($aVals['package_id']);
            if (isset($aPackage['package_id'])) {
                $iPurchaseId = Phpfox::getService('subscribe.purchase.process')->add([
                    'package_id'  => $aPackage['package_id'],
                    'currency_id' => $aPackage['default_currency_id'],
                    'price'       => $aPackage['default_cost']
                ], $iId
                );

                $iDefaultCost = (int)str_replace('.', '', $aPackage['default_cost']);

                if ($iPurchaseId) {
                    if ($iDefaultCost > 0) {
                        define('PHPFOX_MUST_PAY_FIRST', $iPurchaseId);

                        Phpfox::getService('user.field.process')->update($iId, 'subscribe_id', $iPurchaseId);

                        return [Phpfox_Url::instance()->makeUrl('subscribe.register', ['id' => $iPurchaseId])];
                    } else {
                        Phpfox::getService('subscribe.purchase.process')->update($iPurchaseId, $aPackage['package_id'], 'completed', $iId, $aPackage['user_group_id'], $aPackage['fail_user_group']);
                    }
                } else {
                    return false;
                }
            }
        }

        if (Phpfox::isAppActive('Core_Activity_Points')) {
            Phpfox::getService('activitypoint.process')->updatePoints($iId, 'user_signup');
        }

        return $iId;
    }

    public function update($iUserId, $aVals, $aSpecial = [], $bIsAccount = false)
    {
        if (!defined('PHPFOX_IS_CUSTOM_FIELD_UPDATE') && setting('user.split_full_name') && empty($aSpecial['is_api'])) {
            if (empty($aVals['first_name']) || empty($aVals['last_name'])) {
                return Phpfox_Error::set(_p('please_fill_in_both_your_first_and_last_name'));
            }

            $aVals['full_name'] = $aVals['first_name'] . ' ' . $aVals['last_name'];
        }

        if (!empty($aVals['city_location'])) {
            if (!Phpfox_Validator::instance()->check($aVals['city_location'], ['html', 'url'])) {
                return Phpfox_Error::set(_p('not_a_valid_city'));
            }
        }

        if (isset($aVals['full_name']) && Phpfox::getParam('user.validate_full_name')) {
            if (!Phpfox_Validator::instance()->check($aVals['full_name'], ['html', 'url'])) {
                return Phpfox_Error::set(_p('not_a_valid_name'));
            }
            if (!Phpfox_Validator::instance()->check($aVals['full_name'], ['special_characters'])) {
                Phpfox_Error::set(_p('this_profile_name_has_certain_characters_the_arent_allowed'));
            }
            if (Phpfox::getParam('user.maximum_length_for_full_name') > 0 && mb_strlen($aVals['full_name']) > Phpfox::getParam('user.maximum_length_for_full_name')) {
                $aChange = ['iMax' => Phpfox::getParam('user.maximum_length_for_full_name')];
                $sPhrase = Phpfox::getParam('user.display_or_full_name') == 'full_name' ? _p('please_shorten_full_name', $aChange) : _p('please_shorten_display_name', $aChange);
                return Phpfox_Error::set($sPhrase);
            }
        }

        if (!defined('PHPFOX_INSTALLER') && isset($aVals['full_name']) && $aVals['full_name'] == '&#173;') {
            return Phpfox_Error::set(_p('not_a_valid_name'));
        }

        if (isset($aVals['relation']) && Phpfox::getUserParam('custom.can_have_relationship')
            && ((empty($aVals['previous_relation_type']) && empty($aVals['previous_relation_with'])) || $aVals['relation'] != $aVals['previous_relation_type'] || (isset($aVals['relation_with']) && $aVals['relation_with'] != $aVals['previous_relation_with']))
        ) {
            if (isset($_POST['null']) && empty($_POST['null'])) {
                $aVals['relation_with'] = null;
            }
            /* has the user defined another user to share this relationship with? */
            Phpfox::getService('custom.relation.process')->updateRelationship($aVals['relation'], isset($aVals['relation_with']) ? $aVals['relation_with'] : null);

        }
        $oParseInput = Phpfox::getLib('parse.input');
        $aInsert = [
            'dst_check'   => (isset($aVals['dst_check']) ? '1' : '0'),
            'language_id' => (isset($aVals['language_id']) ? $aVals['language_id'] : 0)
        ];

        $bHasCountryChildren = false;
        if (!$bIsAccount) {
            if (isset($aVals['country_iso'])) {
                $aInsert['country_iso'] = $aVals['country_iso'];
                $aCountryChildren = Phpfox::getService('core.country')->getChildren($aVals['country_iso']);
                $bHasCountryChildren = !empty($aCountryChildren);
            }

            if (Phpfox::getUserParam('user.can_edit_dob')) {
                if (isset($aVals['day']) && $aVals['day'] > 0) {
                    $aInsert['birthday_search'] = (isset($aVals['day']) && isset($aVals['month']) && isset($aVals['year']) ? Phpfox::getLib('date')->mktime(0, 0, 0, $aVals['month'], $aVals['day'], $aVals['year']) : 0);
                    if ($aInsert['birthday_search']) {
                        $aInsert['birthday'] = date('mdY', $aInsert['birthday_search']);
                    }
                } else {
                    $aInsert['birthday'] = null;
                    $aInsert['birthday_search'] = 0;
                }
            }

            // update gender
            if (Phpfox::getUserParam('user.can_edit_gender_setting') && isset($aVals['gender'])) {
                if ($aVals['gender'] == 'custom' && Phpfox::getUserParam('user.can_add_custom_gender')) {
                    if (!empty($aVals['custom_gender'])) {
                        $countValid = 0;
                        foreach ($aVals['custom_gender'] as $custom_gender) {
                            $custom_gender = trim($custom_gender);
                            if (!empty($custom_gender)) {
                                $countValid++;
                            }
                        }

                        if ($countValid == count($aVals['custom_gender'])) {
                            $aInsert['gender'] = 127;
                            $aInsert['custom_gender'] = serialize($aVals['custom_gender']);
                        } else {
                            $aInsert['gender'] = 0;
                        }
                    }
                } else {
                    $aInsert['gender'] = (int)$aVals['gender'];
                    $aInsert['custom_gender'] = '';
                }
            }
        }

        if (isset($aVals['time_zone'])) {
            $aInsert['time_zone'] = $aVals['time_zone'];
        }

        (($sPlugin = Phpfox_Plugin::get('user.service_process_update_start')) ? eval($sPlugin) : false);

        // updating user name
        if ((isset($aSpecial['changes_allowed']) && ($aSpecial['changes_allowed'] == 0 || $aSpecial['changes_allowed'] > $aSpecial['total_user_change']) && Phpfox::getUserParam('user.can_change_own_user_name') && !Phpfox::getParam('user.profile_use_id') && isset($aVals['old_user_name']) && $aVals['user_name'] != $aVals['old_user_name'])
            || \Core\Route\Controller::$isApi
        ) {
            Phpfox::getService('user.validate')->user($aVals['user_name']);

            $aValidation['user_name'] = [
                'def'   => 'username',
                'title' => _p('provide_a_valid_user_name', [
                    'min' => Phpfox::getParam('user.min_length_for_username'),
                    'max' => Phpfox::getParam('user.max_length_for_username')
                ])
            ];
            $oValid = Phpfox_Validator::instance()->set([
                'aParams' => $aValidation
            ]);

            if (!Phpfox_Error::isPassed() || !$oValid->isValid($aVals)) {
                return false;
            }

            $aInsert['user_name'] = $aVals['user_name'];
            $this->database()->updateCounter('user_field', 'total_user_change', 'user_id', $iUserId);
        }

        // updating the full name
        if ((isset($aSpecial['full_name_changes_allowed']) &&
                ($aSpecial['full_name_changes_allowed'] > $aSpecial['total_full_name_change'] ||
                    $aSpecial['full_name_changes_allowed'] == 0) &&
                Phpfox::getUserParam('user.can_change_own_full_name') &&
                ($aSpecial['current_full_name'] != $aVals['full_name'])) || \Core\Route\Controller::$isApi
        ) {
            if (Phpfox::getLib('parse.format')->isEmpty($aVals['full_name'])) {
                Phpfox_Error::set(_p('provide_a_name_that_is_not_representing_an_empty_name'));
            }

            list($bHasBanned, $aBanData) = Phpfox::getService('ban')->detailCheck('display_name', $aVals['full_name']);
            if ($bHasBanned) {
                if (isset($aBanData) && !empty($aBanData['user_groups_affected'])) {
                    $aUserAffected = unserialize($aBanData['user_groups_affected']);
                    //Ban User
                    if (is_array($aUserAffected) && in_array(Phpfox::getUserBy('user_group_id'), $aUserAffected)) {
                        Phpfox::getService('ban.process')->banUser(Phpfox::getUserId(), $aUserAffected['days_banned'],
                            $aBanData['return_user_group'], $aBanData['reason']);
                    }
                } else {
                    return Phpfox_Error::set(_p('not_a_valid_name'));
                }
            }

            if (!Phpfox_Error::isPassed()) {
                return false;
            }

            $aInsert['full_name'] = $oParseInput->clean($aVals['full_name'], 255);
            if (isset($aSpecial['full_name_changes_allowed']) && $aSpecial['full_name_changes_allowed'] > 0) {
                $this->database()->updateCounter('user_field', 'total_full_name_change', 'user_id', $iUserId);
            }
        }
        $sFullName = Phpfox::getUserBy('full_name');
        $this->database()->update($this->_sTable, $aInsert, 'user_id = ' . (int)$iUserId);
        $aInsert['prev_full_name'] = $sFullName;
        Phpfox::massCallback('onUserUpdate', $aInsert);
        $this->clearFriendCacheOfFriends();

        if ($sPlugin = Phpfox_Plugin::get('user.service_process_update_1')) {
            eval($sPlugin);
            if (isset($mPluginReturn)) {
                return $mPluginReturn;
            }
        }

        if (!$bIsAccount) {
            if ($bHasCountryChildren && isset($aVals['country_child_id'])) {
                Phpfox::getService('user.field.process')->update($iUserId, 'country_child_id', $aVals['country_child_id']);
            } else {
                Phpfox::getService('user.field.process')->update($iUserId, 'country_child_id', 0);
            }

            if (isset($aVals['city_location'])) {
                Phpfox::getService('user.field.process')->update($iUserId, 'city_location', (empty($aVals['city_location']) ? null : Phpfox::getLib('parse.input')->clean($aVals['city_location'], 100)));
            }

            if (isset($aVals['postal_code'])) {
                Phpfox::getService('user.field.process')->update($iUserId, 'postal_code', (empty($aVals['postal_code']) ? null : Phpfox::getLib('parse.input')->clean($aVals['postal_code'], 20)));
            }

            if (isset($aVals['signature'])) {
                Phpfox::getService('user.field.process')->update($iUserId, 'signature', (empty($aVals['signature']) ? null : Phpfox::getLib('parse.input')->prepare($aVals['signature'])));
                Phpfox::getService('user.field.process')->update($iUserId, 'signature_clean', (empty($aVals['signature']) ? null : Phpfox::getLib('parse.input')->clean($aVals['signature'])));
            }
        }

        if (isset($aVals['default_currency'])) {
            Phpfox::getService('user.field.process')->update($iUserId, 'default_currency', (empty($aVals['default_currency']) ? null : $aVals['default_currency']));
            $this->cache()->remove(['currency', $iUserId]);
        }

        if (Phpfox::getParam('user.split_full_name')) {
            Phpfox::getService('user.field.process')->update($iUserId, 'first_name', (empty($aVals['first_name']) ? null : $aVals['first_name']));
            Phpfox::getService('user.field.process')->update($iUserId, 'last_name', (empty($aVals['last_name']) ? null : $aVals['last_name']));
        }

        if (!$bIsAccount) {
            if (isset($aVals['day']) && isset($aVals['month'])) {
                $this->database()->update(Phpfox::getT('user_field'), ['birthday_range' => '\'' . Phpfox::getService('user')->buildAge($aVals['day'], $aVals['month']) . '\''], 'user_id = ' . $iUserId, false);
            }
        }

        if (isset($aVals['gateway_detail']) && is_array($aVals['gateway_detail'])) {
            $this->database()->delete(Phpfox::getT('user_gateway'), 'user_id = ' . (int)$iUserId);
            foreach ($aVals['gateway_detail'] as $sGateway => $mValue) {
                $this->database()->insert(Phpfox::getT('user_gateway'), [
                        'user_id'        => $iUserId,
                        'gateway_id'     => $sGateway,
                        'gateway_detail' => serialize($mValue)
                    ]
                );
            }
            $this->cache()->remove('api_gateway_user_' . (int)$iUserId);
        }

        $this->database()->insert(Phpfox::getT('user_ip'), [
                'user_id'    => $iUserId,
                'type_id'    => 'update_account',
                'ip_address' => Phpfox::getIp(),
                'time_stamp' => PHPFOX_TIME
            ]
        );

        (($sPlugin = Phpfox_Plugin::get('user.service_process_update_end')) ? eval($sPlugin) : false);

        return true;
    }

    public function updateSimple($iUserId, $aVals)
    {
        $aSql = [
            'gender'          => (isset($aVals['gender']) ? $aVals['gender'] : 0),
            'birthday'        => (isset($aVals['day']) ? Phpfox::getService('user')->buildAge($aVals['day'], $aVals['month'], $aVals['year']) : 0),
            'birthday_search' => (isset($aVals['day']) ? Phpfox::getLib('date')->mktime(0, 0, 0, $aVals['month'], $aVals['day'], $aVals['year']) : 0),
            'country_iso'     => $aVals['country_iso']
        ];

        if (!Phpfox::getUserParam('user.can_edit_dob')) {
            unset($aSql['birthday']);
            unset($aSql['birthday_search']);
        }
        $this->database()->update($this->_sTable, $aSql, 'user_id = ' . (int)$iUserId);
        if (isset($aVals['day'])) {
            $this->database()->update(Phpfox::getT('user_field'), ['birthday_range' => '\'' . Phpfox::getService('user')->buildAge($aVals['day'], $aVals['month']) . '\''], 'user_id = ' . $iUserId, false);
        }

        (($sPlugin = Phpfox_Plugin::get('user.service_process_updatesimple')) ? eval($sPlugin) : false);

        return true;
    }

    public function updateUserGroup($iUserId, $iUserGroupId)
    {
        $this->database()->update($this->_sTable, ['user_group_id' => (int)$iUserGroupId], 'user_id = ' . (int)$iUserId);

        (($sPlugin = Phpfox_Plugin::get('user.service_process_updateusergroup')) ? eval($sPlugin) : false);
    }

    /**
     * @param int    $iId
     * @param bool   $bForce
     * @param string $sPath Path to the photo that we will copy/resize
     * @param bool   $bNoCheck
     * @param int    $iPhotoId
     *
     * @return bool|array
     */
    public function uploadImage($iId, $bForce = true, $sPath = null, $bNoCheck = false, $iPhotoId = 0)
    {
        if ($iId != Phpfox::getUserId() && $sPath === null && $bNoCheck === false) {
            Phpfox::getUserParam('user.can_change_other_user_picture', true);
        }

        $oFile = Phpfox_File::instance();
        $oImage = Phpfox_Image::instance();

        if ($bForce) {
            $sUserImage = Phpfox::getUserBy('user_image');
            if ($iId != Phpfox::getUserId()) {
                $sUserImage = $this->database()->select('user_image')
                    ->from(Phpfox::getT('user'))
                    ->where('user_id = ' . (int)$iId)
                    ->execute('getSlaveField');
            }

            if (!empty($sUserImage)) {
                if (file_exists(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, ''))) {
                    $oFile->unlink(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, ''));
                    foreach (Phpfox::getService('user')->getUserThumbnailSizes() as $iSize) {
                        if (file_exists(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, '_' . $iSize))) {
                            $oFile->unlink(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, '_' . $iSize));
                        }

                        if (file_exists(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, '_' . $iSize . '_square'))) {
                            $oFile->unlink(Phpfox::getParam('core.dir_user') . sprintf($sUserImage, '_' . $iSize . '_square'));
                        }
                    }
                }
            }
        }
        (($sPlugin = Phpfox_Plugin::get('user.service_process_uploadimage')) ? eval($sPlugin) : false);

        if ($sPath === null) {
            $sFileName = $oFile->upload('image', Phpfox::getParam('core.dir_user'), $iId);
        } else {
            $sFileName = md5($iId . PHPFOX_TIME . uniqid()) . '%s.' . substr($sPath, -3);
            $sTo = Phpfox::getParam('core.dir_user') . sprintf($sFileName, '');

            if (file_exists($sTo)) {
                Phpfox::getService('user.space')->update(Phpfox::getUserId(), 'photo', filesize($sTo), '-');
                $oFile->unlink($sTo);
            }

            $mReturn = Phpfox_Request::instance()->send($sPath, [], 'GET');
            $hFile = @fopen($sTo, 'w');
            @fwrite($hFile, $mReturn);
            @fclose($hFile);

            if (filesize($sTo) > 0) {
                $bReturn = Phpfox::getLib('cdn')->put($sTo);
            } else {
                $oFile->unlink($sTo);
                $oFile->copy($sPath, $sTo);
                $bReturn = Phpfox::getLib('cdn')->put($sTo);
            }
        }

        $sTo = Phpfox::getParam('core.dir_user') . sprintf($sFileName, '');
        if (file_exists($sTo)) {
            Phpfox::getService('user.space')->update(Phpfox::getUserId(), 'photo', filesize($sTo));
        }

        $iServerId = Phpfox_Request::instance()->getServer('PHPFOX_SERVER_ID');
        if ($iServerId != 1) {
            Phpfox::getService('profile.process')->saveTempFileToLocalServer($sTo);
        }

        if ($bForce) {
            foreach (Phpfox::getService('user')->getUserThumbnailSizes() as $iSize) {
                if (Phpfox::getParam('core.keep_non_square_images')) {
                    $oImage->createThumbnail(Phpfox::getParam('core.dir_user') . sprintf($sFileName, ''), Phpfox::getParam('core.dir_user') . sprintf($sFileName, '_' . $iSize), $iSize, $iSize);
                }

                $oImage->createThumbnail(Phpfox::getParam('core.dir_user') . sprintf($sFileName, ''), Phpfox::getParam('core.dir_user') . sprintf($sFileName, '_' . $iSize . '_square'), $iSize, $iSize, false);
            }
            if (Phpfox::isAppActive('Core_Photos')) {
                $iMaxWidth = (int)Phpfox::getUserParam('photo.maximum_image_width_keeps_in_server');
                list($width, $height, $type, $attr) = getimagesize(Phpfox::getParam('core.dir_user') . sprintf($sFileName, ''));
                if ($iMaxWidth < $width) {
                    $oImage->createThumbnail(Phpfox::getParam('core.dir_user') . sprintf($sFileName, ''), Phpfox::getParam('core.dir_user') . sprintf($sFileName, ''), $iMaxWidth, $height);
                }
            }
            $this->database()->update($this->_sTable, ['user_image' => $sFileName, 'server_id' => $iServerId], 'user_id = ' . (int)$iId);

            $aAlbum = [];
            if (Phpfox::isAppActive('Core_Photos')) {
                $aAlbum = Phpfox::getService('photo.album')->getForProfileView($iId, true);
            }

            if (!Phpfox::getUserBy('profile_page_id') && !defined('PHPFOX_PAGES_IS_IN_UPDATE') && $iId == Phpfox::getUserId()) {
                if (isset($aAlbum['photo_id']) && !empty($aAlbum['photo_id'])) {
                    (Phpfox::isModule('feed') ? Phpfox::getService('feed.process')->add('user_photo', $aAlbum['photo_id']) : null);
                } else if ($iPhotoId != 0) {
                    (Phpfox::isModule('feed') ? Phpfox::getService('feed.process')->add('user_photo', $iPhotoId) : null);
                }
            }

            if (isset($aAlbum['album_id'])) {
                Phpfox::getService('photo.album.process')->updateCounter($aAlbum['album_id'], 'total_photo');
            }

            $this->saveUserCache($iId);

            return ['user_image' => $sFileName, 'server_id' => $iServerId];
        }

        $this->saveUserCache($iId);

        return ['user_image' => $sFileName];
    }

    public function updateStatus($aVals)
    {
        if (isset($aVals['feed_id']) && $aVals['feed_id']) {
            return $this->editStatus($aVals['feed_id'], $aVals);
        }

        if (Phpfox::getLib('parse.format')->isEmpty($aVals['user_status'])) {
            if (empty($aVals['no_check_empty_user_status'])) {
                return Phpfox_Error::set(_p('add_some_text_to_share'));
            }
        }

        if (!Phpfox::getService('ban')->checkAutomaticBan($aVals['user_status'])) {
            return false;
        }

        $sStatus = $this->preParse()->prepare($aVals['user_status']);
        //Don't check spam if share item
        if (!defined('PHPFOX_INSTALLER') && empty($aVals['no_check_empty_user_status'])) {
            $aUpdates = $this->database()->select('content')
                ->from(Phpfox::getT('user_status'))
                ->where('user_id = ' . (int)Phpfox::getUserId())
                ->limit(Phpfox::getParam('user.check_status_updates'))
                ->order('time_stamp DESC')
                ->execute('getSlaveRows');

            $iReplications = 0;
            foreach ($aUpdates as $aUpdate) {
                if ($aUpdate['content'] == $sStatus) {
                    $iReplications++;
                }
            }
            if ($iReplications > 0) {
                return Phpfox_Error::set(_p('you_have_already_added_this_recently_try_adding_something_else'));
            }
        }

        if (empty($aVals['privacy'])) {
            $aVals['privacy'] = 0;
        }

        if (empty($aVals['privacy_comment'])) {
            $aVals['privacy_comment'] = 0;
        }

        $iUserId = Phpfox::getUserId();
        $aInsert = [
            'user_id'         => $iUserId,
            'privacy'         => $aVals['privacy'],
            'privacy_comment' => $aVals['privacy_comment'],
            'content'         => $sStatus,
            'time_stamp'      => PHPFOX_TIME
        ];

        if (isset($aVals['location']) && isset($aVals['location']['latlng']) && !empty($aVals['location']['latlng'])) {
            $aMatch = explode(',', $aVals['location']['latlng']);
            $aMatch['latitude'] = floatval($aMatch[0]);
            $aMatch['longitude'] = floatval($aMatch[1]);
            $aInsert['location_latlng'] = json_encode([
                'latitude'  => $aMatch['latitude'],
                'longitude' => $aMatch['longitude']
            ]);
        }

        if (isset($aInsert['location_latlng']) && !empty($aInsert['location_latlng']) && isset($aVals['location']) && isset($aVals['location']['name']) && !empty($aVals['location']['name'])) {
            $aInsert['location_name'] = Phpfox::getLib('parse.input')->clean($aVals['location']['name']);
        }
        $iStatusId = $this->database()->insert(Phpfox::getT('user_status'), $aInsert);
        $this->_iStatusId = $iStatusId;

        if (isset($aVals['privacy']) && $aVals['privacy'] == '4') {
            Phpfox::getService('privacy.process')->add('user_status', $iStatusId, (isset($aVals['privacy_list']) ? $aVals['privacy_list'] : []));
        }

        (($sPlugin = Phpfox_Plugin::get('user.service_process_add_updatestatus')) ? eval($sPlugin) : false);

        $feedTypeId = 'user_status';
        $iFeedId = Phpfox::getService('feed.process')->add($feedTypeId, $iStatusId, $aVals['privacy'],
            $aVals['privacy_comment'], 0, null, 0, (isset($aVals['parent_feed_id']) ? $aVals['parent_feed_id'] : 0),
            (isset($aVals['parent_module_id']) ? $aVals['parent_module_id'] : 0));

        // notification to tagged and mentioned friends
        $this->notifyTaggedInFeed($feedTypeId, $sStatus, $iStatusId, $iUserId, $iFeedId, (isset($aVals['tagged_friends']) ? $aVals['tagged_friends'] : ''), $aVals['privacy'], (isset($aVals['parent_user_id']) ? $aVals['parent_user_id'] : 0));

        if (Phpfox::isModule('tag') && Phpfox::getParam('tag.enable_hashtag_support')) {
            Phpfox::getService('tag.process')->add($feedTypeId, $iStatusId, Phpfox::getUserId(), $sStatus, true);
        }

        if (Phpfox::isAppActive('Core_Activity_Points')) {
            Phpfox::getService('activitypoint.process')->updatePoints(Phpfox::getUserId(), !empty($aVals['no_check_empty_user_status']) ? 'share_item' : 'feed_postonwall');
        }

        (($sPlugin = Phpfox_Plugin::get('user.service_process_add_updatestatus_end')) ? eval($sPlugin) : false);

        return $iFeedId;
    }

    /**
     * @param $iFeedId
     * @param $aVals
     *
     * @return bool
     * @throws Exception
     */
    public function editStatus($iFeedId, $aVals)
    {
        //Get current user status information
        $aCallback = [];
        if (isset($aVals['callback_module'])) {
            $aCallback['module'] = $aVals['callback_module'];
            $aCallback['table_prefix'] = $aVals['callback_module'] . '_';
        }

        if (isset($aVals['callback_item_id'])) {
            $aCallback['item_id'] = $aVals['callback_item_id'];
        }

        $aStatusFeed = Phpfox::getService('feed')->getUserStatusFeed($aCallback, $iFeedId);
        if (!((Phpfox::getUserParam('feed.can_edit_own_user_status') && $aStatusFeed['user_id'] == Phpfox::getUserId()) || Phpfox::getUserParam('feed.can_edit_other_user_status'))) {
            return false;
        }

        $sOldContent = (isset($aStatusFeed['feed_status']) && $aStatusFeed['feed_status']) ? $aStatusFeed['feed_status'] : '';
        if (!$aStatusFeed['parent_feed_id'] && !in_array($aStatusFeed['type_id'], ['v', 'photo']) && Phpfox::getLib('parse.format')->isEmpty($aVals['user_status'])) {
            if (!isset($aVals['no_check_empty_user_status']) || empty($aVals['no_check_empty_user_status'])) {
                return Phpfox_Error::set(_p('add_some_text_to_share'));
            }
        }

        if (!Phpfox::getService('ban')->checkAutomaticBan($aVals['user_status'])) {
            return false;
        }
        $sStatus = $this->preParse()->prepare($aVals['user_status']);

        if (!isset($aVals['privacy'])) {
            $aVals['privacy'] = $aStatusFeed['privacy'];
        }

        if (!isset($aVals['privacy_comment'])) {
            $aVals['privacy_comment'] = $aStatusFeed['privacy_comment'];
        }

        $aUpdate = [
            'privacy'         => $aVals['privacy'],
            'privacy_comment' => $aVals['privacy_comment'],
        ];
        $feedTypeId = $feedTypeIdUpdate = $aStatusFeed['type_id'];
        $itemId = $iStatusId = (int)$aStatusFeed['item_id'];
        if (isset($aVals['location']) && isset($aVals['location']['latlng']) && !empty($aVals['location']['latlng'])) {
            $aMatch = explode(',', $aVals['location']['latlng']);
            $aMatch['latitude'] = floatval($aMatch[0]);
            $aMatch['longitude'] = floatval($aMatch[1]);
            $aUpdate['location_latlng'] = json_encode([
                'latitude'  => $aMatch['latitude'],
                'longitude' => $aMatch['longitude']
            ]);
        } else {
            $aUpdate['location_latlng'] = null;
        }

        if (isset($aUpdate['location_latlng']) && !empty($aUpdate['location_latlng']) && isset($aVals['location']) && isset($aVals['location']['name']) && !empty($aVals['location']['name'])) {
            $aUpdate['location_name'] = Phpfox::getLib('parse.input')->clean($aVals['location']['name']);
        } else {
            $aUpdate['location_name'] = null;
        }

        if (in_array($feedTypeId, ['user_status', 'link'])) {
            $aUpdate['content'] = $sStatus;
        }

        // get old data (mention and tagged)
        $oldMentions = $this->getIdFromMentions($sOldContent, true, false);
        $aOldTagged = Phpfox::getService('feed')->getTaggedUserIds($itemId, $feedTypeId);

        if ($feedTypeId == 'link') {
            db()->delete(Phpfox::getT('link_embed'), 'link_id = ' . $itemId);
            db()->delete(Phpfox::getT('link'), 'link_id = ' . $itemId);
            $aUpdate = array_merge($aUpdate, [
                'time_stamp' => $aStatusFeed['time_stamp'],
                'user_id'    => $aStatusFeed['user_id']
            ]);
            $iStatusId = db()->insert(Phpfox::getT('user_status'), $aUpdate);
            db()->update(Phpfox::getT('feed'), ['type_id' => 'user_status', 'item_id' => $iStatusId], 'feed_id = ' . (int)$iFeedId);
            $feedTypeIdUpdate = 'user_status';
        } else {
            if ($feedTypeId == 'user_status') {
                // update user status info
                $this->database()->update(Phpfox::getT('user_status'), $aUpdate, ['status_id' => $itemId]);
            }
        }

        (($sPlugin = Phpfox_Plugin::get('user.service_process_edit_status_update_type')) ? eval($sPlugin) : false);

        if (isset($aVals['privacy']) && $aVals['privacy'] == '4') {
            Phpfox::getService('privacy.process')->update($feedTypeIdUpdate, $iStatusId, (isset($aVals['privacy_list']) ? $aVals['privacy_list'] : []));
        }
        $this->_iStatusId = $iStatusId;

        // update info of item belong to feed
        if (Phpfox::hasCallback($feedTypeIdUpdate, 'updateFeedItemInfo')) {
            Phpfox::callback($feedTypeIdUpdate . '.updateFeedItemInfo', [
                'content'  => $sStatus,
                'location' => [
                    'location_latlng' => $aUpdate['location_latlng'],
                    'location_name'   => $aUpdate['location_name']
                ],
                'privacy'  => $aVals['privacy'],
                'item_id'  => $iStatusId
            ]);
        }

        // notification to tagged and mentioned friends
        $this->notifyTaggedInFeed($feedTypeIdUpdate, $sStatus, $iStatusId, Phpfox::getUserId(), $aStatusFeed['feed_id'], $aVals['tagged_friends'], $aVals['privacy'], (isset($aStatusFeed['parent_user_id']) ? (int)$aStatusFeed['parent_user_id'] : 0), $aOldTagged, $oldMentions);

        if (Phpfox::isModule('tag') && Phpfox::getParam('tag.enable_hashtag_support')) {
            Phpfox::getService('tag.process')->add($feedTypeIdUpdate, $iStatusId, Phpfox::getUserId(), $sStatus, true);
        }

        // update in table `feed` also
        Phpfox::getService('feed.process')->update($feedTypeIdUpdate, $iStatusId, $aVals['privacy'], $aVals['privacy_comment']);

        (($sPlugin = Phpfox_Plugin::get('user.service_process_edit_status_end')) ? eval($sPlugin) : false);

        return true;
    }

    public function getLastStatusId()
    {
        return $this->_iStatusId;
    }

    /**
     * @param        $feedTypeId
     * @param        $sContent
     * @param        $iItemId
     * @param        $iOwnerId
     * @param int    $iFeedId
     * @param string $taggedFriends
     * @param int    $iPrivacy
     * @param int    $iParentUserId
     * @param array  $aOldTagged
     * @param array  $oldMentions
     *
     * @return bool
     */
    public function notifyTaggedInFeed($feedTypeId, $sContent, $iItemId, $iOwnerId, $iFeedId = 0, $taggedFriends = '', $iPrivacy = 0, $iParentUserId = 0, $aOldTagged = [], $oldMentions = [])
    {
        if (!Phpfox::isModule('feed')) {
            return false;
        }
        // notification to tagged and mentioned friends
        $aTagged = [];
        if (!empty($taggedFriends)) {
            $aTagged = explode(',', $taggedFriends);
        }
        return Phpfox::getService('feed.process')->updateFeedTaggedUsers([
            'feed_type'            => $feedTypeId,
            'content'              => $sContent,
            'owner_id'             => $iOwnerId,
            'privacy'              => $iPrivacy,
            'tagged_friend'        => $aTagged,
            'item_id'              => $iItemId,
            'feed_id'              => $iFeedId,
            'parent_user_id'       => $iParentUserId,
            'old_tagged_friend'    => $aOldTagged,
            'old_mentioned_friend' => $oldMentions
        ]);
    }

    /**
     * @param      $sContent
     * @param bool $bForceFriend  check is friend
     * @param bool $bCheckPrivacy check privacy tag
     *
     * @return array
     */
    public function getIdFromMentions($sContent, $bForceFriend = false, $bCheckPrivacy = true)
    {
        $iCount = preg_match_all('/\[user=(\d+)\].+?\[\/user\]/i', $sContent, $aMatches);
        if ($iCount < 1) {
            return [];
        }
        $aOut = [];
        foreach ($aMatches[1] as $iKey => $iUserId) {
            if ($bCheckPrivacy && !Phpfox::getService('user.privacy')->hasAccess($iUserId, 'user.can_i_be_tagged')) {
                continue;
            }

            if ($bForceFriend || (Phpfox::isModule('friend') && Phpfox::getService('friend')->isFriend(Phpfox::getUserId(), $iUserId))) {
                $aOut[] = $iUserId;
            }
        }
        return $aOut;
    }

    /**
     * @param $sContent
     * @param $iItemId
     * @param $sType
     *
     * @throws Exception
     */
    public function notifyTaggedInComment($sContent, $iItemId, $sType)
    {
        $aMentions = $this->getIdFromMentions($sContent);
        if (empty(array_filter($aMentions))) {
            return;
        }
        if (Phpfox::isModule('notification')) {
            $sName = 'comment_';
            if ($sType == 'photo_album' || (strpos($sType, 'music') !== false) || ($sType == 'user_status')) {
                $sName .= $sType . 'tag';
            } else {
                $sName .= $sType . '_tag';
            }

            foreach ($aMentions as $iIndex => $iUserId) {
                if (!Phpfox::getService('notification')->checkExisted($sName, $iItemId, $iUserId)) {
                    Phpfox::getService('notification.process')->add($sName, $iItemId, $iUserId);
                }
            }
        }
    }

    public function mailToTagged($userId, $userName, $link)
    {
        if (empty($userId)) {
            return;
        }
        Phpfox::getLib('mail')->to($userId)
            ->subject(['user_name_tagged_you_in_a_status_update', ['user_name' => $userName]])
            ->message(['user_name_tagged_you_in_a_status_update_check_it_out', ['user_name' => $userName, 'link' => $link]])
            ->send();
    }

    public function updateFooterBar($iUserId, $iTypeId)
    {
        $this->database()->update($this->_sTable, ['footer_bar' => ($iTypeId == 1 ? '1' : '0')], 'user_id = ' . Phpfox::getUserId());
    }

    public function updateDesign($aVals)
    {
        Phpfox::isUser(true);

        if (isset($aVals['order'])) {
            $this->database()->delete(Phpfox::getT('user_dashboard'), 'user_id = ' . Phpfox::getUserId() . ' AND is_hidden = 0');
            foreach ($aVals['order'] as $sCacheId => $aOrder) {
                $aKey = array_keys($aOrder);
                $aValue = array_values($aOrder);
                $this->database()->insert(Phpfox::getT('user_dashboard'), ['user_id' => Phpfox::getUserId(), 'cache_id' => $sCacheId, 'block_id' => $aKey[0], 'ordering' => $aValue[0]]);
            }
        }

        if (isset($aVals['cache_id'])) {
            $this->hideBlock($aVals['cache_id'], ($aVals['is_installed'] ? 1 : 0));
        }

        if (isset($aVals['style_id'])) {
            if (Phpfox::getService('theme.style.process')->setStyle($aVals['style_id'])) {

            }
        }
    }

    public function hideBlock($sBlockId, $iHidden = 1)
    {
        $iHasEntry = $this->database()->select('COUNT(*)')
            ->from(Phpfox::getT('user_dashboard'))
            ->where('user_id = ' . Phpfox::getUserId() . ' AND cache_id = \'js_block_border_' . $this->database()->escape($sBlockId) . '\'')
            ->execute('getSlaveField');

        if ($iHasEntry) {
            $this->database()->update(Phpfox::getT('user_dashboard'), ['is_hidden' => $iHidden], 'user_id = ' . Phpfox::getUserId() . ' AND cache_id = \'js_block_border_' . $this->database()->escape($sBlockId) . '\'');
        } else {
            $this->database()->insert(Phpfox::getT('user_dashboard'), ['user_id' => Phpfox::getUserId(), 'cache_id' => 'js_block_border_' . $sBlockId, 'block_id' => null, 'ordering' => 0, 'is_hidden' => $iHidden]);
        }
    }

    public function updateAdvanced($iUserid, $aVals)
    {
        Phpfox::getUserParam('user.can_edit_users', true);

        $aActivity = [];
        if (isset($aVals['email'])) {
            $aVals['email'] = strtolower($aVals['email']);
        }
        if (isset($aVals['activity'])) {
            $aActivity = (array)$aVals['activity'];
        }
        if (isset($aVals['signature'])) {
            $sSignature = $aVals['signature'];
        }
        $aOrg = $aVals;
        $aForms = [
            'full_name'       => [
                'message' => _p('fill_in_a_display_name'),
                'type'    => 'string:required'
            ],
            'user_group_id'   => [
                'message' => _p('select_a_user_group_for_this_user'),
                'type'    => 'int'
            ],
            'country_iso'     => [
                'message' => _p('select_a_location'),
                'type'    => 'string'
            ],
            'birthday'        => [
                'message' => _p('select_a_date_of_birth'),
                'type'    => 'string'
            ],
            'birthday_search' => [
                'type' => 'int'
            ],
            'time_zone'       => 'string',
            'status'          => 'string',
            'total_spam'      => 'int',
            'language_id'     => 'string',
            'gender'          => [
                'message' => _p('select_a_gender'),
                'type'    => 'int' . (Phpfox::getParam('user.require_basic_field') ? ':required' : '')
            ],
            'custom_gender'   => 'string'
        ];

        $aUserFieldsForms = [
            'country_child_id' => [
                'type' => 'int'
            ],
            'city_location'    => [
                'type' => 'string'
            ],
            'postal_code'      => [
                'type' => 'string'
            ]
        ];

        // update gender
        if ($aVals['gender'] == 'custom') {
            if (!empty($aVals['custom_gender'])) {
                $countValid = 0;
                foreach ($aVals['custom_gender'] as $custom_gender) {
                    $custom_gender = trim($custom_gender);
                    if (!empty($custom_gender)) {
                        $countValid++;
                    }
                }

                if ($countValid == count($aVals['custom_gender'])) {
                    $aVals['gender'] = 127;
                    $aVals['custom_gender'] = serialize($aVals['custom_gender']);
                } else {
                    $aVals['gender'] = 0;
                }
            }
        } else {
            $aVals['gender'] = (int)$aVals['gender'];
            $aVals['custom_gender'] = '';
        }

        if (!empty($aVals['day']) && !empty($aVals['month']) && !empty($aVals['year'])) {
            $aVals['birthday'] = Phpfox::getService('user')->buildAge($aVals['day'], $aVals['month'], $aVals['year']);
            $aVals['birthday_search'] = Phpfox::getLib('date')->mktime(0, 0, 0, $aVals['month'], $aVals['day'], $aVals['year']);
        }

        if (isset($aVals['user_name_check'])) {
            $aForms['user_name'] = [
                'message' => _p('provide_a_valid_user_name', [
                    'min' => Phpfox::getParam('user.min_length_for_username'),
                    'max' => Phpfox::getParam('user.max_length_for_username')
                ]),
                'type'    => ['string:required', 'regex:user_name']
            ];

            Phpfox::getService('user.validate')->user($aVals['user_name']);
        }

        if (isset($aVals['email_check'])) {
            $aForms['email'] = [
                'message' => _p('provide_a_valid_email'),
                'type'    => ['string:required', 'regex:email']
            ];

            Phpfox::getService('user.validate')->email($aVals['email']);
            $bIsEmailPass = true;
        }

        if (isset($aVals['password_check'])) {
            $sSalt = $this->_getSalt();
            $aVals['password'] = Phpfox::getLib('hash')->setHash($aVals['password'], $sSalt);
            $aVals['password_salt'] = $sSalt;
            $aForms['password'] = [
                'type' => 'string'
            ];
            $aForms['password_salt'] = [
                'type' => 'string'
            ];
        }

        if (defined('PHPFOX_IS_HOSTED_SCRIPT') && $aVals['user_group_id'] == ADMIN_USER_ID) {
            $iCurrentUserGroupId = $this->database()->select('user_group_id')
                ->from(Phpfox::getT('user'))
                ->where('user_id = ' . (int)$iUserid)
                ->execute('getSlaveField');

            if ($aVals['user_group_id'] != $iCurrentUserGroupId) {
                $iTotalAdmins = $this->database()->select('COUNT(*)')
                    ->from(Phpfox::getT('user'))
                    ->where('user_group_id = ' . (int)ADMIN_USER_ID)
                    ->execute('getSlaveField');

                if ($iTotalAdmins >= Phpfox::getParam('core.phpfox_grouply_admins')) {
                    Phpfox_Error::set(_p('you_have_currently_reached_your_admin_limit_dot_total_admins_out_of_max_admin',
                        [
                            'total_admins' => $iTotalAdmins,
                            'max_admin'    => Phpfox::getParam('core.phpfox_grouply_admins')
                        ]));
                }
            }
        }

        (($sPlugin = Phpfox_Plugin::get('user.service_process_updateadvanced_start')) ? eval($sPlugin) : false);

        if (isset($aVals['city_location']) && !empty($aVals['city_location'])) {
            $aVals['city_location'] = Phpfox::getLib('parse.input')->clean($aVals['city_location']);
        }

        $aUserFields = $this->validator()->process($aUserFieldsForms, $aVals);
        $aVals = $this->validator()->process($aForms, $aVals);
        if (!Phpfox::getUserParam('user.can_edit_user_group_membership')) {
            unset($aVals['user_group_id']);
        }
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $aImage = Phpfox_File::instance()->load('image', ['jpg', 'gif', 'png'], (Phpfox::getUserParam('user.max_upload_size_profile_photo') === 0 ? null : (Phpfox::getUserParam('user.max_upload_size_profile_photo') / 1024)));
        }
        if (!Phpfox_Error::isPassed()) {
            return false;
        }

        $aVals['full_name'] = Phpfox::getLib('parse.input')->clean($aVals['full_name'], 255);

        $this->database()->update($this->_sTable, $aVals, 'user_id = ' . (int)$iUserid);
        $this->database()->update(Phpfox::getT('user_field'), $aUserFields, 'user_id = ' . (int)$iUserid);

        if (!empty($aOrg['day']) && !empty($aOrg['month'])) {
            $this->database()->update(Phpfox::getT('user_field'), ['birthday_range' => '\'' . Phpfox::getService('user')->buildAge($aOrg['day'], $aOrg['month']) . '\''], 'user_id = ' . (int)$iUserid, false);
        }

        if (count($aActivity)) {
            foreach ($aActivity as $sKey => $sValue) {
                $this->database()->update(Phpfox::getT('user_activity'), [$sKey => (int)$sValue], 'user_id = ' . (int)$iUserid);
            }
        }

        if (isset($bIsEmailPass)) {
            $this->database()->update(Phpfox::getT('user_verify'), ['email' => $aVals['email']], 'user_id = ' . (int)$iUserid);
        }

        if (isset($sSignature)) {
            $this->database()->update(Phpfox::getT('user_field'), [
                'signature' => Phpfox::getLib('parse.input')->clean($sSignature)
            ],
                'user_id = ' . (int)$iUserid);
        }

        if (isset($aImage) && $aImage !== false) {
            $this->uploadImage($iUserid, true);
        }

        $this->clearFriendCacheOfFriends($iUserid);

        (($sPlugin = Phpfox_Plugin::get('user.service_process_updateadvanced_end')) ? eval($sPlugin) : false);

        return true;
    }

    public function cropPhoto($aVals)
    {
        $sFile = Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '');
        if (!file_exists($sFile)) {
            $sActualFile = Phpfox::getLib('image.helper')->display([
                    'server_id'  => Phpfox::getUserBy('server_id'),
                    'path'       => 'core.url_user',
                    'file'       => Phpfox::getUserBy('user_image'),
                    'suffix'     => (!isset($iSize) ? '' : '_' . $iSize),
                    'return_url' => true
                ]
            );
            copy($sActualFile, $sFile);
        }

        Phpfox_Image::instance()->createThumbnail(Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), ''), Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '') . '_temp', $aVals['image_width'], $aVals['image_height'], false);

        Phpfox_Image::instance()->cropImage(
            Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '') . '_temp',
            Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '_50_square'),
            $aVals['w'],
            $aVals['h'],
            $aVals['x1'],
            $aVals['y1'],
            75
        );

        Phpfox_Image::instance()->cropImage(
            Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '') . '_temp',
            Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '') . '_temp',
            $aVals['w'],
            $aVals['h'],
            $aVals['x1'],
            $aVals['y1'],
            $aVals['w']
        );

        foreach (Phpfox::getService('user')->getUserThumbnailSizes() as $iSize) {
            if (Phpfox::getParam('core.keep_non_square_images')) {
                Phpfox_Image::instance()->createThumbnail(
                    Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '') . '_temp',
                    Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '_' . $iSize),
                    $iSize,
                    $iSize
                );
            }
            Phpfox_Image::instance()->createThumbnail(
                Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '') . '_temp',
                Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '_' . $iSize . '_square'),
                $iSize,
                $iSize,
                false
            );

            if (defined('PHPFOX_IS_HOSTED_SCRIPT')) {
                unlink(Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '_' . $iSize));
                unlink(Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '_' . $iSize . '_square'));
            }
        }

        unlink(Phpfox::getParam('core.dir_user') . sprintf(Phpfox::getUserBy('user_image'), '') . '_temp');

        return true;
    }

    public function updatePassword($aVals, $iUserId = null, $bCheck = true)
    {
        Phpfox::isUser(true);
        if ($iUserId === null) {
            $iUserId = Phpfox::getUserId();
        }
        $aUser = storage()->get('fb_new_users_' . $iUserId);
        $bPassOld = !empty($aUser);

        if (!$bPassOld && $bCheck && empty($aVals['old_password'])) {
            return Phpfox_Error::set(_p('missing_old_password'));
        }

        if ($bCheck && empty($aVals['new_password'])) {
            return Phpfox_Error::set(_p('missing_new_password'));
        }

        if ($bCheck && empty($aVals['confirm_password'])) {
            return Phpfox_Error::set(_p('confirm_your_new_password'));
        }

        if ($bCheck && $aVals['confirm_password'] != $aVals['new_password']) {
            return Phpfox_Error::set(_p('your_confirmed_password_does_not_match_your_new_password'));
        }

        $aUser = Phpfox::getService('user')->getUser($iUserId);

        if ($sPlugin = Phpfox_Plugin::get('user.service_process_updatepassword_permission')) {
            eval($sPlugin);
            if (isset($mReturnFromPlugin)) {
                return $mReturnFromPlugin;
            }
        }

        if ($bCheck) {
            require_once(PHPFOX_DIR_SETTING . 'validator.sett.php');
            if (array_key_exists('password', $this->_aDefaults) &&
                (strlen($aVals['new_password']) < $this->_aDefaults['password']['minlen'] ||
                    strlen($aVals['new_password']) > $this->_aDefaults['password']['maxlen'])
            ) {
                return Phpfox_Error::set(_p('not_a_valid_password'));
            }

            if (!$bPassOld) {
                if (strlen($aUser['password']) > 32) {
                    $Hash = new Core\Hash();
                    if (!$Hash->check($aVals['old_password'], $aUser['password'])) {
                        return Phpfox_Error::set(_p('your_current_password_does_not_match_your_old_password'));
                    }
                } else {
                    if (Phpfox::getLib('hash')->setHash($aVals['old_password'],
                            $aUser['password_salt']) != $aUser['password']
                    ) {
                        return Phpfox_Error::set(_p('your_current_password_does_not_match_your_old_password'));
                    }
                }
            }
        }

        $sSalt = $this->_getSalt();
        $aInsert = [];
        $aInsert['password'] = (new Core\Hash())->make($aVals['new_password']);
        $aInsert['password_salt'] = $sSalt;

        $this->database()->update($this->_sTable, $aInsert, 'user_id = ' . $iUserId);

        if ($bPassOld) {
            storage()->del('fb_new_users_' . $iUserId);
        }
        if ($bCheck) {
            list($bLogged, $aUser) = Phpfox::getService('user.auth')->login($aUser['email'], $aVals['new_password'], false, 'email');
        } else {
            $bLogged = true;
        }

        $this->database()->insert(Phpfox::getT('user_ip'), [
                'user_id'    => $iUserId,
                'type_id'    => 'update_password',
                'ip_address' => Phpfox::getIp(),
                'time_stamp' => PHPFOX_TIME
            ]
        );

        (($sPlugin = Phpfox_Plugin::get('user.service_process_updatepassword')) ? eval($sPlugin) : false);

        return ($bLogged ? true : false);
    }

    /**
     * Adds or removes a ban on a user.
     *
     * @param int $iUserId
     * @param int $iType 1|0 => 1 to place the ban, 0 to remove it @deprecated
     *
     * @return <type>
     */
    public function ban($iUserId, $iType)
    {
        Phpfox::isUser(true);

        if (!defined('PHPFOX_SKIP_BAN_ADMIN_CHECK')) {
            Phpfox::getUserParam('admincp.has_admin_access', true);
        }

        if (Phpfox::getService('user')->isAdminUser($iUserId)) {
            return Phpfox_Error::set(_p('you_are_unable_to_ban_a_site_administrator'));
        }

        // Adding a check so we can't ban ourselves.
        if ($iUserId == Phpfox::getUserId() && !defined('PHPFOX_SKIP_BAN_ADMIN_CHECK')) {
            return Phpfox_Error::set(_p('you_should_not_ban_yourself'));
        }

        $aBanned = Phpfox::getService('ban')->isUserBanned(['user_id' => $iUserId]);

        if (isset($aBanned['ban_data_id'])) {
            if ($aBanned['is_banned'] == true) {
                // Removing a user from a ban user group, lets make sure there are no active bans in the ban_data
                if (isset($aBanned['return_user_group']) && ((int)$aBanned['return_user_group'] > 0)) {
                    $this->database()->update($this->_sTable, ['user_group_id' => $aBanned['return_user_group']], 'user_id = ' . (int)$iUserId);
                } else {
                    $this->database()->update($this->_sTable, ['user_group_id' => NORMAL_USER_ID], 'user_id = ' . (int)$iUserId);
                }

                // make sure all bans are expired
                $this->database()->update(Phpfox::getT('ban_data'), [
                    'is_expired' => '1'
                ],
                    'user_id = ' . (int)$iUserId . ''
                );
            } else {
                // add a ban by updating the user group
                $this->database()->update($this->_sTable, ['user_group_id' => Phpfox::getParam('core.banned_user_group_id')], 'user_id = ' . (int)$iUserId);
            }
        } else {
            if ($iType) {
                $this->database()->update($this->_sTable, ['user_group_id' => Phpfox::getParam('core.banned_user_group_id')], 'user_id = ' . (int)$iUserId);
            } else {
                $this->database()->update($this->_sTable, ['user_group_id' => NORMAL_USER_ID], 'user_id = ' . (int)$iUserId);
            }
        }

        (($sPlugin = Phpfox_Plugin::get('user.service_process_banuser')) ? eval($sPlugin) : false);

        return true;
    }

    public function clearStatus($iUserId)
    {
        $this->database()->update(Phpfox::getT('user'), ['status' => null], 'user_id = ' . (int)$iUserId);
    }

    public function userPending($iUserId, $iType)
    {
        $aUser = $this->database()->select('ug.title AS user_group_title, u.user_id')
            ->from(Phpfox::getT('user'), 'u')
            ->join(Phpfox::getT('user_group'), 'ug', 'ug.user_group_id = u.user_group_id')
            ->where('u.user_id = ' . (int)$iUserId)
            ->execute('getSlaveRow');

        if (!isset($aUser['user_id'])) {
            return false;
        }

        if ($iType == '1') {
            Phpfox::getLib('mail')->to($aUser['user_id'])
                ->subject('user.account_approved')
                ->message([
                        'user.your_account_has_been_approved_on_site_title', [
                            'site_title' => Phpfox::getParam('core.site_title'),
                            'link'       => Phpfox_Url::instance()->makeUrl('')
                        ]
                    ]
                )
                ->send();

            $this->database()->update(Phpfox::getT('user'), [
                'view_id' => '0'
            ], 'user_id = ' . $aUser['user_id']
            );

            Phpfox::getService('user.verify.process')->adminVerify($aUser['user_id']);
        } else {
            $this->database()->update(Phpfox::getT('user'), [
                'view_id' => '2'
            ], 'user_id = ' . $aUser['user_id']
            );
        }

        return $aUser;
    }

    /**
     * If a call is made to an unknown method attempt to connect
     * it to a specific plug-in with the same name thus allowing
     * plug-in developers the ability to extend classes.
     *
     * @param string $sMethod    is the name of the method
     * @param array  $aArguments is the array of arguments of being passed
     *
     * @return null;
     */
    public function __call($sMethod, $aArguments)
    {
        /**
         * Check if such a plug-in exists and if it does call it.
         */
        if ($sPlugin = Phpfox_Plugin::get('user.service_process__call')) {
            eval($sPlugin);
            return null;
        }

        /**
         * No method or plug-in found we must throw a error.
         */
        Phpfox_Error::trigger('Call to undefined method ' . __CLASS__ . '::' . $sMethod . '()', E_USER_ERROR);
    }

    private function _getSalt($iTotal = 3)
    {
        $sSalt = '';
        for ($i = 0; $i < $iTotal; $i++) {
            $sSalt .= chr(rand(33, 91));
        }

        return $sSalt;
    }

    /**
     * This function creates a Remind Inactive Users job
     *
     * @param      $aUserIds
     * @param bool $bSendAll
     * @param int  $iDays How many days in the past to search for users
     *
     * @return bool if the job was created, false otherwise
     */
    public function addInactiveJob($aUserIds, $bSendAll = false, $iDays = 0)
    {
        if ($bSendAll) {
            $aUserIds = [];
            $aUsers = Phpfox::getService('user')->getInactiveMembers($iDays);
            if (count($aUsers)) {
                foreach ($aUsers as $aUser) {
                    $aUserIds[] = $aUser['user_id'];
                }
            }
        }
        $aCacheJob = storage()->get('user_inactive_mailing_job');
        if ($aCacheJob) {
            foreach ($aUserIds as $aId) {
                if (!in_array($aId, $aCacheJob->value)) {
                    storage()->update('user_inactive_mailing_job', (array)$aId);
                }
            }
        } else {
            storage()->set('user_inactive_mailing_job', $aUserIds);
            Phpfox_Queue::instance()->addJob('user_inactive_mailing_job', []);
        }
        return true;
    }

    public function processInactiveJob($iJobId)
    {
        $aCacheJob = storage()->get('user_inactive_mailing_job');
        $aIds = $aCacheJob->value;
        if (!count($aIds)) {
            return false;
        }
        $aUsers = $this->database()->select('user_id, email, language_id, full_name, user_name, user_group_id')
            ->from(Phpfox::getT('user'))
            ->where('user_id IN (' . implode(',', array_slice($aIds, 0, 99)) . ')')
            ->order('user_id ASC')
            ->execute('getSlaveRows');

        $oMail = Phpfox::getLib('mail');

        foreach ($aUsers as $aUser) {
            $oMail->aUser($aUser);
        }
        $bSent = $oMail->subject('inactive_member_email_subject')
            ->message([
                'inactive_member_email_body', [
                    'site_name' => Phpfox::getParam('core.site_title'),
                    'site_url'  => Phpfox_Url::instance()->makeUrl('')
                ]
            ])
            ->send();
        if ($bSent) {
            storage()->del('user_inactive_mailing_job');
            $aRemainUser = array_slice($aIds, 99);
            if (count($aRemainUser)) {
                storage()->set('user_inactive_mailing_job', $aRemainUser);
                db()->update(':cron_job', ['is_running' => 0], 'id =' . (int)$iJobId);
            } else {
                db()->delete(':cron_job', 'id =' . (int)$iJobId);
            }
        } else {
            //should run cron again
            db()->update(':cron_job', ['is_running' => 0], 'id =' . (int)$iJobId);
        }
        return false;
    }

    public function addSpamQuestion($aVals, $bReturnId = false)
    {
        $oParse = Phpfox::getLib('parse.input');
        $oFile = Phpfox_File::instance();

        $sQuestion = $oParse->clean($aVals['question'], 250);
        if (empty($sQuestion)) {
            return Phpfox_Error::set(_p('no_question_received'));
        }
        // Check that there is at least one answer
        if (!isset($aVals['answer']) || count($aVals['answer']) < 1) {
            return Phpfox_Error::set(_p('no_answers_received'));
        }

        $bEmptyAnswers = false;
        foreach ($aVals['answer'] as $iKey => $sAnswer) {
            if (empty($oParse->clean($sAnswer))) {
                $bEmptyAnswers = true;
                break;
            }
            $aVals['answer'][$iKey] = $oParse->clean($sAnswer);
        }

        if ($bEmptyAnswers) {
            return Phpfox_Error::set(_p('please_fill_in_all_answers'));
        }

        $aInsert = [
            'question_phrase' => $sQuestion,
            'answers_phrases' => json_encode($aVals['answer']),
            'time_stamp'      => PHPFOX_TIME,
            'image_path'      => (isset($aVals['image_path']) && !empty($aVals['image_path'])) ? $oParse->clean($aVals['image_path']) : '',
            'server_id'       => isset($aVals['server_id']) ? $aVals['server_id'] : 0
        ];

        if (isset($_FILES['file']['name']) && !empty($_FILES['file']['name'])) {
            if (!$oFile->load('file', ['jpg', 'gif', 'png'])) {
                return Phpfox_Error::set(_p('could_not_load_file'));
            }
            $aInsert['image_path'] = $oFile->upload('file', Phpfox::getParam('user.dir_user_spam'), '');
            if ($aInsert['image_path'] == false) {
                return Phpfox_Error::set(_p('could_not_upload_files'));
            }
            $aInsert['image_path'] = sprintf($aInsert['image_path'], '');
            $iServerId = Phpfox::getLib('cdn')->getServerId();
            $aInsert['server_id'] = empty($iServerId) ? 0 : $iServerId;
        }

        $iQuestionId = $this->database()->insert(Phpfox::getT('user_spam'), $aInsert);

        cache()->del('spam/questions');
        if ($bReturnId) {
            return $iQuestionId;
        }

        return true;
    }

    public function editSpamQuestion($aVals)
    {
        $oParse = Phpfox::getLib('parse.input');

        if (!isset($aVals['question']) || empty($oParse->clean($aVals['question']))) {
            return Phpfox_Error::set(_p('no_question_received'));
        }
        // Check that there is at least one answer
        if (!isset($aVals['answer']) || count($aVals['answer']) < 1) {
            return Phpfox_Error::set(_p('no_answers_received'));
        }

        $bEmptyAnswers = false;
        foreach ($aVals['answer'] as $iKey => $sAnswer) {
            if (empty($oParse->clean($sAnswer))) {
                $bEmptyAnswers = true;
                break;
            }
            $aVals['answer'][$iKey] = $oParse->clean($sAnswer);
        }

        if ($bEmptyAnswers) {
            return Phpfox_Error::set(_p('no_answers_received'));
        }

        if (!isset($aVals['question_id']) || !is_numeric($aVals['question_id'])) {
            return Phpfox_Error::set(_p('invalid_question_id_when_editing_dot'));
        }

        if (isset($aVals['preserve_image']) && !empty($aVals['preserve_image'])) {
            $aOldImage = $this->database()->select('image_path, server_id')
                ->from(Phpfox::getT('user_spam'))
                ->where('question_id = ' . (int)$aVals['question_id'])
                ->executeRow();
            $aVals['image_path'] = $aOldImage['image_path'];
            $aVals['server_id'] = $aOldImage['server_id'];
        }
        // add the new question
        $iNewId = $this->addSpamQuestion($aVals, true);

        if ($this->deleteSpamQuestion($aVals['question_id'], (isset($aVals['preserve_image']) && !empty($aVals['preserve_image'])))) {
            // Reset the question_id
            $this->database()->update(Phpfox::getT('user_spam'), ['question_id' => (int)$aVals['question_id']], 'question_id = ' . $iNewId);
        }

        return true;

    }

    public function deleteSpamQuestion($iQuestionId, $bPreserveImage = false)
    {
        // Check if the previous question had an image
        $sImagePath = $this->database()->select('image_path')->from(Phpfox::getT('user_spam'))->where('question_id = ' . (int)$iQuestionId)->execute('getSlaveField');
        $sFilePath = Phpfox::getParam('user.dir_user_spam') . sprintf($sImagePath, '');
        if ($bPreserveImage != true && !empty($sImagePath) && file_exists($sFilePath)) {
            Phpfox_File::instance()->unlink($sFilePath);
        }

        // Delete the previous question from the database
        $this->database()->delete(Phpfox::getT('user_spam'), 'question_id = ' . (int)$iQuestionId);

        cache()->del('spam/questions');

        return true;
    }

    /* Stores a latitude and a longitude for the given user */
    public function saveMyLatLng($aLocation)
    {
        $fLat = floatval($aLocation['latitude']);
        $fLng = floatval($aLocation['longitude']);

        if ($fLat == 0 && $fLng == 0) {
            return false;
        }
        $aUpdate = [
            'latitude'  => $fLat,
            'longitude' => $fLng
        ];
        $this->database()->update(Phpfox::getT('user_field'), ['location_latlng' => json_encode($aUpdate)], 'user_id = ' . Phpfox::getUserId());
        return true;
    }

    /**
     * Delete user's profile picture
     *
     * @param $iId
     */
    public function deleteProfilePicture($iId)
    {
        $aUser = Phpfox::getService('user')->getUser($iId, 'u.user_image');
        if (!$aUser) {
            return;
        }
        if (isset($aUser['user_image']) && $aUser['user_image']) {
            // delete image
            Phpfox_File::instance()->unlink(Phpfox::getParam('core.dir_user') . sprintf($aUser['user_image'], ''));
        }
        db()->update(':user', ['user_image' => ''], ['user_id' => $iId]);
    }
}
