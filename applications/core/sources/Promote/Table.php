<?php
/**
 * @brief		Promotions Table Helper
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		22 Feb 2017
 */

namespace IPS\core\Promote;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Promote Table Helper
 */
class _Table extends \IPS\Helpers\Table\Table
{
	/**
	 * @brief	Sort options
	 */
	public $sortOptions = array( 'promote_scheduled' );
	
	/**
	 * @brief	Rows
	 */
	protected static $rows = null;
	
	/**
	 * @brief	WHERE clause
	 */
	protected $where = array();
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url	Base URL
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url=NULL )
	{
		/* Init */	
		parent::__construct( $url );
		
		$classes = array();
		
		/* Only include classes for enabled applications. */
		foreach( \IPS\Content::routedClasses( FALSE, FALSE, TRUE ) AS $class )
		{
			$classes[] = $class;
		}
		
		$this->where[] = array( \IPS\Db::i()->in( 'promote_class', $classes ) );

		$this->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'promote', 'core', 'front' ), 'promoteTable' );
		$this->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'promote', 'core', 'front' ), 'promoteTableRows' );
	}

	/**
	 * Set member
	 *
	 * @param	\IPS\Member	$member		The member to filter by
	 * @return	void
	 */
	public function setMember( \IPS\Member $member )
	{
		$this->where[] = array( 'promote_added_by=?', $member->member_id );
	}

	/**
	 * Get rows
	 *
	 * @param	array	$advancedSearchValues	Values from the advanced search form
	 * @return	array
	 */
	public function getRows( $advancedSearchValues=NULL )
	{
		if ( static::$rows === NULL )
		{
			/* Check sortBy */
			$this->sortBy = \in_array( $this->sortBy, $this->sortOptions ) ? $this->sortBy : 'promote_scheduled';
	
			/* What are we sorting by? */
			$sortBy = $this->sortBy . ' ' . ( mb_strtolower( $this->sortDirection ) == 'asc' ? 'asc' : 'desc' );
	
			/* Specify filter in where clause */
			$where = isset( $this->where ) ? \is_array( $this->where ) ? $this->where : array( $this->where ) : array();
			
			if ( $this->filter and isset( $this->filters[ $this->filter ] ) )
			{
				$where[] = \is_array( $this->filters[ $this->filter ] ) ? $this->filters[ $this->filter ] : array( $this->filters[ $this->filter ] );
			}
	
			/* Get Count */
			$count = \IPS\Db::i()->select( 'COUNT(*) as cnt', 'core_social_promote', $where )->first();
	  		$this->pages = ceil( $count / $this->limit );
	
			/* Get results */
			$it = \IPS\Db::i()->select( '*', 'core_social_promote', $where, $sortBy, array( ( $this->limit * ( $this->page - 1 ) ), $this->limit ) );
			$rows = iterator_to_array( $it );
	
			foreach( $rows as $index => $row )
			{
				try
				{
					static::$rows[ $index ]	= \IPS\core\Promote::constructFromData( $row );
				}
				catch ( \Exception $e ) { }
			}
		}
		
		/* Return */
		return static::$rows;
	}

	/**
	 * Return the table headers
	 *
	 * @param	array|NULL	$advancedSearchValues	Advanced search values
	 * @return	array
	 */
	public function getHeaders( $advancedSearchValues )
	{
		return array();
	}
}