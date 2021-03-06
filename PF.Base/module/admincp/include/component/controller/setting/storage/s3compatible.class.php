<?php

/**
 * Class Admincp_Component_Controller_Setting_Storage_S3compatible
 * @since 4.8.0
 * @author phpfox
 */
class Admincp_Component_Controller_Setting_Storage_S3compatible extends Phpfox_Component
{
	const SERVICE_ID = 's3compatible';

	public function process()
	{
		$sError = null;
		$manager = Phpfox::getLib('storage.admincp');
		$storage_id = $this->request()->get('storage_id');
		$bIsEdit = !$storage_id;
		$aValidation = array(
			'storage_name' => array(
				'def' => 'string:required',
				'title' => _p('storage_name_is_required')
			),
			'key' => array(
				'def' => 'string:required',
				'title' => _p('amazon_key_id_is_required')
			),
			'secret' => array(
				'def' => 'string:required',
				'title' => _p('amazon_secret_key_is_required')
			),
			'bucket' => array(
				'def' => 'string:required',
				'title' => _p('bucket_is_required')
			),
			'region' => array(
				'def' => 'string:required',
				'title' => _p('region_is_required')
			),

		);

		$oValid = Phpfox::getLib('validator')->set(array(
				'sFormName' => 'js_storage_dos_form',
				'aParams' => $aValidation
			)
		);
		$aVals = $this->request()->get('val');
		if (!empty($aVals) && $oValid->isValid($aVals)) {
			$bIsActive = !!$aVals['is_active'];
			$bIsDefault = !!$aVals['is_default'];

			if ($bIsDefault) {
				$bIsActive = true;
			}

			$bIsValid = true;
			$config = [
				'key' => $aVals['key'],
				'secret' => $aVals['secret'],
				'region' => $aVals['region'],
				'bucket' => $aVals['bucket'],
				'endpoint' => $aVals['endpoint'],
				'base_url' => $aVals['base_url'],
				'cdn_base_url' => $aVals['cdn_base_url'],
				'cdn_enabled' => $aVals['cdn_base_url'],
				'prefix' => isset($aVals['prefix']) ? $aVals['prefix'] : '',
			];

			if ($bIsActive) {
				try {
					$bIsValid = $manager->verifyStorageConfig(self::SERVICE_ID, $config);
					if (!$bIsValid) {
						$sError = 'Invalid configuration';
					}

				} catch (Exception $exception) {
					$bIsValid = false;
					$sError = $exception->getMessage();
				}
			}


			if ($bIsValid) {
				$storage_name = isset($aVals['storage_name']) ? $aVals['storage_name'] : '';
				if ($storage_id) {
					$manager->updateStorageConfig($storage_id, self::SERVICE_ID, $storage_name, $bIsDefault, $bIsActive, $config);
					Phpfox::addMessage(_p('Your changes have been saved!'));
				} else {
					$manager->createStorage($storage_id, self::SERVICE_ID, $storage_name, $bIsDefault, $bIsActive, $config);
					Phpfox::addMessage(_p('Your changes have been saved!'));
					Phpfox::getLib('url')->send('admincp.setting.storage.manage');
				}
			}

		} else if ($storage_id) {
			$aVals = $manager->getStorageConfig($storage_id);
		} else {
			$aVals = [
				'storage_name' => _p('s3compatible')
			];
		}

		$this->template()
			->clearBreadCrumb()
			->setBreadCrumb(_p('storage_system'), $this->url()->makeUrl('admincp.setting.storage.manage'));

		if ($bIsEdit) {
			$this->template()
				->setBreadCrumb(_p('add_storage'), $this->url()->makeUrl('admincp.setting.storage.add'));
		}

		$this->template()
			->setTitle(_p('s3compatible'))
			->setBreadCrumb(_p('s3compatible'))
			->setActiveMenu('admincp.setting.storage')
			->assign([
				'sCreateJs' => $oValid->createJS(),
				'sGetJsForm' => $oValid->getJsForm(),
				'aForms' => $aVals,
				'sError' => $sError
			]);
	}
}