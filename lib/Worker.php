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

namespace OCA\FilesIPTCTagging;


use OC\Files\Storage\Local;
use OCP\Files\Storage\IStorage;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\WorkflowEngine\IManager;

class Worker {

	/** @var ISystemTagObjectMapper */
	protected $objectMapper;

	/** @var ISystemTagManager */
	protected $tagManager;

	/** @var IManager */
	protected $checkManager;

	/**
	 * @param ISystemTagObjectMapper $objectMapper
	 * @param ISystemTagManager $tagManager
	 * @param IManager $checkManager
	 */
	public function __construct(ISystemTagObjectMapper $objectMapper, ISystemTagManager $tagManager, IManager $checkManager) {
		$this->objectMapper = $objectMapper;
		$this->tagManager = $tagManager;
		$this->checkManager = $checkManager;
	}

	/**
	 * @param IStorage $storage
	 * @param int $fileId
	 * @param string $file
	 */
	public function readAndAssignTags(IStorage $storage, $fileId, $file) {
		// TODO copy to tmp file if this fails
		/** @var Local $storage */
		$sourcePath = $storage->getSourcePath($file);
		$result = getimagesize($sourcePath, $info);

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
			} catch (\OCP\SystemTag\TagNotFoundException $e) {
				$systemTag = $this->tagManager->createTag($tag, true, true);
			}
			$tagIds[] = $systemTag->getId();
		}

		$this->objectMapper->assignTags($fileId, 'files', $tagIds);
	}
}
