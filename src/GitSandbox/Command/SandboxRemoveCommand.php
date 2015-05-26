<?php

namespace GitSandbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class SandboxRemoveCommand extends Command {
	protected function configure() {
		$this
			->setName('sandbox:sandbox_remove')
			->setDescription('Remove sandbox')
			->addArgument(
				'project',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'user',
				InputArgument::REQUIRED,
				''
			)->addArgument(
			'confirm',
			InputArgument::OPTIONAL,
			''
		)
		;
	}
	protected function interact(InputInterface $input, OutputInterface $output) {
		if (!$input->getArgument('project')) {
			$project = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a project name:',
				function ($project) {
					if (empty($project)) {
						throw new \Exception('Project name can not be empty');
					}
					return $project;
				}
			);
			$input->setArgument('project', $project);
		}
		if (!$input->getArgument('user')) {
			$user = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a username:',
				function ($user) {
					if (empty($user)) {
						throw new \Exception('User name can not be empty');
					}
					return $user;
				}
			);
			$input->setArgument('user', $user);
		}
		if ($input->getArgument('confirm') != "yes") {
			$confirm = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please confirm. Type "yes" for remove:',
				function ($confirm) {
					if ($confirm != "yes") {
						throw new \Exception('Aborted by user');
					}
					return $confirm;
				}
			);
		}
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		require "/etc/git-sandbox/config.php";
		if (posix_geteuid() !== 0) {
			throw new \Exception('This script should be run as root.');
		}
		$projectname = $input->getArgument('project');
		$user = $input->getArgument('user');
		$handle = @fopen("/etc/passwd", "r");
		if ($handle) {
			while (($buffer = fgets($handle, 4096)) !== false) {
				if (strpos($buffer, $user . ':') !== 0) {
					throw new \Exception('Can\'t find user ' . $user . '.');
				}
			}
			if (!feof($handle)) {
				throw new \Exception("Error: unexpected fgets() fail");
			}
			fclose($handle);
		}
		$project_path_full = "/home/" . $settings["PROJECT_USER"] . "/projects/" . $project . "/httpdocs";
		$fs = new Filesystem();
		if (!$fs->exists($project_path_full)) {
			throw new \Exception('Project ' . $projectname . ' not exists');
		}
		$sandbox_path = "/home/" . $user . "/www/" . $projectname . '/httpdocs';
		if (!$fs->exists($sandbox_path)) {
			throw new \Exception('Sandbox ' . $user . $projectname . ' not exists');
		}
		$fs->remove($sandbox_path);
		$output->writeln("Done");
	}
}