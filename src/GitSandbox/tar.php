<?php
namespace GitSandbox;

/////////////////////////////////////////////////////////////////////////////////////
//                                                                                 //
//    Эта библиотека содержит класс для работы с архивами форматов tar и tar.gz    //
//    Класс предоставляет инструменты для создания архивов, добавления файлов в    //
//    существующие, распаковки файлов и каталогов,  получения информации о         //
//    содержимом архива.                                                           //
//    версия: 1.1, 2013 г.,                                                        //
//    автор: PunkerPoock, http://hkey.ru, http://slyturtle.ru                      //
//    лицензия GNU Lesser General Public License,                                  //
//    http://www.gnu.org/copyleft/lesser.html                                      //
//                                                                                 //
/////////////////////////////////////////////////////////////////////////////////////

//переменные:
// patchname - путь к рабочему каталогу.
// tarname - путь и имя файла архива.
// tarlevel - уровень сжатия (0 - 9), если доступна библиотека Zlib; доступ:  чтение / модификация.
// tarmode - режим ("tar" или "tar.gz"), определяется автоматически при инициализации; доступ: чтение / модификация.
// error - сообщение об ошибке последней выполненной функции; доступ: чтение.
// tarlist - многомерный массив, формата ("name"=> имя файла, "size"=>размер в байтах, "time"=>дата, "perms"=>права, "dir"=>признак каталога (true | false),
//           "pos"=>начальная позиция в архиве(без заголовка)); доступ: чтение. ВНИМАНИЕ! Заполнение массива происзводится только в процессе выполнеения
//           операций извлечения из архива, или после принудительного вызова функции tarReview().

//функции:
// tarArch('filename') - записать в архив файл filename.
// tarReview() - просмотр архива и заполнение массива tarlist.
// tarUnarch('filename') - извлечь из архива файл, или каталог filename' в каталог, указанный в patchname.
// tarAllunarch() - извлечь все файлы из архива.

class tar {
	var $patchname = "";
	var $tarname = "";
	var $tarmode = "tar";
	var $tarlevel = 9;
	var $tarlist = Array();
	var $tarfile = 0;
	var $error = "";

	function tarReview() {
		if ($this->tarfile == 0) {
			$this->tarOpen("read");
			$mod = true;
		}
		if ($this->tarfile == 0) {
			$this->error = $this->error . "Invalid tarReview() function call.";
			return;
		}
		rewind($this->tarfile); // указатель в начало
		while (!$fend) {
			if ($this->tarmode == "tar") {
				$hn = @fread($this->tarfile, 512);
				$pos = ftell($this->tarfile);
			} else {
				$hn = @gzread($this->tarfile, 512);
				$pos = gztell($this->tarfile);
			}
			$hand = @unpack("a100name/a8perms/a8uid/a8gid/a12size/a12time/a8checksum/a1flag/a100link/a6mgc/a2ver/a32un/a32gn/a8dvh/a8dvm/a155pref/a12oth", $hn);
			$octhand = octdec($hand['size']);
			if ($hand['flag'] == 5) {
				$dir = true;
			} else {
				$dir = false;
			}

			if ($hand['name'] != "") {
				$this->tarlist[] = Array("name" => $hand['name'], "size" => $octhand, "time" => octdec($hand['time']), "perms" => $hand['perms'], "dir" => $dir, "pos" => $pos);
			}

			if ($octhand != "" && $octhand != 0) {
				// считаем расстояние до следующего заголовка
				if ($octhand > 512) {
					$todo = ceil($octhand / 512) * 512;
				} else {
					$todo = 512;
				}

				// операция "пустого" чтения является вынужденной заменой переносу курсора, поскольку функция gzseek работает очень медленно.
				if ($this->tarmode == "tar") {
					@fread($this->tarfile, $todo);
				} else {
					gzread($this->tarfile, $todo);
				}

			}
			if ($this->tarmode == "tar") {
				$fend = feof($this->tarfile);
			} else {
				$fend = gzeof($this->tarfile);
			}

		}
		if ($mod) {
			$this->tarClose();
		}

	}

	function tarUnarch($filename) {
		$this->tarOpen("read");
		if ($this->tarfile == 0 || !file_exists($this->patchname)) {
			$this->error = $this->error . "Invalid tarUnarch() function call.";
			return;
		}
		// ищем данные файла
		for ($i = 0; $i < count($this->tarlist); $i++) {
			$ressearch = $this->tarlist[$i];
			if ($ressearch['name'] == $filename || $ressearch['name'] == $filename . "/") {
				break;
			} else {
				$ressearch = "";
			}

		}
		if ($ressearch == "") {
			$this->error = "No search file " . $filename;
			return;
		} else {
			$this->tarDounarh($ressearch);
		}
		$this->tarClose();
	}

	function tarAllunarch() {
		$this->tarOpen("read");
		if ($this->tarfile == 0 || !file_exists($this->patchname)) {
			$this->error = $this->error . "Invalid tarAllunarch() function call.";
			return;
		}
		for ($i = 0; $i < count($this->tarlist); $i++) {
			$ressearch = $this->tarlist[$i];
			$this->tarDounarh($ressearch);
		}
		$this->tarClose();
	}

	function tarDounarh($ressearch) {
		set_time_limit(30);
		rewind($this->tarfile);
		fseek($this->tarfile, $ressearch['pos'], SEEK_SET);
		if ($ressearch['dir']) {
			if (!file_exists($this->patchname . $ressearch['name'])) {
				if (!@mkdir($this->patchname . $ressearch['name'], '0' . $ressearch['perms'])) {
					$this->error = "Can not create directory " . $this->patchname . $ressearch['name'];
					return;
				} else {
					@touch($this->patchname . $ressearch['name'], $ressearch['time']);
				}

				for ($i = 0; $i < count($this->tarlist); $i++) {
					if (strpos($this->tarlist[$i]['name'], $ressearch['name']) === false) {
						continue;
					} else {
						$this->tarDounarh($this->tarlist[$i]);
					}

				}
			}
		} else {
			$farr = explode("/", $ressearch['name']);
			if (count($farr) > 1) {
				array_pop($farr);
				$dirpatch = '';
				for ($i = 0; $i < count($farr); $i++) {
					@mkdir($this->patchname . $dirpatch . $farr[$i], '0' . $ressearch['perms']);
					@touch($this->patchname . $dirpatch . $farr[$i], $ressearch['time']);
					$dirpatch = $dirpatch . '/' . $farr[$i] . '/';
				}
			}
			$resopen = @fopen($this->patchname . $ressearch['name'], "wb");
			if (!$resopen) {
				$this->error = "Can not create file " . $this->patchname . $ressearch['name'];
				return;
			}
			if ($ressearch['size'] < 51200) {
				if ($this->tarmode == "tar") {
					$fr = @fread($this->tarfile, $ressearch['size']);
				} else {
					$fr = @gzread($this->tarfile, $ressearch['size']);
				}

				@fputs($resopen, $fr);
			} else {
				$j = floor($ressearch['size'] / 51200);
				for ($i = 0; $i < $j; $i++) {
					if ($this->tarmode == "tar") {
						$fr = @fread($this->tarfile, 51200);
					} else {
						$fr = @gzread($this->tarfile, 51200);
					}

					@fputs($resopen, $fr);
				}
				$i = $ressearch['size'] - $j * 51200;
				if ($this->tarmode == "tar") {
					$fr = @fread($this->tarfile, $i);
				} else {
					$fr = @gzread($this->tarfile, $i);
				}

				@fputs($resopen, $fr);
			}
			@fclose($resopen);
			@touch($this->patchname . $ressearch['name'], $ressearch['time']);
		}
	}

	function tarArch($shortname) {
		$this->tarOpen("write");
		$filename = $this->patchname . $shortname;
		if ($this->tarfile == 0 || !file_exists($filename)) {
			$this->error = $this->error . "Invalid tarArch() function call.";
			return;
		}
		set_time_limit(30);
		if (is_dir($filename) && strrpos($shortname, "/") < (strlen($shortname) - 1)) {
			$shortname = $shortname . "/";
		}

		// устанавливаем файловый указатель в конец файла архива
		fseek($this->tarfile, 0, SEEK_END);

		// пишем заголовок
		$this->tarHeader($filename, $shortname);
		if ($this->error != "") {
			return;
		}

		if (is_file($filename)) {
			// пишем файл
			$infile = fopen($filename, rb);
			if (!$infile) {
				$this->error = "No open original file.";
				return;
			}
			$j = ceil(filesize($filename) / 51200);
			for ($i = 0; $i < $j; $i++) {
				$fr = @fread($infile, 51200);
				if ($this->tarmode == "tar") {
					@fputs($this->tarfile, $fr);
				} else {
					@gzputs($this->tarfile, $fr);
				}

			}
			fclose($infile);
			// пишем концовку
			$ffs = filesize($filename);
			if ($ffs > 512) {
				$tolast = 512 - fmod($ffs, 512);
			} else {
				$tolast = 512 - $ffs;
			}

			if ($tolast != 512 && $tolast != 0) {
				$fdata = pack("a" . $tolast, "");
				if ($this->tarmode == "tar") {
					$resopen = @fputs($this->tarfile, $fdata);
				} else {
					$resopen = @gzputs($this->tarfile, $fdata);
				}

				if (!$resopen) {
					$this->error = "No save footer in file arhive.";
				}

			}
		} elseif (is_dir($filename)) {
			// если каталог - циклично обрабатываем его содержимое
			$sdir = @opendir($filename);
			while ($sfile = @readdir($sdir)) {
				if ($sfile == "." || $sfile == "..") {
					continue;
				} else {
					$this->tarArch($shortname . $sfile);
				}

			}
		}
		$this->tarClose();
	}

	function tarHeader($filename, $shortname) {
		if ($this->tarfile == 0 || !file_exists($filename)) {
			$this->error = "Invalid tarHeader() function call.";
			return;
		}
		// определяем и переводим в бинарный формат параметры файла
		$info = stat($filename);
		$uid = sprintf("%6s ", DecOct($info[4]));
		$gid = sprintf("%6s ", DecOct($info[5]));
		$perms = sprintf("%6s ", DecOct(fileperms($filename)));
		$mtime = sprintf("%11s ", DecOct(filemtime($filename)));

		// размер
		if (is_dir($filename)) {
			$typeflag = "5";
			$size = 0;
		} else {
			$typeflag = "0";
			$size = filesize($filename);
		}
		$size = sprintf("%11u ", DecOct($size));
		$magic = sprintf("%5s ", "ustar");
		$version = $linkname = $uname = $gname = $devmajor = $devminor = $prefix = "";
		$binary_data_first = pack("a100a8a8a8a12a12", $shortname, $perms, $uid, $gid, $size, $mtime);
		$binary_data_last = pack("a1a100a6a2a32a32a8a8a155a12", $typeflag, $linkname, $magic, $version, $uname, $gname, $devmajor, $devminor, $prefix, "");

		// считаем контрольную сумму файла
		$checksum = 0;
		for ($i = 0; $i < 148; $i++) {
			$checksum += ord(substr($binary_data_first, $i, 1));
		}
		for ($i = 148; $i < 156; $i++) {
			$checksum += ord(' ');
		}
		for ($i = 156, $j = 0; $i < 512; $i++, $j++) {
			$checksum += ord(substr($binary_data_last, $j, 1));
		}
		$checksum = sprintf("%6s ", DecOct($checksum));
		$binary_data = pack("a8", $checksum);

		// пишем заголовок в архив
		if ($this->tarmode == "tar") {
			@fputs($this->tarfile, $binary_data_first, 148);
			@fputs($this->tarfile, $binary_data, 8);
			@fputs($this->tarfile, $binary_data_last, 356);
		} else {
			@gzputs($this->tarfile, $binary_data_first, 148);
			@gzputs($this->tarfile, $binary_data, 8);
			@gzputs($this->tarfile, $binary_data_last, 356);
		}
	}

	function tarOpen($mod) {
		if (strrpos($this->patchname, "/") < (strlen($this->patchname) - 1)) {
			$this->patchname = $this->patchname . "/";
		}

		if (strrpos($this->tarname, "/") === false) {
			$this->tarname = $this->patchname . $this->tarname;
		}

		$ext = end(explode(".", strtolower($this->tarname))); // узнаем тип файла
		if (($ext == "gz" || $ext == "tgz") && $this->tarmode == "tar") {
			$this->error = "Server does not support compression. ";
			return;
		}
		if ($ext == "gz") {
			$this->tarmode = "tar.gz";
		} else {
			$this->tarmode = $ext;
		}

		if ($this->tarmode == "tar") {
			$resopen = @fopen($this->tarname, 'a+b');
		} else {
			if (!$mod || $mod == "read") {
				$resopen = @gzopen($this->tarname, 'rb');
			} else {
				$resopen = @gzopen($this->tarname, 'ab' . $this->tarlevel);
			}

		}
		if (!$resopen) {
			$this->error = "No open file arhive. ";
		} else {
			$this->tarfile = $resopen;
		}

	}

	function tarClose() {
		if ($this->tarmode == "tar") {
			@fclose($this->tarfile);
		} else {
			@gzclose($this->tarfile);
		}

		$this->tarfile = 0;
	}

	function tar() {
		if (defined('FORCE_GZIP')) {
			$this->tarmode = "tar.gz";
		}

		$this->error = "";
	}
}
?>
