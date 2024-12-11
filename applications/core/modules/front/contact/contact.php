<?php
/**
 * @brief		Contact Form
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Nov 2013
 */

namespace IPS\core\modules\front\contact;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Contact Form
 */
class _contact extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Is this for displaying "content"? Affects if advertisements may be shown
	 */
	public $isContentPage = FALSE;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( !\IPS\Member::loggedIn()->canUseContactUs() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S333/1', 403, '' );
		}

		/* Execute */
		return parent::execute();
	}
	
	/**
	 * Method
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Get extensions */
		$extensions = \IPS\Application::allExtensions( 'core', 'ContactUs', FALSE, 'core', 'InternalEmail', TRUE );

		/* Don't let robots index this page, it has no value */
		\IPS\Output::i()->metaTags['robots'] = 'noindex';
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';

		$form = new \IPS\Helpers\Form( 'contact', 'send' );
		$form->hiddenValues['contact_referrer'] = (string) \IPS\Request::i()->referrer();
		$form->class = 'ipsForm_vertical';
		
		$form->add( new \IPS\Helpers\Form\Editor( 'contact_text', NULL, TRUE, array(
				'app'			=> 'core',
				'key'			=> 'Contact',
				'autoSaveKey'	=> 'contact-' . \IPS\Member::loggedIn()->member_id,
		) ) );
		
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'contact_name', NULL, TRUE ) );
			$form->add( new \IPS\Helpers\Form\Email( 'email_address', NULL, TRUE, array( 'bypassProfanity' => \IPS\Helpers\Form\Text::BYPASS_PROFANITY_ALL ) ) );
			if ( \IPS\Settings::i()->bot_antispam_type !== 'none' and \IPS\Settings::i()->guest_captcha )
			{
				$form->add( new \IPS\Helpers\Form\Captcha );
			}
		}
		foreach ( $extensions as $k => $class )
		{
			$class->runBeforeFormOutput( $form );
		}
		
		if ( $values = $form->values() )
		{
			if ( !\IPS\Member::loggedIn()->member_id AND \IPS\Settings::i()->contact_email_verify )
			{
				$key = \IPS\Login::generateRandomString( 32 );
				\IPS\Db::i()->insert( 'core_contact_verify', array(
					'email_address'		=> $values['email_address'],
					'contact_data'		=> json_encode( $values ),
					'verify_key'		=> $key
				), TRUE );
				
				$email = \IPS\Email::buildFromTemplate( 'core', 'contact_verify', array( $values['email_address'], $key ), \IPS\Email::TYPE_TRANSACTIONAL );
				$email->send( $values['email_address'] );
				
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( 'OK' );
				}
				
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'contact_verify' );
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->contactVerify();
				return;
			}
			
			foreach ( $extensions as $k => $class )
			{
				if ( $handled = $class->handleForm( $values ) )
				{
					break;
				}
			}

			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'OK' );
			}

			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'message_sent' );
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'system' )->contactDone();
		}
		else
		{
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'contact' );
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'system' )->contact( $form );	
		}
	}
	
	/**
	 * Confirm
	 *
	 * @return	void
	 */
	protected function confirm()
	{
		/* Show interstitial page to prevent email clients from auto-verifying. */
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		
		$form = new \IPS\Helpers\Form( 'form', NULL );
		$form->class = 'ipsForm_vertical';
		$form->actionButtons[] = \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->button( 'contact_click_to_verify', 'submit', null, 'ipsButton ipsButton_primary ipsButton_fullWidth', array( 'tabindex' => '2', 'accesskey' => 's' ) );
		$form->hiddenValues['email'] = \IPS\Request::i()->email;
		$form->hiddenValues['key'] = \IPS\Request::i()->key;
		
		if ( $values = $form->values() )
		{
			try
			{
				$verify = \IPS\Db::i()->select( '*', 'core_contact_verify', array( "email_address=?", $values['email'] ) )->first();
			}
			catch( \UnderflowException )
			{
				\IPS\Output::i()->error( 'node_error', '2C435/1', 404, '' );
			}
			
			if ( \IPS\Login::compareHashes( $verify['verify_key'], $values['key'] ) === FALSE )
			{
				\IPS\Output::i()->error( 'contact_verify_key_mismatch', '2C435/2', 403, '' );
			}
			
			/* Send it */
			$extensions = \IPS\Application::allExtensions( 'core', 'ContactUs', FALSE, 'core', 'InternalEmail', TRUE );
			
			foreach( $extensions AS $k => $extension )
			{
				if ( $extension->handleForm( json_decode( $verify['contact_data'], true ) ) )
				{
					break;
				}
			}
			
			\IPS\Db::i()->delete( 'core_contact_verify', array( "email_address=?", $values['email'] ) );
			
			\IPS\Output::i()->sidebar['enabled'] = FALSE;
			\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'message_sent' );
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'system' )->contactDone();
			return;
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'contact_verify' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->contactConfirmVerify( $form );
	}
}