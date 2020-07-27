<?php

if(!empty($this->_aVars['aFeed'])) {
    $aFeed = $this->_aVars['aFeed'];
    $noShowReport = ['user_status', 'friend', 'poke'];
    $aParts = explode('_', $aFeed['type_id']);
    if(!in_array($aFeed['type_id'], $noShowReport) && Phpfox::hasCallback($aParts[0],'getReportRedirect' . (isset($aParts[1]) ? ucfirst($aParts[1]) : '')) && $aFeed['user_id'] != Phpfox::getUserId() && (!isset($this->_aVars['sFeedType']) || isset($aFeed['feed_view_comment'])) && !isset($aFeed['report_module']))
    {
        echo "<li><a href=\"#?call=report.add&amp;height=100&amp;width=400&amp;type=". $aFeed['type_id']. "&amp;id=" . $aFeed['item_id'] ."\" class=\"inlinePopup activity_feed_report\" title=\"". _p('report'). "\">
				<span class=\"ico ico-warning-o\"></span>
				". _p('report'). "</a>
		</li>";
    }

    $showEdit = ['link', 'photo', 'v'];
    if(in_array($aFeed['type_id'], $showEdit) && (!isset($aFeed['is_view_item']) || isset($aFeed['feed_view_comment']))) {
        $canEdit = false;
        $module = '';
        $itemId = '';
        $feedCallback = $this->_aVars['aFeedCallback'];
        $feedType = $aFeed['type_id'];
        switch ($feedType) {
            case 'link': {
                $item = Phpfox::getService('link')->getLinkById($aFeed['item_id']);
                break;
            }
            case 'photo': {
                $item = Phpfox::getService('photo')->getPhotoItem($aFeed['item_id']);
                break;
            }
            case 'v': {
                $item = Phpfox::getService('v.video')->getForEdit($aFeed['item_id']);
                break;
            }
        }

        if(!empty($feedCallback['module']) && !empty($feedCallback['item_id'])) {
            $module = $feedCallback['module'];
            $itemId = $feedCallback['item_id'];
            if(in_array($module, ['pages', 'groups']) && !empty($item) && ($item['module_id'] == $module) && (defined('PHPFOX_IS_PAGES_VIEW') && defined('PHPFOX_PAGES_ITEM_TYPE') && PHPFOX_PAGES_ITEM_TYPE === $module)) {
                $isAdmin = Phpfox::getService($module)->isAdmin($aFeed['parent_user_id']);
                $canEdit = ($aFeed['user_id'] == Phpfox::getUserId()) || $isAdmin;
            } else {
                $canEdit = (Phpfox::getUserParam('feed.can_edit_own_user_status') && $aFeed['user_id'] == Phpfox::getUserId()) || Phpfox::getUserParam('feed.can_edit_other_user_status');
            }
        } else {
            if((in_array($feedType, ['link', 'photo'])  && empty($item['module_id'])) || ($feedType == 'v' && in_array($item['module_id'], ['user', 'video']))) {
                $canEdit = (Phpfox::getUserParam('feed.can_edit_own_user_status') && $aFeed['user_id'] == Phpfox::getUserId()) || Phpfox::getUserParam('feed.can_edit_other_user_status');
            }
        }

        if($canEdit) {
            echo "<li class=\"\"><a href=\"#\" class=\"\" onclick=\"tb_show('" ._p('edit_your_post')."', $.ajaxBox('feed.editUserStatus', 'height=400&amp;width=600&amp;id=". $aFeed['feed_id'] ."&amp;module=". $module ."&amp;item_id=". $itemId ."')); return false;\">
			<span class=\"ico ico-pencilline-o\"></span>". _p('edit') ."</a></li>";
        }
    }
}

if(Phpfox::getParam('feed.enable_hide_feed', 1)) {
    Phpfox_Template::instance()->getTemplate('feed.block.link-hide');
}
