<?php
/**
 * @brief		View Blog Entry Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		03 Mar 2014
 */

namespace IPS\blog\modules\front\blogs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * View Blog Entry Controller
 */
class _entry extends \IPS\Content\Controller
{	
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\blog\Entry';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		try
		{
			$this->entry = \IPS\blog\Entry::load( \IPS\Request::i()->id );
				
			if ( !$this->entry->canView( \IPS\Member::loggedIn() ) )
			{
				\IPS\Output::i()->error( 'node_error', '2B202/1', 403, '' );
			}

			if( $this->entry->cover_photo )
			{
				\IPS\Output::i()->metaTags['og:image'] = \IPS\File::get( 'blog_Entries', $this->entry->cover_photo )->url;
			}
			elseif ( $this->entry->container()->cover_photo )
			{
				\IPS\Output::i()->metaTags['og:image'] = \IPS\File::get( 'blog_Blogs', $this->entry->container()->cover_photo )->url;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			if ( !isset( \IPS\Request::i()->do ) or \IPS\Request::i()->do !== 'embed' )
			{
				\IPS\Output::i()->error( 'node_error', '2B202/2', 404, '' );
			}
		}

		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		parent::manage();
		
		$this->entry->container()->clubCheckRules();

		$previous = NULL;
		$next = NULL;

		/* Prev */
		try
		{
			$previous = \IPS\Db::i()->select(
				'*',
				'blog_entries',
				array( 'entry_blog_id=? AND entry_date<? AND entry_status=? AND entry_is_future_entry=0 AND entry_hidden=?', $this->entry->blog_id, $this->entry->date, "published", 1 ),
				'entry_date DESC'
				,1
			)->first();

			$previous = \IPS\blog\Entry::constructFromData( $previous );
		}
		catch ( \UnderflowException $e ) {}

		/* Next */
		try
		{
			$next = \IPS\Db::i()->select(
				'*',
				'blog_entries',
				array( 'entry_blog_id=? AND entry_date>? AND entry_status=? AND entry_is_future_entry=0 AND entry_hidden=?', $this->entry->blog_id, $this->entry->date, "published", 1 ),
				'entry_date ASC'
				,1
			)->first();

			$next = \IPS\blog\Entry::constructFromData( $next );
		}
		catch ( \UnderflowException $e ) {}
		
		/* Online User Location */
		if( !$this->entry->container()->social_group )
		{
			\IPS\Session::i()->setLocation( $this->entry->url(), $this->entry->onlineListPermissions(), 'loc_blog_viewing_entry', array( $this->entry->name => FALSE ) );
		}

		/* Add JSON-ld output */
		\IPS\Output::i()->jsonLd['blog']	= array(
			'@context'		=> "http://schema.org",
			'@type'			=> "Blog",
			'url'			=> (string) $this->entry->container()->url(),
			'name'			=> $this->entry->container()->_title,
			'description'	=> \IPS\Member::loggedIn()->language()->addToStack( \IPS\blog\Blog::$titleLangPrefix . $this->entry->container()->_id . \IPS\blog\Blog::$descriptionLangSuffix, TRUE, array( 'striptags' => TRUE, 'escape' => TRUE ) ),
			
			'commentCount'	=> $this->entry->container()->_comments,
			'interactionStatistic'	=> array(
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/ViewAction",
					'userInteractionCount'	=> $this->entry->container()->num_views
				),
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/FollowAction",
					'userInteractionCount'	=> \IPS\blog\Entry::containerFollowerCount( $this->entry->container() )
				),
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/CommentAction",
					'userInteractionCount'	=> $this->entry->container()->_comments
				),
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/WriteAction",
					'userInteractionCount'	=> $this->entry->container()->_items
				)
			),
			'blogPost' => array(
				'@context'		=> "http://schema.org",
				'@type'			=> "BlogPosting",
				'url'			=> (string) $this->entry->url(),
				'mainEntityOfPage'	=> (string) $this->entry->url(),
				'name'			=> $this->entry->mapped('title'),
				'headline'		=> $this->entry->mapped('title'),
				'articleBody'	=> $this->entry->truncated( TRUE, NULL ),
				'commentCount'	=> $this->entry->mapped('num_comments'),
				'dateCreated'	=> \IPS\DateTime::ts( $this->entry->date )->format( \IPS\DateTime::ISO8601 ),
				'datePublished'	=> \IPS\DateTime::ts( $this->entry->publish_date )->format( \IPS\DateTime::ISO8601 ),
				'author'		=> array(
					'@type'		=> 'Person',
					'name'		=> \IPS\Member::load( $this->entry->mapped('author') )->name,
					'url'		=> (string) \IPS\Member::load( $this->entry->mapped('author') )->url(),
					'image'		=> \IPS\Member::load( $this->entry->mapped('author') )->get_photo( TRUE, TRUE )
				),
				'publisher'		=> array(
					'@id' => \IPS\Settings::i()->base_url . '#organization',
					'member' => array(
						'@type'		=> 'Person',
						'name'		=> \IPS\Member::load( $this->entry->mapped('author') )->name,
						'url'		=> (string) \IPS\Member::load( $this->entry->mapped('author') )->url(),
						'image'		=> \IPS\Member::load( $this->entry->mapped('author') )->get_photo( TRUE, TRUE )
					)
				),
				'interactionStatistic'	=> array(
					array(
						'@type'					=> 'InteractionCounter',
						'interactionType'		=> "http://schema.org/ViewAction",
						'userInteractionCount'	=> $this->entry->views
					),
					array(
						'@type'					=> 'InteractionCounter',
						'interactionType'		=> "http://schema.org/FollowAction",
						'userInteractionCount'	=> \IPS\blog\Entry::containerFollowerCount( $this->entry->container() )
					),
					array(
						'@type'					=> 'InteractionCounter',
						'interactionType'		=> "http://schema.org/CommentAction",
						'userInteractionCount'	=> $this->entry->mapped('num_comments')
					)
				)
			)
		);

		if( $this->entry->container()->coverPhoto()->file )
		{
			\IPS\Output::i()->jsonLd['blog']['image'] = (string) $this->entry->container()->coverPhoto()->file->url;
		}

		if( $this->entry->container()->member_id )
		{
			\IPS\Output::i()->jsonLd['blog']['author'] = array(
				'@type'		=> 'Person',
				'name'		=> \IPS\Member::load( $this->entry->container()->member_id )->name,
				'url'		=> (string) \IPS\Member::load( $this->entry->container()->member_id )->url(),
				'image'		=> \IPS\Member::load( $this->entry->container()->member_id )->get_photo( TRUE, TRUE )
			);
		}

		if( $this->entry->edit_time )
		{
			\IPS\Output::i()->jsonLd['blog']['blogPost']['dateModified']	= \IPS\DateTime::ts( $this->entry->edit_time )->format( \IPS\DateTime::ISO8601 );
		}
		else
		{
			\IPS\Output::i()->jsonLd['blog']['blogPost']['dateModified']	= \IPS\DateTime::ts( $this->entry->publish_date ?: $this->entry->date )->format( \IPS\DateTime::ISO8601 );
		}

		$file = NULL;
		if( $this->entry->image )
		{
			$file = \IPS\File::get( 'blog_Blogs', $this->entry->image );
		}
		elseif( $this->entry->coverPhoto()->file )
		{
			$file = $this->entry->coverPhoto()->file;
		}
		elseif( $this->entry->container()->coverPhoto()->file )
		{
			$file = $this->entry->container()->coverPhoto()->file;
		}

		if( $file !== NULL )
		{
			try
			{
				$dimensions = $file->getImageDimensions();

				\IPS\Output::i()->jsonLd['blog']['blogPost']['image'] = array(
					'@type'		=> 'ImageObject',
					'url'		=> (string) $file->url,
					'width'		=> $dimensions[0],
					'height'	=> $dimensions[1]
				);
			}
			/* File does not exist */
			catch( \RuntimeException $e ) {}
			/* Image is invalid */
			catch( \InvalidArgumentException $e ){}
			catch( \DomainException $e ) {}
		}

		/* Display */
		if( \IPS\Settings::i()->blog_enable_sidebar and $this->entry->container()->sidebar )
		{
			\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate('view')->blogSidebar( $this->entry->container()->sidebar );
		}

		/* Breadcrumb */
		\IPS\Output::i()->breadcrumb = array();
		if ( $club = $this->entry->container()->club() )
		{
			\IPS\core\FrontNavigation::$clubTabActive = TRUE;
			\IPS\Output::i()->breadcrumb = array();
			\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
			\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );

		}
		else
		{
			\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( 'app=blog', 'front', 'blogs' ), \IPS\Member::loggedIn()->language()->addToStack( '__app_blog' ) );
		}


		try
		{
			foreach( $this->entry->container()->category()->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $this->entry->container()->category()->url(), $this->entry->container()->category()->_title );
		} 
		catch ( \OutOfRangeException $e ) {}
		
		/* Set default search option */
		\IPS\Output::i()->defaultSearchOption = array( 'blog_entry', 'blog_entry_pl' );

		\IPS\Output::i()->breadcrumb[] = array( $this->entry->container()->url(), $this->entry->container()->_title );
		\IPS\Output::i()->breadcrumb[] = array( NULL, $this->entry->name );

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->entry( $this->entry, $previous, $next );
	}

	/**
	 * Return the form for editing. Abstracted so controllers can define a custom template if desired.
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	string
	 */
	protected function getEditForm( $form )
	{
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'submit', 'blog' ), 'submitFormTemplate' ) );
	}
	
	/**
	 * Move
	 *
	 * @return	void
	 */
	protected function move()
	{
		try
		{
			$item = \IPS\blog\Entry::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$item->canMove() )
			{
				throw new \DomainException;
			}

			$container = $item->container();
			
			$wizard = new \IPS\Helpers\Wizard( array(
				'blog'	=> function( $data ) use ( $container ) {
					$data['item'] = \IPS\Request::i()->id;
					$item = \IPS\blog\Entry::loadAndCheckPerms( $data['item'] );
					$form = new \IPS\Helpers\Form;
					$form->class = 'ipsForm_vertical';
					$form->add( new \IPS\Helpers\Form\Node( 'move_to', NULL, TRUE, array(
						'class'				=> \get_class( $item->container() ),
						'permissionCheck'	=> function( $node ) use ( $item )
						{
							try
							{
								/* If the item is in a club, only allow moving to other clubs that you moderate */
								if ( \IPS\IPS::classUsesTrait( $item->container(), 'IPS\Content\ClubContainer' ) and $item->container()->club()  )
								{
									return $item::modPermission( 'move', \IPS\Member::loggedIn(), $node ) and $node->can( 'add' ) ;
								}
								
								if ( $node->can( 'add' ) )
								{
									return true;
								}
							}
							catch( \OutOfBoundsException $e ) { }
							
							return false;
						},
						'clubs'	=> TRUE
					) ) );
					
					if ( $values = $form->values() )
					{
						$data['blog'] = $values['move_to'];
						return $data;
					}
					
					return $form;
				},
				'category' => function( $data ) {
					$item = \IPS\blog\Entry::loadAndCheckPerms( $data['item'] );
					$newBlog = $data['blog'];
					
					$form = new \IPS\Helpers\Form;
					
					$categories = \IPS\blog\Entry\Category::roots( NULL, NULL, array( 'entry_category_blog_id=?', $newBlog->id ) );
					$choiceOptions = array( 0 => 'entry_category_choice_new' );
					$choiceToggles = array( 0 => array( 'blog_entry_new_category' ) );
			
					if( \count( $categories ) )
					{
						$choiceOptions[1] = 'entry_category_choice_existing';
						$choiceToggles[1] = array( 'entry_category_id' );
					}
					
					$form->add( new \IPS\Helpers\Form\Radio( 'entry_category_choice', 0, FALSE, array(
						'options' => $choiceOptions,
						'toggles' => $choiceToggles
					) ) );
			
					if( \count( $categories ) )
					{
						$options = array();
						foreach ( $categories as $category )
						{
							$options[ $category->id ] = $category->name;
						}
			
						$form->add( new \IPS\Helpers\Form\Select( 'entry_category_id', NULL, FALSE, array( 'options' => $options, 'parse' => 'normal' ), NULL, NULL, NULL, "entry_category_id" ) );
					}
					$form->add( new \IPS\Helpers\Form\Text( 'blog_entry_new_category', NULL, TRUE, array(), NULL, NULL, NULL, "blog_entry_new_category" ) );
					$this->moderationAlertField( $form, $item );
					
					if ( $values = $form->values() )
					{
						if ( $data['blog'] === NULL OR !$data['blog']->can( 'add' ) )
						{
							\IPS\Output::i()->error( 'node_move_invalid', '1S136/L', 403, '' );
						}
		
						/* If this item is read, we need to re-mark it as such after moving */
						if( $item instanceof \IPS\Content\ReadMarkers )
						{
							$unread = $item->unread();
						}
		
						$item->move( $data['blog'], FALSE );
						
						
						if( $values['entry_category_choice'] == 1 and $values['entry_category_id'] )
						{
							$item->category_id = $values['entry_category_id'];
						}
						else
						{
							$newCategory = new \IPS\blog\Entry\Category;
							$newCategory->name = $values['blog_entry_new_category'];
							$newCategory->seo_name = \IPS\Http\Url\Friendly::seoTitle( $values['blog_entry_new_category'] );
				
							$newCategory->blog_id = $item->blog_id;
							$newCategory->save();
				
							$item->category_id = $newCategory->id;
						}
						
						$item->save();
		
						/* Mark it as read */
						if( $item instanceof \IPS\Content\ReadMarkers and $unread == 0 )
						{
							$item->markRead( NULL, NULL, NULL, TRUE );
						}
						
						if( isset( $values['moderation_alert_content']) AND $values['moderation_alert_content'] )
						{
							$this->sendModerationAlert($values, $item);
						}
		
						\IPS\Session::i()->modLog( 'modlog__action_move', array( $item::$title => TRUE, $item->url()->__toString() => FALSE, $item->mapped( 'title' ) ?: ( method_exists( $item, 'item' ) ? $item->item()->mapped( 'title' ) : NULL ) => FALSE ),  $item );
		
						\IPS\Output::i()->redirect( $item->url() );
					}
					
					return $form;
				}
			), $item->url()->setQueryString( array( 'do' => 'move' ) ) );
			
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'move_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( \IPS\blog\Entry::$title ) ) ) );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->box( $wizard, array( 'ipsPad' ) ); 
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/D', 403, '' );
		}
	}
}