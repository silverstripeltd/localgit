<?php

namespace SilverStripe\LocalGit;

class ReadonlyGitRepository {

	/**
	 * @var string
	 */
	protected $gitUrl;

	/**
	 * @var string
	 */
	protected $gitRevision = 'master';

	/**
	 * @var string path to ssh private key
	 */
	protected $identityFile = '';

	/**
	 * @var string
	 */
	protected $localPath = '';

	/**
	 * @param string $gitUrl
	 * @param string $revision
	 * @param string|null $identityFile
	 * @param string|null $localPath Specify local path to clone repo
	 */
	public function __construct($gitUrl, $revision = 'master', $identityFile = null, $localPath = null) {
		$this->setGitUrl($gitUrl);
		$this->setGitRevision($revision);
		if ($identityFile === null && isset($_SERVER['HOME']) && is_readable($_SERVER['HOME'] . '/.ssh/id_rsa')) {
			$identityFile = $_SERVER['HOME'] . '/.ssh/id_rsa';
		}
		$this->localPath = $localPath;
		$this->setIdentityFile($identityFile);
	}

	/**
	 * Returns the git url
	 *
	 * @return string
	 */
	public function getGitUrl() {
		return $this->gitUrl;
	}

	/**
	 * Set the git url
	 *
	 * @param string $url
	 */
	public function setGitUrl($url) {
		$this->gitUrl = $url;
	}

	/**
	 * Get the git revision.
	 *
	 * @return string
	 */
	public function getGitRevision() {
		return $this->gitRevision;
	}

	/**
	 * Set the git revision
	 *
	 * @param string $revision
	 */
	public function setGitRevision($revision) {
		$this->gitRevision = $revision;
	}

	/**
	 * Get identity file
	 *
	 * @return string
	 */
	public function getIdentityFile() {
		return $this->identityFile;
	}

	/**
	 * Set the identity file
	 *
	 * @param string $identityFile
	 */
	public function setIdentityFile($identityFile) {
		$this->identityFile = $identityFile;
	}

	/**
	 * Return the path used for temporarily downloading this repository.
	 *
	 * @return string
	 */
	public function getLocalPath() {
		return $this->localPath;
	}

	/**
	 * Gets a file from the given revision. Defaults to the current
	 * revision if not given.
	 *
	 * @throws \RuntimeException
	 * @param string $file
	 * @param string|null $revision Specify revision. Defaults to current
	 * @return string|null
	 */
	public function getFileContent($file, $revision = null) {
		$revision = ($revision !== null) ? $revision : $this->getGitRevision();
		$process = new GitProcess(
			sprintf(
				'git show %2$s:%3$s',
				escapeshellarg($this->getLocalPath()),
				escapeshellarg($revision),
				escapeshellarg($file)
			),
			$this->getLocalPath()
		);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		return $process->getOutput();
	}

	/**
	 * Get a list of tags for this repository.
	 * @return array
	 */
	public function getTags() {
		$process = new GitProcess(sprintf('git ls-remote --refs --tags %s', escapeshellarg($this->getGitUrl())));
		if ($this->getIdentityFile()) {
			$process->setIdentityFile($this->getIdentityFile());
		}
		$process->run();
		if (!$process->isSuccessful()) {
			// Couldn't resolve tags
			throw new \RuntimeException($process->getErrorOutput());
		}
		$tags = [];
		foreach ($this->parseGitRemoteOutput($process->getOutput()) as $sha => $name) {
			$tags[] = str_replace('refs/tags/', '', $name);
		}
		return $tags;
	}

	/**
	 * Attempts to resolve a git SHA to a tag reference.
	 *
	 * @param string $value
	 * @return string
	 */
	public function resolveTagReference($value) {
		if (!$value) {
			return null; // can't resolve an empty value.
		}
		$process = new GitProcess(
			sprintf('git describe --tags %s', escapeshellarg($value)),
			$this->getLocalPath()
		);
		if ($this->getIdentityFile()) {
			$process->setIdentityFile($this->getIdentityFile());
		}
		$process->run();
		if (!$process->isSuccessful()) {
			// Couldn't resolve to a tag
			return $value;
		}
		return str_replace(["\n", "\r"], '', $process->getOutput());
	}

	/**
	 * Attempts to resolve a git reference to a single commit SHA
	 *
	 * @param string $value
	 * @return string|null
	 */
	public function resolveGitReference($value) {
		if (preg_match('/^[0-9a-f]{40}$/', $value)) {
			return $value;
		}
		$process = new GitProcess(sprintf(
			'git ls-remote %s %s',
			escapeshellarg($this->getGitUrl()),
			escapeshellarg($value)
		));
		if ($this->getIdentityFile()) {
			$process->setIdentityFile($this->getIdentityFile());
		}
		$process->setTimeout(60);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		$output = trim($process->getOutput());
		if (!$output) {
			// The reference doesn't resolve
			return null;
		}
		$parsed = $this->parseGitRemoteOutput($output);
		if ($parsed) {
			return array_keys($parsed)[0];
		}
		// Failed to get anything.
		return null;
	}

	/**
	 * Parse git ls-remote output of either one line or multiple lines into a map
	 * of sha => reference name.
	 *
	 * The format of each line is like "615d39c6c9bf64634425a9678d071cf1301c06ce<TAB>refs/heads/master"
	 *
	 * @param string $output
	 * @return array
	 */
	protected function parseGitRemoteOutput($output) {
		$refs = explode(PHP_EOL, trim($output));
		$list = [];
		foreach ($refs as $ref) {
			$columns = array_filter(preg_split('/\s+/', $ref));
			if (count($columns) < 2) {
				continue;
			}
			$list[$columns[0]] = $columns[1];
		}
		return $list;
	}

}
