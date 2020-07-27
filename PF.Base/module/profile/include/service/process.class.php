<?php
defined('PHPFOX') or exit('NO DICE!');

/**
 * @deprecated in 4.7.0
 * Class Profile_Service_Process
 */
class Profile_Service_Process extends Phpfox_Service
{
    public function saveTempFileToLocalServer($profileImage)
    {
        $profileImagePath = Phpfox::getService('core.temp-file')->getProfile();
        $aProfileImage = explode('.', $profileImage);
        if($profileImagePath) {
            @unlink(Phpfox::getParam('core.dir_file_temp') . $profileImagePath);
        }
        $tempProfile = 'temp_profile_image_' . uniqid() . '.' . end($aProfileImage);
        $tempFile = Phpfox::getParam('core.dir_file_temp') . $tempProfile;

        if (!is_dir(Phpfox::getParam('core.dir_file_temp'))) {
            Phpfox_File::instance()->mkdir(Phpfox::getParam('core.dir_file_temp'), true, 777);
        }
        Phpfox_File::instance()->write($tempFile, fox_get_contents($profileImage));
        if($profileImagePath) {
            Phpfox::getService('core.temp-file')->updateProfile([
                'size'      => filesize($tempFile),
                'path'      => str_replace(Phpfox::getParam('core.dir_file_temp'), '', $tempFile),
            ]);
        }
        else {
            Phpfox::getService('core.temp-file')->add([
                'type'      => 'profile',
                'size'      => filesize($tempFile),
                'path'      => str_replace(Phpfox::getParam('core.dir_file_temp'), '', $tempFile),
                'server_id' => 1
            ]);
        }

        return str_replace(Phpfox::getParam('core.dir_file_temp'), Phpfox::getParam('core.url_file_temp'), $tempFile);
    }

    /**
     * @param $sMethod
     * @param $aArguments
     *
     * @return null
     */
    public function __call($sMethod, $aArguments)
    {
        /**
         * Check if such a plug-in exists and if it does call it.
         */
        if ($sPlugin = Phpfox_Plugin::get('profile.service_process__call')) {
            eval($sPlugin);

            return null;
        }

        /**
         * No method or plug-in found we must throw a error.
         */
        Phpfox_Error::trigger('Call to undefined method ' . __CLASS__ . '::' . $sMethod . '()', E_USER_ERROR);
    }
}
