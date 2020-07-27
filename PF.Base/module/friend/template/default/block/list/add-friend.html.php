<?php
defined('PHPFOX') or exit('NO DICE!');
?>
<div class="js_add_friend_to_list_block">
    <input type="hidden" id="js_friend_list_id" value="{$list_id}">
    <div class="form-group">
        <div id="js_selected_friends" class="hide_it"></div>
        {module name='friend.search' input='invite' hide=true in_form=true}
    </div>
    <div class="form-group">
        <button id="js_add_friend_to_list_btn" class="btn btn-primary">{_p var='save'}</button>
    </div>
</div>


<script type="text/javascript">
    var aFriendListMembers = {$aFriendListMembers};
    friend_AddFriendsToList.initMembers(aFriendListMembers);
</script>
