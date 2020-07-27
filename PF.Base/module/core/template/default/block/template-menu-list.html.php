{if Phpfox::getUserBy('profile_page_id') <= 0 && isset($aMainMenus)}
{plugin call='theme_template_core_menu_list'}
{foreach from=$aMainMenus key=iKey item=aMainMenu name=menu}
    <li rel="menu{$aMainMenu.menu_id}" {if (isset($iTotalHide) && isset($iMenuCnt) && $iMenuCnt > $iTotalHide)} style="display:none;" {/if} {if (($aMainMenu.url == 'apps' && count($aInstalledApps)) || (isset($aMainMenu.children) && count($aMainMenu.children))) || (isset($aMainMenu.is_force_hidden))}class="{if isset($aMainMenu.is_force_hidden) && isset($iTotalHide)}is_force_hidden{else}explore{/if}{if ($aMainMenu.url == 'apps' && count($aInstalledApps))} explore_apps{/if}"{/if}>
        <a {if !isset($aMainMenu.no_link) || $aMainMenu.no_link != true}href="{url link=$aMainMenu.url}" {else} href="#" onclick="return false;" {/if} class="{if isset($aMainMenu.is_selected) && $aMainMenu.is_selected} menu_is_selected {/if}{if isset($aMainMenu.external) && $aMainMenu.external == true}no_ajax_link {/if}ajax_link">
        {if isset($aMainMenu.mobile_icon) && $aMainMenu.mobile_icon}
            <i class="{$aMainMenu.mobile_icon}"></i>
        {else}
            <i class="ico ico-box-o"></i>
        {/if}
        <span>
            {_p var=$aMainMenu.var_name}{if isset($aMainMenu.suffix)}{$aMainMenu.suffix}{/if}
        </span>
        </a>
    </li>
{/foreach}
{/if}