<?php
/**
 * Redisent, a Redis interface for the modest
 * @author Justin Poliey <justin@getglue.com>
 * @copyright 2009-2012 Justin Poliey <justin@getglue.com>
 * @license http://www.opensource.org/licenses/ISC The ISC License
 * @package Redisent
 */

namespace redisent;

define('CRLF', sprintf('%s%s', chr(13), chr(10)));

/**
 * Wraps native Redis errors in friendlier PHP exceptions
 */
class RedisException extends \Exception {
}

/**
 * Redisent, a Redis interface for the modest among us
 */
class Redis {

	/**
	 * Socket connection to the Redis server
	 * @var resource
	 * @access private
	 */
	private $__sock;

	/**
	 * The structure representing the data source of the Redis server
	 * @var array
	 * @access public
	 */
	public $dsn;

	/**
	 * Flag indicating whether or not commands are being pipelined
	 * @var boolean
	 * @access private
	 */
	private $pipelined = FALSE;

	/**
	 * The queue of commands to be sent to the Redis server
	 * @var array
	 * @access private
	 */
	private $queue = array();

	/**
	 * Creates a Redisent connection to the Redis server at the address specified by {@link $dsn}.
	 * The default connection is to the server running on localhost on port 6379.
	 * @param string $dsn The data source name of the Redis server
	 * @param float $timeout The connection timeout in seconds
	 */
	function __construct($dsn = 'redis://localhost:6379', $timeout = null) {
		$this->dsn = parse_url($dsn);
		$host = isset($this->dsn['host']) ? $this->dsn['host'] : 'localhost';
		$port = isset($this->dsn['port']) ? $this->dsn['port'] : 6379;
		$timeout = $timeout ?: ini_get("default_socket_timeout");
		$this->__sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
		if ($this->__sock === FALSE) {
			throw new \Exception("{$errno} - {$errstr}");
		}
		if (isset($this->dsn['pass'])) {
			$this->auth($this->dsn['pass']);
		}
	}

	function __destruct() {
		fclose($this->__sock);
	}

	/**
	 * Returns the Redisent instance ready for pipelining.
	 * Redis commands can now be chained, and the array of the responses will be returned when {@link uncork} is called.
	 * @see uncork
	 * @access public
	 */
	function pipeline() {
		$this->pipelined = TRUE;
		return $this;
	}

	/**
	 * Flushes the commands in the pipeline queue to Redis and returns the responses.
	 * @see pipeline
	 * @access public
	 */
	function uncork() {
		/* Open a Redis connection and execute the queued commands */
		foreach ($this->queue as $command) {
			for ($written = 0; $written < strlen($command); $written += $fwrite) {
				$fwrite = fwrite($this->__sock, substr($command, $written));
				if ($fwrite === FALSE || $fwrite <= 0) {
					throw new \Exception('Failed to write entire command to stream');
				}
			}
		}

		// Read in the results from the pipelined commands
		$responses = array();
		for ($i = 0; $i < count($this->queue); $i++) {
			$responses[] = $this->readResponse();
		}

		// Clear the queue and return the response
		$this->queue = array();
		if ($this->pipelined) {
			$this->pipelined = FALSE;
			return $responses;
		} else {
			return $responses[0];
		}
	}

	function __call($name, $args) {

		/* Build the Redis unified protocol command */
		array_unshift($args, strtoupper($name));
		$command = sprintf('*%d%s%s%s', count($args), CRLF, implode(array_map(function($arg) {
			return sprintf('$%d%s%s', strlen($arg), CRLF, $arg);
		}, $args), CRLF), CRLF);

		/* Add it to the pipeline queue */
		$this->queue[] = $command;

		if ($this->pipelined) {
			return $this;
		} else {
			return $this->uncork();
		}
	}

	private function readResponse() {
		/* Parse the response based on the reply identifier */
		$reply = trim(fgets($this->__sock, 512));
		switch (substr($reply, 0, 1)) {
			/* Error reply */
			case '-':
				throw new RedisException(trim(substr($reply, 4)));
				break;
			/* Inline reply */
			case '+':
				$response = substr(trim($reply), 1);
				if ($response === 'OK') {
					$response = TRUE;
				}
				break;
			/* Bulk reply */
			case '$':
				$response = NULL;
				if ($reply == '$-1') {
					break;
				}
				$read = 0;
				$size = intval(substr($reply, 1));
				if ($size > 0) {
					do {
						$block_size = ($size - $read) > 1024 ? 1024 : ($size - $read);
						$r = fread($this->__sock, $block_size);
						if ($r === FALSE) {
							throw new \Exception('Failed to read response from stream');
						} else {
							$read += strlen($r);
							$response .= $r;
						}
					} while ($read < $size);
				}
				fread($this->__sock, 2); /* discard crlf */
				break;
			/* Multi-bulk reply */
			case '*':
				$count = intval(substr($reply, 1));
				if ($count == '-1') {
					return NULL;
				}
				$response = array();
				for ($i = 0; $i < $count; $i++) {
					$response[] = $this->readResponse();
				}
				break;
			/* Integer reply */
			case ':':
				$response = intval(substr(trim($reply), 1));
				break;
			default:
				throw new RedisException("Unknown response: {$reply}");
				break;
		}
		/* Party on */
		return $response;
	}

}
