#!/usr/bin/env python3

import os, sys, json, getopt, atexit, time, random, importlib, getpass, re

from Interface import Interface, HTTP, CLI
from Database import Database, SQLite, MySQL, PostgreSQL
from InstallTests import InstallTests
from InterfaceTests import HTTPTests, CLITests
from TestUtils import *

class Main():

    phproot = os.getcwd() # Andromeda dir
    verbose = 0        # verbosity level
    config:dict = None # dictionary of config
    dbconfig:str = None # path to DBConfig.php

    random = random.Random()
    randseed = None # seed for randomness

    interfaces:list[Interface] = [ ] # interfaces to test
    databases:list[Database] = [ ] # databases to test

    appList:list[str] = [ ] # apps that exist
    appModules:dict[str] = { } # app test modules

    testMatch:str = None # filter tests to run
    appMatch:str = None # filter apps to test

    testCount = 0 # total number of tests run
    assertCount = 0 # total number of assertions

    def __init__(self, configPath:str):
        """The method to run all tests with the given config path"""

        # process command line args
        shortargs = "hvp:s:f:a:"
        longargs = ["help","verbose","phproot=","randseed=","filter=","apptest="]
        opts, args = getopt.getopt(sys.argv[1:],shortargs,longargs)

        for opt,arg in opts:
            if opt in ('-h','--help'):
                print(longargs); sys.exit(1)
            if opt in ('-p','--phproot'):
                self.phproot = arg
            if opt in ('-v','--verbose'):
                self.verbose += 1
            if opt in ('-s','--randseed'):
                self.randseed = arg
            if opt in ('-f','--filter'):
                self.testMatch = arg
            if opt in ('-a','--apptest'):
                self.appMatch = arg

        if not os.path.exists(self.phproot+'/init.php'):
            raise Exception("cannot find /Andromeda")
        if not os.path.exists(configPath):
            raise Exception(f"cannot find {configPath}")

        if self.randseed is None:
            self.randseed = self.random.randint(0, sys.maxsize)
        self.random.seed(self.randseed)
        print("randomness seeded to", self.randseed)

        # load the config file, process
        with open(configPath) as file:
            self.config = json.load(file)

        interfaces = self.config['interfaces']
        if 'cli' in interfaces:
            self.interfaces.append(CLI(
                interfaces['cli'], self.verbose))
        if 'http' in interfaces:
            self.interfaces.append(HTTP(
                interfaces['http'], self.verbose))
            if getpass.getuser() != "www-data":
                printBlackOnYellow("WARNING HTTP is configured but not running as www-data!")

        if not len(self.interfaces):
            raise Exception("no interfaces configured")

        # check for an existing DBConfig
        self.dbconfig = self.phproot+'/DBConfig.php'
        if os.path.exists(self.dbconfig):
            os.rename(self.dbconfig, self.dbconfig+'.old')
        atexit.register(self.restoreConfig)

        databases = self.config['databases']
        if 'sqlite' in databases:
            self.databases.append(SQLite(
                databases['sqlite'],self.dbconfig))
        if 'mysql' in databases:
            self.databases.append(MySQL(
                databases['mysql'],self.dbconfig))
        if 'pgsql' in databases:
            self.databases.append(PostgreSQL(
                databases['pgsql'],self.dbconfig))

        if not len(self.databases):
            raise Exception("no databases configured")
        
        runPairs:list[tuple[Interface,Database]] = []
        for interface in self.interfaces:
            for database in self.databases:
                runPairs.append((interface, database))
        self.random.shuffle(runPairs)

        # build the app list by scanning
        for appname in os.listdir(self.phproot+'/Apps'): 
            path = self.phproot+'/Apps/'+appname+'/'+appname+'App.php'
            if not os.path.exists(path): continue 
            else: self.appList.append(appname.lower())

        for appname in self.appList:
            if self.appMatch is not None and re.search(self.appMatch, appname) is None: continue
            path = self.phproot+'/Apps/'+appname.capitalize()+'/_tests/integration/AppTests.py'
            if not os.path.exists(path): continue
            self.appModules[appname] = self.loadModule('AppTests', path)

        assert 'core' in self.appModules
        assert 'testutil' in self.appList
        print("Apps to test:", list(self.appModules.keys()))

        # run tests for every database/interface combination
        for interface, database in runPairs:
            print(); printBlackOnWhite(
                "------------------------------------", os.linesep,
                "--- TEST SUITE -",interface,database,'---', os.linesep,
                "------------------------------------"); print()
            self.runTests(interface, database)
    
        apiCount = 0
        for iface in self.interfaces: 
            apiCount += iface.apiCount
        
        print(); printBlackOnGreen("!! ALL TESTS COMPLETE! "
            f"RAN {self.testCount} TESTS, {apiCount} API CALLS, {self.assertCount} ASSERTS!")


    def runTests(self, interface:Interface, database:Database):
        """ Run all tests for the given interface and database config """

        testUtils = TestUtils(self.random)
        # load the python test module for every app
        appTestMap:dict[str] = { }
        for appname, module in self.appModules.items():
            appConfig = None
            if appname in self.config: appConfig = self.config[appname]
            appTestMap[appname] = module.AppTests(testUtils, interface, self.verbose, appTestMap, appConfig)

        printCyanOnBlack("-- BEGIN INSTALLS -- ")
        InstallTests(self.verbose).run(testUtils, interface, database, appTestMap)

        # run all test modules
        printCyanOnBlack("-- BEGIN", interface, "TESTS --")
        if isinstance(interface, HTTP):
            ifaceTests = HTTPTests(testUtils, interface, self.verbose)
        if isinstance(interface, CLI):
            ifaceTests = CLITests(testUtils, interface, self.verbose)
        testCount = ifaceTests.runTests(self.testMatch)
        
        appTestList:list = list(appTestMap.values())
        self.random.shuffle(appTestList)

        for app in appTestList:
            app.afterInstall()
        for app in appTestList: 
            printCyanOnBlack("-- BEGIN", app, "TESTS --"); 
            testCount += app.runTests(self.testMatch)

        if self.verbose >= 1:
            printYellowOnBlack("CHECKING ERROR LOG")
        ifaceTests.checkForErrors() # LAST

        database.deinstall()
        self.testCount += testCount
        self.assertCount += testUtils.assertCounter
        printGreenOnBlack(f"{testCount} TESTS COMPLETE!")
    
    
    def loadModule(self, name:str, path:str):
        """ Loads and returns a python module at the given path """
        sys.path.append(os.path.dirname(path))
        spec = importlib.util.spec_from_file_location(name, path)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        return module

    def restoreConfig(self):
        """ Restores the original DBConfig.php that existed before testing """
        if os.path.exists(self.dbconfig+'.old'):
            os.rename(self.dbconfig+'.old', self.dbconfig)

if __name__ == "__main__": # cwd is Andromeda
    Main(configPath='../tools/conf/pytest-config.json')
