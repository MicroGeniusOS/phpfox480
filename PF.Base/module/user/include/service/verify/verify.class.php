<?php
defined('PHPFOX') or exit('NO DICE!');

/**
 * Class User_Service_Verify_Verify
 */
class User_Service_Verify_Verify extends Phpfox_Service
{
    /**
     * @var string
     */
    protected $_sTable = '';

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->_sTable = Phpfox::getT('user');
    }


    /**
     * @param $userId
     * @return array|bool|int|string
     */
    public function getVerificationTimeByUserId($userId)
    {
        if (empty($userId)) {
            return false;
        }
        $time = db()->select('time_stamp')
            ->from(Phpfox::getT('user_verify'))
            ->where('user_id = ' . (int)$userId)
            ->execute('getSlaveField');
        return $time ? $time : 0;
    }

    /**
     * @param string $sEmail
     * @return string
     */
    public function getVerifyHashByEmail($sEmail)
    {
        if (!$sEmail)
            return false;

        $hashCode = $this->database()
            ->select('hash_code')
            ->from(Phpfox::getT('user_verify'))
            ->where('email=\'' . $this->database()->escape($sEmail) . '\'')
            ->execute('getSlaveField');

        if (empty($hashCode)) {
            $hashCode = $this->generateHash($sEmail);
        }
        return $hashCode;
    }

    public function generateHash($sEmail)
    {
        $aUser = $this->database()
            ->select('user_id, email, password')
            ->from($this->_sTable)
            ->where('email = \'' . $this->database()->escape($sEmail) . '\'')
            ->execute('getSlaveRow');
        $sHash = '';
        if ($aUser) {
            if (Phpfox::getParam('core.registration_sms_enable')) {
                $sHash = Phpfox::getLib('phpfox.verify')->generateOneTimeTokenToSMS();
            } elseif (Phpfox::getParam('user.verify_email_at_signup')) {
                $sHash = $this->getVerifyHash($aUser);
            }
            if ($sHash) {
                $this->database()->insert(':user_verify', [
                    'user_id' => $aUser['user_id'],
                    'hash_code' => $sHash,
                    'time_stamp' => PHPFOX_TIME,
                    'email' => $aUser['email']
                ]);
            }
        }
        return $sHash;
    }

    /**
     * Generates the unique hash to be used when verifying email addresses
     * @param array $aUser
     * @return String 50~52 chars
     */
    public function getVerifyHash($aUser)
    {
        return Phpfox::getLib('hash')->setRandomHash($aUser['user_id'] . $aUser['email'] . $aUser['password'] . Phpfox::getParam('core.salt'));
    }

    /**
     * If a call is made to an unknown method attempt to connect
     * it to a specific plug-in with the same name thus allowing
     * plug-in developers the ability to extend classes.
     *
     * @param string $sMethod is the name of the method
     * @param array $aArguments is the array of arguments of being passed
     *
     * @return null
     */
    public function __call($sMethod, $aArguments)
    {
        /**
         * Check if such a plug-in exists and if it does call it.
         */
        if ($sPlugin = Phpfox_Plugin::get('user.service_activity__call')) {
            eval($sPlugin);
            return null;
        }

        /**
         * No method or plug-in found we must throw a error.
         */
        Phpfox_Error::trigger('Call to undefined method ' . __CLASS__ . '::' . $sMethod . '()', E_USER_ERROR);
    }
}
