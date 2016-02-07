<?php

/**
 * Class representing one torrent file.
 *
 * It does lazy-initializing on its attributes intensively, because some of them
 * imply performance-heavy calculations (accessing files, calculating hashes).
 *
 * Be aware of that when using this object!
 *
 * @package StealThisTracker
 */
class StealThisTracker_Torrent
{
    /**
     * Piece size in bytes used to construct the torrent. Normally a power of 2 (eg. 512KB).
     *
     * @var integer
     */
    protected $size_piece;

    /**
     * File object of the physical file that belongs to this torrent.
     *
     * @var StealThisTracker_File_File
     */
    protected $file;

    /**
     * Concatenated ahshes of each piece of tis file.
     *
     * @var string
     */
    protected $pieces;

    /**
     * Size of the file.
     *
     * @var integer
     */
    protected $length;

    /**
     * Basename of the file.
     *
     * @var string
     */
    protected $name;

    /**
     * Full path of the physical file of this torrent.
     *
     * @var string
     */
    protected $file_path;

    /**
     * "Info hash" uniquely identifying this torrent.
     *
     * @var string
     */
    protected $info_hash;

    /**
     * Webseed(s) url-list (GetRight implementation)
     *
     * @var null|string|array
     */
    protected $url_list;

    /**
     * Initializing object witht he piece size and file object, optionally setting attributes from the database.
     *
     * @param StealThisTracker_File_File $file To intialize 'file' attribute.
     * @param integer $size_piece To intialize 'size_piece' attribute.
     * @param string $file_path Optional. To set 'file_path' attribute. If not set, will be loaded automatically.
     * @param string $name Optional. To set 'name' attribute. If not set, will be loaded automatically.
     * @param integer $length Optional. To set 'length' attribute. If not set, will be loaded automatically.
     * @param string $pieces Optional. To set 'pieces' attribute. If not set, will be loaded automatically.
     * @param string $info_hash Optional. To set 'info_hash' attribute. If not set, will be loaded automatically.
     * @param null|string|array $url_list Optional. To set 'url_list' attribute.
     * @throws StealThisTracker_Error When the piece size is invalid.
     */
    public function __construct( StealThisTracker_File_File $file, $size_piece, $file_path = null, $name = null, $length = null, $pieces = null, $info_hash = null, $url_list = null )
    {
        if ( 0 >= $size_piece = intval( $size_piece ) )
        {
            throw new StealThisTracker_Error( 'Invalid piece size: ' . $size_piece );
        }

        $this->size_piece   = $size_piece;
        $this->file         = $file;

        // Optional parameters. If we set them here, they will not be lazy-loaded.
        $this->length       = $length;
        $this->name         = $name;
        $this->file_path    = $file_path;
        $this->pieces       = $pieces;
        $this->info_hash    = $info_hash;
        $this->url_list     = $url_list;
    }

    /**
     * Lazy-loading attributes on accessing them using external resources.
     *
     * Object attributes are protected by default, but made read-only with
     * this magic method.
     *
     * @param string $attribute The name of the attribute to accesss.
     * @throws StealThisTracker_Error When trying to access non-existent attribute.
     * @return mixed
     */
    public function __get( $attribute )
    {
        switch ( $attribute )
        {
            case 'pieces':
                if ( !isset( $this->pieces ) )
                {
                    $this->pieces = $this->file->getHashesForPieces( $this->size_piece );
                }
                return $this->pieces;
                break;
            case 'length':
                if ( !isset( $this->length ) )
                {
                    $this->length = $this->file->size();
                }
                return $this->length;
                break;
            case 'name':
                if ( !isset( $this->name ) )
                {
                    $this->name = $this->file->basename();
                }
                return $this->name;
                break;
            case 'file_path':
                if ( !isset( $this->file_path ) )
                {
                    $this->file_path = $this->file . '';
                }
                return $this->file_path;
                break;
            case 'info_hash':
                if ( !isset( $this->info_hash ) )
                {
                    $this->info_hash = $this->calculateInfoHash();
                }
                return $this->info_hash;
                break;
            case 'size_piece':
                return $this->size_piece;
                break;
            case 'url_list':
                return $this->url_list;
                break;
            default:
                throw new StealThisTracker_Error( "Can't access attribute $attribute of " . __CLASS__ );
        }
    }

    /**
     * Telling that "read-only" attributes are set, see __get.
     *
     * All properties accessible via __get should be added here and return true.
     *
     * @param string $attribute The name of the attribute to accesss.
     * @return boolean
     */
    public function __isset( $attribute )
    {
        switch( $attribute )
        {
            case 'pieces':
            case 'length':
            case 'name':
            case 'size_piece':
            case 'info_hash':
            case 'file_path':
            case 'url_list':
                return true;
                break;
        }

        return false;
    }

    /**
     * Calculates info hash (uniue identifier) of the torrent.
     *
     * @return string
     */
    protected function calculateInfoHash()
    {
        // We need to use __get magic method in order to lazy-load attributes.
        return sha1( StealThisTracker_Bencode_Builder::build( array(
            'piece length'  => $this->size_piece,
            'pieces'        => $this->__get( 'pieces' ),
            'name'          => $this->__get( 'name' ),
            'length'        => $this->__get( 'length' ),
        ) ), true );
    }

    /**
     * Returns a bencoded string that represents a .torrent file and can be
     * read by Bittorrent clients.
     *
     * First item in the $announce_list will be used in the 'announce' key of
     * the .torrent file, which is compatible with the Bittorrent specification
     * ('announce-list' is an unofficial extension).
     *
     * @param array $announce_list List of URLs to make announcemenets to.
     * @return string
     */
    public function createTorrentFile( array $announce_list )
    {
        // Announce-list is a list of lists of strings.
        foreach ( $announce_list as &$announce_item )
        {
            if ( is_array( $announce_item ) ) continue;
            $announce_item = array( $announce_item );
        }

        $first_announce_item = reset( $announce_list );
        $torrent_data = array(
            'info' => array(
                'piece length'  => $this->size_piece,
                'pieces'        => $this->__get( 'pieces' ),
                'name'          => $this->__get( 'name' ),
                'length'        => $this->__get( 'length' ),
            ),
            'announce'          => reset( $first_announce_item ),
            'announce-list'     => $announce_list,
        );

        if ( ( $url_list = $this->__get( 'url_list' ) ) !== null )
            $torrent_data['url-list' ] = $url_list;

        return StealThisTracker_Bencode_Builder::build( $torrent_data );
    }

    /**
     * Reads a block of the physical file that the torrent represents.
     *
     * @param integer $piece_index Index of the piece containing the block.
     * @param integer $block_begin Beginning of the block relative to the piece in byets.
     * @param integer $length Length of the block in bytes.
     * @return string
     * @throws StealThisTracker_Error
     * @throws StealThisTracker_File_Error_Unreadable
     */
    public function readBlock( $piece_index, $block_begin, $length )
    {
        if ( $piece_index > ceil( $this->__get( 'length' ) / $this->size_piece ) - 1 )
        {
            throw new StealThisTracker_Error( 'Invalid piece index: ' . $piece_index );
        }
        if ( $block_begin + $length > $this->size_piece )
        {
            throw new StealThisTracker_Error( 'Invalid block boundary: ' . $block_begin . ', ' . $length );
        }

        return $this->file->readBlock( ( $piece_index * $this->size_piece ) + $block_begin , $length );
    }
}