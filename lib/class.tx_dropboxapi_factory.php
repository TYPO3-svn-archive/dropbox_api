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

require_once(dirname(__FILE__) . '/Dropbox/autoload.php');

/**
 * Dropbox Factory.
 *
 * @category    Library
 * @package     TYPO3
 * @subpackage  tx_dropboxapi
 * @author      Xavier Perseguers <typo3@perseguers.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @version     SVN: $Id: class.tx_dropboxapi_pi1.php 39809 2010-11-03 08:37:09Z xperseguers $
 */
class tx_dropboxapi_factory {

	/**
	 * Default constructor.
	 */
	private function __construct() { }

	/**
	 * Creates a Dropbox_API object.
	 *
	 * @param  $settings
	 * @throws t3lib_error_Exception
	 * @return Dropbox_API
	 */
	public static function getDropbox(array $settings) {
		if (!($settings['application.']['key'] && $settings['application.']['secret'])) {
			throw new t3lib_error_Exception('Either application.key or application.secret is not properly set');
		}

		if ($settings['library.']['oauth'] === 'pear') {
			$oAuth = new Dropbox_OAuth_PEAR(
				$settings['application.']['key'],
				$settings['application.']['secret']
			);
		} else {
			$oAuth = new Dropbox_OAuth_PHP(
				$settings['application.']['key'],
				$settings['application.']['secret']
			);
		}
		$dropbox = new Dropbox_API($oAuth);

		$tokens = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'tokens',
			'tx_dropboxapi_cache',
			'email=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($settings['authentication.']['email'], 'tx_dropboxapi_cache'),
			'',
			'',
			1
		);

		if ($tokens) {
			$tokens = unserialize($tokens[0]['tokens']);
		} else {
			try {
				$tokens = $dropbox->getToken(
					$settings['authentication.']['email'],
					$settings['authentication.']['password']
				);
			} catch (OAuthException $e) {
				throw new t3lib_error_Exception('Invalid credentials');
			}

			$data = array(
				'crdate' => $GLOBALS['EXEC_TIME'],
				'email'  => $settings['authentication']['email'],
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
			$dropbox->getAccountInfo();
		} catch (Dropbox_Exception_Forbidden $e) {
			throw new t3lib_error_Exception('Access forbidden. You probably have misconfigured'
				. ' either application.key or application.secret');
		}

		return $dropbox;
	}

}

?>