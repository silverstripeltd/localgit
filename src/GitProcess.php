<?php

namespace SilverStripe\LocalGit;

use Exception;
use Symfony\Component\Process\Process;

/**
 * Class GitProcess
 *
 * This is a light extension of Process to wrap git command with some custom options.
 * This allows us to set a per-execution identity for greater security (using sandboxed credentials),
 * as well as use a customised known_hosts file.
 */
class GitProcess extends Process {

	/**
	 * @var string Path to private key
	 */
	protected $identityFile;

	/**
	 * @var string Path to UserKnownHostsFile
	 */
	protected $knownHostsFile;

	/**
	 * Retrieves an absolute path to git.sh based on self::GIT_SSH.
	 * The input path is assumed to be relative to the base dir of the rainforest project.
	 *
	 * @return string Path to git.sh
	 */
	static public function get_git_sh_path() {
		// SSP seems to have a bug where exec bit disappears from original git.sh during fast deploys
		// (or maybe even full deploys) during composer install, when using cache on Debian. The bug is intermittent.
		$vendorDir = dirname(dirname(dirname(realpath(__DIR__))));
		$gitSh = $vendorDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'git.sh';

		if (!file_exists($gitSh)) {
			throw new Exception(sprintf('Git proxy script not found in "%s".', $gitSh));
		}

		if (!is_executable($gitSh)) {
			throw new Exception(sprintf('Git proxy script at "%s" is not executable.', $gitSh));
		}

		return realpath($gitSh);
	}

	/**
	 * @return string
	 */
	public function getIdentityFile() {
		return $this->identityFile;
	}

	/**
	 * @param string $file Path to private key
	 */
	public function setIdentityFile($file) {
		if(is_file($file) && is_readable($file)) {
			$this->identityFile = $file;
		} else {
			throw new Exception(sprintf('%s does not exist or is not readable.', $file));
		}
	}

	/**
	 * @return string|null
	 */
	public function getKnownHostsFile()
	{
		return $this->knownHostsFile;
	}

	/**
	 * @param string $file Path to known hosts file. Must be read/writable.
	 */
	public function setKnownHostsFile($file) {
		$this->knownHostsFile = $file;
	}

	/**
	 * @param null $callback
	 * @return null|int null or 0 if everything went fine, or an error code
	 */
	public function run($callback = null) {
		$this->setCommandLine(
			'env IDENT_KEY=' . escapeshellarg($this->getIdentityFile())
			. ' KNOWN_HOSTS_FILE=' . escapeshellarg($this->getKnownHostsFile())
			. ' GIT_SSH=' . escapeshellarg(self::get_git_sh_path())
			. ' ' . $this->getCommandLine()
		);

		parent::run($callback);
	}

}
