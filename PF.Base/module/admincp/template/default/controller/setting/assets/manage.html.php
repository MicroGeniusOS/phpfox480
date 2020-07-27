<?php
defined('PHPFOX') or exit('NO DICE!');
?>
<div>
    <form method="post" class="form" action="{url link='current'}" id="js_form">
        <p>{_p var='assets_form_description'}</p>
        <div id="client_details" class="panel panel-default">
            <div class="panel-body">
                <div>

                    <div class="form-group">
                        <label for="pf_assets_storage_id">{_p var='storage'}</label>
                        {foreach from=$aItems item=aItem}
                        <div>
                            {if !$aEnvironmentVars.pf_assets_storage_id || $aForms.pf_assets_storage_id == $aItem.storage_id}
                            <label style="font-weight: normal !important;">
                                <input type="radio" value="{$aItem.storage_id}" name="val[pf_assets_storage_id]" {if $aForms.pf_assets_storage_id == $aItem.storage_id}checked{/if}/>
                                &nbsp;{if $aItem.storage_name}{$aItem.storage_name}{else}{$aItem.service_id}:{$aItem.storage_id}{/if}
                            </label>
                            {/if}
                        </div>
                        {/foreach}
                        {if $aEnvironmentVars.pf_assets_storage_id}
                        <div class="help-block">
                            {_p var='this_configuration_is_set_in_a_configuration_file'}
                        </div>
                        {/if}
                    </div>
                    <div class="form-group">
                        <label for="pf_assets_cdn_url">{_p var='cdn_base_url'}</label>
                        <input class="form-control" type="text" name="val[pf_assets_cdn_url]"
                               {if $aEnvironmentVars.pf_assets_cdn_url}readonly{/if}
                               id="pf_assets_cdn_url" value="{value type='input' id='pf_assets_cdn_url'}"
                               placeholder="{_p var='cdn_base_url'}"/>
                        {if $aEnvironmentVars.pf_assets_cdn_url}
                        <div class="help-block">
                            {_p var='this_configuration_is_set_in_a_configuration_file'}
                        </div>
                        {/if}
                    </div>
                    <div class="form-group">
                        <label>{_p var='enable_cdn'}</label>
                        {if $aEnvironmentVars.pf_assets_cdn_enable}
                        <input type="hidden" name="val[pf_assets_cdn_enable]" value="{$aForms.pf_assets_cdn_enable}" />
                        <div class="pull-right">
                            <span class="text-info">{if $aForms.pf_assets_cdn_enable}{_p var='enabled'}{else}{_p var='disabled'}{/if}</span>
                        </div>
                        <div class="help-block">
                            {_p var='this_configuration_is_set_in_a_configuration_file'}
                        </div>
                        {else}
                        <div class="item_is_active_holder">
                            <span class="js_item_active item_is_active">
                                <input type="radio" name="val[pf_assets_cdn_enable]" value="1" {value type='radio' id='pf_assets_cdn_enable' default='1'}/>
                            </span>
                            <span class="js_item_active item_is_not_active">
                                <input type="radio" name="val[pf_assets_cdn_enable]" value="0" {value type='radio' id='pf_assets_cdn_enable' default='0' selected='true'}/>
                            </span>
                        </div>
                        {/if}
                    </div>
                    <div class="form-group">
                        <label>{_p var="pf_core_bundle_js_css"}</label>
                        {if $aEnvironmentVars.pf_core_bundle_js_css}
                        <input type="hidden" name="val[pf_core_bundle_js_css]" value="{$aForms.pf_core_bundle_js_css}" />
                        <div class="pull-right">
                            <span class="text-info">{if $aForms.pf_core_bundle_js_css}{_p var='enabled'}{else}{_p var='disabled'}{/if}</span>
                        </div>
                        {else}
                        <div class="item_is_active_holder">
                            <span class="js_item_active item_is_active">
                                <input type="radio" name="val[pf_core_bundle_js_css]" value="1" {value type='radio' id='pf_core_bundle_js_css' default='1'}/>
                            </span>
                            <span class="js_item_active item_is_not_active">
                                <input type="radio" name="val[pf_core_bundle_js_css]" value="0" {value type='radio' id='pf_core_bundle_js_css' default='0' selected='true'}/>
                            </span>
                        </div>
                        <div class="help-block">
                            {_p var='this_configuration_is_set_in_a_configuration_file'}
                        </div>
                        {/if}
                    </div>
                    <div class="form-group">
                        <button class="btn btn-primary" type="submit" role="button">{_p var='save_changes'}</button>
                        <a class="btn btn-default" role="button" href="{url link='admincp'}">{_p var='cancel'}</a>
                    </div>
                </div>
            </div>
    </form>

</div>