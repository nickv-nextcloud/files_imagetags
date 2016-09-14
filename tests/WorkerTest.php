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

use OCA\FilesImageTags\Worker;
use OCP\Files\Storage\IStorage;
use Test\TestCase;

class WorkerTest extends TestCase {

	/** @var \OCP\SystemTag\ISystemTagObjectMapper|\PHPUnit_Framework_MockObject_MockObject */
	protected $objectMapper;
	/** @var \OCP\SystemTag\ISystemTagManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $tagManger;
	/** @var \OCP\Files\Config\IMountProviderCollection|\PHPUnit_Framework_MockObject_MockObject */
	protected $mountCollection;
	/** @var \OCP\Files\IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	protected $rootFolder;
	/** @var \OCA\FilesImageTags\Worker */
	protected $worker;

	protected function setUp() {
		parent::setUp();

		$this->objectMapper = $this->getMockBuilder('OCP\SystemTag\ISystemTagObjectMapper')
			->getMock();
		$this->tagManger = $this->getMockBuilder('OCP\SystemTag\ISystemTagManager')
			->getMock();
		$this->mountCollection = $this->getMockBuilder('OCP\Files\Config\IMountProviderCollection')
			->getMock();
		$this->rootFolder = $this->getMockBuilder('OCP\Files\IRootFolder')
			->getMock();

		$this->worker = new Worker(
			$this->objectMapper,
			$this->tagManger,
			$this->mountCollection,
			$this->rootFolder
		);
	}

	protected function getStorageMock() {
		return $this->getMockBuilder('OCP\Files\Storage\IStorage')
			->getMock();
	}

	public function dataReadAndAssignTags() {
		return [
			[$this->getStorageMock(), 123, 'path', [], []],
			[$this->getStorageMock(), 42, 'path2', [['operation' => '2']], [
				[2],
			]],
			[$this->getStorageMock(), 23, 'path2', [
				['operation' => '2,3'],
				['operation' => '42']
			],[
				[2, 3],
				[42],
			]],
		];
	}

	/**
	 * @dataProvider dataReadAndAssignTags
	 *
	 * @param IStorage $storage
	 * @param int $fileId
	 * @param string $file
	 * @param array[] $matches
	 * @param array[] $expected
	 */
	public function testReadAndAssignTags(IStorage $storage, $fileId, $file, array $matches, array $expected) {
		#$this->worker->readAndAssignTags($storage, $fileId, $file);
	}
}
