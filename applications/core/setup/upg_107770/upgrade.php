<?php
/**
 * @brief		4.7.18 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		02 Jul 2024
 */

namespace IPS\core\setup\upg_107770;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.7.18 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Remove CommunityHive Widget and orphaned JS files
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$allApps = array_keys(\IPS\Application::applications() );
		$where= [];
		$where[] = array( \IPS\Db::i()->in( 'javascript_app', $allApps, TRUE ) );
		$where[] = ['javascript_plugin=?', ''];
		\IPS\Db::i()->delete( 'core_javascript', $where );
		
		\IPS\Widget::deprecateWidget('communityhive', 'core' );

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}