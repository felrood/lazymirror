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


// Is it a package or db?
if(hasExtension($request, array('.tar.xz', '.tar.xz.sig', '.db', '.db.sig'))) {

	$cache_path = realpath($pkg_cache.$request);

	if($cache_path !== false) {
		if(substr($cache_path, 0, strlen($pkg_cache)) !== $pkg_cache) {
			// file inclusion attack, bail out
			http_response_code(404);
			die("Inclusion attack");
		}

		if(hasExtension($request, array('.db', '.db.sig'))) {
			$mtime = filemtime($cache_path);
			
			// Older than an hour, delete
			if($mtime < (time() - 3600)) {
				unlink($cache_path);
			}
		} else {
			// its in cache!
			readfile($cache_path);
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
	
	$fp = fopen($upstream_url, "r");

	$tmp_file = $pkg_cache."tmp.".getmypid();
	$cache_dest = $pkg_cache.$request;

	$c_fp = fopen($tmp_file, "w+");

	if($fp === false) {
		http_response_code(404);
		die("Upstream is not there");
	}

	while(!feof($fp)) {
		$chunk = fread($fp, 8192);
		echo $chunk;
		flush();

		fwrite($c_fp, $chunk);
	}

	fclose($fp);
	fclose($c_fp);

	if(!is_dir(dirname($cache_dest))) {
		mkdir(dirname($cache_dest), 0755, true);
	}

	rename($tmp_file, $cache_dest);

	// if rename fails, delete tmp file
	if(file_exists($tmp_file)) {
		unlink($tmp_file);
	}

	return;
}

// 404 everything non-pkg or db
http_response_code(404);
die('File not Found');