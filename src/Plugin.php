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

	public function __construct ($config = array()) {
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
				'Usage: srrdb <dirname|archive-crc>',
				'Searches the SRRDB for the given parameter and returns the result.'
		));
	}

	/**
	 * @param Event $event
	 * @param Queue $queue
	 */
	public function handleCommand (Event $event, Queue $queue) {
		if (preg_match('/^(?:(?<crc>[0-9a-z]+)|(?<dirname>.+))$/i', $event->getCustomParams()[0], $matches)) {

			if (isset($matches['crc']) && !empty($matches['crc'])) {
				$suffix = 'archive-crc:' . $matches['crc'];
			} else {
				$suffix = 'r:' . $matches['dirname'];
			}

			$errorCallback = function ($error) use ($event, $queue) {
				$this->sendReply($event, $queue, $error);
			};

			var_dump('http://www.srrdb.com/api/search/' . $suffix);

			$this->emitter->emit('http.request', [new Request([
					'url'             => 'http://www.srrdb.com/api/search/' . $suffix,
					'resolveCallback' => function ($data) use ($event, $queue, $errorCallback) {
						if (!empty($data) && ($data = json_decode($data, true)) !== null) {
							if (isset($data['results']) && isset($data['resultsCount'])) {
								var_dump($data);
								if ($data['resultsCount'] == 0) {
									$errorCallback('Nothing found!');
									return;
								} else {
									$data = $data['results'][0];
									if (isset($data['release'])) {
										$string = $data['release'] . ' [SRR] ';
										if (isset($data['hasSRS']) && $data['hasSRS'] == 'yes') $string .= '[SRS] ';
										if (isset($data['hasNFO']) && $data['hasNFO'] == 'yes') $string .= '[NFO] ';
										$string .= '- http://www.srrdb.com/release/details/' . $data['release'];
										$string = trim($string);
										if (!empty($string)) {
											$this->sendReply($event, $queue, $string);
											return;
										}
									}
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
