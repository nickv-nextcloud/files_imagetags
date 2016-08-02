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


use OCP\Files\Cache\ICache;
use OCP\Files\Storage\IStorage;

class CacheWrapper extends \OC\Files\Cache\Wrapper\CacheWrapper {

	/** @var IStorage */
	protected $storage;

	/** @var Worker */
	protected $worker;

	/** @var string */
	protected $mountPoint;

	/**
	 * @param ICache $cache
	 * @param IStorage $storage
	 * @param Worker $worker
	 * @param string $mountPoint
	 */
	public function __construct(ICache $cache, IStorage $storage, Worker $worker, $mountPoint) {
		parent::__construct($cache);
		$this->storage = $storage;
		$this->worker = $worker;
		$this->mountPoint = $mountPoint;
	}

	/**
	 * insert meta data for a new file or folder
	 *
	 * @param string $file
	 * @param array $data
	 *
	 * @return int file id
	 * @throws \RuntimeException
	 */
	public function insert($file, array $data) {
		$fileId = $this->cache->insert($file, $data);

		if ($fileId > -1 && $this->isTaggingPath($file)) {
			$this->worker->readAndAssignTags($this->storage, $fileId, $file);
		}

		return $fileId;
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	protected function isTaggingPath($file) {
		$path = $this->mountPoint . $file;

		if (substr_count($path, '/') < 3) {
			return false;
		}

		// '', admin, 'files', 'path/to/file.txt'
		list(,, $folder,) = explode('/', $path, 4);

		return $folder === 'files';
	}
}
