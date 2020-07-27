<?php
defined('PHPFOX') or exit('NO DICE!');

/**
 * Class User_Component_Controller_Browse
 */
class User_Component_Controller_Browse extends Phpfox_Component
{
    /**
     * Controller
     */
    public function process()
    {
        $sViewParam = $this->request()->get('view');
        $aSpecialPages = [
            'online',
            'featured'
        ];
        if (in_array($sViewParam, $aSpecialPages)) {
            $bOldWay = true;
        } else {
            $bOldWay = false;
        }
        if (!$bOldWay && ($this->request()->get('featured') || $this->request()->get('recommend'))) {
            return function () {
                if ($this->request()->get('recommend')) {
                    //Hide users you may know if not login
                    if (Phpfox::isUser()) {
                        $title = _p('users_you_may_know');
                        if (Phpfox::isModule('friend')) {
                            $users = Phpfox::getService('friend.suggestion')->get();
                        } else {
                            $users = false;
                        }
                    } else {
                        $title = '';
                        $users = [];
                    }
                } else { // featured => recently_active
                    $title = _p('recently_active');
                    $users = Phpfox::getService('user.featured')->getRecentActiveUsers();
                }

                $content = '';
                if ((is_array($users) && !$users) || $users === true) {

                } else {
                    $content .= '<div class="block_clear"><div class="title">' . $title . '</div><div class="content"><div class="wrapper-items item-container user-listing">';
                    foreach ($users as $user) {
                        $content .= '<article class="user-item js_user_item_' . $user['user_id'] . '">';
                        $content .= $this->template()->assign('aUser', $user)->getTemplate('user.block.rows_wide', true);
                        $content .= '</article>';
                    }
                    $content .= '</div></div></div>';
                }
                echo $content;
                exit;
            };
        }
        if ($sPlugin = Phpfox_Plugin::get('user.component_controller_browse__1')) {
            eval($sPlugin);
            if (isset($aPluginReturn)) {
                return $aPluginReturn;
            }
        }

        $aCallback = $this->getParam('aCallback', false);
        if (defined('PHPFOX_IS_ADMIN_SEARCH')) {
            if (($aIds = $this->request()->getArray('id')) && count((array)$aIds)) {
                Phpfox::getUserParam('user.can_delete_others_account', true);
                $sUrl = Phpfox::getLib('session')->get('admin_user_redirect');
                if (is_bool($sUrl)) {
                    $sUrl = 'current';
                }
                if ($this->request()->get('delete')) {
                    foreach ($aIds as $iId) {
                        if (Phpfox::getService('user')->isAdminUser($iId)) {
                            $this->url()->send($sUrl, null, _p('you_are_unable_to_delete_a_site_administrator'));
                        }

                        Phpfox::getService('user.auth')->setUserId($iId);
                        Phpfox::massCallback('onDeleteUser', $iId);
                        Phpfox::getService('user.auth')->setUserId(null);
                    }
                    $this->url()->send($sUrl, null, _p('user_s_successfully_deleted'));
                } elseif ($this->request()->get('ban') || $this->request()->get('unban')) {
                    $bHasAdmin = false;
                    foreach ($aIds as $iId) {
                        if (Phpfox::getService('user')->isAdminUser($iId)) {
                            $bHasAdmin = true;
                            continue;
                        }
                        Phpfox::getService('user.process')->ban($iId, ($this->request()->get('ban') ? 1 : 0));
                    }
                    if ($bHasAdmin) {
                        $this->url()->send($sUrl, null, _p('you_are_unable_to_ban_a_site_administrator'));
                    }
                    $this->url()->send($sUrl, null, ($this->request()->get('ban') ? _p('user_s_successfully_banned') : _p('user_s_successfully_un_banned')));
                } elseif ($this->request()->get('resend-verify')) {
                    foreach ($aIds as $iId) {
                        Phpfox::getService('user.verify.process')->sendMail($iId);
                    }
                    $this->url()->send($sUrl, null, _p('email_verification_s_sent'));
                } elseif ($this->request()->get('verify')) {
                    foreach ($aIds as $iId) {
                        Phpfox::getService('user.verify.process')->adminVerify($iId);
                    }
                    $this->url()->send($sUrl, null, _p('user_s_verified'));
                } elseif ($this->request()->get('approve')) {
                    foreach ($aIds as $iId) {
                        Phpfox::getService('user.process')->userPending($iId, '1');
                    }
                    $this->url()->send($sUrl, null, _p('user_s_successfully_approved'));
                }
            }
            else {
                // Create a session so we know where we plan to redirect the admin after do action
                Phpfox::getLib('session')->set('admin_user_redirect', Phpfox_Url::instance()->getFullUrl());
            }
        }

        $aPages = [21, 31, 41, 51];
        $aDisplays = [];
        foreach ($aPages as $iPageCnt) {
            $aDisplays[$iPageCnt] = _p('per_page', ['total' => $iPageCnt]);
        }

        $aSorts = [
            'u.full_name' => _p('name'),
            'u.last_activity' => _p('last_activity'),
            'u.last_login' => _p('last_login'),
        ];

        if (defined('PHPFOX_IS_ADMIN_SEARCH')) {
            $aSorts['u.joined'] = _p('joined');
            $aSorts['ug.title'] = _p('groups');
            $aSorts['u.user_id'] = _p('id');
        }

        $aAge = [];
        for ($i = Phpfox::getService('user')->age(Phpfox::getService('user')->buildAge(1, 1, Phpfox::getParam('user.date_of_birth_end'))); $i <= Phpfox::getService('user')->age(Phpfox::getService('user')->buildAge(1, 1, Phpfox::getParam('user.date_of_birth_start'))); $i++) {
            $aAge[$i] = $i;
        }

        $iYear = date('Y');
        $aUserGroups = [];
        foreach (Phpfox::getService('user.group')->get() as $aUserGroup) {
            $aUserGroups[$aUserGroup['user_group_id']] = Phpfox_Locale::instance()->convert($aUserGroup['title']);
        }

        $aGenders = Phpfox::getService('core')->getGenders();
        $aGenders[''] = _p('all_members');

        if (($sPlugin = Phpfox_Plugin::get('user.component_controller_browse_genders'))) {
            eval($sPlugin);
        }

        $sDefaultOrderName = 'u.full_name';
        $sDefaultSort = 'ASC';
        if (Phpfox::getParam('user.user_browse_default_result') == 'last_login') {
            $sDefaultOrderName = 'u.last_login';
            $sDefaultSort = 'DESC';
        }

        if (!defined('PHPFOX_IS_ADMIN_SEARCH')) {
            $this->search()->set([
                    'type' => 'user',
                    'field' => 'u.user_id',
                    'ignore_blocked' => true,
                    'search_tool' => [
                        'table_alias' => 'u',
                        'search' => [
                            'action' => $this->url()->makeUrl('user.browse'),
                            'default_value' => _p('search_users_dot'),
                            'name' => 'search',
                            'field' => ['u.full_name', 'u.email', 'u.user_name']
                        ],
                        'no_filters' => [_p('when')]
                    ]
                ]
            );
        }

        $bCustomSort = false;
        $aSearch = request()->get('search');
        if (!empty($aSearch) && isset($aSearch['sort'])) {
            $aSearchSort = explode(' ', $aSearch['sort']);
            if (in_array($aSearch['sort'], ['u.last_login', 'u.last_activity'])) {
                $sDefaultSort = 'DESC';
            }

            if (isset($aSearchSort[1])) {
                $sDefaultSort = $aSearchSort[1];
                $bCustomSort = true;
            }
        }

        if (defined('PHPFOX_IS_ADMIN_SEARCH')) {
            $iDisplay = 12;
        } else {
            $iDisplay = 21;
        }

        $aFilters = [
            'display' => [
                'type' => 'select',
                'options' => $aDisplays,
                'default' => $iDisplay
            ],
            'sort' => [
                'type' => 'select',
                'options' => $aSorts,
                'default' => $sDefaultOrderName
            ],
            'sort_by' => [
                'type' => 'select',
                'options' => [
                    'DESC' => _p('descending'),
                    'ASC' => _p('ascending')
                ],
                'default' => $sDefaultSort
            ],
            'keyword' => [
                'type' => 'input:text',
                'size' => 15,
                'class' => 'txt_input'
            ],
            'type' => [
                'type' => 'select',
                'options' => [
                    '0' => [_p('email_name'), 'AND ((u.full_name LIKE \'%[VALUE]%\' OR (u.email LIKE \'%[VALUE]@%\' OR u.email = \'[VALUE]\' OR u.user_name = \'[VALUE]\'))' . (defined('PHPFOX_IS_ADMIN_SEARCH') ? ' OR u.email LIKE \'%[VALUE]%\'' : '') . ')'],
                    '1' => [_p('email'), 'AND ((u.email LIKE \'%[VALUE]@%\' OR u.email = \'[VALUE]\'' . (defined('PHPFOX_IS_ADMIN_SEARCH') ? ' OR u.email LIKE \'%[VALUE]%\'' : '') . '))'],
                    '2' => [_p('name'), 'AND (u.full_name LIKE \'%[VALUE]%\')']
                ],
                'depend' => 'keyword'
            ],
            'group' => [
                'type' => 'select',
                'options' => $aUserGroups,
                'add_any' => true,
                'search' => 'AND u.user_group_id = \'[VALUE]\''
            ],
            'gender' => [
                'type' => 'select',
                'options' => $aGenders,
                'default_view' => '',
                'search' => 'AND u.gender = \'[VALUE]\'',
                'suffix' => '<br />',
                'id' => 'js_adv_search_user_browse_gender'
            ],
            'from' => [
                'type' => 'select',
                'options' => $aAge,
                'select_value' => _p('from'),
                'id' => 'js_adv_search_user_browse_from'
            ],
            'to' => [
                'type' => 'select',
                'options' => $aAge,
                'select_value' => _p('to'),
                'id' => 'js_adv_search_user_browse_to'
            ],
            'country' => [
                'type' => 'select',
                'options' => Phpfox::getService('core.country')->get(),
                'search' => 'AND u.country_iso = \'[VALUE]\'',
                'add_any' => true,
                'id' => 'country_iso'
            ],
            'country_child_id' => [
                'type' => 'select',
                'search' => 'AND ufield.country_child_id = \'[VALUE]\'',
                'clone' => true
            ],
            'status' => [
                'type' => 'select',
                'options' => [
                    '2' => _p('all_members'),
                    '1' => _p('featured_members'),
                    '4' => _p('online'),
                    '3' => _p('pending_verification_members'),
                    '5' => _p('pending_approval'),
                    '6' => _p('not_approved')
                ],
                'default_view' => '2',
            ],
            'city' => [
                'type' => 'input:text',
                'size' => 15,
                'search' => 'AND ufield.city_location LIKE \'%[VALUE]%\''
            ],
            'zip' => [
                'type' => 'input:text',
                'size' => 10,
                'search' => 'AND ufield.postal_code = \'[VALUE]\''
            ],
            'ip' => [
                'type' => 'input:text',
                'size' => 10
            ],
        ];

        if (!Phpfox::getUserParam('user.can_search_by_zip')) {
            unset ($aFilters['zip']);
        }

        if (defined('PHPFOX_IS_ADMIN_SEARCH') && Phpfox::getParam('core.enable_spam_check')) {
            $aFilters['status']['options']['7'] = _p('spammers');
        }

        if ($sPlugin = Phpfox_Plugin::get('user.component_controller_browse_filter')) {
            eval($sPlugin);
        }

        $aSearchParams = [
            'type' => 'browse',
            'filters' => $aFilters,
            'search' => 'keyword',
            'custom_search' => true
        ];

        if (!defined('PHPFOX_IS_ADMIN_SEARCH')) {
            $aSearchParams['no_session_search'] = true;
        }

        $oFilter = Phpfox_Search::instance()->set($aSearchParams);

        $sStatus = $oFilter->get('status');
        $sView = $this->request()->get('view');
        $aCustomSearch = $oFilter->getCustom();
        $bIsOnline = false;
        $bPendingMail = false;
        $mFeatured = false;
        $bIsGender = false;

        switch ((int)$sStatus) {
            case 1:
                $mFeatured = true;
                break;
            case 3:
                if (defined('PHPFOX_IS_ADMIN_SEARCH')) {
                    $oFilter->setCondition('AND u.status_id = 1');
                }
                break;
            case 4:
                $bIsOnline = true;
                break;
            case 5:
                if (defined('PHPFOX_IS_ADMIN_SEARCH')) {
                    $oFilter->setCondition('AND u.view_id = 1');
                }
                break;
            case 6:
                if (defined('PHPFOX_IS_ADMIN_SEARCH')) {
                    $oFilter->setCondition('AND u.view_id = 2');
                }
                break;
            case 7:
                if (defined('PHPFOX_IS_ADMIN_SEARCH') && Phpfox::getParam('core.enable_spam_check', 0)) {
                    $oFilter->setCondition('AND u.total_spam > ' . Phpfox::getParam('core.auto_deny_items'));
                }
                break;
            default:

                break;
        }

        if ($bCustomSort) {
            $oFilter->setSort($aSearchSort[0]);
        }
        $this->template()->setTitle(_p('browse_members'))->setBreadCrumb(_p('browse_members'), ($aCallback !== false ? $this->url()->makeUrl($aCallback['url_home']) : $this->url()->makeUrl((defined('PHPFOX_IS_ADMIN_SEARCH') ? 'admincp.' : '') . 'user.browse')));

        if (!empty($sView)) {
            switch ($sView) {
                case 'online':
                    $bIsOnline = true;
                    break;
                case 'featured':
                    $mFeatured = true;
                    break;
                case 'spam':
                    $oFilter->setCondition('u.total_spam > ' . (int)Phpfox::getParam('core.auto_deny_items'));
                    break;
                case 'pending':
                    if (defined('PHPFOX_IS_ADMIN_SEARCH')) {
                        $oFilter->setCondition('u.view_id = 1');
                    }
                    break;
                case 'top':
                    $bExtendContent = true;
                    if (($iUserGenderTop = $this->request()->getInt('topgender'))) {
                        $oFilter->setCondition('AND u.gender = ' . (int)$iUserGenderTop);
                    }

                    $iFilterCount = 0;
                    $aFilterMenuCache = [];

                    $aFilterMenu = [
                        _p('all_members') => '',
                        _p('male') => '1',
                        _p('female') => '2'
                    ];

                    if ($sPlugin = Phpfox_Plugin::get('user.component_controller_browse_genders_top_users')) {
                        eval($sPlugin);
                    }

                    foreach ($aFilterMenu as $sMenuName => $sMenuLink) {
                        $iFilterCount++;
                        $aFilterMenuCache[] = [
                            'name' => $sMenuName,
                            'link' => $this->url()->makeUrl('user.browse', ['view' => 'top', 'topgender' => $sMenuLink]),
                            'active' => ($this->request()->get('topgender') == $sMenuLink ? true : false),
                            'last' => (count($aFilterMenu) === $iFilterCount ? true : false)
                        ];

                        if ($this->request()->get('topgender') == $sMenuLink) {
                            $this->template()->setTitle($sMenuName)->setBreadCrumb($sMenuName, null, true);
                        }
                    }

                    $this->template()->assign([
                            'aFilterMenus' => $aFilterMenuCache
                        ]
                    );

                    break;
                default:

                    break;
            }
        }

        $bIsSearch = false;
        $bAgeSearch = false;
        if (($iFrom = $oFilter->get('from')) || ($iFrom = $this->request()->getInt('from'))) {
            $oFilter->setCondition('AND u.birthday_search <= \'' . Phpfox::getLib('date')->mktime(0, 0, 0, 1, 1, $iYear - $iFrom) . '\'' . ' AND ufield.dob_setting IN(0,1,2)');
            $bIsGender = true;
            $bAgeSearch = true;
        }
        if (($iTo = $oFilter->get('to')) || ($iTo = $this->request()->getInt('to'))) {
            $oFilter->setCondition('AND u.birthday_search >= \'' . Phpfox::getLib('date')->mktime(0, 0, 0, 1, 1, $iYear - $iTo) . '\'' . ' AND ufield.dob_setting IN(0,1,2)');
            $bIsGender = true;
            $bAgeSearch = true;
        }
        if ($bAgeSearch) {
            $oFilter->setCondition('AND u.birthday IS NOT NULL');
        }

        if (($sLocation = $this->request()->get('location'))) {
            $oFilter->setCondition('AND u.country_iso = \'' . Phpfox_Database::instance()->escape($sLocation) . '\'');
            $bIsSearch = true;
        }

        if (($sGender = $this->request()->getInt('gender'))) {
            $oFilter->setCondition('AND u.gender = \'' . Phpfox_Database::instance()->escape($sGender) . '\'');
            $bIsSearch = true;
        }

        if (($sLocationChild = $this->request()->getInt('state'))) {
            $oFilter->setCondition('AND ufield.country_child_id = \'' . Phpfox_Database::instance()->escape($sLocationChild) . '\'');
            $bIsSearch = true;
        }

        if (($sLocationCity = $this->request()->get('city-name'))) {
            $oFilter->setCondition('AND ufield.city_location = \'' . Phpfox_Database::instance()->escape(Phpfox::getLib('parse.input')->convert($sLocationCity)) . '\'');
            $bIsSearch = true;
        }

        if (!defined('PHPFOX_IS_ADMIN_SEARCH')) {
            $oFilter->setCondition('AND u.status_id = 0 AND u.view_id = 0');
            if (Phpfox::isUser()) {
                $aBlockedUserIds = Phpfox::getService('user.block')->get(null, true);
                if (!empty($aBlockedUserIds)) {
                    $oFilter->setCondition('AND u.user_id NOT IN (' . implode(',', $aBlockedUserIds) . ')');
                }
            }
            if ($iGender = (int)$oFilter->get('gender')) {
                $oFilter->setCondition(in_array($iGender, [1, 2]) ? 'AND u.gender = ' . (int)$iGender : 'AND u.gender NOT IN (1,2)');
                $bIsSearch = true;
            }
        } else {
            $oFilter->setCondition('AND u.profile_page_id = 0');
        }

        if (defined('PHPFOX_IS_ADMIN_SEARCH') && ($sIp = $oFilter->get('ip'))) {
            Phpfox::getService('user.browse')->ip($sIp);
        }

        $bExtend = (defined('PHPFOX_IS_ADMIN_SEARCH') ? true : (((($oFilter->get('show') && $oFilter->get('show') == '2') || (!$oFilter->get('show'))) ? true : false)));
        $iPage = $this->request()->getInt('page');
        $iPageSize = $oFilter->getDisplay();

        if (($sPlugin = Phpfox_Plugin::get('user.component_controller_browse_filter_process'))) {
            eval($sPlugin);
        }

        $iCnt = 0;
        $aUsers = [];

        if ($oFilter->isSearch() || defined('PHPFOX_IS_ADMIN_SEARCH') || $bIsSearch) {
            $aConditions = $oFilter->getConditions();
            $sSort = $oFilter->getSort();
            list($iCnt, $aUsers) = Phpfox::getService('user.browse')->conditions($aConditions)
                ->callback($aCallback)
                ->sort($sSort)
                ->page($oFilter->getPage())
                ->limit($iPageSize)
                ->online($bIsOnline)
                ->extend((isset($bExtendContent) ? true : $bExtend))
                ->featured($mFeatured)
                ->pending($bPendingMail)
                ->custom($aCustomSearch)
                ->gender($bIsGender)
                ->get();

            $aUserExport = [
                'aConditions' => $aConditions,
                'sSort' => $sSort,
                'bIsOnline' => $bIsOnline,
                'mFeatured' => $mFeatured,
                'aCustomSearch' => $aCustomSearch,
                'bIsGender' => $bIsGender
            ];
            $this->template()->setHeader('cache', [
                '<script>window.sUserExportFilter = "' . base64_encode(json_encode($aUserExport)) . '";</script>'
            ]);
        } else {
            if ($bOldWay) {
                list($iCnt, $aUsers) = Phpfox::getService('user.browse')->conditions($oFilter->getConditions())
                    ->callback($aCallback)
                    ->sort($oFilter->getSort())
                    ->page($oFilter->getPage())
                    ->limit($iPageSize)
                    ->online($bIsOnline)
                    ->extend((isset($bExtendContent) ? true : $bExtend))
                    ->featured($mFeatured)
                    ->pending($bPendingMail)
                    ->custom($aCustomSearch)
                    ->gender($bIsGender)
                    ->get();
            }
            $this->template()->assign([
                'highlightUsers' => 1
            ]);
        }

        $iCnt = $oFilter->getSearchTotal($iCnt);
        $aNewCustomValues = [];
        if ($aCustomValues = $this->request()->get('custom')) {
            if (is_array($aCustomValues)) {
                foreach ($aCustomValues as $iKey => $sCustomValue) {
                    $aNewCustomValues['custom[' . $iKey . ']'] = $sCustomValue;
                }
            }
        }
        if (!(defined('PHPFOX_IS_ADMIN_SEARCH'))) {
            Phpfox_Pager::instance()->set([
                'page' => $iPage,
                'size' => $iPageSize,
                'count' => $iCnt,
                'ajax' => 'user.mainBrowse',
                'aParams' => $aNewCustomValues
            ]);
        } else {
            Phpfox_Pager::instance()->set(['page' => $iPage, 'size' => $iPageSize, 'count' => $iCnt]);
        }

        Phpfox_Url::instance()->setParam('page', $iPage);

        if ($this->request()->get('featured') == 1) {
            $this->template()->setHeader([
                    'drag.js' => 'static_script',
                    '<script type="text/javascript">$Behavior.coreDragInit = function() { Core_drag.init({table: \'#js_drag_drop\', ajax: \'user.setFeaturedOrder\'}); }</script>'
                ]
            )
                ->assign(['bShowFeatured' => 1]);
        }
        foreach ($aUsers as $iKey => $aUser) {
            if (!isset($aUser['user_group_id']) || empty($aUser['user_group_id']) || $aUser['user_group_id'] < 1) {
                $aUser['user_group_id'] = $aUsers[$iKey]['user_group_id'] = 5;
                Phpfox::getService('user.process')->updateUserGroup($aUser['user_id'], 5);
                $aUsers[$iKey]['user_group_title'] = _p('user_banned');
            }
            $aBanned = Phpfox::getService('ban')->isUserBanned($aUser);
            $aUsers[$iKey]['is_banned'] = $aBanned['is_banned'];
            $aUsers[$iKey]['is_friend_request'] = (Phpfox::isModule('friend') && Phpfox::getService('friend.request')->isRequested($aUser['user_id'], Phpfox::getUserId())) ? 3 : 0;
        }
        list($bFieldExist, $aCustomFields) = Phpfox::getService('custom')->getForPublic('user_profile', 0, true);

        $this->template()
            ->setHeader('cache', [
                    'country.js' => 'module_core',
                ]
            )->setPhrase(['friend_request_sent', 'request_sent', 'cancel_request', 'remove_friend', 'friend', 'add_as_friend'])
            ->assign([
                    'aUsers' => $aUsers,
                    'bExtend' => $bExtend,
                    'aCallback' => $aCallback,
                    'bIsSearch' => $oFilter->isSearch(),
                    'bIsInSearchMode' => ($this->request()->getInt('search-id') ? true : false),
                    'aForms' => $aCustomSearch,
                    'aCustomFields' => $aCustomFields,
                    'bShowAdvSearch' => $bFieldExist,
                    'sView' => $sView,
                    'sStatus' => $sStatus,
                    'bOldWay' => $bOldWay
                ]
            );
        // add breadcrumb if its in the featured members page and not in admin
        if (!(defined('PHPFOX_IS_ADMIN_SEARCH'))) {
            Phpfox::getUserParam('user.can_browse_users_in_public', true);

            $this->template()->setHeader('cache', [
                    'browse.js' => 'module_user'
                ]
            );

            if ($this->request()->get('view') == 'featured') {
                $this->template()->setBreadCrumb(_p('featured_members'), $this->url()->makeUrl('current'), true);

                $sTitle = _p('title_featured_members');
                if (!empty($sTitle)) {
                    $this->template()->setTitle($sTitle);
                }
            } elseif ($this->request()->get('view') == 'online') {
                $this->template()->setBreadCrumb(_p('menu_who_s_online'), $this->url()->makeUrl('current'), true);
                $sTitle = _p('title_who_s_online');
                if (!empty($sTitle)) {
                    $this->template()->setTitle($sTitle);
                }
            }
        }

        if ($aCallback !== false) {
            $this->template()->rebuildMenu('user.browse', $aCallback['url'])->removeUrl('user.browse', 'user.browse.view_featured');
        }

        $this->setParam('mutual_list', true);

        return null;
    }
}
