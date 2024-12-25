
!!! Andromeda is in early development - do not use in production! !!!
* [Overview](#overview)
* [General Usage](#general-usage)
* [Installation](#installation)
* [License](#license)
* [Development](wiki/Development.md)


# Overview

Andromeda is a self-hostable cloud file storage solution.  This repository contains the backend server.  It is a pure-PHP REST-ish transactional API divided into a reusable core framework and component "apps" which implement the actual API calls.

### Core Framework
The framework is independent of the apps built for Andromeda and can be used for other projects.  The core provides safe input/output handling and formatting, error handling and logging, and an object-oriented transactional database abstraction.  The related "core" app is used for server configuration, enabling/disabling apps, and other core-specific tasks.  

The framework can log accesses and errors to the database, or to log files if a data directory is configured.  It also allows setting up outgoing email configurations that may be used by apps.

### Primary Apps
In pursuit of being a cloud storage solution, Andromeda includes the "accounts" and "files" apps.  Accounts implements the account management and authentication/session-management tasks.  Files app provides the filesystem interface and related features.  The files app requires the accounts app.

As the framework itself is app-agnostic, the commands and documentation are generally written in an app-agnostic way (not specific to accounts or files).  See the [wiki](wiki/Home.md) for more app-specific information.


# General Usage

Andromeda and *all* its API calls can be run either through an HTTP webserver, or via the command line interface (CLI).  The API is thus a bit of a REST-ish hybrid.  All calls run single "actions" and are run as transactions.  Any errors encountered will result in a rolling back of the entire request. 

Run the API from the CLI with no arguments (either `./andromeda-server` or `php index.php`) to view the general CLI usage.  The general usage is `./andromeda-server myapp myaction` where myapp and myaction are the app and action to run.  Use `./andromeda-server core usage` to view the list of all available API calls.  Action-specific parameters use the traditional `--name value` or `--name=value` syntax and come at the end of the command.  Commands showing `[--name value]` with brackets indicates an optional parameter. Commands with a repeated `(action)` line show a subset of usage for the command based on a specific case of the main usage.  Note that app and action are implicit and do not require --app or --action.  Parameters can be specified as a flag with no value, in which case they are implicitly mapped to `true` for booleans and `null` for all other types.  

Commands mentioned in the readme or wiki will omit the `php index.php` or `./andromeda-server` and will only specify the `app action` to run, e.g. `core usage`.  The `core usage` output that documents all API calls is also tracked as USAGE.txt in the [server API docs](https://github.com/irondrive/andromeda-server-docs) repository.

The installer (see below) uses a different entry point (`php install.php` or `./andromeda-install`) with the same general usage format.  

### Common Exceptions
`SAFEPARAM_*` related exceptions indicate a problem with the input provided.  For example `SAFEPARAM_KEY_MISSING` indicates that a required parameter was not given.  `SAFEPARAM_INVALID_VALUE` indicates that the parameter did not pass input validation (e.g. giving a string for a numeric input).  `UNKNOWN_APP` and `UNKNOWN_ACTION` indicate that the requested app or action are invalid.

### Parameter Types
All input parameters are strictly validated against their expected types.  Most that you will see in `core usage` are self-explanatory (`bool`, `int`, etc.).  Less-obvious types include `raw` (no validation), `randstr` (an andromeda-generated random value), `name` (a label or human name), `text` (escapes HTML tags with `FILTER_SANITIZE_SPECIAL_CHARS`), and  `id` (a reference to an object by its ID).  Andromeda code is object-oriented and uses unique IDs to refer to database objects.  A parameter type that begins with ? (e.g. `?int`) indicates that the parameter can be null (e.g. `... --myparam null`).  This can have a different meaning than just omitting the parameter.

Parameters can also be given that are arrays or objects.  On the CLI, this is done using JSON.  E.g. `--myarray "[5,10,15]"` or `--myobj "{test:5}"`.  Via HTTP it would look like `?myarr[0]=test&myarr[1]=test` or `?myobj[key]=val`.

### Global CLI Flags
CLI-specific global flags must come *before* the app/action.
* `--outprop a.b.c` to select a sub-property in the output, e.g. `--outprop db.info`
* `--outmode json`/`--outmode printr` use JSON or PHP printr() for output
* `--dryrun` rollback the transaction at the end of the request
* `--dbconf path/myconf.php` use the provided database configuration file
* `--debug none|errors|details|sensitive` change the debug output level (default server errors only)
* `--metrics none|basic|extended` will show performance metrics, SQL queries executed, etc.

Note that if an invalid --outprop is given, you will get an error output but the requested action will still be run and committed!

### Environment Variables
To ease command line usage for commands that may involve repeated parameters (e.g. a session key), environment variables prefixed with `andromeda_` can be set that will be included in a request.  For example, `export andromeda_mykey=myvalue` is equivalent to adding `--mykey=myvalue` to all future commands.

### Response Format
Every request will return an object with `ok` and `code`.  `ok` denotes whether the transaction was successful, and `code` returns the corresponding HTTP error code. If the request was successful (200), the `appdata` field will have the output from the app-action.  If there was an error, `code` will have the equivalent HTTP code and `message` will have a string describing the error.  For cleaner output, when using CLI the default `--outmode` is `plain`, meaning only the `appdata` or `message` field will be printed (unless debug/metrics are to be output).

### Alternative Input
Certain parameters (password, etc.) are better when not direclty on the command line.  Using `!` at the end of a parameter name (e.g. `--myparam!`) will read the parameter value interactively from the console (or from STDIN, though the order is not specified).  This is a good way to input things like passwords.  Unfortunately PHP does not support silent input, so all input will be echoed to the console.  A parameter can also source its content from a file using `--myparam@ path`.  Or, you can use environment variables as above.

### File Inputs
Certain app actions require that they are passed a file stream as input.  With HTTP they should be a regular `multipart/form-data` file upload. See PHP's $_FILES.  With CLI they can be specified as a path with `--myfile% path` or they can be read directly from STDIN (one file only) with `--myfile-`.  The inputted file's name can optionally be changed as well, e.g. `--myfile% path newname` or `--myfile- myname`.  The name for stdin files defaults to "data". App actions that require file input will specify `%` or `-` in their usage text.

### HTTP Differences
Parameters can be placed in the URL query string, the POST body as `application/x-www-form-urlencoded` or similar (see PHP $_POST), or cookies.  The only restrictions are app and action must be URL variables, and any parameter starting with `auth_` or `password` cannot be in the URL.  Andromeda does not make use of the different HTTP methods, headers, or endpoints.  Only GET or POST are allowed.  The output format is always JSON.  The actual HTTP response code is only used in plain/none output modes (e.g. downloading a file).  Example `/index.php?_app=myapp&_act=myaction&myparam=myval`.


# Installation

For development, simply clone the repo and use `composer install` to download and install the required PHP dependencies.  By default this includes development-specific dependencies, which may require additional PHP extensions beyond what is listed below.  For production, download a release tarball with dependencies included or use the `tools/mkrelease` script.  **DO NOT** use the git repo directly in production as it contains development-only materials (testutil app, etc.).  Database installation is done with the `./andromeda-install` entry point.

### Basic Requirements
Andromeda requires **64-bit** PHP >= 8.1 with libargon2.  Required PHP extensions are mbstring, PDO, sodium.  The files app S3 backend also requires simplexml and dom.  Supported databases are MySQL/mariadb, PostgreSQL and SQLite. These require their corresponding extensions (mysqli, pdo_mysql, pgsql, pdo_pgsql, sqlite3, pdo_sqlite). SQLite uses [WAL mode](https://www.sqlite.org/wal.html) which imposes some requirements on filesystem capabilities.  Ubuntu 22.04 is currently used as the version baseline, so the minimum database versions are MySQL 8.0, MariaDB 10.6, PostgreSQL 14.

Andromeda does not use any OS or webserver-specific functions and should work on any platform where PHP runs.  No specific PHP or webserver configuration is required.  Windows works but is supported only on a "best-effort" basis.  32-bit PHP is NOT supported or tested and is known to not work with files > 2GB.  The following platforms are officially supported and tested regularly:
* Ubuntu 22.04 amd64 (PHP 8.1) + Apache
* Ubuntu 24.10 amd64 (PHP 8.3) + Nginx

### Additional php.ini Config
It is recommended to set `max_execution_time` and `memory_limit` to -1 to avoid issues with requests not completing.  The `post_max_size` and `upload_max_filsize` settings can have any value (and clients must handle it) but small values will reduce upload performance greatly.  Nginx also imposes its own limit on upload size, 1M by default.  All upload filesize limits should be increased to something large (like 100M) to optimize performance.

Andromeda has its own debug logging system and captures its own exceptions/backtraces and does not use PHP debug visibility settings.  Error reporting is overriden to E_ALL.

### Security Considerations
* It is strongly recommended to only make the entry points (`index.php`, `install.php`) directly web-accessible.  This means andromeda-server should be installed outside `/var/www` (e.g. `/usr/local/lib`) and then symlinks to the entry points (or copies, if symlinks aren't allowed) can be put in `/var/www`.  The entry points by default look for the `Andromeda` folder (which must be next to `vendor`) in `__DIR__/`, `/usr/local/lib/andromda-server`, and `/usr/lib/andromeda-server`.  In case the Andromeda/vendor folders must exist in `/var/www`, .htaccess files are included to restrict access with Apache 2.4, but manual configuration is needed for nginx or other servers.  Having these directories web-accessible (especially vendor) [may create vulnerabilities](https://thephp.cc/articles/phpunit-a-security-risk).
* It is strongly recommended that the web-server cannot write to any of the andromeda-server files or directories.
* It is mildly *better* to only do install/upgrades via CLI if possible, and not have `install.php` available on the web.  Install/upgrade commands are not authenticated and are allowed by any user on any interface when required.  Or, you can disallow external access to the web server when beginning an install or upgrade (also good practice).

#### Database Config
The `./andromeda-install core dbconf` command is used to create and test database configuration. The `--outfile` option controls where to write the configuration file.  Using `--outfile` as a flag will store the configuration file (`DBConfig.php`) in the `Andromeda/` folder.  Using `--outfile -` will return the config string as output.  Otherwise, using `--outfile fspath` will store the config at the specified path.  When Andromeda runs it checks its `./Andromeda/`, `~/.config/andromeda-server/`, `/usr/local/etc/andromeda-server/` and `/etc/andromeda-server/` in that order for `DBConfig.php`.  Remember that DBConfig.php will contain database credentials and should be protected from other users appropriately.

For example to create and use an SQLite database and save the config file in the default location, run `./andromeda-install core dbconf --driver sqlite --dbpath mydata.s3db --outfile`.  SQLite is only recommended for testing or tiny deployments as it does not support concurrent access.

### Example CLI Install Steps
Use the `./andromeda-install core usage` command to see options for all available install commands.  `./andromeda-install core scanapps` will list apps that exist for install.

1. Run `./andromeda-install core dbconf --outfile` to generate and write database configuration to the default location.
2. Run `./andromeda-install core setupall` to install database tables for and enable all apps that exist. It returns a list of all installed apps mapped their specific install output.  The `core setupall` command can take any parameter needed by an individual app.  Apps can also be installed and enabled separately, e.g. `./andromeda-install accounts install; ./andromeda-server core enableapp --appname accounts`.  Apps can have database dependencies that may dictate installation order.

Note that MySQL does not support transactions for queries that modify table structure.  If an install/upgrade fails midway, the database may be left in an inconsistent state.

### Upgrading
When the code being run does not match the version stored in an app's database, running the app's upgrade command is required, e.g. `./andromeda-install core upgrade` or `./andromeda-install accounts upgrade`.  You can upgrade the core and all apps at once with `core upgradeall`.  It returns a list of all upgraded apps mapped their specific upgrade output.  Apps can have database dependencies that may dictate upgrade order.  The `core upgradeall` command can take any parameter needed by an individual app. 

#### Full SQLite Web Server Install Example with Proper Directories
This is just a reference, don't run directly.  Assuming that "andromeda-server" was extracted from a release or `mkrelease` and is in the current directory.

```

# server code:  /usr/local/lib/andromeda-server/
# db config:    /usr/local/etc/andromeda-server/
# sqlite db:    /var/lib/andromeda-server/
# index.php:    /var/www/html/api/
# CLI script:   /usr/local/bin/

mv andromeda-server /usr/local/lib/

# link the entry points
ln -s /usr/local/lib/andromeda-server /usr/local/bin/
ln -s /usr/local/lib/andromda-install /usr/local/bin/
ln -s /usr/local/lib/index.php /var/www/html/api/
ln -s /usr/local/lib/install.php /var/www/html/api/

# create directories
mkdir /var/lib/andromeda-server
mkdir /usr/local/etc/andromeda-server
chown -R www-data:www-data /var/lib/andromeda-server
chown -R www-data:www-data /usr/local/etc/andromeda-server
chmod -R 770 /var/lib/andromeda-server
chmod -R 770 /usr/local/etc/andromeda-server

# initialize the SQLite database
./andromeda-install core dbconf --driver sqlite \
   --dbpath /var/lib/andromeda-server/database.s3db \
   --outfile /usr/local/etc/andromeda-server/DBConfig.php
./andromeda-install core setupall

# set the core datadir (for logging)
./andromeda-server core setconfig --datadir /var/lib/andromeda-server

# not shown - make sure /var/www/html/api/, /usr/local/lib/andromeda-server
# and /usr/local/etc/andromeda-server are readable but NOT writeable by www-data!
# /var/lib/andromeda-server must be writeable by www-data (for logs, db)

```


# License

Andromeda including all source code, documentation, and APIs are copyrighted by the author.  Use of any code is licensed under the SSPL (Server Side Public License) Version 1.  This license also applies to the external API, and therefore any other software that substantially implements the server API, but not to external consumers of it (client software).  Use of any documentation (wiki, readme, etc.) is licensed under the Creative Commons Attribution-NonCommercial-ShareAlike 3.0 (CC BY-NC-SA 3.0) license.  Alternative commercial licenses for either can be obtained separately.  Contributors agree to the terms in CONTRIBUTING.md for all contributions.  All 3rdparty code (located in `vendor/` folders) retains its original licenses - see `composer licenses`.  All must be copyleft-permissive for SSPL compatibility - no GPL or derivatives.
