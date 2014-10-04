# Lazymirror
**An efficient mirror for Archlinux packages**

(small warning: not all functionality is yet implemented, see TODO)

It's halfway between a proxy and a mirror. Packages that are not in cache are fetched from an upstream mirror and stored in the cache, older versions of the package are deleted from the cache. This means you only cache the packages you actually use.
Because it is tailored to Achlinux packages, and not a generic solution, it's more efficient and simple.

License: GPL2.0

## How to use

Setup a webserver on a local domain (pkgcache.lan for example) and create a rewrite rule like ``all request -> index.php``. Tested with Lighttpd, nginx and apache should work too.

Example lighttpd config:

```
server.port			= 80
server.username		= "http"
server.groupname	= "http"
server.document-root	= "/srv/pkg/lazymirror/"
server.errorlog		= "/var/log/lighttpd/error.log"
dir-listing.activate	= "disable"
index-file.names = (
	"index.php"
)

mimetype.assign		= (
	"" => "application/octet-stream"
)

server.modules = (
	"mod_fastcgi"
	"mod_rewrite"
)

url.rewrite-once = ("" => "index.php")

fastcgi.server = (
    ".php" => (
      "localhost" => ( 
        "bin-path" => "/usr/bin/php-cgi",
        "socket" => "/var/run/lighttpd/php-fastcgi.sock",
        "max-procs" => 2,
        "bin-environment" => (
          "PHP_FCGI_CHILDREN" => "4",
        ),
        "broken-scriptfilename" => "enable"
      ))
)
```

Edit mirrorlist to this:
```
Server = http://pkgcache.lan/$repo/os/$arch
```

If you have other repositories besides the usual ones, you can leave them alone or update the ``Server = `` directive to reflect the cache. For example:

```
[infinality-bundle]
Server = http://pkgcache.lan/infinality-bundle/$arch
```

Make sure the ``$upstream`` array knows about the repo!

## TODO

- make it a 404 handler (more efficient and adds support for custom repositories)
- use stream_socket_client, to support 'Content-Length' header
- parse package name and remove older packages
- make db timout configurable
