<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Forums
 * @since		15 Dec 2023
 */

namespace IPS\forums\extensions\core\Statistics;

use IPS\core\Statistics\Chart as ParentClass;
use IPS\Db;
use IPS\Helpers\Chart;
use IPS\Helpers\Chart\Database as DbChart;
use IPS\Http\Url;
use IPS\DateTime as IPSDateTime;
use IPS\Member;
use IPS\Helpers\Form\Select;
use IPS\Member\Group;

use UnderflowException;
use DateInterval;
use Exception;

use function defined;
use function header;


/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _SolvedByGroup extends ParentClass
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'forums_stats_solved_groups';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( Url $url ): Chart
	{
		/* Determine minimum date */
		$minimumDate = NULL;
		
		/* We can't retrieve any stats prior to the new tracking being implemented */
		try
		{
			$oldestLog = \IPS\Db::i()->select( 'MIN(time)', 'core_statistics', array( 'type=?', 'solved' ) )->first();
		
			if( !$minimumDate OR $oldestLog < $minimumDate->getTimestamp() )
			{
				$minimumDate = \IPS\DateTime::ts( $oldestLog );
			}
		}
		catch( \UnderflowException $e )
		{
			/* We have nothing tracked, set minimum date to today */
			$minimumDate = \IPS\DateTime::create();
		}
		
		$chart = new \IPS\Helpers\Chart\Database( $url, 'core_solved_index', 'solved_date', '', array( 
				'isStacked' => FALSE,
				'backgroundColor' 	=> '#ffffff',
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4,
				'chartArea'			=> array( 'width' => '70%', 'left' => '5%' ),
				'height'			=> 400,
			),
			'LineChart',
			'monthly',
			array( 'start' => ( new \IPS\DateTime )->sub( new \DateInterval('P90D') ), 'end' => new \IPS\DateTime ),
			array(),
			'solved' 
		);
		$chart->setExtension( $this );
		
		$chart->joins = array( array( 'forums_posts', array( 'comment_class=? and core_solved_index.comment_id=forums_posts.pid', 'IPS\forums\Topic\Post' ) ), array( 'core_members', array( "core_members.member_id=forums_posts.author_id" ) ) );
		$chart->where = array( array( 'queued=?', 0 ) ); 
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack( 'stats_topics_tab_groups' );
		$chart->availableTypes = array( 'LineChart', 'ColumnChart' );
	
		$chart->groupBy = 'member_group_id';
		$customValues = ( isset( $chart->savedCustomFilters['chart_groups'] ) ? array_values( explode( ',', $chart->savedCustomFilters['chart_groups'] ) ) : 0 );
		
		$chart->customFiltersForm = array(
			'form' => array(
				new \IPS\Helpers\Form\Select( 'chart_groups', $customValues, FALSE, array( 'options' => $this->_getGroups(), 'zeroVal' => 'any', 'multiple' => TRUE ), NULL, NULL, NULL, 'chart_groups' )
			),
			'where' => function( $values )
			{
				$groups = \is_array( $values['chart_groups'] ) ? array_values( $values['chart_groups'] ) : explode( ',', $values['chart_groups'] );
				if ( count( $groups ) )
				{
					return \IPS\Db::i()->in( 'member_group_id', $groups );
				}
				else
				{
					return '';
				}
			},
			'groupBy' => 'member_group_id',
			'series'  => function( $values )
			{
				$series = array();
				$groups = array_filter( \is_array( $values['chart_groups'] ) ? array_values( $values['chart_groups'] ) : explode( ',', $values['chart_groups'] ) );
				if ( count( $groups ) )
				{
					foreach( $groups as $id )
					{
						$series[] = array( \IPS\Member::loggedIn()->language()->addToStack( 'core_group_' . $id ), 'number', 'COUNT(*)', FALSE, $id );
					}
				}
				else
				{
					foreach( $this->_getGroups() AS $id => $group )
					{
						$series[] = array( \IPS\Member::loggedIn()->language()->addToStack( 'core_group_' . $id ), 'number', 'COUNT(*)', FALSE, $id );
					}
				}
				return $series;
			},
			'defaultSeries' => function()
			{
				$series = array();
				foreach( $this->_getGroups() AS $id => $group )
				{
					$series[] = array( \IPS\Member::loggedIn()->language()->addToStack( 'core_group_' . $id ), 'number', 'COUNT(*)', FALSE, $id );
				}
				return $series;
			}
		);
		
		return $chart;
	}
	
	/**
	 * Groups
	 *
	 * @return	array
	 */
	protected function _getGroups(): array
	{
		$return = array();
		foreach( \IPS\Member\Group::groups( TRUE, FALSE ) AS $group )
		{
			$return[ $group->g_id ] = $group->name;
		}
		
		return $return;
	}
}