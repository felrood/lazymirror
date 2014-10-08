<?php
// Configurable stuff

// Upstream urls
$upstream = array(
	'core' => 'http://mirror.nl.leaseweb.net/archlinux/core/',
	'extra' => 'http://mirror.nl.leaseweb.net/archlinux/extra/',
	'community' => 'http://mirror.nl.leaseweb.net/archlinux/community/',
	'multilib' => 'http://mirror.nl.leaseweb.net/archlinux/multilib/',
	'archlinuxfr' =>'http://repo.archlinux.fr/',
	'infinality-bundle' => 'http://bohoomil.com/repo/',
);

$db_timeout = 1*3600; // Cache .db and .db.sig for 1 hour

// Caching directory, must be absolute path with trailing slash
$pkg_cache = '/srv/pkg/cache/';

// END Config


function hasExtension($url, $exts) {

	foreach($exts as $e) {
		if(substr($url, -strlen($e)) === $e) return true;
	}

	return false;
}

$request = $_SERVER['REQUEST_URI'];

// Is it a package?
if(hasExtension($request, array('.tar.xz', '.tar.xz.sig', '.db', '.db.sig'))) {
	
	$is_db = false;
	if(hasExtension($request, array('.db', '.db.sig'))) {
		$is_db = true;
	}

	if($is_db) {
		$cache_path = $pkg_cache.'dbcache'.$request;
	} else {
		$cache_path = $pkg_cache.$request;
	}

	$real_path = realpath($cache_path);

	if($real_path !== false) {
		if(substr($real_path, 0, strlen($pkg_cache)) !== $pkg_cache) {
			// file inclusion attack, bail out
			http_response_code(404);
			die("Inclusion attack");
		}

		$mtime = filemtime($real_path);
			
		// Database older than cachedtime, delete
		if($is_db && $mtime < (time() - $db_timeout)) {
			unlink($real_path);
		} else {	
			if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {

				$last = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);

				if($last >= $mtime) {
					http_response_code(304);
					return;
				}
			}

			// its in cache!
			$size = filesize($real_path);
			header("Content-Length: ".$size);
			readfile($real_path);
			return;
		}
	}

	// figure out upstream mirror
	$repo = substr($request, 1, strpos($request, '/', 1)-1);
	$pkg = substr($request, strpos($request, '/', 1)+1);

	// Unknown repo..
	if(!array_key_exists($repo, $upstream)) {
		http_response_code(404);
		die("Unknown repo");
	}

	$upstream_url = $upstream[$repo].$pkg;

	// supress errors	
	$fp = @fopen($upstream_url, "r");
	
	if($fp === false) {
		http_response_code(404);
		die("Upstream is not there");
	}

	// The $http_response_header appears magically after fopen()
	$len = 0;
	foreach($http_response_header as $line) {
		if(substr($line, 0, strlen('Content-Length')) == 'Content-Length') {
			$len = substr($line, strlen('Content-Length: '));
		}
	}

	if($len > 0) {
		header('Content-Length: '.$len);
	}
	
	$tmp_file = $pkg_cache."tmp.".getmypid();
	$c_fp = fopen($tmp_file, "w+");

	while(!feof($fp)) {
		$chunk = fread($fp, 8192);
		echo $chunk;
		flush();

		fwrite($c_fp, $chunk);
	}

	fclose($fp);
	fclose($c_fp);

	if(!is_dir(dirname($cache_path))) {
		mkdir(dirname($cache_path), 0755, true);
	}

	rename($tmp_file, $cache_path);
	
	if(file_exists($tmp_file)) {
		unlink($tmp_file);
	}

	return;
}

// 404 everything non-pkg or db
http_response_code(404);
die('File not Found');

