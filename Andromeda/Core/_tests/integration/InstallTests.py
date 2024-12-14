#!/usr/bin/env python3

import os, sys, json, getopt, time, random, importlib, colorama

from Interface import Interface
from Database import Database
from BaseTest import BaseAppTest
from TestUtils import *

class InstallTests():
    """ Runs install tests and leaves the server in the installed state """
    
    verbose:int = None

    def __init__(self, verbose:int):
        self.verbose = verbose

    def run(self, util:TestUtils, interface:Interface, database:Database, appTests:dict[str, BaseAppTest]):
        """ Runs install (and install tests) for the given interface and database """

        util.assertOk(interface.run(app='core',action='usage',install=True))
        appInstallList = util.assertOk(interface.run(app='core',action='scanapps',install=True))
        util.assertError(interface.run(app='core',action='install',install=True), 503, 'DATABASE_CONFIG_MISSING')
        util.assertError(interface.run(app='core',action='getconfig'), 503, 'DATABASE_CONFIG_MISSING')
        
        for appname, appTest in appTests.items():
            if appTest.requiresInstall():
                util.assertIn(appname, appInstallList)

        # TEST the basic setting up dbconfig process
        database.install(util, interface)

        if not interface.isPriv:
            util.assertError(interface.run(app='core',action='dbconf',install=True), 403, 'ADMIN_REQUIRED')
            util.assertError(interface.run(app='core',action='scanapps',install=True), 403, 'ADMIN_REQUIRED')
        util.assertError(interface.run(app='core',action='getconfig'), 503, 'INSTALL_REQUIRED: core')
        
        # TEST installing apps separately (priv interfaces only) + reset when done
        if interface.isPriv:
            for appname in appInstallList: # sorted in dependency order
                if appname in appTests:
                    retval = appTests[appname].installSelf()
                    appTests[appname].checkInstallRetval(retval)
            database.deinstall()
            database.install(util, interface)

        # TEST install and enable everything at once (priv or non-priv interfaces)
        params = { }
        for appname in appInstallList:
            if appname in appTests:
                appParams = appTests[appname].getInstallParams()
                if appParams is not None: params.update(appParams)
        retvals = util.assertOk(interface.run(app='core',action='setupall',install=True))
        for appname, retval in retvals.items():
            if appname in appTests:
                appTests[appname].checkInstallRetval(retval)
        for appname in appInstallList:
            util.assertIn(appname, retvals)

        util.assertError(interface.run(app='core',action='install',install=True), 400, 'INSTALLED_ALREADY: core')
        util.assertError(interface.run(app='core',action='setupall',install=True), 400, 'INSTALLED_ALREADY: core')
        util.assertError(interface.run(app='core',action='upgrade',install=True), 400, 'UPGRADED_ALREADY: core')
        util.assertError(interface.run(app='core',action='upgradeall',install=True), 400, 'UPGRADED_ALREADY: core')
        util.assertOk(interface.run(app='core',action='getconfig'))
