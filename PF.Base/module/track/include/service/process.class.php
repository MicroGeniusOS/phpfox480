<?php
/**
 * [PHPFOX_HEADER]
 */

defined('PHPFOX') or exit('NO DICE!');

/**
 *
 *
 * @copyright        [PHPFOX_COPYRIGHT]
 * @author        phpFox LLC
 * @package        Module_Track
 * @version        $Id: process.class.php 5594 2013-03-28 14:36:07Z phpFox LLC $
 */
class Track_Service_Process extends Phpfox_Service
{
    /**
     * Class constructor 2
     */
    public function __construct()
    {

    }

    public function add($sType, $iId, $iUserId = null)
    {
        if (Phpfox::getUserBy('is_invisible')) {
            return false;
        }

        return Phpfox::hasCallback($sType, 'addTrack') ? Phpfox::callback($sType . '.addTrack', $iId, $iUserId) :  true;
    }

    public function update($sTypeId, $iId)
    {
        if (!Phpfox::isUser()) {
            return false;
        }

        $this->database()->update(Phpfox::getT('track'), [
            'time_stamp' => PHPFOX_TIME
        ], 'item_id = ' . (int)$iId . ' AND user_id = ' . Phpfox::getUserId() . ' AND type_id=\'' . $sTypeId . '\'');
        return null;
    }

    public function remove($sType, $iId, $iUserId = null)
    {
        return Phpfox::hasCallback($sType, 'removeTrack') ? Phpfox::callback($sType . '.removeTrack', $iId, $iUserId) : true;
    }

    public function __call($sMethod, $aArguments)
    {
        if ($sPlugin = Phpfox_Plugin::get('track.service_process__call')) {
            eval($sPlugin);
            return null;
        }

        Phpfox_Error::trigger('Call to undefined method ' . __CLASS__ . '::' . $sMethod . '()', E_USER_ERROR);
    }
}