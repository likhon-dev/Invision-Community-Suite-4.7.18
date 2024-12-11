<?php
/**
 * @brief		X share link
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Sept 2023
 * @see			<a href='https://dev.x.com/docs/tweet-button'>X button documentation</a>
 */

namespace IPS\Content\ShareServices;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * X share link
 */
class _X extends \IPS\Content\ShareServices
{
	/**
	 * Determine whether the logged in user has the ability to autoshare
	 *
	 * @return	boolean
	 */
	public static function canAutoshare(): bool
	{
		return FALSE;
	}
	
	/**
	 * Publish text or a URL to this service
	 *
	 * @param	string	$content	Text to publish
	 * @param	string	$url		[URL to publish]
	 * @return	void
	 */
	public static function publish( $content, $url=null )
	{
		throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('x_publish_no_user') );
	}

	/**
	 * Add any additional form elements to the configuration form. These must be setting keys that the service configuration form can save as a setting.
	 *
	 * @param	\IPS\Helpers\Form				$form		Configuration form for this service
	 * @param	\IPS\core\ShareLinks\Service	$service	The service
	 * @return	void
	 */
	public static function modifyForm( \IPS\Helpers\Form &$form, $service )
	{
		$form->add( new \IPS\Helpers\Form\Text( 'x_hashtag', \IPS\Settings::i()->x_hashtag, FALSE ) );
	}

	/**
	 * Return the HTML code to show the share link
	 *
	 * @return	string
	 */
	public function __toString()
	{
		try
		{
			$url = preg_replace_callback( "{[^0-9a-z_.!~*'();,/?:@&=+$#-]}i",
				function ( $m )
				{
					return sprintf( '%%%02X', \ord( $m[0] ) );
				},
				$this->url) ;

			$title = $this->title ?: NULL;
			if ( \IPS\Settings::i()->x_hashtag !== '')
			{
				$title .= ' ' . \IPS\Settings::i()->x_hashtag;
			}
			return \IPS\Theme::i()->getTemplate( 'sharelinks', 'core' )->x( urlencode( $url ), rawurlencode( $title ) );
		}
		catch ( \Exception $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
		catch ( \Throwable $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
	}
}