<?php
/**
 * @brief		solved
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		25 Jul 2022
 */

namespace IPS\forums\modules\admin\stats;

use IPS\Dispatcher\Controller;
use IPS\Dispatcher;
use IPS\Output;
use IPS\Request;
use IPS\Http\Url;
use IPS\Http\Url\Friendly;
use IPS\core\Statistics\Chart;
use IPS\Member;
use IPS\Theme;
use IPS\Helpers\Table\Db as TableDb;
use IPS\Session;
use IPS\Db;
use IPS\Task;
use IPS\forums\Forum;
use Exception;

use function defined;
use function header;
use function array_key_exists;

use const IPS\Helpers\Table\SEARCH_NODE;
use const IPS\Helpers\Table\SEARCH_MEMBER;
use const IPS\Helpers\Table\SEARCH_DATE_RANGE;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * solved
 */
class _solved extends Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * @brief	Allow MySQL RW separation for efficiency
	 */
	public static $allowRWSeparation = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		Dispatcher::i()->checkAcpPermission( 'topics_manage' );
		parent::execute();
	}

	/**
	 * Show the stats then
	 *
	 * core_statistics mapping:
	 * type: solved
	 * value_1: forum_id
	 * value_2: total topics added
	 * value_3: total solved
	 * value_4: AVG time to solved (in seconds)
	 * time: timestamp of the start of the day (so 0:00:00)
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$tabs = array(
			'time' 		 => 'forums_solved_stats_time',
			'percentage' => 'forums_solved_stats_percentage',
			'solved'	 => 'stats_topics_tab_solved',
			'groups'	 => 'stats_topics_tab_groups',
			'unsolved'	 => 'stats_topics_tab_unsolved'
		);

		/* Show button to adjust settings */
		Output::i()->sidebar['actions']['settings'] = array(
			'icon'		=> 'cog',
			'title'		=> 'solved_stats_rebuild_button',
			'link'		=> Url::internal( 'app=forums&module=stats&controller=solved&do=rebuildStats' )->csrf(),
			'data'		=> array( 'confirm' => '' )
		);
		
		Request::i()->tab ??= 'time';
		$activeTab = ( isset( Request::i()->tab ) and array_key_exists( Request::i()->tab, $tabs ) ) ? Request::i()->tab : 'time';
		
		$url = Url::internal( "app=forums&module=stats&controller=solved&tab={$activeTab}" );
		$output = match( $activeTab ) {
			'percentage'	=> Chart::loadFromExtension( 'forums', 'PercentageSolved' )->getChart( $url ),
			'solved'		=> Chart::loadFromExtension( 'forums', 'SolvedByForum' )->getChart( $url ),
			'groups'		=> CHart::LoadFromExtension( 'forums', 'SolvedByGroup' )->getChart( $url ),
			'unsolved'		=> $this->_unsolved(),
			default			=> Chart::loadFromExtension( 'forums', 'TimeSolved' )->getChart( $url )
		};
		
		if ( Request::i()->isAjax() )
		{
			Output::i()->output = (string) $output;
		}
		else
		{
			Output::i()->title = Member::loggedIn()->language()->addToStack( 'menu__forums_stats_solved' );
			Output::i()->output = Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string) $output, Url::internal( "app=forums&module=stats&controller=solved" ) );
		}
	}
	
	/**
	 * Unsolved Topics
	 *
	 * @return	\IPS\Helpers\Table\Db
	 */
	protected function _unsolved(): TableDb
	{
		/* Load Forums into Memory */
		Forum::loadIntoMemory( NULL );
		
		$table							= new TableDb( 'forums_topics', Url::internal( "app=forums&module=stats&controller=solved&tab=unsolved" ), static::_unsolvedWHere() );
		$table->langPrefix				= 'topics_noposts_';
		$table->include					= array( 'tid', 'title', 'state', 'start_date', 'starter_id', 'views', 'forum_id', 'approved' );
		
		$table->parsers = array(
			'title'			=> function( $val, $row )
			{
				/* Manually construct URL to save on queries */
				$url = Url::internal( "app=forums&module=forums&controller=topic&id={$row['tid']}", 'front', 'forums_topic', array( $row['title_seo'] ) );
				return Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $url, TRUE, $val );
			},
			'state'			=> function( $val )
			{
				return match( $val ) {
					'open'		=> Member::loggedIn()->language()->addToStack('open'),
					'closed'	=> Member::loggedIn()->language()->addToStack('locked')
				};
			},
			'start_date'	=> function( $val )
			{
				return (string) \IPS\DateTime::ts( $val );
			},
			'starter_id'	=> function( $val, $row )
			{
				if ( $val )
				{
					$url = Url::internal( "app=core&module=members&controller=profile&id={$val}", 'front', 'profile', array( Friendly::seoTitle( $row['starter_name'] ) ) );
					return Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $url, TRUE, $row['starter_name'] );
				}
				else
				{
					if ( $row['is_anon'] )
					{
						return Member::loggedIn()->language()->addToStack( 'post_anonymously_placename' );
					}
					else
					{
						$guest = new Member;
						if ( $row['starter_name'] )
						{
							$guest->name = $row['starter_name'];
						}
						
						return $guest->name;
					}
				}
			},
			'views'			=> function( $val )
			{
				return Member::loggedIn()->language()->formatNumber( $val );
			},
			'forum_id'		=> function( $val )
			{
				$forum = Forum::load( $val );
				if ( $club = $forum->club() )
				{
					$title = Member::loggedIn()->language()->addToStack( 'club_container_title', FALSE, array( 'sprintf' => array( $club->name, $forum->_title ) ) );
				}
				else
				{
					$title = $forum->_title;
				}
				
				return Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $forum->url(), TRUE, $title );
			},
			'approved'		=> function( $val )
			{
				if ( $val )
				{
					return "&#10003;";
				}
				else
				{
					return "&#10007;";
				}
			}
		);
		
		$table->filters = array(
			'open'			=> "state='open'",
			'locked'		=> "state='closed'",
			'approved'		=> "approved=1",
			'unapproved'	=> "approved=0"
		);
		
		$table->advancedSearch['forum_id']		= array( SEARCH_NODE, array( 'class' => 'IPS\\forums\\Forum', 'clubs' => TRUE ) );
		$table->advancedSearch['starter_id']	= SEARCH_MEMBER;
		$table->advancedSearch['start_date']	= SEARCH_DATE_RANGE;
		
		return $table;
	}
	
	/**
	 * Get Unsolved Topics Where Clause
	 *
	 * @return	array
	 */
	protected static function _unsolvedWhere(): array
	{
		$where = array();
		$where[] = array( "topic_answered_pid=?", 0 ); // Looks weird, but this is the correct way to see topics with no replies.
		$where[] = array( "pinned=?", 0 );
		$where[] = array( "featured=?", 0 );
		$where[] = array( Db::i()->in( 'approved', array( 0, 1 ) ) );
		$where[] = array( Db::i()->in( 'forum_id', Db::i()->select( 'id', 'forums_forums' ) ) );
		$where[] = array( Db::i()->in( 'state', array( 'link', 'merged' ), TRUE ) );
		
		return $where;
	}
	
	/**
	 * Kick off a rebuild of the stats
	 *
	 */
	public function rebuildStats()
	{
		Session::i()->csrfCheck();

		foreach( Db::i()->select( '*', 'forums_forums', array( 'topics>? and ( forums_bitoptions & ? or forums_bitoptions & ? or forums_bitoptions & ? )', 0, 4, 8, 16 ) ) as $forum )
		{
			Task::queue( 'forums', 'RebuildSolvedStats', array( 'forum_id' => $forum['id'] ) );
		}

		Output::i()->redirect( \IPS\Http\Url::internal('app=forums&module=stats&controller=solved'), 'solved_stats_rebuild_started' );
	}
	
	/**
	 * Get valid forum IDs to protect against bad data when a forum is removed
	 *
	 * @return array
	 */
	protected function getValidForumIds()
	{
		$validForumIds = [];
		
		foreach( Db::i()->select( 'value_1', 'core_statistics', [ 'type=?', 'solved' ], NULL, NULL, 'value_1' ) as $forumId )
		{
			try
			{
				$validForumIds[ $forumId ] = Forum::load( $forumId );
			}
			catch( Exception $e ) { }
		}
		
		return $validForumIds;
	}
}