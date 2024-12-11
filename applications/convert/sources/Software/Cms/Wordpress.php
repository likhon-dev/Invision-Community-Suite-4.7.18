<?php

/**
 * @brief		Converter Wordpress Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Cms;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Wordpress Pages Converter
 */
class _Wordpress extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "WordPress (5.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "wordpress";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertCmsPages'				=> array(
				'table'							=> 'posts',
				'where'							=> array( "post_type=?", 'page' )
			),
			'convertCmsDatabases'			=> array(
				'table'							=> 'cms_database',
				'where'							=> NULL
			),
			'convertCmsDatabaseCategories'	=> array(
				'table'							=> 'term_taxonomy',
				'where'							=> array( "taxonomy=?", 'category' )
			),
			'convertCmsDatabaseRecords'		=> array(
				'table'							=> 'posts',
				'where'							=> array( "post_type=?", 'post' ),
			),
			'convertCmsDatabaseComments'	=> array(
				'table'							=> 'comments',
				'where'							=> NULL
			),
			'convertAttachments'			=> array(
				'table'							=> 'posts',
				'where'							=> array( "post_type=?", 'attachment' )
			)
		);
	}

	/**
	 * Count Source Rows for a specific step
	 *
	 * @param	string		$table		The table containing the rows to count.
	 * @param	array|NULL	$where		WHERE clause to only count specific rows, or NULL to count all.
	 * @param	bool		$recache	Skip cache and pull directly (updating cache)
	 * @return	integer
	 * @throws	\IPS\convert\Exception
	 */
	public function countRows( $table, $where=NULL, $recache=FALSE )
	{
		switch( $table )
		{
			case 'cms_database':
				return 1;
				break;
			
			default:
				return parent::countRows( $table, $where, $recache );
				break;
		}
	}

	/**
	 * Requires Parent?
	 *
	 * @return	bool
	 */
	public static function requiresParent()
	{
		return TRUE;
	}
	
	/**
	 * Available Parents
	 *
	 * @return	array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'wordpress', 'wpforo' ) );
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertAttachments',
			'convertCmsDatabaseRecords'
		);
	}
	
	/**
	 * Get More Information
	 *
	 * @param	string	$method	Conversion method
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();
		
		switch( $method )
		{
			case 'convertCmsDatabaseRecords':
				$return['convertCmsDatabaseRecords']['file_location'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Text',
					'field_required'	=> TRUE,
					'field_default'		=> NULL,
					'field_extra'		=> array(),
					'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack( 'convert_wp_typical_path' ),
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				break;
				
			case 'convertAttachments':
				$return['convertAttachments']['file_location'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Text',
					'field_required'	=> TRUE,
					'field_default'		=> NULL,
					'field_extra'		=> array(),
					'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack( 'convert_wp_typical_path' ),
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
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
		try
		{
			$database = $this->app->getLink( 1, 'cms_databases' );
			\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\cms\Categories' . $database, 'count' => 0 ), 5, array( 'class' ) );
			\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\cms\Records' . $database ), 3, array( 'class' ) );

			\IPS\Task::queue( 'convert', 'RebuildTagCache', array( 'app' => $this->app->app_id, 'link' => 'cms_records', 'class' => 'IPS\cms\Records' . $database ), 3, array( 'app', 'link', 'class' ) );
			\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'link' => 'cms_custom_database_' . $database, 'class' => 'IPS\cms\Records' . $database ), 2, array( 'app', 'link', 'class' ) );
			\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'link' => 'cms_database_comments', 'class' => 'IPS\cms\Records\Comment' . $database ), 2, array( 'app', 'link', 'class' ) );

			return array( "f_recount_cms_categories", "f_rebuild_cms_tags" );
		}
		catch( \OutOfRangeException $e )
		{
			return array();
		}
	}

	/**
	 * Convert CMS pages
	 *
	 * @return	void
	 */
	public function convertCmsPages()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'ID' );
		
		foreach( $this->fetch( 'posts', 'ID', array( "post_type=?", 'page' ) ) AS $row )
		{
			$libraryClass->convertCmsPage( array(
				'page_id'		=> $row['ID'],
				'page_name'		=> $row['post_title'],
				'page_seo_name'	=> $row['post_name'],
				'page_content'	=> $row['post_content'],
			) );
			
			$libraryClass->setLastKeyValue( $row['ID'] );
		}
	}

	/**
	 * Convert CMS databases
	 *
	 * @return	void
	 */
	public function convertCmsDatabases()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass->convertCmsDatabase( array(
			'database_id'			=> 1,
			'database_name'			=> "WordPress Posts",
			'database_sln'			=> 'post',
			'database_pln'			=> 'posts',
			'database_scn'			=> 'Post',
			'database_pcn'			=> 'Posts',
			'database_ia'			=> 'a post',
			'database_tags_enabled'	=> 1,
		), array(
			array(
				'field_id'				=> 1,
				'field_name'			=> 'Title',
				'field_type'			=> 'Text',
				'field_key'				=> 'post_title',
				'field_required'		=> 1,
				'field_position'		=> 1,
				'field_display_listing'	=> 1,
				'field_is_title'		=> 1,
			),
			array(
				'field_id'				=> 2,
				'field_name'			=> 'Content',
				'field_type'			=> 'Editor',
				'field_key'				=> 'post_content',
				'field_required'		=> 1,
				'field_position'		=> 3,
				'field_is_content'		=> 1,
			)
		) );
		
		/* Throw an exception here to tell the library that we're done with this step */
		throw new \IPS\convert\Software\Exception;
	}

	/**
	 * Convert CMS database categories
	 *
	 * @return	void
	 */
	public function convertCmsDatabaseCategories()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'term_taxonomy.term_taxonomy_id' );
		
		foreach( $this->fetch( 'term_taxonomy', 'term_taxonomy.term_taxonomy_id', array( "term_taxonomy.taxonomy=?", 'category' ) )->join( 'terms', 'terms.term_id = term_taxonomy.term_id' ) AS $row )
		{
			$info = array(
				'category_id'			=> $row['term_taxonomy_id'],
				'category_database_id'	=> 1,
				'category_name'			=> $row['name'],
				'category_furl_name'	=> $row['slug'],
				'category_fields'		=> array( 'post_title', 'post_content' ),
			);
			
			$libraryClass->convertCmsDatabaseCategory( $info );
			$libraryClass->setLastKeyValue( $row['term_taxonomy_id'] );
		}
	}

	/**
	 * Convert CMS database records
	 *
	 * @return	void
	 */
	public function convertCmsDatabaseRecords()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'ID' );
		
		foreach( $this->fetch( 'posts', 'ID', array( "post_type=?", 'post' ) ) AS $row )
		{
			/* We only support one category per record - in this instance, fetch them all, pop off the last one, then convert the rest as tags later. */
			$categories = array();
			foreach( $this->db->select( '*', 'term_relationships', array( "term_relationships.object_id=? AND term_taxonomy.taxonomy=?", $row['ID'], 'category' ) )->join( 'term_taxonomy', 'term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id' ) AS $term )
			{
				$categories[] = $term['term_taxonomy_id'];
			}
			$category = array_pop( $categories );
			
			/* Post Meta */
			$meta = array();
			foreach( $this->db->select( '*', 'postmeta', array( "post_id=?", $row['ID'] ) ) AS $m )
			{
				$meta[$m['meta_key']] = $m['meta_value'];
			}
			
			/* Work out wacky approved status */
			switch( $row['post_status'] )
			{
				case 'publish':
					$approved = 1;
					break;
				
				case 'auto-draft':
				case 'draft':
					$approved = 0;
					break;
				
				case 'trash':
				case 'private':
					$approved = -1;
					break;
				
				default:
					$approved = 0; # play it on the safe side.
					break;
			}
			
			/* Record Image */
			$imagePath = $filename = NULL;
			if ( isset( $meta['_thumbnail_id'] ) )
			{
				try
				{
					$location = $this->db->select( 'meta_value', 'postmeta', array( "post_id=? AND meta_key=?", $meta['_thumbnail_id'], '_wp_attached_file' ) )->first();
					$imagePath = rtrim( $this->app->_session['more_info']['convertCmsDatabaseRecords']['file_location'], '/' ) . '/' . $location;
					$filename = basename( $imagePath );
				}
				catch( \UnderflowException $e ) {}
			}
			
			$info = array(
				'record_id'				=> $row['ID'],
				'record_database_id'	=> 1,
				'member_id'				=> $row['post_author'],
				'record_locked'			=> ( $row['comment_status'] == 'closed' ) ? 1 : 0,
				'record_comments'		=> $row['comment_count'],
				'record_allow_comments'	=> 1,
				'record_saved'			=> new \IPS\DateTime( $row['post_date'] ),
				'record_updated'		=> $row['post_modified'] !== '0000-00-00 00:00:00' ? new \IPS\DateTime( $row['post_modified'] ) : NULL, # Older WordPress installs may be missing a modified date
				'category_id'			=> $category,
				'record_approved'		=> $approved,
				'record_dynamic_furl'	=> $row['post_name'],
				'record_static_furl'	=> $row['post_name'],
				'record_publish_date'	=> new \IPS\DateTime( $row['post_date'] ),
				'record_image'			=> $filename,
			);
			
			$fields = array(
				1 => $row['post_title'],
				2 => $row['post_content']
			);
			
			$libraryClass->convertCmsDatabaseRecord( $info, $fields, $imagePath );
			
			/* Tags */
			$tags = array();
			
			/* ... from categories */
			foreach( $this->db->select( 'term_id', 'term_taxonomy', array( $this->db->in( 'term_taxonomy_id', $categories ) ) ) AS $cat )
			{
				$text = $this->db->select( 'name', 'terms', array( "term_id=?", $cat ) )->first();
				$tags[] = $text;
			}
			
			/* ... from actual tags */
			foreach( $this->db->select( '*', 'term_relationships', array( "term_relationships.object_id=? AND term_taxonomy.taxonomy=?", $row['ID'], 'post_tag' ) )->join( 'term_taxonomy', 'term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id' ) AS $term )
			{
				$text = $this->db->select( 'name', 'terms', array( "term_id=?", $term['term_id'] ) )->first();
				$tags[] = $text;
			}
			
			/* Now convert them... we need the database ID. */
			$database = $this->app->getLink( 1, 'cms_databases' );
			foreach( $tags AS $tag )
			{
				$libraryClass->convertTag( array(
					'tag_meta_app'			=> 'cms',
					'tag_meta_area'			=> "records{$database}",
					'tag_meta_parent_id'	=> $category,
					'tag_meta_id'			=> $row['ID'],
					'tag_text'				=> $tag,
					'tag_member_id'			=> $row['post_author'],
					'tag_added'             => new \IPS\DateTime( $row['post_date'] ),
					'tag_prefix'			=> 0,
					'tag_meta_link'			=> 'cms_custom_database_' . $database,
					'tag_meta_parent_link'	=> 'cms_database_categories',
				) );
			}
			
			$libraryClass->setLastKeyValue( $row['ID'] );
		}
	}

	/**
	 * Convert CMS database comments
	 *
	 * @return	void
	 */
	public function convertCmsDatabaseComments()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'comment_ID' );
		
		foreach( $this->fetch( 'comments', 'comment_ID' ) AS $row )
		{
			switch( $row['comment_approved'] )
			{
				case 1:
					$approved = 1;
					break;
				
				case 0:
					$approved = 0;
					break;
				
				case 'trash':
				case 'spam':
					$approved = -1;
					break;
			}
			
			$libraryClass->convertCmsDatabaseComment( array(
				'comment_id'			=> $row['comment_ID'],
				'comment_database_id'	=> 1,
				'comment_record_id'		=> $row['comment_post_ID'],
				'comment_date'			=> new \IPS\DateTime( $row['comment_date'] ),
				'comment_ip_address'	=> $row['comment_author_IP'],
				'comment_user'			=> $row['user_id'],
				'comment_author'		=> $row['comment_author'],
				'comment_approved'		=> $approved,
				'comment_post'			=> $row['comment_content'],
			) );
			
			$libraryClass->setLastKeyValue( $row['comment_ID'] );
		}
	}

	protected $postContent = [];

	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$database = $this->app->getLink( 1, 'cms_databases' );
		$contentField = $this->app->getLink( 2, 'cms_database_fields' );
		$libraryClass = $this->getLibrary();

		$data = iterator_to_array( $this->fetch( 'posts', 'ID', [ 'post_type=?', 'attachment' ] ) );
		$this->app->preCacheLinks( $data, [ 'core_members' => 'post_author' ] );

		foreach( $data AS $row )
		{
			try
			{
				$this->db->select( '*', 'postmeta', [ 'meta_key=? AND meta_value=?', '_thumbnail_id', $row['ID'] ] )->first();
				$libraryClass->setLastKeyValue( $row['ID'] );
				continue;
			}
			catch( \UnderflowException $e ) {}

			try
			{
				$wpPost = $this->db->select( '*', 'posts', [ 'ID=? AND post_type=?', $row['post_parent'], 'post' ] )->first();
				$postId = $this->app->getLink( $wpPost['ID'], "cms_custom_database_" . $database );
				if( !isset( $this->postContent[ $postId ] ) )
				{
					$this->postContent[ $postId ] = \IPS\Db::i()->select( 'field_' . $contentField, 'cms_custom_database_' . $database, array( "primary_id_field=?", $postId ) )->first();
				}
			}
			catch( \UnderflowException | \OutOfRangeException $e )
			{
				$libraryClass->setLastKeyValue( $row['ID'] );
				continue;
			}

			/* Post Meta */
			$meta = array();
			foreach( $this->db->select( '*', 'postmeta', array( "post_id=?", $row['ID'] ) ) AS $m )
			{
				$meta[ $m['meta_key'] ] = $m['meta_value'];
			}

			$map = [
				'id1'				=> $wpPost['ID'],
				'id2'				=> 2,
				'id2_type'			=> 'cms_database_fields',
				'id3'				=> 1
			];

			$info = array(
				'attach_id'			=> $row['ID'],
				'attach_file'		=> basename( $meta['_wp_attached_file'] ),
				'attach_date'		=> new \IPS\DateTime( $row['post_date'] ),
				'attach_member_id'	=> $row['post_author']
			);

			$attachId = $libraryClass->convertAttachment( $info, $map, rtrim( $this->app->_session['more_info']['convertAttachments']['file_location'], '/' ) . '/' . $meta['_wp_attached_file'] );

			if( $attachId !== FALSE AND mb_stristr( $this->postContent[ $postId ], $meta['_wp_attached_file'] ) )
			{
				$dom = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
				$dom->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $this->postContent[ $postId ] ) );
				$xPath = new \DOMXPath( $dom );

				foreach ( $xPath->query( "//img[contains(@src, '" . $meta['_wp_attached_file'] ."')] | //a[contains(@href, '" . $meta['_wp_attached_file'] ."')]" ) as $tag )
				{
					$text = $dom->createTextNode( '[attachment=' . $attachId . ':name]' );
					$tag->parentNode->replaceChild( $text, $tag );
				}

				/* Get DOMDocument output */
				$content = \IPS\Text\DOMParser::getDocumentBodyContents( $dom );

				/* Replace file storage tags */
				$content = preg_replace( '/&lt;fileStore\.([\d\w\_]+?)&gt;/i', '<fileStore.$1>', $content );

				/* DOMDocument::saveHTML will encode the base_url brackets, so we need to make sure it's in the expected format. */
				$this->postContent[ $postId ] = str_replace( '&lt;___base_url___&gt;', '<___base_url___>', $content );
			}

			$libraryClass->setLastKeyValue( $row['ID'] );
		}

		/* Do the updates */
		foreach( $this->postContent as $pid => $content )
		{
			\IPS\Db::i()->update( 'cms_custom_database_' . $database, array( 'field_' . $contentField => $content ), array( 'primary_id_field=?', $pid ) );
		}
	}
}