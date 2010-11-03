<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Xavier Perseguers <typo3@perseguers.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Wizards for the dropbox_api plugins.
 *
 * @category    Wizard
 * @package     TYPO3
 * @subpackage  tx_dropboxapi
 * @author      Xavier Perseguers <typo3@perseguers.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @version     SVN: $Id$
 */
class tx_dropboxapi_wizard {

	protected $extKey = 'dropbox_api';
	protected $prefixId = '';

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @var Dropbox_API
	 */
	protected $dropbox;

	/**
	 * Returns a Dropbox directory picker.
	 *
	 * @param array $PA TCA configuration passed by reference
	 * @param $pObj
	 * @return string HTML snippet to be put after the itemsProcFunc field
	 */
	public function directoryPicker(array &$PA, $pObj) {
		$this->init($PA);

		$directories = array('', '/');

		try {
			$this->dropbox = tx_dropboxapi_factory::getDropbox($this->settings);
			$this->populateDropboxDirectories($directories, '/');
		} catch (t3lib_error_Exception $e) {
			// Nothing to do
		}

		$updateJS = 'var itemsProcFunc = document.' . $PA['formName'] . '[\'directory_picker\'].value;';
		$updateJS .= 'document.' . $PA['formName'] . '[\'' . $PA['itemName'] . '\'].value = itemsProcFunc;';
		$updateJS .= implode('', $PA['fieldChangeFunc']) . ';return false;';

		$PA['item'] .= '<br />';
		$PA['item'] .= 'Dropbox directories: <select name="directory_picker" onchange="' . $updateJS . '">';
		foreach ($directories as $directory) {
			$PA['item'] .= sprintf('<option value="%s">%s</option>', $directory, $directory);
		}
		$PA['item'] .= '</select>';
	}

	/**
	 * Appends to $directories the Dropbox directories found in $path.
	 *
	 * @param array &$directories
	 * @param string $path
	 * @param integer $maxLevels
	 * @return array
	 */
	protected function populateDropboxDirectories(array &$directories, $path, $maxLevels = 3) {
		try {
			$root = $this->dropbox->getMetaData($path);
		} catch (Dropbox_Exception_Forbidden $e) {
			return;
		}

		if (isset($root['contents'])) {
			foreach ($root['contents'] as $fileOrDirectory) {
				if ($fileOrDirectory['is_dir']) {
					$directories[] = $fileOrDirectory['path'];

					if ($maxLevels > 1) {
						$this->populateDropboxDirectories($directories, $fileOrDirectory['path'] . '/', $maxLevels - 1);
					}
				}
			}
		}
	}

	/**
	 * Initializes this wizard.
	 *
	 * @param array $PA
	 * @return void
	 */
	protected function init(array $PA) {
		$pid  = $PA['pid'];
		$this->prefixId = str_replace($this->extKey, 'tx_' . str_replace('_', '', $this->extKey), $PA['row']['list_type']);

			// Initialize default values based on extension TS
		$this->settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		if (!is_array($this->settings)) {
			$this->settings = array();
		}

		$this->initializeFrontendTemplate($pid);
		$settings = $GLOBALS['TSFE']->tmpl->setup['plugin.'][$this->prefixId . '.'];
		if (!is_array($settings)) {
			$settings = array();
		}

			// Base configuration is equal the the plugin's TS setup
		$this->settings = array_merge($this->settings, $settings);

		$piFlexForm = t3lib_div::xml2array($PA['row']['pi_flexform']);
		if (!is_array($piFlexForm)) {
			$piFlexForm = array();
		}
		tx_dropboxapi_ts::overrideSettings($this->prefixId, $this->settings, $piFlexForm);

		unset($GLOBALS['TSFE']);
		unset($GLOBALS['TT']);
	}

	/**
	 * Returns the frontend template for a given page.
	 *
	 * @param integer $pid
	 * @return t3lib_tstemplate
	 */
	protected function initializeFrontendTemplate($pid) {
		$GLOBALS['TT'] = new t3lib_timeTrackNull();
		$GLOBALS['TSFE'] = t3lib_div::makeInstance('tslib_fe', $GLOBALS['TYPO3_CONF_VARS'], $pid, 0);
		$GLOBALS['TSFE']->initFEuser();
		//$GLOBALS['TSFE']->checkAlternativeIdMethods();
		//$GLOBALS['TSFE']->clear_preview();
		$GLOBALS['TSFE']->determineId();
		$GLOBALS['TSFE']->initTemplate();
		$GLOBALS['TSFE']->getFromCache();
		$GLOBALS['TSFE']->getConfigArray();
	}
}

?>