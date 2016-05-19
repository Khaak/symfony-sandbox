<?php

namespace GitSandbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class UserAddCommand extends Command {
	protected function configure() {
		$this
			->setName('sandbox:user_add')
			->setDescription('Add sandbox user')
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'password',
				InputArgument::REQUIRED,
				''
			)
		;
	}
	protected function interact(InputInterface $input, OutputInterface $output) {
		if (!$input->getArgument('name')) {
			$name = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a name: ',
				function ($name) {
					if (empty($name)) {
						throw new \Exception('Username can not be empty');
					}
					return $name;
				}
			);
			$input->setArgument('name', $name);
		}
		if (!$input->getArgument('password')) {
			$password = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a password: ',
				function ($password) {
					if (empty($password)) {
						throw new \Exception('Password can not be empty');
					}
					return $password;
				}
			);
			$input->setArgument('password', $password);
		}
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		require "/etc/git-sandbox/config.php";
		if (posix_geteuid() !== 0) {
			throw new \Exception('This script should be run as root.');
		}
		$name = $input->getArgument('name');
		$password = $input->getArgument('password');
		$homedir = "/home/" . $name;
		$group = $settings["PROJECT_GROUP"];
		$handle = @fopen("/etc/passwd", "r");
		if ($handle) {
			while (($buffer = fgets($handle, 4096)) !== false) {
				if (strpos($buffer, $name . ':') === 0) {
					throw new \Exception('User ' . $name . ' already exists. Please enter a different username.');
				}
			}
			if (!feof($handle)) {
				throw new \Exception("Error: unexpected fgets() fail");
			}
			fclose($handle);
		}

		$text = $name . " " . $password;

		$process = new Process('useradd -m ' . $name);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		$process = new Process('echo ' . $password . ' | passwd ' . $name . ' --stdin');
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}

		$fs = new Filesystem();
		try {
			$fs->mkdir($homedir . "/www");
		} catch (IOExceptionInterface $e) {
			echo "An error occurred while creating directory at " . $e->getPath();
		}
		try {
			$fs->chown($homedir, $name, true);
		} catch (IOExceptionInterface $e) {
			echo "An error occurred while chown directory at " . $e->getPath();
		}
		try {
			$fs->chgrp($homedir, $group, true);
		} catch (IOExceptionInterface $e) {
			echo "An error occurred while chgrp directory at " . $e->getPath();
		}

		$process = new Process('usermod -G ' . $group . ' ' . $name);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}

		//$text = posix_geteuid();
		//$output->writeln($text);
	}
}