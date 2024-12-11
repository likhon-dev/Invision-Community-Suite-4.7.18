<!DOCTYPE html>
<html lang="en">
<head>
<title>Invision Community Update Extractor</title>
<style type='text/css'>body{font-family:BlinkMacSystemFont, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;}@keyframes progress-bar-stripes{from{background-position:40px 0}to{background-position:0 0}}.ipsProgressBar{width:50%;margin:auto;height:26px;overflow:hidden;background:rgb(237, 242, 247);background-image:linear-gradient(to bottom,rgba(23, 126, 201,0.1),rgba(23, 126, 201,0.1));border-radius:4px;}.ipsProgressBar_animated .ipsProgressBar_progress{background-color:rgb(23, 126, 201);background-image:linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-size:40px 40px;animation:progress-bar-stripes 2s linear infinite}.ipsProgressBar_progress{display:flex;align-items:center;width:0;height:100%;font-size:12px;color:#ffffff;text-align:right;background:rgb(23, 126, 201);position:relative;white-space:nowrap;line-height:26px;text-indent:6px;padding-right:2px;}.ipsProgressBar>span:first-child{padding-left:7px;}.ipsProgressBar_progress[data-progress]:after{top:0;color:#fff;content:attr(data-progress);display:block;right:5px;}@media (prefers-color-scheme: dark) { body{ background-color: rgb(45, 49, 57); } }</style>
</head>
<body style="margin:0">
<?php
/**
 * @brief		Update Extractor
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 Jul 2017
 */

// This file deliberately exists outside of the framework
// and MUST NOT call init.php

@ini_set('display_errors', 'off');

@require( "../../system/3rd_party/pclzip/pclzip.lib.php" );

if ( file_exists( "../../constants.php" ) )
{
	require "../../constants.php";
}
foreach ( [ 'FOLDER_PERMISSION_NO_WRITE' => 0755, 'FILE_PERMISSION_NO_WRITE' => 0644, 'IPS_FILE_PERMISSION' => 0666, 'TEMP_DIRECTORY' => sys_get_temp_dir(), 'TEXT_ENCRYPTION_KEY' => NULL ] as $k => $v )
{
	if ( !\defined( $k ) )
	{
		\define( $k, $v );
	}
}

/**
 * Extractor Class
 */
class Extractor
{
	const NUMBER_PER_GO = 20;
	
	/**
	 * @brief	File
	 */
	private $file;
	
	/**
	 * @brief	Root Directory
	 */
	private $container;
	
	/**
	 * @brief	PclZip Object
	 */
	private $zip;
	
	/**
	 * @brief	Zip Contents
	 */
	private $contents;
	
	/**
	 * @brief	FTP Connection
	 */
	private $ftp;
	
	/**
	 * @brief	SSH Connection
	 */
	private $ssh;
	
	/**
	 * @brief	SFTP Connection
	 */
	private $sftp;
	
	/**
	 * @brief	SFTP Directory
	 */
	private $sftpDir;

	/**
	 * @brief	Database connection
	 */
    public $db;

	/**
	 * @brief	Database prefix
	 */
	public $dbPrefix;
	
	/**
	 * Constructor
	 *
	 * @param	string	$file		Zip File
	 * @param	string	$container	Root directory
	 * @return	void
	 */
	public function __construct( $file, $container )
	{
		if ( !$file or !$container )
		{
			throw new Exception( "Zip file or directory to extract to was not supplied" );
		}
		
		$this->file = $file;
		$this->container = $container . '/';
	}
	
	/**
	 * Security Check
	 *
	 * @param	string	$key	Security key
	 * @return	void
	 */
	public function securityCheck( $key )
	{
		require "../../conf_global.php";

		$this->db = new mysqli( $INFO['sql_host'], $INFO['sql_user'], $INFO['sql_pass'], $INFO['sql_database'], !empty( $INFO['sql_port'] ) ? $INFO['sql_port'] : null, !empty( $INFO['sql_socket'] ) ? $INFO['sql_socket'] : null );
        $this->dbPrefix = $INFO['sql_tbl_prefix'] ?? '';

		/* If the connection failed, do not continue */
		if( $error = mysqli_connect_error() )
		{
			return FALSE;
		}

		/* Check that there is an upgrade in progress */
		$query = $this->db->query( "SELECT conf_value FROM {$this->dbPrefix}core_sys_conf_settings WHERE conf_key='setup_in_progress'" );
		if( !$query )
        {
            return FALSE;
        }

		if( (bool) $query->fetch_assoc()['conf_value'] === FALSE )
		{
			return FALSE;
		}

		return $this->_compareHashes( md5( $INFO['board_start'] . $_GET['file'] . $INFO['sql_pass'] ), $key );
	}
	
	/**
	 * Establish FTP/SSH connection
	 *
	 * @param	array	$value	FTP/SSH Credentials
	 * @return	void
	 */
	public function connectToFtp( $value )
	{
		if ( $value['protocol'] == 'sftp' )
		{
			$this->ssh = ssh2_connect( $value['server'], $value['port'] );		
			if ( $this->ssh === FALSE )
			{
				throw new Exception( "Could not connect to SCP" );
			}
			if ( !@ssh2_auth_password( $this->ssh, $value['un'], $value['pw'] ) )
			{
				throw new Exception( "Could not login to SCP" );
			}
			$this->sftp = @ssh2_sftp( $this->ssh );
			if ( $this->sftp === FALSE )
			{
				throw new Exception( "Could not initiate SFTP connection" );
			}
			
			if ( $value['path'] and !@ssh2_sftp_stat( $this->sftp, $value['path'] ) )
			{
				throw new Exception( "Could not locate path via SFTP" );
			}
			
			$this->sftpDir = ssh2_sftp_realpath( $this->sftp, $value['path'] ) . '/';
		}
		else
		{			
			if ( $value['protocol'] == 'ssl_ftp' )
			{
				$this->ftp = @ftp_ssl_connect( $value['server'], $value['port'], 3 );
			}
			else
			{
				$this->ftp = @ftp_connect( $value['server'], $value['port'], 3 );
			}
			if ( $this->ftp === FALSE )
			{
				throw new Exception( "Could not connect to FTP" );
			}
			if ( !@ftp_login( $this->ftp, $value['un'], $value['pw'] ) )
			{
				throw new Exception( "Could not login to FTP" );
			}
			if( ftp_nlist( $this->ftp, '.' ) === FALSE )
			{
				@ftp_pasv( $this->ftp, true );
			}
			
			if ( !@ftp_chdir( $this->ftp, $value['path'] ) )
			{
				throw new Exception( "Could not change directory to {$value['path']}" );
			}
		}
	}
	
	/**
	 * Number of files
	 *
	 * @return	int
	 */
	public function numberOfFiles()
	{
		$properties = $this->zip->properties();
		return $properties['nb'];
	}
	
	/**
	 * Extract the files
	 *
	 * @param	int	$offset	Offset
	 * @return	int
	 */
	public function extract( $offset )
	{
		$this->zip = new PclZip( $this->file );
		$this->contents = $this->zip->listContent();
		if ( !$this->contents )
		{
			throw new Exception( "Could not list the files in the zip" );
		}
		
		$done = 0;
		for ( $i = 0; $i < self::NUMBER_PER_GO; $i++ )
		{
			$index = $offset + $i;
			
			if ( isset( $this->contents[ $index ] ) )
			{
				$this->_extractFile( $index );
				$done++;
			}
			else
			{
				return $done;
			}
		}
		
		return $done;
	}
	
	/**
	 * Extract a particular file
	 *
	 * @param	int	$index	File index in zip
	 * @return	void
	 */
	private function _extractFile( $index )
	{
		/* Get the file */
		$path = mb_substr( $this->contents[ $index ]['filename'], 0, mb_strlen( $this->container ) ) === $this->container ? mb_substr( $this->contents[ $index ]['filename'], mb_strlen( $this->container ) ) : $this->contents[ $index ]['filename'];
		if ( !$path or mb_substr( $path, -1 ) === '/' or mb_substr( $path, 0, 1 ) === '.' or mb_substr( $path, 0, 9 ) === '__MACOSX/' )
		{
			return;
		}
		
		/* Create a directory if needed */
		$dir = \dirname( $path );
		$directories = array( $dir );
		while ( $dir != '.' )
		{
			$dir = \dirname( $dir );
			if ( $dir != '.' )
			{
				$directories[] = $dir;
			}
		}
		foreach ( array_reverse( $directories ) as $dir )
		{
			if ( !is_dir( "../../{$dir}" ) )
			{
				if ( $this->sftp )
				{
					@ssh2_sftp_mkdir( $this->sftp, $this->sftpDir . $dir );
				}
				if ( $this->ftp )
				{
					@ftp_mkdir( $this->ftp, $dir );
				}
				else
				{
					@mkdir( "../../{$dir}", FOLDER_PERMISSION_NO_WRITE );
				}
			}
		}
				
		/* Write contents */
		$contents = @$this->zip->extractByIndex( $index, \PCLZIP_OPT_EXTRACT_AS_STRING );
		$contents = $contents[0]['content'];
		if ( $this->sftp or $this->ftp )
		{
			$tmpFile = tempnam( TEMP_DIRECTORY, 'IPS' );
			\file_put_contents( $tmpFile, $contents );
			
			if ( $this->sftp )
			{
				if ( @ssh2_scp_send( $this->ssh, $tmpFile, $this->sftpDir . $path, FILE_PERMISSION_NO_WRITE ) === FALSE )
				{
					throw new \Exception( "Could not transfer file to server via SFTP" );
				}
			}
			else
			{
				if ( @ftp_put( $this->ftp, $path, $tmpFile, FTP_BINARY ) === FALSE )
				{
					throw new \Exception( "Could not transfer file to server via FTP" );
				}
			}
			
			@unlink( $tmpFile );
		}
		else
		{
			/* Determine if the file exists (we'll want to know this later) */
			$fileExists	= file_exists( "../../{$path}" );

			$fh = @\fopen( "../../{$path}", 'w+' );
			if ( $fh === FALSE )
			{
				$lastError = error_get_last();
				throw new Exception( $lastError['message'] );
			}
			if ( @\fwrite( $fh, $contents ) === FALSE )
			{
				$lastError = error_get_last();
				throw new Exception( $lastError['message'] );
			}
			else
			{
				/* If the file existed before we started, we should clear it from opcache if opcache is enabled */
				if( $fileExists )
				{
					if ( \function_exists( 'opcache_invalidate' ) )
					{
						@opcache_invalidate( "../../{$path}" );
					}
				}
				/* Otherwise, we should set file permissions on the file if it's brand new in case server defaults to something odd */
				else
				{
					@chmod( "../../{$path}", FILE_PERMISSION_NO_WRITE );
				}
			}
			@\fclose( $fh );
		}
	}

	/* !Misc Utility Methods */

	/**
	 * Compare hashes in fixed length, time constant manner.
     * This is replicated from \IPS\Login::compareHashes(), however we don't want to include framework code here.
	 *
	 * @param	string	$expected	The expected hash
	 * @param	string	$provided	The provided input
	 * @return	boolean
	 */
	private function _compareHashes( $expected, $provided )
	{
		if ( !\is_string( $expected ) || !\is_string( $provided ) || $expected === '*0' || $expected === '*1' || $provided === '*0' || $provided === '*1' ) // *0 and *1 are failures from crypt() - if we have ended up with an invalid hash anywhere, we will reject it to prevent a possible vulnerability from deliberately generating invalid hashes
		{
			return FALSE;
		}

		$len = \strlen( $expected );
		if ( $len !== \strlen( $provided ) )
		{
			return FALSE;
		}

		$status = 0;
		for ( $i = 0; $i < $len; $i++ )
		{
			$status |= \ord( $expected[ $i ] ) ^ \ord( $provided[ $i ] );
		}

		return $status === 0;
	}
}

/**
 * Encrypted - code from \IPS\Text\Encrypt
 */
class Encrypt
{
	/**
	 * Get Key
	 *
	 * @return	void
	 */
	public static function key()
	{
		require "../../conf_global.php";
		return \TEXT_ENCRYPTION_KEY ?: md5( $INFO['sql_pass'] . $INFO['sql_database'] );
	}

	/**
	 * @brief	Cipher
	 */
	public $cipher;

	/**
	 * @brief	IV
	 */
	protected $iv = NULL;

	/**
	 * @brief	Tag
	 */
	protected $tag = NULL;

	/**
	 * @brief	Hash of cipher
	 */
	protected $hmac = NULL;

	/**
	 * From plaintext
	 *
	 * @param	string	$plaintext	Plaintext
	 * @return	static
	 */
	public static function fromPlaintext( $plaintext )
	{
		$obj = new static;

		/* Try to use OpenSSL if it's available... */
		if ( \function_exists( 'openssl_get_cipher_methods' ) )
		{
			/* If GCM is available (PHP 7.1+), use that as if provides authenticated encryption natively */
			if ( \in_array( 'aes-128-gcm', openssl_get_cipher_methods() ) )
			{
				$obj->iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-128-gcm' ) );
				$obj->cipher = openssl_encrypt( $plaintext, 'aes-128-gcm', static::key(), 0, $obj->iv, $obj->tag );
				return $obj;
			}

			/* Otherwise, use CBC and store the hash so we can do our own authentication when decrypting */
            elseif ( \in_array( 'aes-128-cbc', openssl_get_cipher_methods() ) )
			{
				$obj->iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-128-cbc' ) );
				$obj->cipher = openssl_encrypt( $plaintext, 'aes-128-cbc', static::key(), OPENSSL_RAW_DATA, $obj->iv );
				$obj->hmac = hash_hmac( 'sha256', $obj->cipher, static::key(), TRUE );
				return $obj;
			}
		}

		/* If we're still here, fallback to the PHP library */
		require_once '../../system/3rd_party/AES/AES.php';
		$obj->cipher = \AesCtr::encrypt( $plaintext, static::key(), 256 );
		return $obj;
	}

	/**
	 * From plaintext
	 *
	 * @param	string		$cipher	Cipher
	 * @param	string|null	$iv		The IV, or if null, will use the PHP library rather than built-in openssl_*() methods
	 * @param	string|null	$tag		The tag if using AES-128-GCM
	 * @param	string|null	$hash	The hash if using AES-128-CBC
	 * @return	static
	 */
	public static function fromCipher( $cipher, $iv = NULL, $tag = NULL, $hash = NULL )
	{
		$obj = new static;
		$obj->cipher = $cipher;
		$obj->iv = $iv;
		$obj->tag = $tag;
		$obj->hmac = $hash;
		return $obj;
	}

	/**
	 * From tag
	 *
	 * @param	string	$tag	Tag
	 * @return	static
	 */
	public static function fromTag( $tag )
	{
		if ( preg_match( '/^\[\!AES128GCM\[(.+?)\]\[(.+?)\]\[(.+?)\]\]/', $tag, $matches ) )
		{
			return static::fromCipher( $matches[1], hex2bin( $matches[2] ), hex2bin( $matches[3] ) );
		}
        elseif ( preg_match( '/^\[\!AES128CBC\[(.+?)\]\]/', $tag, $matches ) )
		{
			$cipher = base64_decode( $matches[1] );
			$ivLength = openssl_cipher_iv_length('aes-128-cbc');

			return static::fromCipher( \substr( $cipher, $ivLength + 32 ), \substr( $cipher, 0, $ivLength ), NULL, \substr( $cipher, $ivLength, 32 ) );
		}
        elseif ( preg_match( '/^\[\!AES\[(.+?)\]\]/', $tag, $matches ) )
		{
			return static::fromCipher( $matches[1] );
		}
		else
		{
			return static::fromPlaintext( $tag );
		}
	}

	/**
	 * Wrap in a tag to use later with fromTag
	 *
	 * @return	string
	 */
	public function tag()
	{
		if ( $this->tag )
		{
			return '[!AES128GCM[' . $this->cipher . '][' . bin2hex( $this->iv ) . '][' . bin2hex( $this->tag ) . ']]';
		}
        elseif ( $this->hmac )
		{
			return '[!AES128CBC[' . base64_encode( $this->iv . $this->hmac . $this->cipher ) . ']]';
		}
		else
		{
			return '[!AES[' . $this->cipher . ']]';
		}
	}

	/**
	 * Decript
	 *
	 * @return	string
	 */
	public function decrypt()
	{
		if ( $this->tag )
		{
			return openssl_decrypt( $this->cipher, 'aes-128-gcm', static::key(), 0, $this->iv, $this->tag );
		}
        elseif ( $this->hmac )
		{
			$decrypted = openssl_decrypt( $this->cipher, 'aes-128-cbc', static::key(), OPENSSL_RAW_DATA, $this->iv );
			if ( hash_equals( $this->hmac, hash_hmac( 'sha256', $this->cipher, static::key(), TRUE ) ) )
			{
				return $decrypted;
			}
			return '';
		}
		else
		{
			require_once '../../system/3rd_party/AES/AES.php';
			return \AesCtr::decrypt( $this->cipher, static::key(), 256 );
		}
	}
}

/**
 * Function to write a log file to disk
 *
 * @param	mixed	$message	Exception or message to log
 * @return	void
 */
function writeLogFile( $message )
{
	/* What are we writing? */
	$date = date('r');
	if ( $message instanceof \Exception )
	{
		$messageToLog = $date . "\n" . \get_class( $message ) . '::' . $message->getCode() . "\n" . $message->getMessage() . "\n" . $message->getTraceAsString();
	}
	else
	{
		if ( \is_array( $message ) )
		{
			$message = var_export( $message, TRUE );
		}
		$messageToLog = $date . "\n" . $message . "\n" . ( new \Exception )->getTraceAsString();
	}
	
	/* Where are we writing it? */
	$dir = rtrim( __DIR__, '/' ) . '/../../uploads/logs';
	
	/* Write it */
	$header = "<?php exit; ?>\n\n";
	$file = $dir . '/' . date( 'Y' ) . '_' . date( 'm' ) . '_' . date('d') . '_' . ( 'extractfailure' ) . '.php';
	if ( file_exists( $file ) )
	{
		@\file_put_contents( $file, "\n\n-------------\n\n" . $messageToLog, FILE_APPEND );
	}
	else
	{
		@\file_put_contents( $file, $header . $messageToLog );
	}
	@chmod( $file, IPS_FILE_PERMISSION );
}

/* ! Controller */
try
{
	$extractor = new Extractor( isset( $_GET['file'] ) ? $_GET['file'] : NULL, isset( $_GET['container'] ) ? $_GET['container'] : '' );
	
	/* Check this request came from the ACP */
	if ( !$extractor->securityCheck( $_GET['key'] ) )
	{
		throw new Exception( "Security check failed" );
	}
	
	/* Establish an FTP connection if necessary */
	if ( $_GET['ftp'] )
	{
		/* Get encrypted FTP credentials */
		$query = $extractor->db->query( "SELECT conf_value FROM " . $extractor->dbPrefix . "core_sys_conf_settings WHERE conf_key='upgrade_ftp_details'" );
		if( !$query )
		{
			throw new \RuntimeException('Cannot find (S)FTP credentials');
		}

		$ftpCredentials = $query->fetch_assoc()['conf_value'];
		if( empty( $ftpCredentials ) )
		{
			throw new \RuntimeException('Cannot load (S)FTP credentials');
		}

		if ( \substr( $ftpCredentials, 0, 5 ) === '[!AES' )
		{
			$decodedFtpDetails = Encrypt::fromTag( $ftpCredentials )->decrypt();
		}
		else
		{
			$decodedFtpDetails = Encrypt::fromCipher( $ftpCredentials )->decrypt();
		}

		$extractor->connectToFtp( json_decode( $decodedFtpDetails, TRUE ) );
	}
	
	/* Extract a batch of files */
	$i = isset( $_GET['i'] ) ? $_GET['i'] : 0;
	$done = $extractor->extract( $i );
	
	/* If we didn't extract anything, are we done? */
	if ( !$done and $i >= $extractor->numberOfFiles() )
	{
		@unlink( $_GET['file'] );
		
		if ( \function_exists( 'opcache_reset' ) )
		{
			@opcache_reset();
		}
		
		echo <<<HTML
<div class="ipsProgressBar ipsProgressBar_animated">
	<div class="ipsProgressBar_progress" style="width: 100%;" data-progress='100%'></div>
</div>
<script type='text/javascript'>parent.location = parent.location + '&check=1';</script><noscript><a href='index.php' target='_parent'>Click here to continue</a></noscript>
HTML;
	}
	
	/* Nope, redirect to the next batch */
	else
	{
		$newI = $i + $extractor::NUMBER_PER_GO;
		$percentComplete = round( 100 / $extractor->numberOfFiles() * $newI, 2 );
		$percentComplete = ( $percentComplete > 100 ) ? 100 : $percentComplete;
		
		$properties = array(
			'file'		=> $_GET['file'],
			'container'	=> $_GET['container'],
			'key'		=> $_GET['key'],
			'ftp'		=> $_GET['ftp'],
			'i'			=> $newI,
		);
		$url = "extract.php?" . http_build_query( $properties, '', '&' );
		echo <<<HTML
<div class="ipsProgressBar ipsProgressBar_animated">
	<div class="ipsProgressBar_progress" style="width: {$percentComplete}%;" data-progress='{$percentComplete}%'></div>
</div>
<noscript><a href='{$url}' target='_parent'>Click here to continue</a></noscript>
<script type='text/javascript'>window.onload = function(){setTimeout(function(){window.location = '{$url}';}, 50);};</script>
HTML;
	}
}
catch ( Throwable $e )
{
	writeLogFile( $e );
	
	echo "<script type='text/javascript'>parent.location = parent.location + '&fail=1';</script><noscript>An error occurred. Please visit the <a href='https://remoteservices.invisionpower.com/docs/client_area' target='_blank' rel='noopener'>client area</a> to manually download the latest version. After uploading the files, <a href='index.php' target='_parent'>continue to the upgrader</a>.</noscript>";
	exit;
}
?>
</body>
</html>
