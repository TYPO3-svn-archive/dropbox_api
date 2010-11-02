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

require_once(dirname(__FILE__) . '/../lib/Dropbox/autoload.php');

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
		$this->pi_loadLL();

		$content = '';

		if (!($this->settings['consumerKey'] && $this->settings['consumerSecret'])) {
			return $this->error('Either consumerKey or consumerSecret is not properly set');
		}

		$this->initializeDropbox();
		//t3lib_div::debug($this->dropbox->getAccountInfo(), 'account info');

		if (isset($_FILES[$this->prefixId])) {
			if ($this->dropbox->putFile($_FILES[$this->prefixId]['name']['file'], $_FILES[$this->prefixId]['tmp_name']['file'])) {
				$content .= 'Successfuly uploaded file!';
			} else {
				$content .= 'Fail to upload file :(';
			}
		}

		$content .= '
			<form action="' . $this->pi_getPageLink($GLOBALS['TSFE']->id) . '" method="post" enctype="multipart/form-data">
				<label for="file">File to upload to Dropbox:</label>
				<input type="file" id="file" name="' . $this->prefixId . '[file]" /><br />
				<input type="submit" value="Send to Dropbox" />
			</form>
		';

		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * Initializes the Dropbox API object.
	 *
	 * @return void
	 */
	protected function initializeDropbox() {
		if ($this->settings['oauthLibrary'] === 'pear') {
			$oauth = new Dropbox_OAuth_PEAR($this->settings['consumerKey'], $this->settings['consumerSecret']);
		} else {
			$oauth = new Dropbox_OAuth_PHP($this->settings['consumerKey'], $this->settings['consumerSecret']);
		}
		$this->dropbox = new Dropbox_API($oauth);

		$tokens = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'tokens',
			'tx_dropboxapi_cache',
			'email=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->settings['email'], 'tx_dropboxapi_cache'),
			'',
			'',
			1
		);

		if ($tokens) {
			$tokens = unserialize($tokens[0]['tokens']);
		} else {
			$tokens = $this->dropbox->getToken($this->settings['email'], $this->settings['password']);

			$data = array(
				'crdate' => $GLOBALS['EXEC_TIME'],
				'email'  => $this->settings['email'],
				'tokens' => serialize($tokens),
			);

			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'tx_dropboxapi_cache',
				$data
			);
		}

		$oauth->setToken($tokens);
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

		if (is_array($piFlexForm['data'])) {
				// Traverse the entire array based on the language
				// and assign each configuration option to $this->settings array...
			foreach ($piFlexForm['data'] as $sheet => $langData) {
				foreach ($langData as $lang => $fields) {
					foreach (array_keys($fields) as $field) {
						$value = $this->pi_getFFvalue($piFlexForm, $field, $sheet);

						if (!empty($value)) {
							$this->settings[$field] = $value;
						}
					}
				}
			}
		}

			// Load full setup to allow references to outside definitions in 'myTS'
		$globalSetup = $GLOBALS['TSFE']->tmpl->setup;
		$localSetup = array('plugin.' => array($this->prefixId . '.' => $this->settings));
		$setup = t3lib_div::array_merge_recursive_overrule($globalSetup, $localSetup);

			// Override configuration with TS from FlexForm itself
		$flexformTyposcript = $this->settings['myTS'];
		unset($this->settings['myTS']);
		if ($flexformTyposcript) {
			require_once(PATH_t3lib . 'class.t3lib_tsparser.php');
			$tsparser = t3lib_div::makeInstance('t3lib_tsparser');
			// Copy settings into existing setup
			$tsparser->setup = $setup;
			// Parse the new Typoscript
			$tsparser->parse('plugin.' . $this->prefixId . '.' . $flexformTyposcript);
			// Copy the resulting setup back into settings
			$this->settings = $tsparser->setup['plugin.'][$this->prefixId . '.'];
		}
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