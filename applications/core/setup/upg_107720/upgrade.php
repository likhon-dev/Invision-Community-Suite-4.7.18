<?php
/**
 * @brief		4.7.14 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		18 Sep 2023
 */

namespace IPS\core\setup\upg_107720;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.7.14 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Member\PrivacyAction::resetDeletionAcpNotifications();

		return TRUE;
	}

	/**
	 * ...
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Settings::i()->changeValues( array( 'x_hashtag' => \IPS\Settings::i()->twitter_hashtag ) );

		return TRUE;
	}

	/**
	 * ...
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		if( \IPS\Settings::i()->site_social_profiles AND $links = json_decode( \IPS\Settings::i()->site_social_profiles, TRUE ) AND \count( $links ) )
		{
			$newLinks = [];

			/* Loop over the links...if we detect twitter, set the flag that we need to update the site links array */
			foreach( $links as $link )
			{
				if( mb_strpos( $link['value'], 'twitter' ) !== FALSE )
				{
					$link = [
						'key'   => (string) \IPS\Http\Url::external( $link['key'] )->setHost('x.com'),
						'value' => 'x'
					];
				}

				$newLinks[] = $link;
			}

			if( \count( $newLinks ) )
			{
				\IPS\Settings::i()->changeValues( array( 'site_social_profiles' => json_encode( $newLinks ) ) );
			}
		}

		return TRUE;
	}

	/**
	 * ...
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', 'marketplaceSetup' );

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}