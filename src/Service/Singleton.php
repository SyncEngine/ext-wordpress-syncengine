<?php
/**
 * Copyright (C) Keraweb - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Jory Hogeveen <info@keraweb.nl>
 *
 * @author  Jory Hogeveen
 * @link    https://www.keraweb.nl/
 * @package Keraweb
 */

namespace SyncEngine\WordPress\Service;

/**
 * @package Keraweb
 */
abstract class Singleton
{
	/**
	 * Already loaded singleton instances.
	 */
	private static $instances = array();

	/**
	 * @return static
	 */
	public static function get_instance() {
		$class = get_called_class();
		if ( empty( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new $class();
		}
		return self::$instances[ $class ];
	}

	protected function __construct() {
		// Nope.
	}

	private function __clone() {
		// Nope.
	}
}
