<?php

/**
 * @brief        DelayedCount
 * @author        <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) Invision Power Services, Inc.
 * @license        https://www.invisioncommunity.com/legal/standards/
 * @package        Invision Community
 * @subpackage
 * @since        6/25/2024
 */

namespace IPS\Node;
use function array_key_exists;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

trait DelayedCount
{
	/**
	 * @var string
	 */
	public static string $storageKey = 'nodeSyncTimes';

	/**
	 * Cutoff time, in minutes
	 *
	 * @var int
	 */
	protected static int $cutoff = 5;

	/**
	 * @return string
	 */
	protected function getStorageId() : string
	{
		return \get_called_class() . '_' . $this->_id;
	}

	/**
	 * Store the update time in Redis so that we know it needs to be
	 * synced later
	 *
	 * @return void
	 */
	public function storeUpdateTime()
	{
		/* Check Redis to see if we already have this node stored.
			If not, set it with a timestamp. */
		try
		{
			if( ! \IPS\Redis::isEnabled() )
			{
				throw new \BadMethodCallException;
			}

			/* hGet will return a false value if it doesn't exist, or the key doesn't exist in the Redis database */
			$timestamp = \IPS\Redis::i()->hGet( static::$storageKey, $this->getStorageId() );

			if( empty( $timestamp ) )
			{
				\IPS\Redis::i()->hSet( static::$storageKey, $this->getStorageId(), time() );
			}
		}
		catch( \BadMethodCallException | \RedisException )
		{
			try
			{
				$key = static::$storageKey;
				$timestamps = \IPS\Data\Store::i()->$key;
			}
			catch( \OutOfRangeException )
			{
				$timestamps = [];
			}

			if( !array_key_exists( $this->getStorageId(), $timestamps ) )
			{
				$timestamps[ $this->getStorageId() ] = time();
				\IPS\Data\Store::i()->$key = $timestamps;
			}
		}

		/* Bubble up to parent nodes */
		if( $parent = $this->parent() )
		{
			$parent->storeUpdateTime();
		}
	}

	/**
	 * Check Redis to see if this node needs to be synced
	 *
	 * @return void
	 */
	public function checkUpdateTime()
	{
		/* Get the timestamp from Redis. If it's more than 30 minutes,
			run the recount method. */
		$timestamp = false;
		try
		{
			if( ! \IPS\Redis::isEnabled() )
			{
				throw new \BadMethodCallException;
			}

			$timestamp = \IPS\Redis::i()->hGet( static::$storageKey, $this->getStorageId() );
		}
		catch( \BadMethodCallException | \RedisException ){}
		{
			try
			{
				$key = static::$storageKey;
				$timestamps = \IPS\Data\Store::i()->$key;

				if ( isset( $timestamps[ $this->getStorageId() ] ) )
				{
					$timestamp = $timestamps[ $this->getStorageId() ];
				}
			}
			catch( \OutOfRangeException ){}
		}

		if( $timestamp and $timestamp < ( time() - ( static::$cutoff * 60 ) ) )
		{
			/* Remove it from the storage first to avoid getting stuck in a setLastComment->node::load() -> checkUpdatetime -> setLastComment->node::load() loop */
			$this->clearUpdateTime();

			/* Recount items, comments, and reviews */
			$this->recount();

			/* Force a reset of the last comment */
			$this->setLastComment();
		}
	}

	/**
	 * Remove the node from Redis
	 *
	 * @return void
	 */
	public function clearUpdateTime()
	{
		try
		{
			if( ! \IPS\Redis::isEnabled() )
			{
				throw new \BadMethodCallException;
			}

			\IPS\Redis::i()->hDel( static::$storageKey, $this->getStorageId() );
		}
		catch( \BadMethodCallException | \RedisException ){}
		{
			try
			{
				$key = static::$storageKey;

				/* @var array $timestamps */
				$timestamps = \IPS\Data\Store::i()->$key;
				if( array_key_exists( $this->getStorageId(), $timestamps ) )
				{
					unset( $timestamps[ $this->getStorageId() ] );
					\IPS\Data\Store::i()->$key = $timestamps;
				}
			}
			catch( \OutOfRangeException ){}
		}
	}

	/**
	 * Count all comments, items, etc
	 *
	 * @return mixed
	 */
	abstract protected function recount();

	/**
	 * Update counts if needed
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		$this->checkUpdateTime();
	}
}