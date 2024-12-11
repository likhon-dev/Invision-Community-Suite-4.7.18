<?php

/**
 * @brief		Converter Invisioncommunnity Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Converter
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Calendar;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Invisioncommunity extends \IPS\convert\Software
{
	/**
	 * @brief 	Whether the versions of IPS4 match
	 */
	public static $versionMatch = FALSE;

	/**
	 * @brief 	Whether the database has been required
	 */
	public static $dbNeeded = FALSE;

	/**
	 * Constructor
	 *
	 * @param	\IPS\convert\App	$app	The application to reference for database and other information.
	 * @param	bool				$needDB	Establish a DB connection
	 * @return	void
	 * @throws	\InvalidArgumentException
	 */
	public function __construct( \IPS\convert\App $app, $needDB=TRUE )
	{
		/* Set filename obscuring flag */
		\IPS\convert\Library::$obscureFilenames = FALSE;

		$return = parent::__construct( $app, $needDB );

		if( $needDB )
		{
			static::$dbNeeded = TRUE;

			try
			{
				$version = $this->db->select( 'app_version', 'core_applications', array( 'app_directory=?', 'core' ) )->first();

				/* We're matching against the human version since the long version can change with patches */
				if ( $version == \IPS\Application::load( 'core' )->version )
				{
					static::$versionMatch = TRUE;
				}
			}
			catch( \IPS\Db\Exception $e ) {}

			/* Get parent sauce */
			$this->parent = $this->app->_parent->getSource();
		}

		return $return;
	}
	
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return 'Invision Community (' . \IPS\Application::load( 'core' )->version . ')';
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "invisioncommunity";
	}
	
	/**
	 * Requires Parent
	 *
	 * @return	boolean
	 */
	public static function requiresParent()
	{
		return TRUE;
	}
	
	/**
	 * Possible Parent Conversions
	 *
	 * @return	array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'invisioncommunity' ) );
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array|NULL
	 * @note	NULL indicates this converter cannot be used yet, useful to move redirect scripts into the 4.x framework
	 */
	public static function canConvert()
	{
		return array(
			'convertCalendarCalendars'		=> array(
				'table'		=> 'calendar_calendars',
				'where'		=> NULL,
			),
			'convertCalendarVenues'			=> array(
				'table'		=> 'calendar_venues',
				'where'		=> NULL,
			),
			'convertCalendarEvents'			=> array(
				'table'		=> 'calendar_events',
				'where'		=> NULL,
			),
			'convertCalendarComments'		=> array(
				'table'		=> 'calendar_event_comments',
				'where'		=> NULL
			),
			'convertCalendarReviews'		=> array(
				'table'		=> 'calendar_event_reviews',
				'where'		=> NULL,
			),
			'convertCalendarRsvps'			=> array(
				'table'		=> 'calendar_event_rsvp',
				'where'		=> NULL,
			),
			'convertCalendarFeeds'			=> array(
				'table'		=> 'calendar_import_feeds',
				'where'		=> NULL,
			),
			'convertCalendarReminders'		=> array(
				'table'		=> 'calendar_event_reminders',
				'where'		=> NULL,
			),
			'convertAttachments'			=> array(
				'table'		=> 'core_attachments_map',
				'where'		=> array( "location_key=?", 'calendar_Calendar' )
			)
		);
	}
	
	/**
	 * Can we convert passwords from this software.
	 *
	 * @return 	boolean
	 */
	public static function loginEnabled()
	{
		return FALSE;
	}
	
	/**
	 * Can we convert settings?
	 *
	 * @return	boolean
	 */
	public static function canConvertSettings()
	{
		return FALSE;
	}
	
	/**
	 * Settings Map
	 *
	 * @return	array
	 */
	public function settingsMap()
	{
		return array();
	}
	
	/**
	 * Settings Map Listing
	 *
	 * @return	array
	 */
	public function settingsMapList()
	{
		return array();
	}
	
	/**
	 * Returns a block of text, or a language string, that explains what the admin must do to start this conversion
	 *
	 * @return	string|NULL
	 */
	public static function getPreConversionInformation()
	{
		return NULL;
	}
	
	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertAttachments'
		);
	}
	
	/**
	 * Get More Information
	 *
	 * @param	string	$method	Method name
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();

		switch( $method )
		{
			case 'convertAttachments':
				\IPS\Member::loggedIn()->language()->words["upload_path"] = \IPS\Member::loggedIn()->language()->addToStack( 'convert_invision_upload_input' );
				\IPS\Member::loggedIn()->language()->words["upload_path_desc"] = \IPS\Member::loggedIn()->language()->addToStack( 'convert_invision_upload_input_desc' );
				$return[ $method ] = array(
					'upload_path'				=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Text',
						'field_default'		=> isset( $this->parent->app->_session['more_info']['convertEmoticons']['upload_path'] ) ? $this->parent->app->_session['more_info']['convertEmoticons']['upload_path'] : NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array(),
						'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_invision_upload_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					)
				);
				break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}
	
	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		/* Content Rebuilds */
		\IPS\Task::queue( 'convert', 'InvisionCommunityRebuildContent', array( 'app' => $this->app->app_id, 'link' => 'calendar_events', 'class' => 'IPS\calendar\Event' ), 2, array( 'app', 'link', 'class' ) );
		\IPS\Task::queue( 'convert', 'InvisionCommunityRebuildContent', array( 'app' => $this->app->app_id, 'link' => 'calendar_event_comments', 'class' => 'IPS\calendar\Event\Comment' ), 2, array( 'app', 'link', 'class' ) );
		\IPS\Task::queue( 'convert', 'InvisionCommunityRebuildContent', array( 'app' => $this->app->app_id, 'link' => 'calendar_event_reviews', 'class' => 'IPS\calendar\Event\Review' ), 2, array( 'app', 'link', 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\calendar\Event' ), 3, array( 'class' ) );
		
		return array( "f_rebuild_events", "f_recount_calendar" );
	}
	
	/**
	 * Fix post data
	 *
	 * @param 	string		raw post data
	 * @return 	string		parsed post data
	 */
	public static function fixPostData( $post )
	{
		return $post;
	}
	
	/**
	 * Convert Calendars
	 *
	 * @return	void
	 */
	public function convertCalendarCalendars()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'cal_id' );
		
		foreach( $this->fetch( 'calendar_calendars', 'cal_id' ) AS $row )
		{
			$this->parent->unsetNonStandardColumns( $row, 'calendar_calendars', 'calendar' );
			
			$row['cal_title']		= $this->parent->getWord( "calendar_calendar_{$row['cal_id']}" );
			$row['cal_description']	= $this->parent->getWord( "calendar_calendar_{$row['cal_id']}_desc" );
			
			$libraryClass->convertCalendar( $row );
			
			/* Follows */
			foreach( $this->db->select( '*', 'core_follow', array( "follow_app=? AND follow_area=? AND follow_rel_id=?", 'calendar', 'calendar', $row['cal_id'] ) ) AS $follow )
			{
				$this->parent->unsetNonStandardColumns( $follow, 'core_follow', 'core' );
				$libraryClass->convertFollow( $follow );
			}
			
			$libraryClass->setLastKeyValue( $row['cal_id'] );
		}
	}
	
	/**
	 * Convert Calendar Venues
	 *
	 * @return	void
	 */
	public function convertCalendarVanues()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'venue_id' );
		
		foreach( $this->fetch( 'calendar_venues', 'venue_id' ) AS $row )
		{
			$this->parent->unsetNonStandardColumns( $row, 'calendar_venues', 'calendar' );
			$libraryClass->convertCalendarVenue( $row );
			$libraryClass->setLastKeyValue( $row['venue_id'] );
		}
	}
	
	/**
	 * Convert Calendar Events
	 *
	 * @return	void
	 */
	public function convertCalendarEvents()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'event_id' );
		
		foreach( $this->fetch( 'calendar_events', 'event_id' ) AS $row )
		{
			$this->parent->unsetNonStandardColumns( $row, 'calendar_events', 'calendar' );
			
			$cover = NULL;
			if ( $row['event_cover_photo'] )
			{
				$cover = $this->app->_session['more_info']['convertAttachments']['upload_path'] . '/' . $row['event_cover_photo'];
			}
			
			$libraryClass->convertCalendarEvent( $row, $cover );
			
			/* Follows */
			foreach( $this->db->select( '*', 'core_follow', array( "follow_app=? AND follow_area=? AND follow_rel_id=?", 'calendar', 'event', $row['event_id'] ) ) AS $follow )
			{
				$this->parent->unsetnonStandardColumns( $follow, 'core_follow', 'core' );
				$libraryClass->convertFollow( $follow );
			}
			
			/* Reputation */
			foreach( $this->db->select( '*', 'core_reputation_index', array( "app=? AND type=? AND type_id=?", 'calendar', 'event_id', $row['event_id'] ) ) AS $rep )
			{
				$this->parent->unsetNonStandardColumns( $rep, 'core_reputation_index', 'core' );
				$libraryClass->convertReputation( $rep );
			}
			
			/* Tags */
			foreach( $this->db->select( '*', 'core_tags', array( "tag_meta_app=? AND tag_meta_area=? AND tag_meta_id=?", 'calendar', 'calendar', $row['event_id'] ) ) AS $tag )
			{
				$this->parent->unsetNonStandardColumns( $tag, 'core_tags', 'core' );
				$libraryClass->convertTag( $tag );
			}
		}
	}
		
	/**
	 * Convert Calendar Comments
	 *
	 * @return	void
	 */
	public function convertCalendarComments()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'comment_id' );
		
		foreach( $this->fetch( 'calendar_event_comments', 'comment_id' ) AS $row )
		{
			$this->parent->unsetNonStandardColumns( $row, 'calendar_event_comments', 'calendar' );
			$libraryClass->convertCalendarComment( $row );
			
			/* Reputation */
			foreach( $this->db->select( '*', 'core_reputation_index', array( "app=? AND type=? AND type_id=?", 'calendar', 'comment_id', $row['comment_id'] ) ) AS $rep )
			{
				$this->parent->unsetNonStandardColumns( $rep, 'core_reputation_index', 'core' );
				$libraryClass->convertReputation( $rep );
			}
			
			$libraryClass->setLastKeyValue( $row['comment_id'] );
		}
	}
	
	/**
	 * Convert Calendar Review
	 *
	 * @return	void
	 */
	public function convertCalendarReviews()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'review_id' );
		
		foreach( $this->fetch( 'calendar_event_reviews', 'review_id' ) AS $row )
		{
			$this->parent->unsetNonStandardColumns( $row, 'calendar_event_reviews', 'calendar' );
			$libraryClass->convertCalendarReview( $row );
			
			/* Reputation */
			foreach( $this->db->select( '*', 'core_reputation_index', array( "app=? AND type=? AND type_id=?", 'calendar', 'review_id', $row['review_id'] ) ) AS $rep )
			{
				$this->parent->unsetNonStandardColumns( $rep, 'core_reputation_index', 'core' );
				$libraryClass->convertReputation( $rep );
			}
			
			$libraryClass->setLastKeyValue( $row['review_id'] );
		}
	}
	
	/**
	 * Convert Calendar Reminders
	 *
	 * @return	void
	 */
	public function convertCalendarReminders()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'reminder_id' );
		
		foreach( $this->fetch( 'calendar_event_reminders', 'reminder_id' ) AS $row )
		{
			$this->parent->unsetNonStandardColumns( $row, 'calendar_event_reviews', 'calendar' );
			$libraryClass->convertCalendarReminder( $row );
			$libraryClass->setLastKeyValue( $row['reminder_id'] );
		}
	}
	
	/**
	 * Convert Calendar RSVPs
	 *
	 * return	void
	 */
	public function convertCalendarRsvps()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'rsvp_id' );
		
		foreach( $this->fetch( 'calendar_event_rsvp', 'rsvp_id' ) AS $row )
		{
			$this->parent->unsetNonStandardColumns( $row, 'calendar_event_rsvp', 'calendar' );
			$libraryClass->convertCalendarRsvp( $row );
			$libraryClass->setLastKeyValue( $row['rsvp_id'] );
		}
	}
	
	/**
	 * Convert Calendar Feeds
	 *
	 * @return	void
	 */
	public function convertCalendarFeeds()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'feed_id' );
		
		foreach( $this->fetch( 'calendar_import_feeds', 'feed_id' ) AS $row )
		{
			$this->parent->unsetNonStandardColumns( $row, 'calendar_import_feeds', 'calendar' );
			$libraryClass->convertCalendarFeed( $row );
			$libraryClass->setLastKeyValue( $row['feed_id'] );
		}
	}
	
	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'attach_id' );

		foreach( $this->fetch( 'core_attachments', 'attach_id' ) AS $row )
		{
			try
			{
				$attachmentMap = $this->db->select( '*', 'core_attachments_map', array( 'attachment_id=? AND location_key=?', $row['attach_id'], 'calendar_Calendar' ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$libraryClass->setLastKeyValue( $row['attach_id'] );
				continue;
			}

			/* Remove non-standard columns */
			$this->parent->unsetNonStandardColumns( $row, 'core_attachments', 'core' );
			$this->parent->unsetNonStandardColumns( $attachmentMap, 'core_attachments_map', 'core' );

			/* Remap rows */
			$name = explode( '/', $row['attach_location'] );
			$row['attach_container'] = isset( $name[1] ) ? $name[0] : NULL;
			$thumbName = explode( '/', $row['attach_thumb_location'] );
			$row['attach_thumb_container'] = isset( $thumbName[1] ) ? $thumbName[0] : NULL;

			$filePath = $this->app->_session['more_info']['convertAttachments']['upload_path'] . '/' . $row['attach_location'];
			$thumbnailPath = $this->app->_session['more_info']['convertAttachments']['upload_path'] . '/' . $row['attach_thumb_location'];

			unset( $row['attach_file'] );

			$libraryClass->convertAttachment( $row, $attachmentMap, $filePath, NULL, $thumbnailPath );		
			$libraryClass->setLastKeyValue( $row['attach_id'] );
		}
	}
}