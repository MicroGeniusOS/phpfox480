<?php 
defined('PHPFOX') or exit('NO DICE!');
?>

<div class="table-responsive">
    <div id="js_statistic_total_item">
        <table class="table table-admin">
            <tbody>
            <tr>
                <td class="w140">{_p('user_group')}</td>
                <td class="w140">
                    {if ($aUser.status_id == 1)}
                        <div class="js_verify_email_{$aUser.user_id}">{_p var='pending_email_verification'}</div>
                    {/if}
                    {if Phpfox::getParam('user.approve_users') && $aUser.view_id == '1'}
                        <span id="js_user_pending_group_{$aUser.user_id}">{_p var='pending_approval'}</span>
                    {elseif $aUser.view_id == '2'}
                        {_p var='not_approved'}
                    {else}
                        {$aUser.title|convert}
                    {/if}
                </td>
            </tr>
            {foreach from=$aStats name=stat key=iKey item=aStat}
                <tr>
                    <td class="w140">{$aStat.name}</td>
                    <td class="w140">{$aStat.total}</td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
</div>