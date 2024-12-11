<?php
/**
 * @brief		rebuildPopularTime Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
{subpackage}
 * @since		14 May 2024
 */

namespace IPS\forums\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * rebuildPopularTime Task
 */
class _rebuildPopularTime extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		/* We don't use this on cloud */
		if( \IPS\CIC )
		{
			return NULL;
		}

		/* Check if popular now is enabled */
		$popularNowSettings = json_decode( \IPS\Settings::i()->forums_popular_now, TRUE );
		if ( !$popularNowSettings['posts'] or !$popularNowSettings['minutes'] )
		{
			return NULL;
		}

		$cutoff = $this->last_run ?: ( time() - ( $popularNowSettings['minutes'] * 30 ) );
$topicIds = iterator_to_array(
			\IPS\Db::i()->select( 'DISTINCT topic_id', 'forums_posts', [ 'post_date > ?', $cutoff ], null, 15 )
		);

		if( \count( $topicIds ) )
		{
			foreach( new \IPS\Patterns\ActiveRecordIterator(
				\IPS\Db::i()->select( '*', 'forums_topics', \IPS\Db::i()->in( 'tid', $topicIds ) ),
				'IPS\forums\Topic'
					 ) as $topic )
			{
				$topic->rebuildPopularTime();
			}
		}

		return NULL;
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}