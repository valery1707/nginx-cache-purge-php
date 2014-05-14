<?php

$cache_root = '/var/cache/nginx/all_realty';

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
	foreach ($files as $file) {
		# Открываем файл на чтение
		$fp = fopen($file, 'r');
		if ($fp) {
			# Пропускаем заголовок и слово 'KEY: '
			fseek($fp, 30);
			# Считываем значение ключа
			$key = fgets($fp);
			# Закрываем файл
			fclose($fp);
			$result[$file] = trim($key);
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
	$filter = '/' . preg_quote($filter, '/') . '/';
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

$filter = $_GET['filter'];
$files = find_cache_files($cache_root);
$files = filter_files_by_key($files, $filter);
purge_cache($files);

$response = array(
	'success' => count($files) > 0,
	'filter' => $filter,
	'count' => count($files),
	'files' => $files,
);
echo json_encode($response);
echo "\r\n";

?>
