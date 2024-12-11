<?php
/**
 * @brief		topics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		18 Aug 2014
 */

namespace IPS\forums\modules\admin\stats;

use IPS\Dispatcher;
use IPS\Dispatcher\Controller;
use IPS\Db;
use IPS\Request;
use IPS\core\Statistics\Chart;
use IPS\Http\Url;
use IPS\Http\Url\Friendly;
use IPS\Output;
use IPS\Member;
use IPS\Theme;
use IPS\Helpers\Table\Db as TableDb;
use IPS\forums\Forum;

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
 * topics
 */
class _topics extends Controller
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
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		Dispatcher::i()->checkAcpPermission( 'topics_manage' );

		$tabs = array( 'total' => 'stats_topics_tab_total' );

        if( Db::i()->select( 'COUNT(*)', 'forums_forums', array( 'topics>?', 0 ) )->first() )
        {
            $tabs['byforum'] = 'stats_topics_tab_byforum';
        }
        
        if ( Db::i()->select( 'COUNT(*)', 'forums_topics', static::_noPostsWhere() )->first() )
        {
	        $tabs['noposts'] = 'stats_topics_tab_noposts';
        }

		Request::i()->tab ??= 'total';
		$activeTab	= ( array_key_exists( Request::i()->tab, $tabs ) ) ? Request::i()->tab : 'total';

		$output = match( $activeTab ) {
			'total'		=> Chart::loadFromExtension( 'forums', 'Topics' )->getChart( Url::internal( "app=forums&module=stats&controller=topics&tab=total" ) ),
			'byforum'	=> Chart::loadFromExtension( 'forums', 'TopicsByForum' )->getChart( Url::internal( "app=forums&module=stats&controller=topics&tab=byforum" ) ),
			'noposts'	=> $this->_noPostsTable()
		};

		if ( Request::i()->isAjax() )
		{
			Output::i()->output = (string) $output;
		}
		else
		{	
			Output::i()->title = Member::loggedIn()->language()->addToStack('menu__forums_stats_topics');
			Output::i()->output = Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string) $output, Url::internal( "app=forums&module=stats&controller=topics" ) );
		}
	}
	
	/**
	 * Topics with No Posts Table
	 *
	 * @return	\IPS\Helpers\Table\Db
	 */
	protected function _noPostsTable(): TableDb
	{
		/* Load Forums into Memory */
		Forum::loadIntoMemory( NULL );
		
		$table							= new TableDb( 'forums_topics', Url::internal( "app=forums&module=stats&controller=topics&tab=noposts" ), static::_noPostsWhere() );
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
	 * Get No Posts Where Clause
	 *
	 * @return	array
	 */
	protected static function _noPostsWhere(): array
	{
		$where = array();
		$where[] = array( "posts=?", 1 ); // Looks weird, but this is the correct way to see topics with no replies.
		$where[] = array( "pinned=?", 0 );
		$where[] = array( "featured=?", 0 );
		$where[] = array( Db::i()->in( 'approved', array( 0, 1 ) ) );
		$where[] = array( Db::i()->in( 'forum_id', Db::i()->select( 'id', 'forums_forums' ) ) );
		$where[] = array( Db::i()->in( 'state', array( 'link', 'merged' ), TRUE ) );
		
		return $where;
	}
}