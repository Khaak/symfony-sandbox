<?php

namespace GitSandbox\Command;

use GitSandbox\PasswordGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class ProjectAddCommand extends Command {
	protected function configure() {
		$this
			->setName('sandbox:project_add')
			->setDescription('Add project')
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'encoding',
				InputArgument::OPTIONAL,
				''
			)
		;
	}
	protected function interact(InputInterface $input, OutputInterface $output) {
		if (!$input->getArgument('name')) {
			$name = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a name:',
				function ($name) {
					if (empty($name)) {
						throw new \Exception('Project name can not be empty');
					}
					return $name;
				}
			);
			$input->setArgument('name', $name);
		}
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		require "/etc/git-sandbox/config.php";
		if (posix_geteuid() !== 0) {
			throw new \Exception('This script should be run as root.');
		}
		$projectname = $input->getArgument('name');
		$url_bitrixsetup = "http://www.1c-bitrix.ru/download/scripts/bitrixsetup.php";
		$url_bitrixrestore = "http://www.1c-bitrix.ru/download/scripts/restore.php";
		$project_path = "/home/" . $settings["PROJECT_USER"] . "/projects";
		$project_path_full = $project_path . "/" . $projectname . "/httpdocs";
		$fs = new Filesystem();
		if ($fs->exists($project_path_full)) {
			throw new \Exception('Project ' . $projectname . ' already exists');
		}
		$fs->mkdir($project_path_full, 0775);
		$fs->copy("/etc/git-sandbox/vm.tar.gz", $project_path_full . "/vm.tar.gz");

		$process = new Process('tar -xf ' . $project_path_full . "/vm.tar.gz -C " . $project_path_full);
		$process->run();
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
		$fs->remove($project_path_full . "/vm.tar.gz");

		$pass = new PasswordGenerator();
		$dbHost = "localhost";
		$dbLogin = $projectname;
		$dbName = $projectname . "_db";
		$dbPasswd = $pass->generate();
		$sqlstring = "CREATE DATABASE " . $dbName . "; GRANT ALL PRIVILEGES ON " . $dbName . ".* TO '" . $dbLogin . "'@'%' IDENTIFIED BY '" . $dbPasswd . "'; GRANT ALL PRIVILEGES ON " . $dbName . ".* TO '" . $dbLogin . "'@'localhost' IDENTIFIED BY '" . $dbPasswd . "';";
		try {
			$dbh = new \PDO("mysql:host=127.0.0.1", $settings["DB_USER"], $settings["DB_PASS"]);
			if (!$dbh->exec($sqlstring)) {
				throw new \Exception(print_r($dbh->errorInfo(), true));
			}

		} catch (PDOException $e) {
			throw new \Exception("DB ERROR: " . $e->getMessage());
		}
		$dbconn = $project_path_full . "/bitrix/php_interface/dbconn.php";
		if (file_exists($dbconn)) {
			include $dbconn;
			$arFile = file($dbconn);
			foreach ($arFile as $line) {
				if (preg_match("#^[ \t]*" . '\$' . "(DB[a-zA-Z]+)#", $line, $regs)) {
					$setNewVal = false;
					$key = $regs[1];
					switch ($key) {
						case 'DBLogin':
							$new_val = $dbLogin;
							$setNewVal = true;
							break;
						case 'DBHost':
							$new_val = $dbHost;
							$setNewVal = true;
							break;
						case 'DBPassword':
							$new_val = $dbPasswd;
							$setNewVal = true;
							break;
						case 'DBName':
							$new_val = $dbName;
							$setNewVal = true;
							break;
						case 'BX_UTF':
							$new_val = $BX_UTF;
							$setNewVal = true;
							break;
					}
					if (isset($new_val) && $$key != $new_val && $setNewVal) {
						$strFile .= '#' . $line .
						'$' . $key . ' = "' . addslashes($new_val) . '";' . "\n\n";
					} else {
						$strFile .= $line;
					}

				} else {
					$strFile .= $line;
				}

			}
			$f = fopen($dbconn, "wb");
			fputs($f, $strFile);
			fclose($f);
		}
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url_bitrixsetup);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		$data = curl_exec($curl);
		curl_close($curl);
		$fs->dumpFile($project_path_full . "/bitrixsetup.php", $data);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url_bitrixrestore);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		$data = curl_exec($curl);
		curl_close($curl);
		$fs->dumpFile($project_path_full . "/restore.php", $data);
		$fs->chown($project_path_full, $settings["PROJECT_USER"], true);
		$fs->chgrp($project_path_full, $settings["PROJECT_GROUP"], true);
		$output->writeln("Done");
	}
}