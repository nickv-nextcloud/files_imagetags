<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FilesImageTags;


use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\Storage\IStorage;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\MapperEvent;
use OCP\SystemTag\TagNotFoundException;

class Worker {

	/** @var ISystemTagObjectMapper */
	protected $objectMapper;

	/** @var ISystemTagManager */
	protected $tagManager;

	/** @var IMountProviderCollection */
	protected $mountCollection;

	/** @var IRootFolder */
	protected $rootFolder;

	/** @var bool */
	protected $nestedCall = false;

	/**
	 * @param ISystemTagObjectMapper $objectMapper
	 * @param ISystemTagManager $tagManager
	 * @param IMountProviderCollection $mountCollection
	 * @param IRootFolder $rootFolder
	 */
	public function __construct(ISystemTagObjectMapper $objectMapper, ISystemTagManager $tagManager, IMountProviderCollection $mountCollection, IRootFolder $rootFolder) {
		$this->objectMapper = $objectMapper;
		$this->tagManager = $tagManager;
		$this->mountCollection = $mountCollection;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * @param IStorage $storage
	 * @param int $fileId
	 * @param string $file
	 */
	public function readAndAssignTags(IStorage $storage, $fileId, $file) {
		$tmpPath = $storage->getLocalFile($file);
		$result = getimagesize($tmpPath, $info);

		if (!$result || !isset($info["APP13"])) {
			return;
		}

		$tagInfo = iptcparse($info["APP13"]);
		if (!isset($tagInfo["2#025"])) {
			return;
		}

		$tagIds = [];
		foreach ($tagInfo["2#025"] as $tag) {
			try {
				$systemTag = $this->tagManager->getTag($tag, true, true);
			} catch (TagNotFoundException $e) {
				$systemTag = $this->tagManager->createTag($tag, true, true);
			}
			$tagIds[] = $systemTag->getId();
		}

		$this->nestedCall = true;
		$this->objectMapper->assignTags($fileId, 'files', $tagIds);
		$this->nestedCall = false;
	}

	/**
	 * @param MapperEvent $event
	 */
	public function mapperEvent(MapperEvent $event) {
		if ($this->nestedCall) {
			return;
		}

		$tagIds = $event->getTags();
		if ($event->getObjectType() !== 'files' || empty($tagIds)
			|| !in_array($event->getEvent(), [MapperEvent::EVENT_ASSIGN, MapperEvent::EVENT_UNASSIGN])) {
			// System tags not for files, no tags, not (un-)assigning or no activity-app enabled (save the energy)
			return;
		}

		try {
			$tags = $this->tagManager->getTagsByIds($tagIds);
		} catch (TagNotFoundException $e) {
			// User assigned/unassigned a non-existing tag, ignore...
			return;
		}

		if (empty($tags)) {
			return;
		}

		// Get all mount point owners
		$cache = $this->mountCollection->getMountCache();
		$mounts = $cache->getMountsForFileId($event->getObjectId());
		if (empty($mounts)) {
			return;
		}

		foreach ($mounts as $mount) {
			$owner = $mount->getUser()->getUID();
			$ownerFolder = $this->rootFolder->getUserFolder($owner);
			$nodes = $ownerFolder->getById($event->getObjectId());
			if (!empty($nodes)) {
				/** @var Node $node */
				$node = array_shift($nodes);

				if ($node instanceof Folder) {
					// Can't write tags into folders
					return;
				}

				$source = $node->getStorage()->fopen($node->getInternalPath(), 'r');
				if ($source === false) {
					continue;
				}

				$tmpFile = \OC::$server->getTempManager()->getTemporaryFile();
				file_put_contents($tmpFile, $source);
				fclose($source);
				$c = file_get_contents($tmpFile);

				$result = getimagesize($tmpFile, $info);

				if (!$result || !isset($info["APP13"])) {
					return;
				}

				$app13 = iptcparse($info["APP13"]);
				if (!isset($app13["2#025"])) {
					$app13["2#025"] = [];
				}

				if ($event->getEvent() === MapperEvent::EVENT_ASSIGN) {
					$app13["2#025"] = array_merge($app13["2#025"], $this->getTagNames($event->getTags()));
				} else {
					$app13["2#025"] = array_diff($app13["2#025"], $this->getTagNames($event->getTags()));
				}

				// Convert the IPTC tags into binary code again
				$data = '';
				foreach($app13 as $tag => $string) {
					$tag = substr($tag, 2);
					if (is_array($string)) {
						foreach ($string as $str) {
							$data .= $this->iptc_make_tag(2, $tag, $str);
						}
					} else {
						$data .= $this->iptc_make_tag(2, $tag, $string);
					}
				}

				// Embed the IPTC data
				$content = iptcembed($data, $tmpFile);
				$source = $node->getStorage()->fopen($node->getInternalPath(), 'wb');
				fwrite($source, $content);
				fclose($source);
			}
		}
	}

	/**
	 * @param int[] $tags
	 * @return string[]
	 */
	protected function getTagNames(array $tags) {
		$names = [];

		foreach ($tags as $tagId) {
			try {
				$systemTags = $this->tagManager->getTagsByIds($tagId);
			} catch (TagNotFoundException $e) {
				continue;
			}
			$names[] = $systemTags[$tagId]->getName();
		}

		return $names;
	}

	/**
	 * Copied from http://en.php.net/manual/en/function.iptcembed.php
	 * iptc_make_tag() function by Thies C. Arntzen
	 *
	 * @param int $rec
	 * @param string $data
	 * @param string $value
	 * @return string
	 */
	protected function iptc_make_tag($rec, $data, $value)
	{
		$length = strlen($value);
		$retval = chr(0x1C) . chr($rec) . chr($data);

		if($length < 0x8000) {
			$retval .= chr($length >> 8) .  chr($length & 0xFF);
		} else {
			$retval .= chr(0x80) .
				chr(0x04) .
				chr(($length >> 24) & 0xFF) .
				chr(($length >> 16) & 0xFF) .
				chr(($length >> 8) & 0xFF) .
				chr($length & 0xFF);
		}

		return $retval . $value;
	}
}
