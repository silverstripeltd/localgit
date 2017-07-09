# Local git cache

This library manages a local clone of a git repository. Files can be read from any revision, and the repository will be
automatically fetched before that happens (unless `ReadonlyGitRepository` is used).

## Example

```
$repo = new GitRepository(
	'git@github.com:mateusz/blank.git',
	'master'
	'~/.ssh/id_rsa',
	'/var/tmp/my-clone
);
$content = $repo->getFileContent('README.md');
```
