<?php

namespace GitSandbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VirtualhostAddCommand extends Command {
	protected function configure() {
		$this
			->setName('sandbox:virtualhost_add')
			->setDescription('Add virtualhost')
			->addArgument(
				'type',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'project',
				InputArgument::REQUIRED,
				''
			)
			->addArgument(
				'user_encoding',
				InputArgument::OPTIONAL,
				''
			)
		;
	}
	protected function interact(InputInterface $input, OutputInterface $output) {
		if (!$input->getArgument('type')) {
			$type = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please choose type of Vhost (project|sandbox): ',
				function ($type) {
					if (empty($type)) {
						throw new \Exception('You must choose a type');
					}
					return $type;
				}
			);
			$input->setArgument('type', $type);
		}
		if (!$input->getArgument('project')) {
			$project = $this->getHelper('dialog')->askAndValidate(
				$output,
				'Please enter a project name: ',
				function ($project) {
					if (empty($project)) {
						throw new \Exception('Project name can not be empty');
					}
					return $project;
				}
			);
			$input->setArgument('project', $project);
		}
		$tmp_type = $input->getArgument('type');
		if($tmp_type == "sandbox"){
			if (!$input->getArgument('user_encoding')) {
				$user_encoding = $this->getHelper('dialog')->askAndValidate(
					$output,
					'Please enter a user name: ',
					function ($user_encoding) {
						if (empty($user_encoding)) {
							throw new \Exception('User name can not be empty for sandbox');
						}
						return $user_encoding;
					}
				);
				$input->setArgument('user_encoding', $user_encoding);
			}
		}else{
			if (!$input->getArgument('user_encoding')) {
				$user_encoding = $this->getHelper('dialog')->askAndValidate(
					$output,
					'Please choose encoding (UTF-8|windows-1251): ',
					function ($user_encoding) {
						if (empty($user_encoding)) {
							$user_encoding = "UTF-8";
						}
						return $user_encoding;
					}
				);
				$input->setArgument('user_encoding', $user_encoding);
			}
		}
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		require "/etc/git-sandbox/config.php";
		if (posix_geteuid() !== 0) {
			throw new \Exception('This script should be run as root.');
		}
		$type = $input->getArgument('type');
		$projectname = $input->getArgument('project');
		$project_path = "/home/" . $settings["PROJECT_USER"] . "/projects";
		$project_path_full = $project_path . "/" . $projectname . "/httpdocs";
		$fs = new Filesystem();
		if (!$fs->exists($project_path_full)) {
			throw new \Exception('Project ' . $projectname . ' not exists');
		}
		if ($type == "sandbox") {
			$user = $input->getArgument('user_encoding');
			$handle = @fopen("/etc/passwd", "r");
			$result = 0;
			if ($handle) {
				while (($buffer = fgets($handle, 4096)) !== false) {
					if (strpos($buffer, $user . ':') === 0) {
						$result = 1;
					}
				}
				if ($result == 0) {
					throw new \Exception('Can\'t find user ' . $user . '.');
				}
				if (!feof($handle)) {
					throw new \Exception("Error: unexpected fgets() fail");
				}
				fclose($handle);
			}
		}
		$project_vhost_name = $projectname . "." . $settings["DOMAIN"];
		$vhost_name=$project_vhost_name;
		$vhost_root = "/home/" . $settings["PROJECT_USER"] . "/projects/" . $projectname . "/httpdocs";
		if ($type == "sandbox") {
			$vhost_name = $user . "." . $project_vhost_name;
			$vhost_root = "/home/" . $user . "/www/" . $projectname . "/httpdocs";
		}
		$phpsessionsavepath = '/tmp/php_sessions/' . $vhost_name;
		$phpuploadtmpdir = '/tmp/php_upload/' . $vhost_name;

		$fs->mkdir($phpsessionsavepath, 0770);
		$fs->mkdir($phpuploadtmpdir, 0770);
		if ($user) {
			$tmp_user = $user;
		} else {
			$tmp_user = $settings["PROJECT_USER"];
		}
		$fs->chown($phpsessionsavepath, $tmp_user);
		$fs->chgrp($phpuploadtmpdir, $settings["PROJECT_GROUP"]);
		$fs->chown($phpsessionsavepath, $tmp_user);
		$fs->chgrp($phpuploadtmpdir, $settings["PROJECT_GROUP"]);
		$nginxtemplatefile = $settings["TEMPLATES_DIR"] . "/" . $type . "/nginx/site_template.conf";
		$httpdtemplatefile = $settings["TEMPLATES_DIR"] . "/" . $type . "/httpd/site_template.conf";
		$nginxconffile = $settings["NGINX_CONF_DIR"] . "/sandbox/site_avaliable/" . $vhost_name . ".conf";
		$httpdconffile = $settings["HTTPD_CONF_DIR"] . "/sandbox/conf/" . $vhost_name . ".conf";
		$projecthttpdconffile = $settings["HTTPD_CONF_DIR"] . "/sandbox/conf/" . $project_vhost_name . ".conf";
		if ($type == "sandbox") {
			$process = new Process('grep "mbstring.func_overload[ \t]*0" '.$projecthttpdconffile);
			$process->run();
			if (!$process->isSuccessful()) {
				$encoding = $settings["ENCODING_SITE"];
			}else{
				$exec_output=trim($process->getOutput(), " \t");
			}
			if($exec_output){
				if(strpos($exec_output, "#")===0){
					$encoding = "UTF-8";
				}else{
					$encoding = "windows-1251";
				}
			}else{
				$encoding = "UTF-8";
			}
		}else{
			$encoding = $input->getArgument('user_encoding');
		}
		if ($encoding == "UTF-8") {
			$character = "DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci";
			$charset = "utf-8";
			$phpoverload = " ";
			$phpinternal = " ";
		} elseif ($encoding == "windows-1251") {
			$character = "DEFAULT CHARACTER SET cp1251 COLLATE cp1251_general_ci";
			$charset = "windows-1251";
			$phpoverload = "php_admin_value mbstring.func_overload 0";
			$phpinternal = "php_admin_value mbstring.internal_encoding latin";
		}
		$nginxtemplate = file_get_contents($nginxtemplatefile);
		$tags = array("#SERVER_NAME#", "#SERVER_DIR#", "#SERVER_ENCODING#");
		$set = array($vhost_name, $vhost_root, $charset);
		$nginxtemplate = str_replace($tags, $set, $nginxtemplate);
		$fs->dumpFile($nginxconffile, $nginxtemplate);
		unset($nginxtemplate);
		$fs->symlink($nginxconffile, $settings["NGINX_CONF_DIR"] . "/sandbox/site_enabled/" . $vhost_name . ".conf");
		$httpdtemplate = file_get_contents($httpdtemplatefile);
		$tags = array("#SERVER_NAME#", "#SERVER_DIR#", "#PHP_OVERLOAD#", "#PHP_INTERNAL#", "#PHP_SESSIONS#", "#PHP_UPLOAD#");
		$set = array($vhost_name, $vhost_root, $phpoverload, $phpinternal, "php_admin_value session.save_path " . $phpsessionsavepath, "php_admin_value upload_tmp_dir " . $phpuploadtmpdir);
		if ($type == "sandbox") {
			$tags[] = "#USER_GROUP#";
			$set[] = $user . " " . $settings["PROJECT_GROUP"];
		}
		$httpdtemplate = str_replace($tags, $set, $httpdtemplate);
		$fs->dumpFile($httpdconffile, $httpdtemplate);
		unset($httpdtemplate);
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