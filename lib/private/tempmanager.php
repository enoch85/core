<?php
/**
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC;

use OCP\ILogger;
use OCP\ITempManager;

class TempManager implements ITempManager {
	/**
	 * Current temporary files and folders
	 *
	 * @var string[]
	 */
	protected $current = array();

	/**
	 * i.e. /tmp on linux systems
	 *
	 * @var string
	 */
	protected $tmpBaseDir;

	/**
	 * @var \OCP\ILogger
	 */
	protected $log;

	/**
	 * @param string $baseDir
	 * @param \OCP\ILogger $logger
	 */
	public function __construct($baseDir, ILogger $logger) {
		$this->tmpBaseDir = $baseDir;
		$this->log = $logger;
	}

	protected function generatePath($postFix) {
		return $this->tmpBaseDir . '/oc_tmp_' . md5(time() . rand()) . $postFix;
	}

	/**
	 * Create a temporary file and return the path
	 *
	 * @param string $postFix
	 * @return string
	 */
	public function getTemporaryFile($postFix = '') {
		$file = $this->generatePath($postFix);
		if (is_writable($this->tmpBaseDir)) {
			touch($file);
			$this->current[] = $file;
			return $file;
		} else {
			$this->log->warning(
				'Can not create a temporary file in directory {dir}. Check it exists and has correct permissions',
				array(
					'dir' => $this->tmpBaseDir
				)
			);
			return false;
		}
	}

	/**
	 * Create a temporary folder and return the path
	 *
	 * @param string $postFix
	 * @return string
	 */
	public function getTemporaryFolder($postFix = '') {
		$path = $this->generatePath($postFix);
		if (is_writable($this->tmpBaseDir)) {
			mkdir($path);
			$this->current[] = $path;
			return $path . '/';
		} else {
			$this->log->warning(
				'Can not create a temporary folder in directory {dir}. Check it exists and has correct permissions',
				array(
					'dir' => $this->tmpBaseDir
				)
			);
			return false;
		}
	}

	/**
	 * Remove the temporary files and folders generated during this request
	 */
	public function clean() {
		$this->cleanFiles($this->current);
	}

	protected function cleanFiles($files) {
		foreach ($files as $file) {
			if (file_exists($file)) {
				try {
					\OC_Helper::rmdirr($file);
				} catch (\UnexpectedValueException $ex) {
					$this->log->warning(
						"Error deleting temporary file/folder: {file} - Reason: {error}",
						array(
							'file' => $file,
							'error' => $ex->getMessage()
						)
					);
				}
			}
		}
	}

	/**
	 * Remove old temporary files and folders that were failed to be cleaned
	 */
	public function cleanOld() {
		$this->cleanFiles($this->getOldFiles());
	}

	/**
	 * Get all temporary files and folders generated by oc older than an hour
	 *
	 * @return string[]
	 */
	protected function getOldFiles() {
		$cutOfTime = time() - 3600;
		$files = array();
		$dh = opendir($this->tmpBaseDir);
		if ($dh) {
			while (($file = readdir($dh)) !== false) {
				if (substr($file, 0, 7) === 'oc_tmp_') {
					$path = $this->tmpBaseDir . '/' . $file;
					$mtime = filemtime($path);
					if ($mtime < $cutOfTime) {
						$files[] = $path;
					}
				}
			}
		}
		return $files;
	}
}
