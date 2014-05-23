<?php

$cache_root = '/var/cache/nginx/all_realty';

/**
 * @link http://stackoverflow.com/a/10473026/1263442
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
function startsWith($haystack, $needle) {
	return $needle === "" || strpos($haystack, $needle) === 0;
}

/**
 * @link http://stackoverflow.com/a/10473026/1263442
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
function endsWith($haystack, $needle) {
	return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

/**
 * Поиск всех вложенных директорий в указанной директории
 * @param string $root Корневая директория для поиска
 * @return array Список директорий
 */
function find_dirs($root) {
	$result = array($root);
	$subs = glob($root . '/' . '*', GLOB_ONLYDIR);
	foreach ($subs as $dir) {
		$result = array_merge($result, find_dirs($dir));
	}
	return $result;
}

/**
 * Поиск всех файлов в указанной директории
 * @param string $root Корневая директория для поиска
 * @return array Список файлов с полными путями
 */
function find_files($root) {
	$result = array();
	$dirs = find_dirs($root);
	sort($dirs);
	foreach ($dirs as $dir) {
		$files = glob($dir . '/' . '*');
		foreach ($files as $file) {
			if (is_file($file) && is_readable($file) && is_writable($file)) {
				$result[] = $file;
			}
		}
	}
	sort($result);
	return $result;
}

/**
 * Загрузка списка закешированных файлов
 * @param string $cache_root
 * @return array (полное имя файла) -> (ключ кеша)
 */
function find_cache_files($cache_root) {
	$result = array();
	$files = find_files($cache_root);
	$prefix = 'KEY: ';
	$prefix_len = strlen($prefix);
	foreach ($files as $file) {
		# Открываем файл на чтение
		$fp = fopen($file, 'r');
		if ($fp) {
			$key = '';
			while (!startsWith($key, $prefix)) {
				# Считываем значение ключа
				$key = fgets($fp);
			}
			# Закрываем файл
			fclose($fp);
			if (startsWith($key, $prefix)) {
				$key = trim(substr($key, $prefix_len));
			} else {
				$key = null;
			}
			$result[$file] = $key;
		}
	}
	return $result;
}

/**
 * Фильтрация файлов по ключу используя переданную регулярку
 * @param array $files (полное имя файла) -> (ключ кеша)
 * @param string $filter регулярка для фильтрации
 * @return array (полное имя файла) -> (ключ кеша)
 */
function filter_files_by_key($files, $filter) {
	$filter = '/' . str_replace('/', '\/', $filter) . '/';
	$result = array();
	foreach ($files as $file => $key) {
		if (preg_match($filter, $key)) {
			$result[$file] = $key;
		}
	}
	return $result;
}

/**
 * Удаление файлов из кеша
 * @param array $files (полное имя файла) -> (ключ кеша)
 */
function purge_cache($files) {
	foreach ($files as $file => $key) {
		unlink($file);
	}
}

/**
 * Преобразование списка файлов в формат удобный для автоматизированной обработки ответа
 * @param array $files (полное имя файла) -> (ключ кеша)
 */
function map_files_for_result($files) {
	$result = array();
	foreach ($files as $file => $key) {
		$result[] = array('file' => $file, 'url' => $key);
	}
	return $result;
}

$filter = $_GET['filter'];
$mode_readonly = isset($_GET['readonly']);

$files = find_cache_files($cache_root);
$files = filter_files_by_key($files, $filter);
if (!$mode_readonly) {
	purge_cache($files);
}

$response = array(
	'success' => count($files) > 0,
	'filter' => $filter,
	'count' => count($files),
	'files' => map_files_for_result($files),
	'mode_readonly' => $mode_readonly,
);
echo json_encode($response);
echo "\r\n";

?>
