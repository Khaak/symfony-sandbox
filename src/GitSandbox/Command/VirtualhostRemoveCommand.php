<?php

namespace GitSandbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class VirtualhostRemoveCommand extends Command {
	protected function configure() {
		$this
			->setName('sandbox:virtualhost_remove')
			->setDescription('Remove virtualhost')
			->addArgument(
				'host',
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
		if (!$input->getArgument('host')) {
			$host = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a host name:',
				function ($host) {
					if (empty($host)) {
						throw new \Exception('Host name can not be empty');
					}
					return $host;
				}
			);
			$input->setArgument('host', $host);
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
		$host = $input->getArgument('host');
		$nginxconffile1 = $settings["NGINX_CONF_DIR"] . "sandbox/site_avaliable/" . $host . ".conf";
		$nginxconffile2 = $settings["NGINX_CONF_DIR"] . "sandbox/site_enabled/" . $host . ".conf";
		$httpdconffile = $settings["HTTPD_CONF_DIR"] . "/sandbox/conf/" . $host . ".conf";
		$phpsessionsavepath = '/tmp/php_sessions/' . $host;
		$phpuploadtmpdir = '/tmp/php_upload/' . $host;

		$fs = new Filesystem();
		if (!$fs->exists($httpdconffile)) {
			throw new \Exception('Host not exists');
		}
		$fs->remove($nginxconffile1);
		$fs->remove($nginxconffile2);
		$fs->remove($httpdconffile);
		$fs->remove($phpsessionsavepath);
		$fs->remove($phpuploadtmpdir);
		$process = new Process('service httpd reload');
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		$process = new Process('nginx -s reload');
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		$output->writeln("Done");
	}
}