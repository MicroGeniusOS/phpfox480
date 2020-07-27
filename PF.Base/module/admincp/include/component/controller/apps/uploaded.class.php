<?php

defined('PHPFOX') or exit('NO DICE!');

use Core\Installation\Manager as InstallationManager;

/**
 * Class Admincp_Component_Controller_Apps_index
 */
class Admincp_Component_Controller_Apps_Uploaded extends Phpfox_Component
{

	/**
	 * @param bool $showAll
	 * @return array
	 */
	private function getUploadedApps($showAll = false)
	{
		$uploadedApps = [];

		$installedApps = array_reduce(db()
			->select('a.*')
			->from(':apps', 'a')
			->execute('getSlaveRows'), function ($carry, $item) {
			$carry[$item['apps_id']] = $item;
			return $carry;
		}, []);


		foreach (scandir(PHPFOX_DIR_SITE_APPS) as $dir) {
			if (substr($dir, 0, 1) == '.') {
				continue;
			}
			$installFile = PHPFOX_DIR_SITE_APPS . $dir . PHPFOX_DS . 'Install.php';
			if (!file_exists($installFile)) {
				continue;
			}

			$content = file_get_contents($installFile);

			$info = [
				'apps_dir' => $dir,
			];

			if (preg_match('/\$this->id\s*=\s*[\'|"](\w+)[\'|"]\s*;/m', $content, $match)) {
				$info['apps_id'] = $match[1];
			} else {
				continue;
			}

			$id = $info['apps_id'];

			if (preg_match('/\$this->version\s*=\s*[\'|"]([\w\.\-]+)[\'|"]\s*;/m', $content, $match)) {
				$info['version'] = $match[1];
			} else {
				continue;
			}

			if (array_key_exists($id, $installedApps)) {
				$info['can_install'] = false;
				$info['can_upgrade'] = version_compare($info['version'], $installedApps[$id]['version'], '>');
				$info['current_version'] = $installedApps[$id]['version'];
			} else {
				$info['can_install'] = true;
			}

			if (!$showAll && !$info['can_upgrade'] && !$info['can_install']) {
				continue;
			}

			$uploadedApps[] = $info;
		}

		return $uploadedApps;
	}

	/**
	 * @param string $cmd
	 * @param string $type
	 * @param string $appId
	 * @param string $appDir
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function processUploadedApp($cmd, $type, $appId, $appDir)
	{
		$installer = new InstallationManager([]);

		if ($cmd == 'install' || $cmd == 'upgrade') {
			$installer->dryInstall([
				'type' => $type,
				'productName' => $appId,
				'apps_dir' => base64_encode($appDir),
				'is_upgrade' => $cmd == 'upgrade',
			]);
		} elseif ($cmd == 're_validate') {
			if ($appInit = \Core\Lib::appInit($appId)) {
				$appInit->processInstall();
				Phpfox::addMessage(_p('the_app_has_been_re_validated'));
			}
		}
		return true;
	}


	public function process()
	{
		$warnings = [];
		$cmd = $this->request()->get('cmd');
		$appId = $this->request()->get('apps_id');
		$appDir = $this->request()->get('apps_dir');
		$showAll = $this->request()->get('show_all', '0');
		$cmdResult = false;

		try {
			if ($cmd && $appId && $appDir) {
				$cmdResult = $this->processUploadedApp($cmd, 'app', $appId, $appDir);
			}
		} catch (Exception $exception) {
			$warnings[] = $exception->getMessage();
		}

		if ($cmdResult) {
			Phpfox::getLib('cache')->remove();
			$this->url()->send($this->url()->makeUrl('admincp.apps.uploaded', ['show_all' => $showAll]));
		}


		$uploadedApps = $this->getUploadedApps($showAll);


		$this->template()
			->setSectionTitle(_p('apps'))
			->setTitle(_p('Uploaded Apps'))
			->setBreadCrumb(_p('Uploaded Apps'))
			->setActiveMenu('admincp.apps.uploaded')
			->assign([
				'showAll' => $showAll,
				'uploadedApps' => $uploadedApps,
				'bShowClearCache' => true,
				'warning' => implode('<br />', $warnings)
			]);
	}
}