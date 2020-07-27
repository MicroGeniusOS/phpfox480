<?php
defined('PHPFOX') or exit('NO DICE!');

class User_Component_Block_Admincp_Exportusers extends Phpfox_Component
{
    public function process()
    {
        $aFields = [
            'user_id' => [
                'is_main_field' => true,
                'text' => _p('id')
            ],
            'full_name' => [
                'is_main_field' => true,
                'text' => _p('display_name'),
            ],
            'user_name' => [
                'is_main_field' => true,
                'text' => _p('username'),
            ],
            'email' => [
                'is_main_field' => true,
                'text' => _p('email_address'),
            ],
            'user_group_id' => [
                'is_main_field' => true,
                'text' => _p('group_without_s'),
            ],// table user_group
            'last_activity' => [
                'is_main_field' => true,
                'text' => _p('last_activity'),
            ],
            'last_ip_address' => [
                'is_main_field' => true,
                'text' => _p('ban_filter_ip'),
            ],
            'gender' => [
                'is_main_field' => true,
                'text' => _p('gender'),
            ],
            'country_iso' => [
                'is_main_field' => true,
                'text' => _p('location'),
            ],
            'city_location' => [
                'is_main_field' => true,
                'text' => _p('city')
            ], //table user_field
            'postal_code' => [
                'is_main_field' => true,
                'text' => _p('zip_postal_code'),
            ],//table user_field
            'country_child_id' => [
                'is_main_field' => true,
                'text' => _p('state_province'),
            ],//table user_field
            'birthday_search' => [
                'is_main_field' => true,
                'text' => _p('age'),
            ],
        ];
        list(, $aCustomFields) = Phpfox::getService('custom')->getForPublic('user_profile', 0, true);
        if(!empty($aCustomFields))
        {
            foreach($aCustomFields as $aCustomField)
            {
                if(isset($aCustomField['fields']))
                {
                    foreach($aCustomField['fields'] as $customField)
                    {
                        $aFields['cf_'. $customField['field_name']] = [
                            'is_main_field' => false,
                            'text' => _p($customField['phrase_var_name'])
                        ];
                    }
                }
            }
        }
        $this->template()->assign([
            'aFields' => $aFields
        ]);
        return 'block';
    }
}