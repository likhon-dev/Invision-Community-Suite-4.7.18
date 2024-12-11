<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/

 * @since		26 Jan 2023
 */

namespace IPS\core\extensions\core\Statistics;

use IPS\core\Statistics\Chart as ParentClass;
use IPS\Http\Url;
use IPS\Helpers\Chart;
use IPS\Helpers\Chart\Database;
use IPS\Member;
use IPS\Theme;
use IPS\DateTime as IPSDateTime;
use IPS\Db;

use function defined;
use function header;
use function count;
use function implode;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _Moderators extends ParentClass
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_moderators';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( Url $url ): Chart
	{
		$chart	= new Database( $url, 'core_moderator_logs', 'ctime', '', array( 
			'isStacked' => TRUE,
			'backgroundColor' 	=> '#ffffff',
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		 ), 'LineChart', 'monthly', array( 'start' => 0, 'end' => 0 ), array( 'member_id', 'ctime' ), 'users' );
		 $chart->setExtension( $this );
		
		$chart->groupBy = 'member_id';
		$chart->title = Member::loggedIn()->language()->addToStack('stats_moderator_activity_title');
		$chart->availableTypes = array( 'LineChart', 'AreaChart', 'ColumnChart', 'BarChart' );
		
		$chart->tableLangPrefix = 'mod_stats_';
		$chart->tableInclude = array( 'member_id', 'lang_key', 'ctime' );
		$chart->tableParsers = array(
			'member_id'	=> function( $val ) {
				return Theme::i()->getTemplate( 'global', 'core', 'admin' )->userLinkWithPhoto( Member::load( $val ) );
			},
			'lang_key'	=> function( $val ) {
				return Member::loggedIn()->language()->addToStack( $val . '_stats' );
			},
			'ctime'	=> function( $val )
			{
				return (string) IPSDateTime::ts( $val );
			}
		);
		
		/* Didn't find any specified? Do the default of the top 50 moderators */
		$where = array();
		if ( $chart->start instanceof IPSDateTime or $chart->end instanceof IPSDateTime )
		{
			$start = $chart->start instanceof IPSDateTime ? $chart->start->getTimestamp() : 0;
			$end   = $chart->end instanceof IPSDateTime ? $chart->end->getTimestamp() : time();
			$where[]  = array( 'ctime BETWEEN ? AND ?', $start, $end );
		}
		
		/* Get actual moderators */
		$modMember = [];
		$modGroup = [];
		
		foreach ( Db::i()->select( '*', 'core_moderators' ) as $mod )
		{
			if ( $mod['type'] == 'g' )
			{
				$modGroup[] = $mod['id'];
			}
			else
			{
				$modMember[] = $mod['id'];	
			}
		}
		
		$query = [];
		
		if ( count( $modGroup ) )
		{
			$query[] = Db::i()->in( 'member_group_id', $modGroup );
			$query[] = Db::i()->findInSet( 'mgroup_others', $modGroup );
		}
		
		if ( count( $modMember ) )
		{
			$query[] = Db::i()->in( 'member_id', $modMember );
		}
		
		/* I mean $query should never be empty, but lets not wait for a ticket and have to release a patch to find out that it could be... */
		if ( count( $query ) )
		{
			$where[] = [ 'member_id IN(?)', Db::i()->select( 'member_id', 'core_members', implode( ' OR ', $query ) ) ];
		}
		
		$topModerators = Db::i()->select( 'member_id, COUNT(*) as _mod_count', 'core_moderator_logs', $where, '_mod_count DESC', 50, array( 'member_id' ) );

		foreach ( $topModerators as $moderatorRow )
		{
			$member = Member::load( $moderatorRow['member_id'] );
			$chart->addSeries(
				( $member->member_id ) ? $member->name : \IPS\Member::loggedIn()->language()->addToStack( 'deleted_member' ),
				'number',
				'COUNT(*)',
				TRUE,
				$moderatorRow['member_id']
			);
		}
		
		return $chart;
	}
}