<?php

namespace StealThisShow\StealThisTracker\Seeder;

use StealThisShow\StealThisTracker\Torrent;

/**
 * Object holding information of a client connecting to the seeder server.
 *
 * @package StealThisTracker
 * @subpackage Seeder
 */
class Client
{
    /**
     * 20 bytes peer ID of the client.
     *
     * @var string
     */
    protected $peer_id;

    /**
     * Address of the connecting client.
     *
     * @var string
     */
    protected $address;

    /**
     * Port where client listens.
     *
     * @var integer
     */
    protected $port;

    /**
     * The torrent the the client intends to download.
     *
     * @var Torrent
     */
    protected $torrent;

    /**
     * Socket established for the incoming connection (not the one listening!).
     *
     * @var resource
     */
    protected $communication_socket;

    /**
     * The listening socket
     *
     * @var resource
     */
    protected $listening_socket;

    /**
     * Flag to tell if the client is 'choked' by the seed server.
     *
     * @var boolean
     */
    protected $choked = true;

    /**
     * Stat counter of bytes sent in total to the client (including protocol messages).
     *
     * @var boolean
     */
    protected $bytes_sent = 0;

    /**
     * Stat counter of bytes received in total from the client (including protocol messages).
     *
     * @var boolean
     */
    protected $bytes_received = 0;

    /**
     * Stat counter of data bytes sent in total to the client (excluding protocol messages).
     *
     * @var boolean
     */
    protected $data_sent = 0;

    /**
     * Used in self::addStatBytes $type argument.
     */
    const STAT_BYTES_SENT       = 1;

    /**
     * Used in self::addStatBytes $type argument.
     */
    const STAT_BYTES_RECEIVED   = 2;

    /**
     * Used in self::addStatBytes $type argument.
     */
    const STAT_DATA_SENT        = 3;

    /**
     * Start accepting incoming connections on the listening socket.
     *
     * @param resource $listening_socket
     */
    public function __construct( $listening_socket )
    {
        $this->socketAccept( $listening_socket );
    }

    /**
     * Closing open communication socket if the object is destructed.
     */
    public function __destruct()
    {
        if ( isset( $this->communication_socket ) )
        {
            socket_close( $this->communication_socket );
        }
    }

    /**
     * Blocks execution until incoming connection comes.
     *
     * @throws Error\Socket If the accepting is unsuccessful.
     * @param resource $listening_socket
     */
    public function socketAccept( $listening_socket )
    {
        if ( false === ( $this->communication_socket = socket_accept( $listening_socket ) ) )
        {
            $this->communication_socket = null;
            throw new Error\Socket( 'Socket accept failed: ' . socket_strerror( socket_last_error( $this->listening_socket ) ) );
        }
        // After successfully accepting connection, we obtain IP address and port of the client for logging.
        if ( false === socket_getpeername( $this->communication_socket, $this->address, $this->port ) )
        {
            $this->address = $this->port = null;
        }
    }

    /**
     * Reads the expected length of bytes from the client.
     *
     * Blocks execution until the wanted number of bytes arrives.
     *
     * @param integer $wanted_length Expected message length in bytes.
     * @throws Error\Socket If reading fails.
     * @throws Error\CloseConnection If client closes the connection.
     * @return string
     */
    public function socketRead( $wanted_length )
    {
        $message = '';

        while ( strlen( $message ) < $wanted_length )
        {
            if ( false === ( $buffer = socket_read( $this->communication_socket, min( $wanted_length - strlen( $message ), 2048 ), PHP_BINARY_READ ) ) )
            {
                throw new Error\Socket( 'Socket reading failed: ' . socket_strerror( $err_no = socket_last_error( $this->communication_socket ) ) . " ($err_no)" );
            }
            if ( '' == $buffer )
            {
                throw new Error\CloseConnection( 'Client closed the connection.' );
            }
            $message .= $buffer;
        }

        $this->addStatBytes( $wanted_length, self::STAT_BYTES_RECEIVED );

        return $message;
    }

    /**
     * Sends a message to the client.
     *
     * @param string $message
     */
    public function socketWrite( $message )
    {
        socket_write( $this->communication_socket, $message, $len = strlen( $message ) );
        $this->addStatBytes( $len, self::STAT_BYTES_SENT );
    }

    /**
     * Unchokes the client so that it is allowed to send requests.
     */
    public function unchoke()
    {
        $this->socketWrite( pack( 'NC', 1, 1 ) );
        $this->choked = false;
    }

    /**
     * Chokes the client so that it is not allowed to send requests.
     */
    public function choke()
    {
        $this->socketWrite( pack( 'NC', 1, 0 ) );
        $this->choked = true;
    }

    /**
     * Increments data transfer statistics for this client.
     *
     * @param integer $bytes Number of bytes to increment statistics with.
     * @param integer $type Telling the type of the stat, see self::STAT_*.
     */
    public function addStatBytes( $bytes, $type )
    {
        switch ( $type )
        {
            case self::STAT_BYTES_SENT:
                $this->bytes_sent += $bytes;
                break;
            case self::STAT_BYTES_RECEIVED:
                $this->bytes_received += $bytes;
                break;
            case self::STAT_DATA_SENT:
                $this->data_sent += $bytes;
                break;
        }
    }

    /**
     * Gives string representation of transfer statistics.
     *
     * @return string
     */
    public function getStats()
    {
        return <<<STATS
Bytes sent: $this->bytes_sent,
Bytes received: $this->bytes_received,
Data sent: $this->data_sent
STATS;
    }

    /**
     * @return string
     */
    public function getPeerId()
    {
        return $this->peer_id;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return Torrent
     */
    public function getTorrent()
    {
        return $this->torrent;
    }

    /**
     * @param string $peer_id
     */
    public function setPeerId($peer_id)
    {
        $this->peer_id = $peer_id;
    }

    /**
     * @param string $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
    }

    /**
     * @param int $port
     */
    public function setPort($port)
    {
        $this->port = $port;
    }

    /**
     * @param Torrent $torrent
     */
    public function setTorrent($torrent)
    {
        $this->torrent = $torrent;
    }
}
