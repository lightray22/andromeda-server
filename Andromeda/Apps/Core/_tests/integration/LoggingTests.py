
import os, json

from TestUtils import *

def testActionLogging(self):
    """ Tests action log config and functionality """
    if not self.interface.isPriv:
        self.util.assertError(self.interface.run(app='core',action='getactions'), 403, "ADMIN_REQUIRED")
        self.util.assertError(self.interface.run(app='core',action='countactions'), 403, "ADMIN_REQUIRED")
    if not self.canAdmin:
        if self.verbose >= 1: printBlackOnYellow("cannot admin, skipping")
        return False
    if not 'datadir' in self.config:
        if self.verbose >= 1: printBlackOnYellow("no datadir configured, skipping")
        return False
    file = self.config['datadir']+"/actions.log"
    if os.path.exists(file): os.remove(file)

    dblogs = 0 # number of log entries added to the DB
    filelogs = 0 # number of log entries added to the file
    
    self.util.assertOk(self.interface.run(app='testutil',action='clearactions'))
    res = self.util.assertOk(self.interface.run(app='core',action='getconfig',params=self.asAdmin())) # admin only
    self.util.assertSame(res['actionlog_db'], False) # default
    self.util.assertSame(res['actionlog_file'], False) # default
    self.util.assertSame(res['actionlog_details'], 'basic') # default

    def getLastAction():
        return list(self.util.assertOk(self.interface.run(app='core',action='getactions',params=self.asAdmin())).values())[0]

    def checkTwoActions(actions):
        self.util.assertCount(actions, 2) # ordered by newest first
        self.util.assertGreaterOrEqual(actions[0]['time'], actions[1]['time'])
        self.util.assertSame(actions[0]['app'], 'testutil'); self.util.assertSame(actions[0]['action'], 'random')
        self.util.assertSame(actions[1]['app'], 'core'); self.util.assertSame(actions[1]['action'], 'getconfig')
        for i in range(0, 2):
            self.util.assertType(actions[i]['addr'], str)
            self.util.assertType(actions[i]['agent'], str)
            for prop in ['errcode','errtext','authuser','params','files','details']:
                self.util.assertNotIn(prop, actions[i])
        self.util.assertNotIn('admin', actions[0]) # testutil
        self.util.assertSame(actions[1]['admin'], self.interface.isPriv) # core
        # TODO TESTS check the action log for the account/client/session field too

    # test logging to db only and basic getactions/countactions
    self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='setconfig',params=self.asAdmin({'actionlog_db':True})))['actionlog_db'], True)
    self.util.assertOk(self.interface.run(app='core',action='getconfig')); dblogs += 1
    self.util.assertOk(self.interface.run(app='testutil',action='random')); dblogs += 1
    checkTwoActions(list(self.util.assertOk(self.interface.run(app='core',action='getactions',params=self.asAdmin())).values())); dblogs += 1
    self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='countactions',params=self.asAdmin())), dblogs); dblogs += 1

    # test logging to db+file and check the file content
    params = self.asAdmin({'datadir':self.config['datadir'],'actionlog_file':True}) # now logging to both
    # ... setconfig will actually save to the log immediately, because DB log is enabled so the actionlog object exists during the request!
    self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='setconfig',params=params))['actionlog_file'], True); dblogs += 1; filelogs += 1
    self.util.assertOk(self.interface.run(app='core',action='getconfig')); dblogs += 1; filelogs += 1
    self.util.assertOk(self.interface.run(app='testutil',action='random')); dblogs += 1; filelogs += 1
    with open(self.config['datadir']+'/actions.log','r') as logfile:
        actions = list(map(lambda line: json.loads(line), logfile))
        actions.reverse() # ordered oldest to newest!
    self.util.assertCount(actions, filelogs)
    checkTwoActions(actions[0:2])
    self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='countactions',params=self.asAdmin())), dblogs); dblogs += 1; filelogs += 1

    # test logging to the file only (just count file lines)
    self.util.assertOk(self.interface.run(app='core',action='setconfig',params=self.asAdmin({'actionlog_db':False}))); filelogs += 1
    self.util.assertOk(self.interface.run(app='testutil',action='random')); filelogs += 1
    with open(self.config['datadir']+'/actions.log','r') as logfile:
        actions = list(map(lambda line: json.loads(line), logfile))
    self.util.assertCount(actions, filelogs)

    # test logging client exceptions
    self.util.assertOk(self.interface.run(app='core',action='setconfig',params=self.asAdmin({'actionlog_db':True}))); dblogs += 1
    self.util.assertError(self.interface.run(app='testutil',action='testiface',params={'clienterror':True}), 400, 'UNKNOWN_ACTION: testiface'); dblogs += 1
    errlog = getLastAction(); dblogs += 1
    self.util.assertSame(errlog['errcode'], 400)
    self.util.assertSame(errlog['errtext'], 'UNKNOWN_ACTION: testiface')

    # test actionlog with NONE output mode (past bug)
    res = self.interface.run(app='core',action='phpinfo',isJson=False,params=self.asAdmin()).decode("utf-8"); dblogs += 1
    self.util.assertIn("PHP Version", res)
    self.util.assertSame(getLastAction()['action'], 'phpinfo'); dblogs += 1
    self.util.assertOk(self.interface.run(app='core',action='geterrors',params=self.asAdmin())); dblogs += 1 # log in case not 0
    self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='counterrors',params=self.asAdmin())), 0); dblogs += 1

    # check different details levels
    def checkDetails(level, params, files, details):
        nonlocal dblogs
        self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='setconfig',
            params=self.asAdmin({'actionlog_details':level})))['actionlog_details'], level); dblogs += 1
        self.util.assertOk(self.interface.run(app='testutil',action='testactlog',
            params={'logtest1':'a','logtest2':'b'},files={'myfile':('a','')})); dblogs += 1
        actlog = getLastAction(); dblogs += 1
        self.util.assertSame(actlog['action'],'testactlog')
        if params is None: self.util.assertNotIn('params',actlog)
        else: self.util.assertSame(actlog['params'], params)
        if files is None: self.util.assertNotIn('files',actlog)
        else: self.util.assertSame(list(actlog['files'].keys()), files)
        if details is None: self.util.assertNotIn('details',actlog)
        else: self.util.assertSame(actlog['details'], details)    
    checkDetails('none',None,None,None)
    checkDetails('basic',{'logtest1':'a'},['myfile'],{'mytest1':'mydetails1'})
    checkDetails('full',{'logtest1':'a','logtest2':'b'},['myfile'],{'mytest1':'mydetails1','mytest2':'mydetails2'})
    
    # final count check, restore original configuration (stop logging)
    self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='countactions',params=self.asAdmin())), dblogs)
    params = self.asAdmin({'datadir':None,'actionlog_db':False,'actionlog_file':False,'actionlog_details':'basic'})
    self.util.assertOk(self.interface.run(app='core',action='setconfig',params=params))

    # TODO TESTS test various get/count filter options
    #core getactions [--mintime float] [--maxtime float] [--addr utf8] [--agent utf8] [--errcode ?int32] [--errtext ?utf8] [--asc bool] [--app core] [--action alphanum] [--logic and|or] [--limit uint] [--offset uint] [--expand bool]
    #core (getactions) --app core [--admin bool] [--account id] [--sudouser id] [--client id]
    #core countactions [--mintime float] [--maxtime float] [--addr utf8] [--agent utf8] [--errcode ?int32] [--errtext ?utf8] [--asc bool] [--app core] [--action alphanum] [--logic and|or]
    #core (countactions) --app core [--admin bool] [--account id] [--sudouser id] [--client id]

def testErrorLogging(self):
    """ Tests error log config and functionality """
    if not self.interface.isPriv:
        self.util.assertError(self.interface.run(app='core',action='geterrors'), 403, "ADMIN_REQUIRED")
        self.util.assertError(self.interface.run(app='core',action='counterrors'), 403, "ADMIN_REQUIRED")
    if not self.canAdmin:
        if self.verbose >= 1: printBlackOnYellow("cannot admin, skipping")
        return False
    if not 'datadir' in self.config:
        if self.verbose >= 1: printBlackOnYellow("no datadir configured, skipping")
        return False
    file = self.config['datadir']+"/errors.log"
    if os.path.exists(file): os.remove(file)

    res = self.util.assertOk(self.interface.run(app='core',action='getconfig',params=self.asAdmin())) # admin only
    self.util.assertSame(res['debug'], 'errors') # default
    self.util.assertSame(res['debug_dblog'], True) # default
    self.util.assertSame(res['debug_filelog'], False) # default

    #def getLastError():
    #    return list(self.util.assertOk(self.interface.run(app='core',action='geterrors',params=self.asAdmin())).values())[0]

    # TODO TESTS implement error log tests like above with action log + check debug_http!

    # TODO TESTS test various get/count filter options
    #core geterrors [--mintime float] [--maxtime float] [--code int32] [--addr utf8] [--agent utf8] [--app alphanum] [--action alphanum] [--message utf8] [--asc bool] [--logic and|or] [--limit uint] [--offset uint]
    #core counterrors [--mintime float] [--maxtime float] [--code int32] [--addr utf8] [--agent utf8] [--app alphanum] [--action alphanum] [--message utf8] [--asc bool] [--logic and|or]

def testMetricsLogging(self):
    """ Tests metrics log config and functionality """
    if not self.canAdmin:
        if self.verbose >= 1: printBlackOnYellow("cannot admin, skipping")
        return False
    if not 'datadir' in self.config:
        if self.verbose >= 1: printBlackOnYellow("no datadir configured, skipping")
        return False
    if self.metricsOutput:
        if self.verbose >= 1: printBlackOnYellow("metrics output already on, skipping")
        return False
    file = self.config['datadir']+"/metrics.log"
    if os.path.exists(file): os.remove(file)

    filelogs = 0 # number of log entries added to the file

    res = self.util.assertOk(self.interface.run(app='core',action='getconfig',params=self.asAdmin())) # admin only
    self.util.assertSame(res['metrics'], "none") # default
    self.util.assertSame(res['metrics_dblog'], False) # default
    self.util.assertSame(res['metrics_filelog'], False) # default

    # TODO FUTURE don't have user-facing metrics functions yet, just check the file gets written
    # ALSO do actual inspection of what's logged to file also like action log above
    params = self.asAdmin({'datadir':self.config['datadir'],'metrics':'basic','metrics_dblog':True,'metrics_filelog':True})
    for res in [self.util.assertOk(self.interface.run(app='core',action='setconfig',params=params)), 
                self.util.assertOk(self.interface.run(app='core',action='getconfig',params=self.asAdmin()))]:
        self.util.assertSame(res['metrics'], 'basic')
        self.util.assertSame(res['metrics_dblog'], True)
        self.util.assertSame(res['metrics_filelog'], True)
    filelogs += 1 # from getconfig
    self.util.assertOk(self.interface.run(app='testutil',action='random')); filelogs += 1
    
    params['metrics'] = 'extended'
    self.util.assertSame(self.util.assertOk(self.interface.run(app='core',action='setconfig',params=params))['metrics'], 'extended'); filelogs += 1
    with open(self.config['datadir']+'/metrics.log','r') as logfile:
        metrics = list(map(lambda line: json.loads(line), logfile))
        metrics.reverse() # ordered oldest to newest!
    self.util.assertCount(metrics, filelogs)

    # restore defaults
    params = self.asAdmin({'datadir':None,'metrics':'none','metrics_dblog':False,'metrics_filelog':False})
    self.util.assertOk(self.interface.run(app='core',action='setconfig',params=params))
