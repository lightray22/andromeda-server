
import os
from BaseTest import BaseAppTest
from TestUtils import *
import LoggingTests

# TODO ACCOUNTS need to enable/disable the accounts app and test both ways? at least for just one command
    
class AppTests(BaseAppTest):
    def __str__(self):
        return "CORE"

    def requiresInstall(self) -> bool:
        return True
    
    def getInstallParams(self) -> dict:
        return { }

    def installSelf(self):
        return self.util.assertOk(self.interface.run(app='core',action='install',install=True,params=self.getInstallParams()))

    def checkInstallRetval(self, retval):
        self.util.assertSame(None, retval)

    canAdmin:bool = False

    def afterInstall(self): # init
        apps = self.util.assertOk(self.interface.run(app='core',action='getconfig'))['apps']
        self.canAdmin = ('accounts' in apps) or self.interface.isPriv

    def asAdmin(self, params:dict = {}, withUser:bool = False):
        """ Returns params with admin params added if not a private interface """
        if ('accounts' in self.appTestMap):
            return self.appTestMap['accounts'].asAdmin(params, withUser)
        else:
            assert(self.interface.isPriv)
    
    #################################################
        
    def testDatabase(self):
        """ Runs the testutil database unit test command """
        self.util.assertOk(self.interface.run(app='testutil',action='testdb'))

    def testUsage(self):
        """ Tests the core usage command and appname filter """
        rval1 = self.util.assertOk(self.interface.run(app='core',action='usage')) # core + testutil
        self.util.assertInstance(rval1, list)
        self.util.assertNotEmpty(rval1)
        rval2 = self.util.assertOk(self.interface.run(app='core',action='usage',params={'appname':'core'}))
        self.util.assertAny(len(rval1) > len(rval2))
        rval3 = self.util.assertOk(self.interface.run(app='core',action='usage',params={'appname':'none'}))
        self.util.assertEmpty(rval3)

    def testGetConfig(self):
        """ Tests the core getconfig command """
        res = self.util.assertOk(self.interface.run(app='core',action='getconfig'))
        self.util.assertType(res['enabled'], bool)
        self.util.assertType(res['read_only'], bool)
        self.util.assertType(res['apiver'], str)
        self.util.assertIn('core', res['apps'])
        self.util.assertIn('testutil', res['apps'])
        for appver in res['apps'].values():
            # if any are null, they failed to load
            self.util.assertType(appver, str)

        props = ['date_created','datadir','email',
                    'actionlog_file','actionlog_db','actionlog_details',
                    'metrics','metrics_dblog','metrics_filelog',
                    'debug','debug_http','debug_dblog','debug_filelog']
        if not self.interface.isPriv: # normie output
            for appver in res['apps'].values(): # no patch versions
                self.util.assertSame(appver.count('.'), 1)
            for prop in props:
                self.util.assertNotIn(prop, res)
        else: # admin output
            for appver in res['apps'].values(): # yes patch versions
                self.util.assertGreaterOrEqual(appver.count('.'), 2)
            self.util.assertNotEquals(res['date_created'], 0.0)
            # the other params are checked in other tests
            for prop in props:
                self.util.assertIn(prop, res)
            self.util.assertSame(res, self.util.assertOk(
                self.interface.run(app='core',action='setconfig'))) # setconfig gives admin output

    def testScanApps(self):
        """ Tests the core scanapps command """
        if not self.interface.isPriv:
            self.util.assertError(self.interface.run(app='core',action='scanapps'), 403, "ADMIN_REQUIRED")
        if not self.canAdmin:
            if self.verbose >= 1: print("cannot admin, skipping")
            return False
        
        params = self.asAdmin()
        res1 = self.util.assertOk(self.interface.run(app='core',action='scanapps',params=params))
        self.util.assertIn('core', res1)
        self.util.assertIn('testutil', res1)
        res2 = self.util.assertOk(self.interface.run(app='core',action='getconfig',params=params))['apps']
        for appname in res2:
            self.util.assertIn(appname, res1)

    def testPhpInfo(self):
        """ Tests the core phpinfo command """
        if not self.interface.isPriv:
            res = self.interface.run(app='core',action='phpinfo',isJson=False).decode("utf-8") # plain output
            self.util.assertSame(res, 'ERROR: ADMIN_REQUIRED')
        if not self.canAdmin:
            if self.verbose >= 1: print("cannot admin, skipping")
            return False
        
        res = self.interface.run(app='core',action='phpinfo',isJson=False,params=self.asAdmin()).decode("utf-8")
        if self.interface.isPriv:
            self.util.assertStartsWith(res, "phpinfo()")
        else:
            self.util.assertStartsWith(res, "<!DOCTYPE html")
        self.util.assertIn("PHP Version", res)

    def testServerInfo(self):
        """ Tests the core serverinfo command """
        if not self.interface.isPriv:
            self.util.assertError(self.interface.run(app='core',action='serverinfo'), 403, "ADMIN_REQUIRED")
        if not self.canAdmin:
            if self.verbose >= 1: print("cannot admin, skipping")
            return False

        res = self.util.assertOk(self.interface.run(app='core',action='serverinfo',params=self.asAdmin()))
        self.util.assertType(res['php_version'], str)
        self.util.assertType(res['zend_version'], str)
        self.util.assertType(res['uname'], str)
        self.util.assertIn('load', res) # array or false
        self.util.assertNotEmpty(res['server']) # array
        self.util.assertType(res['db']['driver'], str)
        self.util.assertType(res['db']['cversion'], str)
        self.util.assertType(res['db']['sversion'], str)
        if res['db']['driver'] != 'sqlite':
            self.util.assertType(res['db']['sinfo'], str)

    def testGetDbConfig(self):
        """ Tests the core getdbconfig command """
        if not self.interface.isPriv:
            self.util.assertError(self.interface.run(app='core',action='getdbconfig'), 403, "ADMIN_REQUIRED")
        if not self.canAdmin:
            if self.verbose >= 1: print("cannot admin, skipping")
            return False

        res = self.util.assertOk(self.interface.run(app='core',action='getdbconfig',params=self.asAdmin()))
        self.util.assertType(res['DRIVER'], str)
        self.util.assertType(res['CONNECT'], str)
        self.util.assertType(res['PERSISTENT'], bool)
        if 'USERNAME' in res:
            self.util.assertType(res['USERNAME'], str)
        if 'PASSWORD' in res:
            self.util.assertType(res['PASSWORD'], bool) # NOT str!

    def testEnableApps(self):
        """ Tests the core enableapp and disableapp commands """
        if not self.interface.isPriv:
            self.util.assertError(self.interface.run(app='core',action='enableapp'), 403, "ADMIN_REQUIRED")
            self.util.assertError(self.interface.run(app='core',action='disableapp'), 403, "ADMIN_REQUIRED")
        if not self.canAdmin:
            if self.verbose >= 1: print("cannot admin, skipping")
            return False
        
        params = self.asAdmin({'appname':'../hackattempt'})
        self.util.assertError(self.interface.run(app='core',action='enableapp',params=params), 400, "SAFEPARAM_INVALID_TYPE: appname")
        self.util.assertError(self.interface.run(app='core',action='disableapp',params=params), 400, "SAFEPARAM_INVALID_TYPE: appname")

        params['appname'] = 'core' # cannot disable core!
        self.util.assertError(self.interface.run(app='core',action='disableapp',params=params), 400, "INVALID_APPNAME: core")

        params['appname'] = 'myapp' # invalid
        self.util.assertError(self.interface.run(app='core',action='enableapp',params=params), 400, "INVALID_APPNAME: myapp")
        self.util.assertOk(self.interface.run(app='core',action='disableapp',params=params)) # no error if not enabled        

        params['appname'] = 'testutil' # test disabling testutil
        res = self.util.assertOk(self.interface.run(app='core',action='disableapp',params=params))
        self.util.assertNotIn('testutil', res)
        res = self.util.assertOk(self.interface.run(app='core',action='getconfig'))['apps']
        self.util.assertNotIn('testutil', res)
        self.util.assertError(self.interface.run(app='testutil',action='random'), 400, "UNKNOWN_APP: testutil")

        # re-enable testutil and check
        res = self.util.assertOk(self.interface.run(app='core',action='enableapp',params=params))
        self.util.assertOk(self.interface.run(app='core',action='enableapp',params=params)) # can do > once
        self.util.assertIn('testutil', res)
        res = self.util.assertOk(self.interface.run(app='core',action='getconfig'))['apps']
        self.util.assertIn('testutil', res)
        self.util.assertOk(self.interface.run(app='testutil',action='random'))

    def testSetConfigAndDataDir(self):
        """ Tests the core setconfig admin requirement and datadir config """
        if not self.interface.isPriv:
            self.util.assertError(self.interface.run(app='core',action='setconfig'), 403, "ADMIN_REQUIRED")
        if not self.canAdmin:
            if self.verbose >= 1: print("cannot admin, skipping")
            return False
        
        params = self.asAdmin({'datadir':'?badpath'})
        self.util.assertError(self.interface.run(app='core',action='setconfig',params=params), 400, "SAFEPARAM_INVALID_TYPE: datadir") 
        params['datadir'] = '/nonexistent'
        self.util.assertError(self.interface.run(app='core',action='setconfig',params=params), 400, "DATADIR_NOT_WRITEABLE")
        
        if not 'datadir' in self.config:
            if self.verbose >= 1: print("no datadir configured, skipping")
            return False
        params['datadir'] = self.config['datadir']
        res = self.util.assertOk(self.interface.run(app='core',action='setconfig',params=params))
        self.util.assertSame(res['datadir'], os.path.abspath(params['datadir']))
        res = self.util.assertOk(self.interface.run(app='core',action='getconfig',params=self.asAdmin()))
        self.util.assertSame(res['datadir'], os.path.abspath(params['datadir']))
        res = self.util.assertOk(self.interface.run(app='core',action='setconfig',params=params))
        self.util.assertSame(res['datadir'], params['datadir'])

    def testReadOnly(self):
        """ Tests the core read_only config """
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='getconfig'))['read_only'], False) # default
        if not self.canAdmin:
            if self.verbose >= 1: print("cannot admin, skipping")
            return False   

        # test read_only with the action log enabled to ensure it too doesn't attempt to write  
        params = self.asAdmin({'read_only':True,'actionlog_db':True})
        res = self.util.assertOk(self.interface.run(app='core',action='setconfig',params=params))
        self.util.assertSame(res['read_only'], True); self.util.assertSame(res['actionlog_db'], True)
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='getconfig'))['read_only'], True)
        tparams = self.asAdmin({'appname':'testutil'})
        self.util.assertError(self.interface.run(app='core',action='disableapp',params=tparams), 403, "READ_ONLY_DATABASE")

        params = self.asAdmin({'read_only':False,'actionlog_db':False}) # defaults
        res = self.util.assertOk(self.interface.run(app='core',action='setconfig',params=params))
        self.util.assertSame(res['read_only'], False); self.util.assertSame(res['actionlog_db'], False)
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='getconfig'))['read_only'], False)
        self.util.assertOk(self.interface.run(app='core',action='disableapp',params=tparams))
        self.util.assertOk(self.interface.run(app='core',action='enableapp',params=tparams))

    def testHttpEnabled(self):
        """ Tests the core http enable config """
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='getconfig'))['enabled'], True) # default
        if not self.canAdmin:
            if self.verbose >= 1: print("cannot admin, skipping")
            return False
        
        if not self.interface.isPriv:
            if self.verbose >= 1: print("can't test HTTP disable over HTTP, skipping")
            return False
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='setconfig',params={'enabled':False}))['enabled'], False)
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='getconfig'))['enabled'], False) # has no effect on CLI
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='setconfig',params={'enabled':True}))['enabled'], True)
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='getconfig'))['enabled'], True) # has no effect on CLI
    
    def testEmailConfig(self):
        """ Tests get/create/delete emailers and tests sending """
        if not self.interface.isPriv:
            self.util.assertError(self.interface.run(app='core',action='testmail'), 403, "ADMIN_REQUIRED")
            self.util.assertError(self.interface.run(app='core',action='getmailers'), 403, "ADMIN_REQUIRED")
            self.util.assertError(self.interface.run(app='core',action='createmailer'), 403, "ADMIN_REQUIRED")
            self.util.assertError(self.interface.run(app='core',action='deletemailer'), 403, "ADMIN_REQUIRED")
        if not self.canAdmin:
            if self.verbose >= 1: print("cannot admin, skipping")
            return False
        
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='getconfig',params=self.asAdmin()))['email'], True) # default
        self.util.assertEmpty(self.util.assertOk(self.interface.run(app='core',action='getmailers',params=self.asAdmin())))
        self.util.assertError(self.interface.run(app='core',action='testmail',params=self.asAdmin({'dest':'test@test.com'})), 400, "MAILER_UNAVAILABLE")

        if not 'email' in self.config:
            if self.verbose >= 1: print("no email configured, skipping")
            return False
        
        def checkEmailer(res):
            self.util.assertType(res['id'], str)
            self.util.assertNotEquals(res['date_created'], 0.0)
            self.util.assertSame(res['type'], self.config['email']['type'])
            self.util.assertType(res['password'], bool) # NOT the real value!
            self.util.assertSame(res['from_address'], self.config['email']['from_address'])
        emailer = self.util.assertOk(self.interface.run(app='core',action='createmailer',params=self.asAdmin(self.config['email'])))
        checkEmailer(emailer)
        res = self.util.assertOk(self.interface.run(app='core',action='getmailers',params=self.asAdmin()))
        checkEmailer(res[emailer['id']]) # indexed by ID

        if not 'test' in self.config['email']:
            if self.verbose >= 1: print("no email test configured, skipping")
            return False
        
        # TODO ACCOUNTS if authenticated, can test without a "dest" here, should send to contact emails
        params = self.asAdmin({'dest':self.config['email']['test'], 'testkey':self.util.random.randint(0,999999)})
        self.util.assertOk(self.interface.run(app='core',action='testmail',params=params)) # no mailid = use any
        self.util.assertSame(str(params['testkey']), input("Enter testkey sent in email:"))

        params['mailid'] = 'nonexistent'
        self.util.assertError(self.interface.run(app='core',action='testmail',params=params), 404, "UNKNOWN_MAILER")
        params['mailid'] = emailer['id']
        params['testkey'] = self.util.random.randint(0,999999)
        self.util.assertOk(self.interface.run(app='core',action='testmail',params=params))
        self.util.assertSame(str(params['testkey']), input("Enter testkey sent in email:"))

        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='setconfig',params=self.asAdmin({'email':False})))['email'], False)
        self.util.assertError(self.interface.run(app='core',action='testmail',params=params), 400, "EMAIL_DISABLED")
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='setconfig',params=self.asAdmin({'email':True})))['email'], True)

        self.util.assertError(self.interface.run(app='core',action='deletemailer',params=self.asAdmin({'mailid':'none'})), 404, "UNKNOWN_MAILER")
        self.util.assertOk(self.interface.run(app='core',action='deletemailer',params=self.asAdmin({'mailid':emailer['id']})))
        self.util.assertEmpty(self.util.assertOk(self.interface.run(app='core',action='getmailers',params=self.asAdmin())))

    def testActionLogging(self):
        return LoggingTests.testActionLogging(self)

    def testErrorLogging(self):
        return LoggingTests.testErrorLogging(self)

    def testMetricsLogging(self):
        return LoggingTests.testMetricsLogging(self)
