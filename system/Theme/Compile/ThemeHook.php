<?php

/**
 * @brief       Theme Hook Compilation Skeleton - Catch calls to parent during compile.
 * @author		<a href='https://invisioncommunity.com/'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2023 Invision Power Services, Inc.
 * @package		Invision Community
 * @since       16 February 2023
 */

namespace IPS\Theme\Compile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Theme Hook skeleton class
 */
class _ThemeHook
{
	/**
	 * @param   $name
	 * @param   $arguments
	 * @return  string
	 */
	public function __call( string $name, array $arguments ): string
	{
		return '';
	}

	/**
	 * @param   $name
	 * @param   $arguments
	 * @return  string
	 */
	public static function __callStatic( string $name, array $arguments ): string
	{
		return '';
	}

	/**
	 * @param   $name
	 * @return  void
	 */
	public function __set( string $name, mixed $value ): void
	{

	}
}