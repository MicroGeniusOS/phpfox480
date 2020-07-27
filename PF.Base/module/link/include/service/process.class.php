<?php
/**
 * [PHPFOX_HEADER]
 */

defined('PHPFOX') or exit('NO DICE!');

/**
 *
 *
 * @copyright        [PHPFOX_COPYRIGHT]
 * @author        phpFox LLC
 * @package        Phpfox_Service
 * @version        $Id: process.class.php 6496 2013-08-23 11:34:09Z Fern $
 */
class Link_Service_Process extends Phpfox_Service
{
    private $_iLinkId = 0;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->_sTable = Phpfox::getT('link');
    }

    public function add($aVals, $bIsCustom = false, $aCallback = null)
    {
        if (!defined('PHPFOX_FORCE_IFRAME')) {
            define('PHPFOX_FORCE_IFRAME', true);
        }

        $sStatus = isset($aVals['status_info']) ? $aVals['status_info'] : '';
        if ((trim($aVals['link']['url']) == trim($sStatus)) && (!empty($aVals['link']['title']) || !empty($aVals['link']['description']))) {
            $sStatus = null;
        }

        $needInsert = false;
        $feed = [];
        $feedId = isset($aVals['feed_id']) ? $aVals['feed_id'] : 0;
        $sOldContent = '';
        if ($feedId) {
            $feed = Phpfox::getService('feed')->getUserStatusFeed($aCallback, $feedId);
            if (!$feed) {
                return false;
            }
            if (!(
                (Phpfox::getUserParam('feed.can_edit_own_user_status') && $feed['user_id'] == Phpfox::getUserId())
                || Phpfox::getUserParam('feed.can_edit_other_user_status')
                || (isset($aCallback['module']) && isset($aCallback['item_id']) && Phpfox::hasCallback($aCallback['module'], 'isAdmin') && Phpfox::callback($aCallback['module'] . '.isAdmin', $aCallback['item_id']))
            )) {
                return false;
            }
            if (!isset($aVals['privacy_comment'])) {
                $aVals['privacy_comment'] = $feed['privacy_comment'];
            }
            if (!isset($aVals['privacy'])) {
                $aVals['privacy'] = $feed['privacy'];
            }
            $sOldContent = (isset($feed['feed_status']) && $feed['feed_status']) ? $feed['feed_status'] : '';
            $isNormalStatus = in_array($feed['type_id'], ['user_status', 'feed_comment']);
            $needInsert = !empty($feed['type_id']) && ($isNormalStatus || (!empty($aCallback['module']) && $feed['type_id'] == ($aCallback['module'] . '_' . 'comment'))) ? true : false;
        }

        if (!isset($aVals['privacy_comment'])) {
            $aVals['privacy_comment'] = 0;
        }

        if (!isset($aVals['privacy'])) {
            $aVals['privacy'] = 0;
        }

        $aInsert = [
            'is_custom' => ($bIsCustom ? '1' : '0'),
            'link' => $this->preParse()->clean($aVals['link']['url'], 255),
            'image' => ((isset($aVals['link']['image_hide']) && $aVals['link']['image_hide'] == '1') || !isset($aVals['link']['image']) ? null : $this->preParse()->clean($aVals['link']['image'], 1023)),
            'title' => (isset($aVals['link']['title']) ? $this->preParse()->clean($aVals['link']['title'], 255) : ''),
            'description' => isset($aVals['link']['description']) ? $this->preParse()->clean($aVals['link']['description'], 200) : '',
            'status_info' => (empty($sStatus) ? null : $this->preParse()->prepare($sStatus)),
            'privacy' => (int)$aVals['privacy'],
            'has_embed' => (empty($aVals['link']['embed_code']) ? '0' : '1')
        ];

        if (!$feedId || $needInsert) {
            $aInsert = array_merge($aInsert, [
                'user_id' => Phpfox::getUserId(),
                'module_id' => ($aCallback === null ? null : $aCallback['module']),
                'item_id' => ($aCallback === null ? 0 : $aCallback['item_id']),
                'parent_user_id' => (isset($aVals['parent_user_id']) ? (int)$aVals['parent_user_id'] : 0),
                'privacy_comment' => (int)$aVals['privacy_comment'],
                'time_stamp' => PHPFOX_TIME,
            ]);
        }

        if (isset($aVals['location']) && isset($aVals['location']['latlng']) && !empty($aVals['location']['latlng'])) {
            $aMatch = explode(',', $aVals['location']['latlng']);
            $aMatch['latitude'] = floatval($aMatch[0]);
            $aMatch['longitude'] = floatval($aMatch[1]);
            $aInsert['location_latlng'] = json_encode(['latitude' => $aMatch['latitude'], 'longitude' => $aMatch['longitude']]);
        } else {
            $aInsert['location_latlng'] = null;
        }

        if (isset($aInsert['location_latlng']) && !empty($aInsert['location_latlng']) && isset($aVals['location']) && isset($aVals['location']['name']) && !empty($aVals['location']['name'])) {
            $aInsert['location_name'] = Phpfox::getLib('parse.input')->clean($aVals['location']['name']);
        } else {
            $aInsert['location_name'] = null;
        }

        if (!$feedId || $needInsert) {
            $iId = $this->database()->insert($this->_sTable, $aInsert);
            if ($needInsert && isset($feed)) { // edit feed type
                $feedTypeId = $feed['type_id'];
                $feedItemId = (int)$feed['item_id'];
                if ($feedTypeId == 'user_status') {
                    db()->delete(Phpfox::getT('user_status'), 'status_id = ' . $feedItemId);
                } elseif ($feedTypeId == 'feed_comment') {
                    db()->delete(Phpfox::getT('feed_comment'), 'feed_comment_id = ' . $feedItemId);
                } else {
                    $table = $aCallback['table_prefix'] . 'feed_comment';
                    db()->delete(Phpfox::getT($table), 'feed_comment_id = ' . $feedItemId);
                }
                $feedTable = (!empty($aCallback['table_prefix']) ? $aCallback['table_prefix'] : '') . 'feed';
                $updateFeedTable = [
                    'type_id' => 'link', 'item_id' => $iId
                ];
                $updateFeedTable['privacy'] = $aVals['privacy'];
                $updateFeedTable['privacy_comment'] = $aVals['privacy_comment'];
                db()->update(Phpfox::getT($feedTable), $updateFeedTable, 'type_id = "' . $feedTypeId . '" AND item_id = ' . $feedItemId);
                //Update feed in phpfox_feed
                if (in_array($aCallback['module'], ['pages', 'groups'])) {
                    db()->update(Phpfox::getT('feed'), $updateFeedTable, 'type_id = "' . $feedTypeId . '" AND item_id = ' . $feedItemId);
                }
            }
        } else {
            $feedTypeId = $feed['type_id'];
            $iId = $feedItemId = (int)$feed['item_id'];
            $updateFeedTable = [
                'privacy' => $aVals['privacy'],
                'privacy_comment' => $aVals['privacy_comment']
            ];
            $feedTable = (!empty($aCallback['table_prefix']) ? $aCallback['table_prefix'] : '') . 'feed';
            db()->update(Phpfox::getT($feedTable), $updateFeedTable, 'type_id = "' . $feedTypeId . '" AND item_id = ' . $feedItemId);
            //Update feed in phpfox_feed
            if (in_array($aCallback['module'], ['pages', 'groups'])) {
                db()->update(Phpfox::getT('feed'), $updateFeedTable, 'type_id = "' . $feedTypeId . '" AND item_id = ' . $feedItemId);
            }
            db()->update($this->_sTable, $aInsert, 'link_id = ' . $feedItemId);
        }

        if (Phpfox::isModule('tag') && Phpfox::getParam('tag.enable_hashtag_support')) {
            Phpfox::getService('tag.process')->add('link', $iId, Phpfox::getUserId(), $sStatus, true);
        }

        if ($feedId) {
            $count = db()->select('COUNT(*)')
                ->from(Phpfox::getT('link_embed'))
                ->where('link_id = ' . $iId)
                ->execute('getSlaveField');
            $embedCode = !empty($aVals['link']['embed_code']) ? $this->preParse()->prepare($aVals['link']['embed_code']) : '';
            if ($count) {
                db()->update(Phpfox::getT('link_embed'), ['embed_code' => $embedCode], 'link_id = ' . (int)$iId);
            } else {
                if (!empty($embedCode)) {
                    db()->insert(Phpfox::getT('link_embed'), [
                            'link_id' => $iId,
                            'embed_code' => $this->preParse()->prepare($aVals['link']['embed_code'])
                        ]
                    );
                }
            }
        } else {
            if (!empty($aVals['link']['embed_code'])) {
                $this->database()->insert(Phpfox::getT('link_embed'), [
                        'link_id' => $iId,
                        'embed_code' => $this->preParse()->prepare($aVals['link']['embed_code'])
                    ]
                );
            }
        }

        if (!$feedId && $aCallback === null && isset($aVals['parent_user_id']) && $aVals['parent_user_id'] > 0 && $aVals['parent_user_id'] != Phpfox::getUserId()) {
            $aUser = $this->database()->select('user_name')
                ->from(Phpfox::getT('user'))
                ->where('user_id = ' . (int)$aVals['parent_user_id'])
                ->execute('getSlaveRow');

            $sLink = Phpfox_Url::instance()->makeUrl($aUser['user_name'], ['link-id' => $iId]);

            Phpfox::getLib('mail')->to($aVals['parent_user_id'])
                ->subject(['link.full_name_posted_a_link_on_your_wall', ['full_name' => Phpfox::getUserBy('full_name')]])
                ->message(['link.full_name_posted_a_link_on_your_wall_message', ['full_name' => Phpfox::getUserBy('full_name'), 'link' => $sLink]])
                ->notification('comment.add_new_comment')
                ->send();

            if (Phpfox::isModule('notification')) {
                Phpfox::getService('notification.process')->add('feed_comment_link', $iId, $aVals['parent_user_id']);
            }
        }

        if (Phpfox::isModule('privacy') && $aVals['privacy'] == '4') {
            $function = $feedId ? 'update' : 'add';
            Phpfox::getService('privacy.process')->{$function}('link', $iId, (isset($aVals['privacy_list']) ? $aVals['privacy_list'] : []));
        }

        $feedId = (!$feedId ? Phpfox::getService('feed.process')->callback($aCallback)->add('link', $iId, $aVals['privacy'], $aVals['privacy_comment'], (isset($aVals['parent_user_id']) ? (int)$aVals['parent_user_id'] : 0)) : $feedId);

        // get old data (mention and tagged)
        $oldMentions = Phpfox::getService('user.process')->getIdFromMentions($sOldContent, true, false);
        $aOldTagged = $needInsert ? $this->cache()->get('tagged_users_' . $feed['type_id'] . '_' . $feed['item_id']) : $this->cache()->get('tagged_users_link_' . $iId);
        $aOldTagged = !empty($aOldTagged) ? array_column($aOldTagged, 'user_id') : [];

        // notification to tagged and mentioned friends
        $this->notifyTaggedInFeed($sStatus, $iId, Phpfox::getUserId(), $feedId, $aVals['tagged_friends'], $aVals['privacy'], (isset($aVals['parent_user_id']) ? (int)$aVals['parent_user_id'] : 0), $aOldTagged, $oldMentions, (isset($aCallback['module']) ? $aCallback['module'] : ''));

        $this->_iLinkId = $iId;

        return ($bIsCustom ? $iId : $feedId);
    }

    /**
     * @param $sContent
     * @param $iItemId
     * @param $iOwnerId
     * @param int $iFeedId
     * @param string $taggedFriends
     * @param int $iPrivacy
     * @param int $iParentUserId
     * @param array $aOldTagged
     * @param array $oldMentions
     * @param string $moduleId
     * @return bool
     */
    public function notifyTaggedInFeed($sContent, $iItemId, $iOwnerId, $iFeedId = 0, $taggedFriends = '', $iPrivacy = 0, $iParentUserId = 0, $aOldTagged = [], $oldMentions = [], $moduleId = '')
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
            'feed_type' => 'link',
            'content' => $sContent,
            'owner_id' => $iOwnerId,
            'privacy' => $iPrivacy,
            'tagged_friend' => $aTagged,
            'item_id' => $iItemId,
            'feed_id' => $iFeedId,
            'parent_user_id' => $iParentUserId,
            'old_tagged_friend' => $aOldTagged,
            'old_mentioned_friend' => $oldMentions,
            'module_id' => $moduleId
        ]);
    }

    public function getInsertId()
    {
        return (int)$this->_iLinkId;
    }

    public function delete($iId)
    {
        $aLink = $this->database()->select('l.*, a.*')
            ->from(Phpfox::getT('link'), 'l')
            ->join(Phpfox::getT('attachment'), 'a', 'a.link_id = l.link_id')
            ->where('l.link_id = ' . (int)$iId)
            ->execute('getSlaveRow');

        if (!isset($aLink['link_id'])) {
            return false;
        }

        if ((Phpfox::getUserParam('attachment.delete_own_attachment') && $aLink['user_id'] == Phpfox::getUserId()) || Phpfox::getUserParam('attachment.delete_user_attachment')) {
            $this->database()->delete(Phpfox::getT('link'), 'link_id = ' . (int)$aLink['link_id']);
            Phpfox::getService('attachment.process')->updateItemCount($aLink['category_id'], $aLink['attachment_id'], '-');

            if (!empty($aLink['attachment_id'])) {
                $this->database()->delete(Phpfox::getT('attachment'), 'attachment_id = ' . (int)$aLink['attachment_id']);
            }
        }

        return false;
    }

    /**
     * If a call is made to an unknown method attempt to connect
     * it to a specific plug-in with the same name thus allowing
     * plug-in developers the ability to extend classes.
     *
     * @param string $sMethod is the name of the method
     * @param array $aArguments is the array of arguments of being passed
     */
    public function __call($sMethod, $aArguments)
    {
        /**
         * Check if such a plug-in exists and if it does call it.
         */
        if ($sPlugin = Phpfox_Plugin::get('link.service_process__call')) {
            eval($sPlugin);
            return null;
        }

        /**
         * No method or plug-in found we must throw a error.
         */
        Phpfox_Error::trigger('Call to undefined method ' . __CLASS__ . '::' . $sMethod . '()', E_USER_ERROR);
    }
}