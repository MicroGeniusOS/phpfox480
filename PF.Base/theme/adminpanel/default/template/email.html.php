<?php 
 
defined('PHPFOX') or exit('NO DICE!'); 

?>
{if $bHtml}	
	{if isset($sName)}
	{_p var='hello_name' name=$sName}
	{else}
	{_p var='hello'}
	{/if},
	<br />
	<br />
	{$sMessage}
	<br />
	<br />
	{$sEmailSig}	
{else}	
	{if isset($sName)}
	{_p var='hello_name' name=$sName}
	{else}
	{_p var='hello'}
	{/if},
	{$sMessage}

	{$sEmailSig}	
{/if}