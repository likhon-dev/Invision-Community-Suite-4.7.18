<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/

 * @since		20 Dec 2023
 */

namespace IPS\core\extensions\core\Statistics;

use IPS\core\Statistics\Chart as ParentClass;
use IPS\Helpers\Chart;
use IPS\Helpers\Chart\Database;
use IPS\Http\Url;
use IPS\DateTime as IPSDateTime;
use IPS\Member;
use IPS\Member\Group;
use IPS\Db;
use IPS\Helpers\Form\Select;
use IPS\Helpers\Form\Radio;
use IPS\Theme;

use DateInterval;

use function defined;
use function header;
use function array_values;
use function explode;
use function is_array;
use function count;


/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _ModeratorsByAction extends ParentClass
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_moderators_actions';
	
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
		 ), 'LineChart', 'monthly', array( 'start' => IPSDateTime::create()->sub( new DateInterval( 'P90D' ) ), 'end' => IPSDateTime::create() ), array( 'lang_key', 'ctime' ), 'actions' );
		 $chart->setExtension( $this );
		
		$chart->groupBy = 'lang_key';
		$chart->title = Member::loggedIn()->language()->addToStack('stats_moderator_activity_title');
		$chart->availableTypes = array( 'LineChart', 'AreaChart', 'ColumnChart', 'BarChart' );
		$chart->joins = array( array( 'core_members', "core_moderator_logs.member_id=core_members.member_id" ) );
		
		$chart->tableInclude = array( 'member_id', 'lang_key', 'ctime' );
		$chart->tableParsers = array(
			'member_id'	=> function( $val, $row ) {
				return Theme::i()->getTemplate( 'global', 'core', 'admin' )->userLinKWithPhoto( Member::constructFromData( $row ) );
			},
			'lang_key'	=> function( $val ) {
				return Member::loggedIn()->language()->addToStack( $val . '_stats' );
			},
			'ctime'	=> function( $val )
			{
				return (string) IPSDateTime::ts( $val );
			}
		);
		$chart->tableLangPrefix = 'mod_stats_';
		
		$customActionValues = ( isset( $chart->savedCustomFilters['chart_actions'] ) ) ? array_values( explode( ',', $chart->savedCustomFilters['chart_actions'] ) ) : -1;
		$customGroupValues = ( isset( $chart->savedCustomFilters['chart_groups'] ) ) ? array_values( explode( ',', $chart->savedCustomFilters['chart_groups'] ) ) : -1;
		
		$allActions = array();
		foreach( Db::i()->select( 'DISTINCT(lang_key)', 'core_moderator_logs' ) AS $action )
		{
			$allActions[ $action ] = $action . '_stats';
		}
		
		$allGroups = array();
		foreach( Group::groups( TRUE, FALSE ) AS $group )
		{
			$allGroups[ $group->g_id ] = $group->name;
		}
		
		$chart->customFiltersForm = array(
			'form' => array(
				new Select( 'chart_actions', $customActionValues, FALSE, array( 'options' => $allActions, 'multiple' => TRUE, 'noDefault' => TRUE, 'unlimited' => -1 ), NULL, NULL, NULL, 'chart_actions' ),
				new Select( 'chart_groups', $customGroupValues, FALSE, array( 'options' => $allGroups, 'multiple' => TRUE, 'noDefault' => TRUE, 'unlimited' => -1 ), NULL, NULL, NULL, 'chart_groups' ),
			),
			'where' => function( $values )
			{
				$actions = array();
				if ( $values['chart_actions'] != -1 )
				{
					$actions	= is_array( $values['chart_actions'] ) ? array_values( $values['chart_actions'] ) : explode( ',', $values['chart_actions'] );
				}
				
				$groups = array();
				if ( $values['chart_groups'] != -1 )
				{
					$groups		= is_array( $values['chart_groups'] ) ? array_values( $values['chart_groups'] ) : explode( ',', $values['chart_groups'] );
				}
				
				$where		= array();
				if ( count( $actions ) )
				{
					$where[] = Db::i()->in( 'lang_key', $actions );
				}

				if ( count( $groups ) )
				{
					$where[] = Db::i()->in( 'core_members.member_group_id', $groups );
				}
				
				if ( count( $where ) )
				{
					return array( implode( ' AND ', $where ) );
				}
				else
				{
					return array();
				}
			},
			'groupBy' => 'lang_key',
			'series'  => function( $values ) use ( $allActions )
			{
				$series = array();
				if ( $values['chart_actions'] == -1 )
				{
					$custom = array_keys( $allActions );
				}
				else
				{
					$custom = is_array( $values['chart_actions'] ) ? array_values( $values['chart_actions'] ) : explode( ',', $values['chart_actions'] );
				}
				
				foreach( $custom as $key )
				{
					$series[] = array( Member::loggedIn()->language()->addToStack( $key . '_stats' ), 'number', 'COUNT(*)', FALSE, $key );
				}
				return $series;
			},
			'defaultSeries' => function() use ( $allActions )
			{
                $series = array();
				foreach( $allActions as $key => $lang )
				{
					$series[] = array( Member::loggedIn()->language()->addToStack( $lang ), 'number', 'COUNT(*)', FALSE, $key );
				}

				return $series;
			}
		);
		
		return $chart;
	}
}