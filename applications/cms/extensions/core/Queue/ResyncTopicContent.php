<?php
/**
 * @brief		Background Task: Resychronise the first post of a topic generated by Pages
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		7 Oct 2016
 */

namespace IPS\cms\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild database records
 */
class _ResyncTopicContent
{
	/**
	 * @brief Number of content items to rebuild per cycle
	 */
	public $rebuild	= \IPS\REBUILD_NORMAL;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		try
		{
			$database = \IPS\cms\Databases::load( $data['databaseId'] );
			
			if ( $database->forum_record )
			{
				$data['count'] = (int) \IPS\Db::i()->select( 'COUNT(*)', 'cms_custom_database_' . $data['databaseId'] )->first();
			}
			else
			{
				$cats = iterator_to_array( \IPS\Db::i()->select( 'category_id', 'cms_database_categories', array( 'category_forum_record=1 AND category_database_id=?', $data['databaseId'] ) ) );
				
				if ( \count( $cats ) )
				{
					$data['count'] = (int) \IPS\Db::i()->select( 'COUNT(*)', 'cms_custom_database_' . $data['databaseId'], array( \IPS\Db::i()->in( 'category_id', $cats ) ) )->first();
				}
				else
				{
					return null;
				}
			}
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}
		
		if( $data['count'] == 0 )
		{
			return null;
		}

		$data['completed'] = 0;
		
		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed						$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int							$offset	Offset
	 * @return	int							New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( &$data, $offset )
	{
		$class	= '\IPS\cms\Records' . $data['databaseId'];
		$where	= array( 'primary_id_field>?', $offset );
		$last	= NULL;

		try
		{
			$database = \IPS\cms\Databases::load( $data['databaseId'] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		if ( ! $database->forum_record )
		{
			$cats = iterator_to_array( \IPS\Db::i()->select( 'category_id', 'cms_database_categories', array( 'category_forum_record=1 AND category_database_id=?', $data['databaseId'] ) ) );
			$where[] = array( \IPS\Db::i()->in( 'category_id', $cats ) );
		}

		if ( \IPS\Db::i()->checkForTable( 'cms_custom_database_' . $data['databaseId'] ) )
		{
			foreach ( \IPS\Db::i()->select( '*', 'cms_custom_database_' . $data['databaseId'], $where, 'primary_id_field asc', array( 0, $this->rebuild ) ) as $row )
			{
				try
				{
					$record = $class::constructFromData( $row );
					$record->syncTopic();
				}
				catch( \Exception $ex ) { }
				
				$data['completed']++;
				$last = $row['primary_id_field'];
			}
		}
		
		if( $last === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $last;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		$title = ( \IPS\Application::appIsEnabled('cms') ) ? \IPS\cms\Databases::load( $data['databaseId'] )->_title : 'Database #' . $data['databaseId'];
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_cms_database_sync_topics', FALSE, array( 'sprintf' => array( $title ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $data['completed'], 2 ) ) : 100 );
	}	
}