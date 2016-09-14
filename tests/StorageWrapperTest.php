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

namespace OCA\FilesImageTags\Tests;

use OCA\FilesImageTags\StorageWrapper;
use Test\TestCase;

class StorageWrapperTest extends TestCase {

	protected function getStorageMock() {
		return $this->getMockBuilder('OCP\Files\Storage\IStorage')
			->getMock();
	}

	protected function getWorker() {
		return $this->getMockBuilder('OCA\FilesImageTags\Worker')
			->disableOriginalConstructor()
			->getMock();
	}

	public function dataGetCache() {
		return [
			[$this->getStorageMock(), $this->getWorker(), 'mountPoint1', 'path1', $this->getStorageMock()],
			[$this->getStorageMock(), $this->getWorker(), 'mountPoint2', 'path2', null],
		];
	}

	/**
	 * @dataProvider dataGetCache
	 *
	 * @param \OCP\Files\Storage\IStorage|\PHPUnit_Framework_MockObject_MockObject $constructorStorage
	 * @param \OCA\FilesImageTags\Worker $worker
	 * @param string $mountPoint
	 * @param string $path
	 * @param \OCP\Files\Storage\IStorage|null $storage
	 */
	public function testGetCache($constructorStorage, $worker, $mountPoint, $path, $storage) {
		$test = new StorageWrapper([
			'storage' => $constructorStorage,
			'worker' => $worker,
			'mountPoint' => $mountPoint,
		]);

		$cache = $this->getMockBuilder('OCP\Files\Cache\ICache')
			->getMock();
		if ($storage === null) {
			$usedStorage = $test;
		} else {
			$usedStorage = $storage;
		}

		$constructorStorage->expects($this->once())
			->method('getCache')
			->with($path, $usedStorage)
			->willReturn($cache);

		$test = $test->getCache($path, $storage);

		$this->assertInstanceOf('OCA\FilesImageTags\CacheWrapper', $test);
	}
}
