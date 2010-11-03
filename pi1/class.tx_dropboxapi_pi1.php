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
		$this->pi_USER_INT_obj = 1;	// Configuring so caching is not expected.
		$this->pi_loadLL();

		$content = '';

		try {
			$this->initializeDropbox();
		} catch (t3lib_error_Exception $e) {
			return $this->error($e->getMessage());
		}

		if (isset($_FILES[$this->prefixId])) {
			$targetDirectory = rtrim($this->settings['directory'], '/') . '/';
			$files = array_keys($_FILES[$this->prefixId]['name']);
			foreach ($files as $file) {
				if ($_FILES[$this->prefixId]['name'][$file]) {
					if ($this->dropbox->putFile($targetDirectory . $_FILES[$this->prefixId]['name'][$file], $_FILES[$this->prefixId]['tmp_name'][$file])) {
						$content .= '<p>' . $this->pi_getLL('message_upload_success') . '</p>';
					} else {
						$content .= '<p>' . $this->pi_getLL('message_upload_failure') . '</p>';
					}
				}
			}
		}

		$content .= '
			<form action="' . $this->pi_getPageLink($GLOBALS['TSFE']->id) . '" method="post" enctype="multipart/form-data">
				<label for="file_1">' . $this->pi_getLL('label_file_upload') . '</label>
				<input type="file" id="file_1" name="' . $this->prefixId . '[file_1]" /><br />
				<label for="file_2">' . $this->pi_getLL('label_file_upload') . '</label>
				<input type="file" id="file_2" name="' . $this->prefixId . '[file_2]" /><br />
				<input type="submit" value="' . $this->pi_getLL('label_submit') . '" />
			</form>
		';

		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * Initializes the Dropbox API object.
	 *
	 * @throws t3lib_error_Exception
	 * @return void
	 */
	protected function initializeDropbox() {
		if (!($this->settings['application.']['key'] && $this->settings['application.']['secret'])) {
			throw new t3lib_error_Exception('Either application.key or application.secret is not properly set');
		}

		if ($this->settings['library.']['oauth'] === 'pear') {
			$oAuth = new Dropbox_OAuth_PEAR(
				$this->settings['application.']['key'],
				$this->settings['application.']['secret']
			);
		} else {
			$oAuth = new Dropbox_OAuth_PHP(
				$this->settings['application.']['key'],
				$this->settings['application.']['secret']
			);
		}
		$this->dropbox = new Dropbox_API($oAuth);

		$tokens = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'tokens',
			'tx_dropboxapi_cache',
			'email=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->settings['authentication.']['email'], 'tx_dropboxapi_cache'),
			'',
			'',
			1
		);

		if ($tokens) {
			$tokens = unserialize($tokens[0]['tokens']);
		} else {
			try {
				$tokens = $this->dropbox->getToken(
					$this->settings['authentication.']['email'],
					$this->settings['authentication.']['password']
				);
			} catch (OAuthException $e) {
				throw new t3lib_error_Exception('Invalid credentials');
			}

			$data = array(
				'crdate' => $GLOBALS['EXEC_TIME'],
				'email'  => $this->settings['authentication']['email'],
				'tokens' => serialize($tokens),
			);

			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'tx_dropboxapi_cache',
				$data
			);
		}

		$oAuth->setToken($tokens);

			// Perform a simple test to ensure connection is established
		try {
			$this->dropbox->getAccountInfo();
		} catch (Dropbox_Exception_Forbidden $e) {
			throw new t3lib_error_Exception('Access forbidden. You probably have a misconfiguration with'
				. ' either application.key or application.secret');
		}
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

						if (trim($value) !== '') {
								// Handle dotted fields by transforming them as sub configuration TS
							$setting =& $this->settings;
							while (($pos = strpos($field, '.')) !== FALSE) {
								$prefix = substr($field, 0, $pos + 1);
								$field = substr($field, $pos + 1);

								$setting =& $setting[$prefix];
							}
							$setting[$field] = $value;
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
			$tsparser->parse('plugin.' . $this->prefixId . "{\n" . $flexformTyposcript . "\n}");
				// Copy the resulting setup back into settings
			$this->settings = $tsparser->setup['plugin.'][$this->prefixId . '.'];
		}

			// Allow cObject on settings
		$this->resolveCObject($this->settings, 'application.key');
		$this->resolveCObject($this->settings, 'application.secret');
		$this->resolveCObject($this->settings, 'authentication.email');
		$this->resolveCObject($this->settings, 'authentication.password');
		$this->resolveCObject($this->settings, 'directory');
	}

	/**
	 * Resolves CObject on a given array of settings.
	 *
	 * @param array &$settings
	 * @param string $key
	 * @return void
	 */
	protected function resolveCObject(array &$settings, $key) {
		if (($pos = strpos($key, '.')) !== FALSE) {
			$subKey = substr($key, $pos + 1);
			$key = substr($key, 0, $pos + 1);

			$this->resolveCObject($settings[$key], $subKey);
		} else {
			if (isset($settings[$key . '.'])) {
				$settings[$key] = $this->cObj->cObjGetSingle(
					$settings[$key],
					$settings[$key . '.']
				);
			}
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