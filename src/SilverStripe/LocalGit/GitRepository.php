<?php

namespace SilverStripe\LocalGit;

use SilverStripe\LocalGit\Exceptions\GitCloneException;
use Symfony\Component\Process\Process;

class GitRepository extends ReadonlyGitRepository {

	/**
	 * Should cleanup cloned repos after this object is destroyed?
	 * @var bool
	 */
	protected $cleanup = true;

	/**
	 * @param string $gitUrl
	 * @param string $revision
	 * @param string|null $identityFile
	 * @param string|null $localPath Specify local path to clone repo. Defaults to auto-generated path in temp
	 */
	public function __construct($gitUrl, $revision = 'master', $identityFile = null, $localPath = null) {
		parent::__construct($gitUrl, $revision, $identityFile, $localPath);

		if ($localPath === null) {
			$localBasePath = sprintf(
				'%s/temp-clone',
				sys_get_temp_dir()
			);

			if (!is_dir($localBasePath)) {
				mkdir($localBasePath);
			}

			$localPath = sprintf(
				'%s/%s',
				$localBasePath,
				sha1(microtime() . $this->getGitUrl())
			);
			$this->setCleanup(true);
		} else {
			// if an explicit path was given (local path wasn't auto-generated for us), then
			// we probably don't want to be cleaning it up in the destructor, as it may be needed afterwards.
			$this->setCleanup(false);
		}

		$this->localPath = $localPath;

		$this->cloneTempRepo();
	}

	/**
	 * Clean up temp repo. This is called either when destroying this object, or when
	 * PHP shuts down at the end of a Rainforest command.
	 */
	public function __destruct() {
		if ($this->cleanup && file_exists($this->getLocalPath())) {
			$process = new Process(sprintf('rm -rf %s', escapeshellarg($this->getLocalPath())));
			$process->run();
		}
	}

	/**
	 * Set cleanup
	 * @param bool $value
	 */
	public function setCleanup($value) {
		$this->cleanup = $value;
	}

	/**
	 * Download the repository from a remote repository.
	 * @throws \RuntimeException
	 * @return bool
	 */
	public function cloneTempRepo() {
		if (file_exists($this->getLocalPath())) {
			// We have created a new instance of this repo where it's already cloned. To ensure
			// we are up to date with metadata like tags and references we fetch here.
			$this->fetch();
			return true;
		}

		$process = new GitProcess(sprintf(
			'git clone %2$s %1$s -n && git --git-dir=%1$s/.git --work-tree=%1$s checkout %3$s',
			escapeshellarg($this->getLocalPath()),
			escapeshellarg($this->getGitUrl()),
			escapeshellarg($this->getGitRevision())
		));
		if ($this->getIdentityFile()) {
			$process->setIdentityFile($this->getIdentityFile());
		}
		$process->setTimeout(600);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new GitCloneException($process->getErrorOutput());
		}
		return true;
	}

	/**
	 * Fetch the latest information about the remote repository.
	 * @throws \RuntimeException
	 * @return bool
	 */
	public function fetch() {
		if (!file_exists($this->getLocalPath())) {
			return false;
		}
		$process = new GitProcess('git fetch', $this->getLocalPath());
		if ($this->getIdentityFile()) {
			$process->setIdentityFile($this->getIdentityFile());
		}
		$process->setTimeout(60);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		return true;
	}

}
