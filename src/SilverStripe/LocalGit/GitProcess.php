<?php

namespace SilverStripe\LocalGit;

use Symfony\Component\Process\Process;

/**
 * Class GitProcess
 *
 * This is a light extension of Process to wrap git command with an identity key and pass through
 * to a shell script. This means we can securely clone repo's that users have permission to clone instead
 * of using deploynaut's key which has access to private repo's.
 *
 * @example
 * env IDENT_KEY=~/.ssh/id_rsa GIT_SSH=./git.sh git clone git@code.platform.silverstripe.com:222/aws/project.git
 */
class GitProcess extends Process {

	/**
	 * @var string Path to private key
	 */
	protected $identityFile;

	/**
	 * Retrieves an absolute path to git.sh based on self::GIT_SSH.
	 * The input path is assumed to be relative to the base dir of the rainforest project.
	 *
	 * @return string Path to git.sh
	 */
	static public function get_git_sh_path() {
		// "realpath" dereferences symlinks.
		$gitSh = dirname(dirname(dirname(realpath(__DIR__)))) . DIRECTORY_SEPARATOR . 'git.sh';
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
	 * @throws \RuntimeException
	 */
	public function setIdentityFile($file) {
		if(is_file($file) && is_readable($file)) {
			$this->identityFile = $file;
		} else {
			throw new \RuntimeException(sprintf('%s does not exist or is not readable.', $file));
		}
	}

	/**
	 * @param null $callback
	 * @return null|int null or 0 if everything went fine, or an error code
	 */
	public function run($callback = null) {
		$this->setCommandLine(
			'env IDENT_KEY=' . escapeshellarg($this->getIdentityFile())
			. ' GIT_SSH=' . escapeshellarg(self::get_git_sh_path())
			. ' ' . $this->getCommandLine()
		);

		parent::run($callback);
	}

}
