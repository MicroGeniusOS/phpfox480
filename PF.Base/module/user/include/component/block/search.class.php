<?php
defined('PHPFOX') or exit('NO DICE!');

class User_Component_Block_Search extends Phpfox_Component
{
    public function process()
    {
        $sView = $this->request()->get('view');
        $aSearch = $this->request()->getArray('search');
        $aCustomSearch = $this->request()->getArray('custom');
        if(!empty($aSearch['gender']))
        {
            $aCustomSearch['gender'] = $aSearch['gender'];
        }
        list(,$aCustomFields) = Phpfox::getService('custom')->getForPublic('user_profile', 0, true);
        if(is_array($aSearch) && !empty($aSearch))
        {
            $this->template()->assign(array(
                    'sCountryISO' => isset($aSearch['country']) ? Phpfox::getLib('parse.output')->htmlspecialchars($aSearch['country']) : '',
                    'sCountryChildId' => isset($aSearch['country_child_id']) ? Phpfox::getLib('parse.output')->htmlspecialchars($aSearch['country_child_id']) : '',
                    'aForms' => $aCustomSearch
                )
            );
        }

        $this->template()->assign([
            'sView' => $sView,
            'aCustomFields' => $aCustomFields,
            'aAboutMeCustomField' => Phpfox::getService('custom')->getFieldByName(('about_me')),
            'aGenders' => Phpfox::getService('core')->getGenders(true)
        ]);
        return 'block';
    }
}