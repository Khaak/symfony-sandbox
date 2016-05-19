<?php

namespace GitSandbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ConfigureCommand extends Command {
	protected function configure() {
		$this
			->setName('sandbox:configure')
			->setDescription('')
			->addArgument(
				'user',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'group',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'encoding',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'domain',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'templatesdir',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'httpdconf',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'nginxconf',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'dbuser',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'dbpass',
				InputArgument::REQUIRED,
				''
			)
		;
	}
	protected function interact(InputInterface $input, OutputInterface $output) {
		if (!$input->getArgument('user')) {
			$user = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please choose a project user (Can not be empty): ',
				function ($user) {
					if (empty($user)) {
						throw new \Exception('Can not be empty');
					}
					return $user;
				}
			);
			$input->setArgument('user', $user);
		}
		if (!$input->getArgument('group')) {
			$group = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please choose a devevopment group (Can not be empty): ',
				function ($group) {
					if (empty($group)) {
						throw new \Exception('Can not be empty');
					}
					return $group;
				}
			);
			$input->setArgument('group', $group);
		}
		if (!$input->getArgument('encoding')) {
			$encoding = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a default site encoding (Can not be empty): ',
				function ($encoding) {
					if (empty($encoding)) {
						throw new \Exception('Can not be empty');
					}
					return $encoding;
				}
			);
			$input->setArgument('encoding', $encoding);
		}
		if (!$input->getArgument('domain')) {
			$domain = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a base domain (Can not be empty): ',
				function ($domain) {
					if (empty($domain)) {
						throw new \Exception('Can not be empty');
					}
					return $domain;
				}
			);
			$input->setArgument('domain', $domain);
		}
		if (!$input->getArgument('templatesdir')) {
			$templatesdir = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please locate a vhost templates directory (Can not be empty): ',
				function ($templatesdir) {
					if (empty($templatesdir)) {
						throw new \Exception('Can not be empty');
					}
					return $templatesdir;
				}
			);
			$input->setArgument('templatesdir', $templatesdir);
		}
		if (!$input->getArgument('httpdconf')) {
			$httpdconf = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please locate a httpd conf directory (Can not be empty): ',
				function ($httpdconf) {
					if (empty($httpdconf)) {
						throw new \Exception('Can not be empty');
					}
					return $httpdconf;
				}
			);
			$input->setArgument('httpdconf', $httpdconf);
		}
		if (!$input->getArgument('nginxconf')) {
			$nginxconf = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please locate a nginx conf directory (Can not be empty): ',
				function ($nginxconf) {
					if (empty($nginxconf)) {
						throw new \Exception('Can not be empty');
					}
					return $nginxconf;
				}
			);
			$input->setArgument('nginxconf', $nginxconf);
		}
		if (!$input->getArgument('dbuser')) {
			$dbuser = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a database admin login (Can not be empty): ',
				function ($dbuser) {
					if (empty($dbuser)) {
						throw new \Exception('Can not be empty');
					}
					return $dbuser;
				}
			);
			$input->setArgument('dbuser', $dbuser);
		}
		if (!$input->getArgument('dbpass')) {
			$dbpass = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a database admin password: ',
				function ($dbpass) {
					return $dbpass;
				}
			);
			$input->setArgument('dbpass', $dbpass);
		}
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		if (posix_geteuid() !== 0) {
			//throw new \Exception('This script should be run as root.');
		}
		$user = $input->getArgument('user');
		$group = $input->getArgument('group');
		$encoding = $input->getArgument('encoding');
		$domain = $input->getArgument('domain');
		$templatesdir = $input->getArgument('templatesdir');
		$httpdconf = $input->getArgument('httpdconf');
		$nginxconf = $input->getArgument('nginxconf');
		$dbuser = $input->getArgument('dbuser');
		$dbpass = $input->getArgument('dbpass');
		$file_cont = '<?php
$settings["PROJECT_USER"]="' . $user . '";
$settings["PROJECT_GROUP"]="' . $group . '";

// Default encoding site
$settings["ENCODING_SITE"]="' . $encoding . '";

// Domain for vhost
$settings["DOMAIN"]="' . $domain . '";

// Templates dir
$settings["TEMPLATES_DIR"]="' . $templatesdir . '";

// httpd and nginx config dirs
$settings["HTTPD_CONF_DIR"]="' . $httpdconf . '";
$settings["NGINX_CONF_DIR"]="' . $nginxconf . '";

// DB settings
$settings["DB_USER"]="' . $dbuser . '";
$settings["DB_PASS"]="' . $dbpass . '";
?>';
		$fs = new Filesystem();
		$fs->dumpFile('/etc/git-sandbox/config.php', $file_cont);
		$output->writeln("Done");
	}
}