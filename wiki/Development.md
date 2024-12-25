* [Bug Reporting](#bug-reporting)
* [Generated Documentation](#generated-documentation)
* [Versioning Scheme](#versioning-scheme)
* [Main Core Classes](#main-core-classes)
* [Creating a Basic App](#creating-a-basic-app)
* [Tools and Scripts](#tools-and-scripts)


## Bug Reporting

Any code 500 server-error response should be considered a bug and reported.  This excludes errors caught when interfacing with a badly-behaving storage backends.  By default, basic backtraces (no personal info) are logged to the database and can be viewed with the `core geterrors` command.  Bug reports made here must include the relevant error log entry if possible.

See the [error logging how-to](Core-and-CoreApp.md#error-logging) for more details.


## Generated Documentation

* [Repository](https://github.com/irondrive/andromeda-server-docs)
* [Deployment](https://irondrive.github.io/andromeda-server-docs)

The API documentation is generated using the `tools/mkdocs` script.


## Versioning Scheme

Andromeda follows the standard major.minor.patch semantic versioning scheme.

External clients need only match the major version exactly, while minor versions bring optional new features.  Non-admin external clients cannot see patch versions.  For the internal core<->app APIs, the minor version must also match exactly as minor releases _may_ change APIs.

|                | Purpose | External APIs | Internal APIs | Database Upgrade |
| ------------- | ------------- | ------------- | ------------- | ------------- |
| Patch Versions | Bugfixes | Unchanged | Unchanged | Not Required |
| Minor Versions | Small Features | Compatible | Incompatible | Required |
| Major Versions | Large Features | Incompatible | Incompatible | Required |


## Main Core Classes

`index.php` is a very short wrapper that initializes the `IOInterface`, `ErrorManager`, `ApiPackage`, and `AppRunner` objects, and passes input from the interface to Run().  `init.php` handles some global setup as well as the class autoloader.

The `ErrorManager` class takes care of logging exceptions to the database and handling uncaught errors and exceptions. It also provides `LogDebugHint()` and `LogBreakpoint()` for debug logging. The `IOInterface` takes care of abstracting the differences between HTTP and CLI and is used for input and output handling.  `ApiPackage` initializes the database and loads config.  It is also the primary API class for apps to use (via `$this->API`), holding references to the `ObjectDatabase`, `Config`, `AppRunner`, `ErrorManager`, and `IOInterface`.  

The primary request handler is the `AppRunner`.  This class loads and handles running apps.  Run() sends the action to the app, handles logging, saves/commits the result, and writes output to the interface.  `InstallRunner` is the `AppRunner` equivalent for the install system.  

### Safe Input Classes
`Input` contains functions to read file inputs, as well as `GetParams()` to return a `SafeParams`.  The `SafeParams` object has functions to get required or optional input parameters (and specify their defaults or logging settings).  Each parameter is a `SafeParam` which has various functions for safe input validation, null-checking, etc.  For example to read an optional non-null integer named `mytest` with a default value of 0, you could use `$input->GetParams()->GetParam('mytest',0)->GetInt()`.


### Database API
The `PDODatabase` is the PDO-based database driver that abstracts the different database types.  The next layer, the `ObjectDatabase`, is the object-oriented database abstraction.  It provides an easy way to load and save objects without needing manual SQL.  The `ObjectDatabase` works with `BaseObject`, the base class for all database table classes.  `BaseObject`s use traits in `TableTypes.php` to specify their polymorphism/inheritance structures.  Each field in a `BaseObject` that corresponds to a table column inherits from a class in `FieldTypes.php`.  This provides several different types of fields with appropriate get/set functions, including various scalars, a counter, auto-JSON fields, and object ID references.  The `ObjectDatabase` also provides a unique key cache to reduce duplicate queries.  Andromeda relies on the `REPEATABLE READ` SQL isolation level.  

Every BaseObject at a minimum needs to override `CreateFields` to new its various fields (and call the parent!).  All DB objects have a random ID string as their primary key, the length of which can be changed.  BaseObjects can add static functions to call the ObjectDatabase to load/count/delete/create objects based on appropriate queries.  

Changes are made to FieldTypes, and newly created objects, are not saved immediately.  This can be done manually with `BaseObject::Save()`, or it will happen automatically when the `ObjectDatabase` is told to save before committing.  Newly created objects are saved first in creation order, then modified objects.  


## Creating a Basic App

When writing custom server side code, you'll need to create a new app.  An app named 'test' should exist as `TestApp` in `Apps/Test/TestApp.php`.  Apps inherit from `BaseApp`.  Apps are all constructed with $API pointing to `ApiPackage`.  The basic functions your app will need to provide are `getUsage()` which returns the CLI usage strings, `getName()` which returns the app's name, `getVersion()` which returns the version string, and `Run()` which maps `Input` to a returned value (does the actual work).  

In `Run()`, your app will need to switch based on the `$input->GetAction()` and then run the appropriate routine.  Your app also can implement its own commit() and rollback() routines by overriding the given stub functions.  If your app wants to use the framework's action logging capabilities, `getLogClass()` should return the name of your class that inherits from `BaseAppLog`, and `wantActionLog()` should be checked in Run().  

If your app uses database tables or has other install-time requirements, you must create a `Apps/Test/TestInstallApp.php` inheriting from `InstallApp`.  This class takes care of the install and upgrade systems of the app.  Your app must have a config table inheriting from `BaseConfig` that stores its version, and you must implement `getConfigClass()`.  Dependencies on other apps can be specified by implementing `getDependencies()`.

If your app needs exceptions, they should inherit from `ServerException` or `ClientException`.  `Crypto.php` provides a wrapper around libsodium and `Utilities.php` provides other useful tools. Remember that all input is done via the `Input` object (see above) and all output is done by returning a value in `Run()`.  The returned value MUST be UTF-8 safe.  Your app should never use `$_GET` or `printr` or `echo` or anything similar as this breaks CLI/HTTP abstraction.  If your app needs to do its own binary/non-JSON output, it can use the interface's `SetOutputMode()` and `SetOutputHandler()` - see CoreApp.php `PHPInfo()` for an example. 


## Tools and Scripts

Tools and scripts are located in the `tools/` folder and must be run from the repository root.  The `tools/checkall` script runs static analysis, unit testing, and integration tests all at once.

### Database Export
Development is currently done primarily with mysql and phpmyadmin.  The database is then exported into the database templates that are committed with Andromeda.  This is accomplished with the `tools/dbexport` script.  This requires `pgloader` to be installed.  The [`mysql2sqlite`](https://github.com/dumblob/mysql2sqlite) script will be downloaded automatically from github.  It assumes that MySQL uses the `root` user and Postgres uses the `postgres` user, both on localhost, and that the MySQL database name is `andromeda`.

The tool works by first using `tools/pgtransfer` which uses `pgloader` to transfer the database to postgres.  Then the templates are created for core and each app using `mysqldump`, `pg_dump`, and finally `mysql2sqlite`.  

### API Docs Generation
Use the `tools/mkdocs` script.  This will output in the docs folder, which should itself be a checkout of the API server docs repository.  The resulting documentation can then be committed.  This is normally only run as part of the release process, or when major changes are made.

### Release Export
Use the `tools/mkrelease tag` script. The first argument is the git tag to checkout.  This script will clone the API from github, checkout the given tag, remove everything development related, generate docs, and output release-ready tarballs/zips.  

### Static Analysis
The `tools/analyze` script will run PHPStan for static code analysis.  PHPStan is installed via composer.  The script uses a strict set of rules and checks every supported PHP version.

### PHP Unit Testing
PHPUnit is used for unit testing.  Tests are located in any `_tests/phpunit` folders.  The `tools/unittests` script will run PHPUnit.  PHPUnit is installed via composer.

### Integration Testing
A python test framework is used for integration testing.  The `tools/inttests` script will run the test suite.  The test suite will run as a consumer of the Andromeda API and exhaustively check every command for expected input/output.  It checks both the HTTP and CLI interfaces and can run with all database types.  Integration tests are located in `_tests/integration` in core and apps.

The test framework uses a `pytest-config.json` file that must be located in the `tools/conf/` directory.  This config file specifies which optional components to test based on what you have available in your test environment.  `cli` determines whether to test CLI.  `http` gives the URL for testing via HTTP. `sqlite`, `mysql` and `pgsql` respectively set up which databases to test with.  The objects you set them to use parameters identical to that of the `core dbconf` command, except that pgsql does NOT support persistent connections.  A minimal integration test would use `{"interfaces":{"cli":"."},"databases":{"sqlite":{"dbpath":"mytest.s3db"}}}`.  This will run the test suite using CLI with a local SQLite database. Paths are relative to `Andromeda/`.

Note that HTTP testing can be done with PHP's integrated web server rather than needing to install a real one, e.g. `php -S localhost:8080`.  

Python 3.10 is the minimum required (Ubuntu 22.04 is the current baseline).  Other (possibly non-exhaustive) requirements: `sudo apt install python3-dev python3-pkgconfig libmysqlclient-dev postgresql-server-dev-all python3-requests python3-colorama python3-mysql.connector python3-psycopg2`
