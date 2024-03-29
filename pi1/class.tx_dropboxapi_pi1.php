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
 * Frontend plugin for the dropbox_api extension.
 *
 * @category    Plugin
 * @package     TYPO3
 * @subpackage  tx_dropboxapi
 * @author      Xavier Perseguers <typo3@perseguers.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @version     SVN: $Id$
 */
class tx_dropboxapi_pi1 extends tslib_pibase {

	public $prefixId      = 'tx_dropboxapi_pi1';
	public $scriptRelPath = 'pi1/class.tx_dropboxapi_pi1.php';
	public $extKey        = 'dropbox_api';

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @var Dropbox_API
	 */
	protected $dropbox;

	/**
	 * Main-function, returns output.
	 *
	 * @param string $content: The plugin content
	 * @param array	$settings: The plugin configuration
	 * @return string Content which appears on the website
	 */
	public function main($content, array $settings) {
		$this->init($settings);
		$this->pi_setPiVarDefaults();
		$this->pi_USER_INT_obj = 1;	// Configuring so caching is not expected.
		$this->pi_loadLL();

		$content = '';

		try {
			$this->dropbox = tx_dropboxapi_factory::getDropbox($this->settings);
		} catch (t3lib_error_Exception $e) {
			return $this->error($e->getMessage());
		}

		$template = $this->cObj->fileResource($this->settings['templateFile']);
		$templateCode = $this->cObj->getSubpart($template, '###UPLOAD_FORM###');

		$templateNotification = $this->cObj->getSubpart($templateCode, '###NOTIFICATION###');
		$templateMessageLine  = $this->cObj->getSubpart($templateNotification, '###MESSAGE_LINE###');
		$messageLines = array();

		if (isset($_FILES[$this->prefixId])) {
			$targetDirectory = rtrim($this->settings['directory'], '/') . '/';
			$files = array_keys($_FILES[$this->prefixId]['name']);
			foreach ($files as $file) {
				if ($_FILES[$this->prefixId]['name'][$file]) {
					$markerArray = array();

						// Upload the file to Dropbox
					$success = $this->dropbox->putFile(
						$targetDirectory . $_FILES[$this->prefixId]['name'][$file],
						$_FILES[$this->prefixId]['tmp_name'][$file]
					);

					if ($success) {
						$markerArray['MESSAGE_CLASS'] = $this->prefixId . '-success';
						$markerArray['MESSAGE'] = $this->pi_getLL('message_upload_success');
					} else {
						$markerArray['MESSAGE_CLASS'] = $this->prefixId . '-failure';
						$markerArray['MESSAGE'] = $this->pi_getLL('message_upload_failure');
					}

					$messageLines[] = $this->cObj->substituteMarkerArray(
						$templateMessageLine,
						$markerArray,
						'###|###'
					);
				}
			}

			if ($messageLines) {
				$templateNotification = $this->cObj->substituteSubpart(
					$templateNotification,
					'###MESSAGE_LINE###',
					implode("\n", $messageLines)
				);
			} else {
				$templateNotification = '';
			}
		} else {
			$templateNotification = '';
		}

		$templateCode = $this->cObj->substituteSubpart(
			$templateCode,
			'###NOTIFICATION###',
			$templateNotification
		);

		$markerArray = array(
			'FORM_PREFIX'       => $this->prefixId,
			'LABEL_FILE_UPLOAD' => $this->pi_getLL('label_file_upload'),
			'LABEL_SUBMIT'      => $this->pi_getLL('label_submit'),
		);

		$formFields = $this->cObj->substituteMarkerArray(
			$templateCode,
			$markerArray,
			'###|###'
		);

		$content .= '
			<form action="' . $this->pi_getPageLink($GLOBALS['TSFE']->id) . '" method="post" enctype="multipart/form-data">
				' . $formFields . '
			</form>
		';

		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * This method performs various initializations.
	 *
	 * @param array $settings: Plugin configuration, as received by the main() method
	 * @return void
	 */
	protected function init(array $settings) {
			// Initialize default values based on extension TS
		$this->settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		if (!is_array($this->settings)) {
			$this->settings = array();
		}

			// Base configuration is equal the the plugin's TS setup
		$this->settings = array_merge($this->settings, $settings);

			// Load the flexform and loop on all its values to override TS setup values
			// Some properties use a different test (more strict than not empty) and yet some others no test at all
			// see http://wiki.typo3.org/index.php/Extension_Development,_using_Flexforms
		$this->pi_initPIflexForm(); // Init and get the flexform data of the plugin

			// Assign the flexform data to a local variable for easier access
		$piFlexForm = $this->cObj->data['pi_flexform'];

		tx_dropboxapi_ts::$contentObj = $this->cObj;
		tx_dropboxapi_ts::overrideSettings($this->prefixId, $this->settings, $piFlexForm, $GLOBALS['TSFE']->tmpl->setup);
	}

	/**
	 * Returns an error message for frontend output.
	 *
	 * @param string Error message input
	 * @return string
	 */
	protected function error($string) {
		return '
			<!-- ' . get_class($this) . ' ERROR message: -->
			<div class="' . $this->pi_getClassName('error') . '" style="
					border: 2px red solid;
					background-color: yellow;
					color: black;
					text-align: center;
					padding: 20px 20px 20px 20px;
					margin: 20px 20px 20px 20px;
					">'.
				'<strong>' . get_class($this) . ' ERROR:</strong><br /><br />' . nl2br(trim($string)) .
			'</div>';
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dropbox_api/pi1/class.tx_dropboxapi_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dropbox_api/pi1/class.tx_dropboxapi_pi1.php']);
}

?>