<?php
/**
 * @brief		Provides notifications results in  RESTAPI-friendly format
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 August 2022
 */

namespace IPS\Notification;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * REST API Notification RESPONSE
 */
class _RestApi extends \IPS\Notification\Inline
{
	/**
	 * Get data from extension
	 *
	 * @param	bool $htmlEscape	TRUE to escape HTML
	 */
	public function getData( $htmlEscape = TRUE )
	{
		$methodName = "parse_rest_{$this->notification_key}";

		foreach ( $this->notification_app->extensions( 'core', 'Notifications' ) as $class )
		{
			if ( method_exists( $class, $methodName ) )
			{
				$return = $class->$methodName( $this, $htmlEscape );
				if ( !isset( $return['unread'] ) )
				{
					$return['unread'] = !$this->read_time;
				}
				return $return;
			}
		}
		// if there's no REST specific method, just let the parent class handle everything else
		return parent::getData($htmlEscape);
	}
}