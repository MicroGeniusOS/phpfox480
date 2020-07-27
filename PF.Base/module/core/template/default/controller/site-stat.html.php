<?php
defined('PHPFOX') or exit('NO DICE!');
?>
<div class="block site-stat-container">
    {foreach from=$aStats key=sKey item=aStatItem}
    <div class="site-stat-group">
        <div class="title">
            {$sKey}
        </div>
        <div class="content">
            {foreach from=$aStatItem item=aStat}
            <div class="info">
                <div class="info_left" style="text-transform: capitalize">
                    {$aStat.phrase}:
                </div>
                <div class="info_right">
                    {if isset($aStat.link)}<a href="{$aStat.link}">{/if}{$aStat.value}{if isset($aStat.link)}</a>{/if}
                </div>
            </div>
            {/foreach}
        </div>
    </div>
    {/foreach}
</div>