<?php

namespace StealThisShow\StealThisTracker;


/**
 * Create Torrent Test
 *
 * @package StealThisTracker
 * @author  StealThisShow <info@stealthisshow.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 */
class CreateTorrentTest extends \PHPUnit_Framework_TestCase
{
    /**
     * The PDO object
     *
     * @var Persistence\Pdo
     */
    protected $persistence;

    const ANNOUNCE_URL      = 'http://127.0.0.1:80/announce.php';
    const FILE_TO_DOWNLOAD  = 'cookie_monster.gif';
    const PIECE_LENGTH      = 524288;

    protected $db_path;
    protected $sql_path;

    /**
     * Setup
     *
     * @return void
     */
    public function setUp()
    {
        $this->db_path = sys_get_temp_dir() . '/sqlite_test.db';
        $this->sql_path = dirname(__FILE__) . '/../Fixtures/sqlite.sql';
        touch($this->db_path);
        $this->setupDatabaseFixture($this->db_path, $this->sql_path);

        $this->persistence = new Persistence\Pdo('sqlite:' . $this->db_path);
    }

    /**
     * Set-up SQLite database
     *
     * @param string $db_file  The DB file
     * @param string $sql_file The SQL file
     *
     * @return void
     */
    protected function setupDatabaseFixture($db_file, $sql_file)
    {
        $table_definitions = file_get_contents($sql_file);
        $driver = new \PDO('sqlite:' . $db_file);
        $statements = preg_split(
            '/;[ \t]*\n/', $table_definitions, -1, PREG_SPLIT_NO_EMPTY
        );
        foreach ($statements as $statement) {
            if (!$driver->query($statement)) {
                $this->fail(
                    'Could not set up database fixture: ' .
                    var_export($driver->errorInfo(), true)
                );
            }
        }
    }

    /**
     * Tear down
     *
     * @return void
     */
    public function tearDown()
    {
        if (file_exists($this->db_path)) {
            unlink($this->db_path);
        }

    }

    /**
     * Test torrent file contents
     *
     * @return void
     */
    public function testTorrentFileContents()
    {
        $torrent_file   = $this->createTorrent();
        $parsed_torrent = $this->parseTorrent($torrent_file);

        $this->assertEquals(self::ANNOUNCE_URL, $parsed_torrent['announce']);
        $this->assertEquals(
            array(array(self::ANNOUNCE_URL)),
            $parsed_torrent['announce-list']
        );
        $this->assertEquals(
            self::FILE_TO_DOWNLOAD,
            $parsed_torrent['info']['name']
        );
        $this->assertEquals(
            self::PIECE_LENGTH,
            $parsed_torrent['info']['piece length']
        );
        $this->assertEquals(
            filesize(__DIR__ . '/../Fixtures/' . self::FILE_TO_DOWNLOAD),
            $parsed_torrent['info']['length']
        );

        // We don't verify pieces here, because setting up the fixture
        // is difficult and prone to creating test for the output and not
        // the other way around. However, we test pieces with
        // another system test with real download.
        $this->assertArrayHasKey('pieces', $parsed_torrent['info']);
    }

    /**
     * Test persistence
     *
     * @return void
     */
    public function testPersistence()
    {
        $torrent_file   = $this->createTorrent();
        $info_hash      = $this->getInfoHash($torrent_file);
        $saved_torrent  = $this->persistence->getTorrent($info_hash);

        $this->assertEquals(
            (string) $torrent_file,
            (string) $saved_torrent->createTorrentFile()
        );
    }


    /**
     * Test create torrent
     *
     * @return string
     */
    protected function createTorrent()
    {
        $core = new Core($this->persistence);
        $core
            ->setIp('127.0.0.1')
            ->setInterval(60);

        $file = new File\File(
            dirname(__FILE__) . '/../Fixtures/' . self::FILE_TO_DOWNLOAD
        );
        $torrent = new Torrent($file, self::PIECE_LENGTH);
        $torrent->setAnnounceList(array(self::ANNOUNCE_URL));

        return $core->addTorrent($torrent);
    }

    /**
     * Get info hash
     *
     * @param string $torrent Torrent
     *
     * @return string
     * @throws Bencode\Error\Build
     */
    protected function getInfoHash($torrent)
    {
        $parsed_torrent = $this->parseTorrent($torrent);
        return sha1(
            Bencode\Builder::build(
                array(
                    'piece length'  => $parsed_torrent['info']['piece length'],
                    'pieces'        => $parsed_torrent['info']['pieces'],
                    'name'          => $parsed_torrent['info']['name'],
                    'length'        => $parsed_torrent['info']['length'],
                    'private'       => $parsed_torrent['info']['private']
                )
            ), true
        );
    }

    /**
     * Parse a torrent (Bencode decode)
     *
     * @param string $torrent Torrent
     *
     * @return mixed
     * @throws Bencode\Error\Parse
     */
    protected function parseTorrent($torrent)
    {
        $parser = new Bencode\Parser($torrent);
        return $parser->parse()->represent();
    }
}