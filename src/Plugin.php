<?php

namespace hashworks\Phergie\Plugin\SRRDatabaseSearch;

use Phergie\Irc\Bot\React\AbstractPlugin;
use \WyriHaximus\Phergie\Plugin\Http\Request;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;

/**
 * Plugin class.
 *
 * @category Phergie
 * @package hashworks\Phergie\Plugin\SRRDatabaseSearch
 */
class Plugin extends AbstractPlugin {

	private $limit = 5;
	private $hideUrlOnMultipleResults = false;

	public function __construct ($config = array()) {
		if (isset($config['limit'])) $this->limit = intval($config['limit']);
		if (isset($config['hideUrlOnMultipleResults'])) $this->hideUrlOnMultipleResults = boolval($config['hideUrlOnMultipleResults']);
	}

	/**
	 * @return array
	 */
	public function getSubscribedEvents () {
		return array(
				'command.srrdb'      => 'handleCommand',
				'command.srrdb.help' => 'handleCommandHelp',
		);
	}

	/**
	 * Sends reply messages.
	 *
	 * @param Event        $event
	 * @param Queue        $queue
	 * @param array|string $messages
	 */
	protected function sendReply (Event $event, Queue $queue, $messages) {
		$method = 'irc' . $event->getCommand();
		if (is_array($messages)) {
			$target = $event->getSource();
			foreach ($messages as $message) {
				$queue->$method($target, $message);
			}
		} else {
			$queue->$method($event->getSource(), $messages);
		}
	}

	/**
	 * @param Event $event
	 * @param Queue $queue
	 */
	public function handleCommandHelp (Event $event, Queue $queue) {
		$this->sendReply($event, $queue, array(
				'Usage: srrdb <imdbid|archive-crc|dirname|query>',
				'Searches the SRRDB for the given parameter and returns the result.'
		));
	}

	/**
	 * @param Event $event
	 * @param Queue $queue
	 */
	public function handleCommand (Event $event, Queue $queue) {
		if (preg_match('/^(?:(?:t{0,2}(?<imdbid>[0-9]{7}))|(?<crc>[0-9A-Z]+)|(?<dirname>[a-zA-Z0-9._]{4,}-[a-zA-Z0-9]{3,})|(?<search>.+))$/', join(' ', $event->getCustomParams()), $matches)) {

			if (isset($matches['imdbid']) && !empty($matches['imdbid'])) {
				$suffix = 'imdb:' . rawurlencode($matches['imdbid']);
			} elseif (isset($matches['crc']) && !empty($matches['crc'])) {
				$suffix = 'archive-crc:' . rawurlencode($matches['crc']);
			} elseif (isset($matches['dirname']) && !empty($matches['dirname'])) {
				$suffix = 'r:' . rawurlencode($matches['dirname']);
			} else {
				$this->sendReply($event, $queue, 'Searching the SRR database...');
				$queryWords = explode(' ', $matches['search']);
				array_map('rawurlencode', $queryWords);
				$suffix = join('/', $queryWords);
			}
			$suffix .= '/order:date-desc';

			$errorCallback = function ($error) use ($event, $queue) {
				$this->sendReply($event, $queue, $error);
			};

			$this->emitter->emit('http.request', [new Request([
					'url'             => 'http://www.srrdb.com/api/search/' . $suffix,
					'resolveCallback' => function ($data) use ($event, $queue, $errorCallback) {
						if (!empty($data) && ($data = json_decode($data, true)) !== null) {
							if (isset($data['results']) && isset($data['resultsCount'])) {
								if ($data['resultsCount'] == 0) {
									$errorCallback('Nothing found!');
									return;
								} else {
									$results = array_slice($data['results'], 0, $this->limit);
									$sendReply = false;
									foreach($results as $result) {
										if (isset($result['release'])) {
											$string = $result['release'] . ' [SRR] ';
											if (isset($result['hasSRS']) && $result['hasSRS'] == 'yes') $string .= '[SRS] ';
											if (isset($result['hasNFO']) && $result['hasNFO'] == 'yes') $string .= '[NFO] ';
											if (count($results) == 1 || $this->hideUrlOnMultipleResults == false) {
												$string .= '- http://www.srrdb.com/release/details/' . $result['release'];
											}
											$string = trim($string);
											if (!empty($string)) {
												$this->sendReply($event, $queue, $string);
												$sendReply = true;
											}
										}
									}
									if ($sendReply) return;
								}
							}
						}
						$errorCallback('Failed to search srrdb.com.');
					},
					'rejectCallback'  => $errorCallback
			])]);

		} else {
			$this->handleCommandHelp($event, $queue);
		}
	}

}
