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
 * TypoScript Library.
 *
 * @category    Library
 * @package     TYPO3
 * @subpackage  tx_dropboxapi
 * @author      Xavier Perseguers <typo3@perseguers.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @version     SVN: $Id: class.tx_dropboxapi_pi1.php 39809 2010-11-03 08:37:09Z xperseguers $
 */
class tx_dropboxapi_ts {

	/**
	 * @var tslib_cObj
	 */
	public static $contentObj;

	/**
	 * Default constructor.
	 */
	private function __construct() { }

	/**
	 * Overrides settings with FlexForm configuration.
	 *
	 * @param string $prefixId
	 * @param array &$settings
	 * @param array $piFlexForm
	 * @return void
	 */
	public static function overrideSettings($prefixId, array &$settings, array $piFlexForm) {
		if (is_array($piFlexForm['data'])) {
				// Traverse the entire array based on the language
				// and assign each configuration option to $this->settings array...
			foreach ($piFlexForm['data'] as $sheet => $langData) {
				foreach ($langData as $lang => $fields) {
					foreach (array_keys($fields) as $field) {
						$value = self::getFFvalue($piFlexForm, $field, $sheet);

						if (trim($value) !== '') {
								// Handle dotted fields by transforming them as sub configuration TS
							$setting =& $settings;
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
		$localSetup = array('plugin.' => array($prefixId . '.' => $settings));
		$setup = t3lib_div::array_merge_recursive_overrule($globalSetup, $localSetup);

			// Override configuration with TS from FlexForm itself
		$flexformTyposcript = $settings['myTS'];
		unset($settings['myTS']);
		if ($flexformTyposcript) {
			require_once(PATH_t3lib . 'class.t3lib_tsparser.php');
			$tsparser = t3lib_div::makeInstance('t3lib_tsparser');
				// Copy settings into existing setup
			$tsparser->setup = $setup;
				// Parse the new Typoscript
			$tsparser->parse('plugin.' . $prefixId . "{\n" . $flexformTyposcript . "\n}");
				// Copy the resulting setup back into settings
			$settings = $tsparser->setup['plugin.'][$prefixId . '.'];
		}

			// Allow cObject on settings
		self::resolveCObject($settings, 'application.key');
		self::resolveCObject($settings, 'application.secret');
		self::resolveCObject($settings, 'authentication.email');
		self::resolveCObject($settings, 'authentication.password');
		self::resolveCObject($settings, 'directory');
	}

	/**
	 * Resolves CObject on a given array of settings.
	 *
	 * @param array &$settings
	 * @param string $key
	 * @return void
	 */
	protected static function resolveCObject(array &$settings, $key) {
		if (($pos = strpos($key, '.')) !== FALSE) {
			$subKey = substr($key, $pos + 1);
			$key = substr($key, 0, $pos + 1);

			if (isset($settings[$key])) {
				static::resolveCObject($settings[$key], $subKey);
			}
		} else {
			if (isset($settings[$key . '.'])) {
				if (!self::$contentObj) {
					self::$contentObj = t3lib_div::makeInstance('tslib_cObj');
					self::$contentObj->start($GLOBALS['TSFE']->page, 'pages');
				}

				$settings[$key] = self::$contentObj->cObjGetSingle(
					$settings[$key],
					$settings[$key . '.']
				);
			}
		}
	}

	/**
	 * Returns the value from somewhere inside a FlexForm structure.
	 *
	 * @param array FlexForm data
	 * @param string Field name to extract. Can be given like "test/el/2/test/el/field_templateObject" where each part will dig a level deeper in the FlexForm data.
	 * @param string Sheet pointer, eg. "sDEF"
	 * @param string Language pointer, eg. "lDEF"
	 * @param string Value pointer, eg. "vDEF"
	 * @return string The content.
	 */
	protected static function getFFvalue(array $T3FlexForm_array, $fieldName, $sheet = 'sDEF', $lang = 'lDEF', $value = 'vDEF') {
		$sheetArray = $T3FlexForm_array['data'][$sheet][$lang];
		if (is_array($sheetArray))	{
			return self::getFFvalueFromSheetArray($sheetArray, explode('/', $fieldName), $value);
		}
	}

	/**
	 * Returns part of $sheetArray pointed to by the keys in $fieldNameArray.
	 *
	 * @param array Multidimensional array, typically FlexForm contents
	 * @param array Array where each value points to a key in the FlexForms content - the input array will have the value returned pointed to by these keys. All integer keys will not take their integer counterparts, but rather traverse the current position in the array an return element number X (whether this is right behavior is not settled yet...)
	 * @param string Value for outermost key, typ. "vDEF" depending on language.
	 * @return mixed The value, typ. string.
	 * @see getFFvalue()
	 */
	protected static function getFFvalueFromSheetArray(array $sheetArray, array $fieldNameArr, $value) {
		$tempArr = $sheetArray;
		foreach ($fieldNameArr as $k => $v) {
			if (t3lib_div::testInt($v)) {
				if (is_array($tempArr)) {
					$c = 0;
					foreach ($tempArr as $values) {
						if ($c == $v) {
							$tempArr = $values;
							break;
						}
						$c++;
					}
				}
			} else {
				$tempArr = $tempArr[$v];
			}
		}
		return $tempArr[$value];
	}
}

?>