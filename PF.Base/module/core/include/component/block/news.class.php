<?php
defined('PHPFOX') or exit('NO DICE!');

/**
 * Class Core_Component_Block_News
 */
class Core_Component_Block_News extends Phpfox_Component
{
    /**
     * Controller
     */
    public function process()
    {
        // check if news slide is active
        $aNews = Phpfox::getService('core.admincp')->getNews();

        if ($aNews === false) {
            return false;
        }

        if (!Phpfox::getUserParam('core.can_view_news_updates')) {
            return false;
        }

        if ((is_array($aNews) && !count($aNews)) || is_bool($aNews)) {
            return false;
        }

        $this->template()->assign(array(
                'aPhpfoxNews' => $aNews
            )
        );

        return 'block';
    }

    /**
     * Garbage collector. Is executed after this class has completed
     * its job and the template has also been displayed.
     */
    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('core.component_block_news_clean')) ? eval($sPlugin) : false);
    }
}
