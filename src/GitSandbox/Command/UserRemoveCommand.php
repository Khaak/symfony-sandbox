<?php

namespace GitSandbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class UserRemoveCommand extends Command {
	protected function configure() {
		$this
			->setName('sandbox:UserRemove')
			->setDescription('Remove sandbox user')
			->addArgument(
				'name',
				InputArgument::REQUIRED,
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
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		require "config.php";
		if (posix_geteuid() !== 0) {
			throw new \Exception('This script should be run as root.');
		}
		$name = $input->getArgument('name');
		$dir = "/home/" . $name . "/www";
		$finder = new Finder();
		$dirs = $finder->directories()->in($dir)->count();
		if ($dirs !== 0) {
			throw new \Exception('User ' . $name . ' have sandboxes. You must remove sandboxes before removing this user');
		}
		$process = new Process('userdel -r ' . $name);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		$output->writeln("User " . $name . " removed");
	}
}