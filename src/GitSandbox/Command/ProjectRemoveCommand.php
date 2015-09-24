<?php

namespace GitSandbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ProjectRemoveCommand extends Command {
	protected function configure() {
		$this
			->setName('sandbox:project_remove')
			->setDescription('Remove project')
			->addArgument(
				'name',
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
		if (!$input->getArgument('name')) {
			$name = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please choose a name:',
				function ($name) {
					if (empty($name)) {
						throw new \Exception('Username can not be empty');
					}
					return $name;
				}
			);
			$input->setArgument('name', $name);
		}
		if ($input->getArgument('confirm') != "yes") {
			$confirm = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please confirm. Type "yes" for remove.',
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
		$name = $input->getArgument('name');
		$project_path = "/home/" . $settings["PROJECT_USER"] . "/projects";
		$project_path_full = $project_path . "/" . $projectname . "/httpdocs";
		$dbconn = $project_path_full . "/bitrix/php_interface/dbconn.php";
		if (file_exists($dbconn)) {
			include $dbconn;

		}
		$sqlstring = "DROP DATABASE " . $DBName . "; DROP USER " . $DBLogin . "@'%';";
		try {
			$dbh = new \PDO("mysql:host=127.0.0.1", $settings["DB_USER"], $settings["DB_PASS"]);
			if (!$dbh->exec($sqlstring)) {
				throw new \Exception(print_r($dbh->errorInfo(), true));
			}

		} catch (PDOException $e) {
			throw new \Exception("DB ERROR: " . $e->getMessage());
		}
		$fs = new Filesystem();
		$fs->remove($project_path_full);
		$output->writeln("Project " . $name . " removed");
	}
}