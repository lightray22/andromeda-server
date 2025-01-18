
from BaseTest import BaseAppTest
from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "ACCOUNTS"

    def getTestModules(self):
        import ContactTests
        import SessionTests
        import TwoFactorTests
        return [self, ContactTests, SessionTests, TwoFactorTests]

    def requiresInstall(self) -> bool:
        return True
    
    accountid:str = None
    username:str = None
    password:str = None

    def getInstallParams(self) -> dict:
        self.username = self.util.randAlphanum(8)
        self.password = self.util.randBytes(8)
        return {'username':self.username,'password':self.password}

    def installSelf(self):
        return self.util.assertOk(self.interface.run(app='accounts',action='install',install=True,params=self.getInstallParams()))

    def checkInstallRetval(self, retval):
        self.util.assertType(retval['id'], str)
        self.util.assertType(retval['username'], str)
        self.accountid = retval['id']

    sessionid:str = None
    sessionkey:str = None

    def afterInstall(self): # init
        if self.sessionid is not None:
            return # already done
        res = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
            params={'username':self.username,'auth_password':self.password}))
        session = res['client']['session']
        self.sessionid = session['id']
        self.sessionkey = session['authkey']

    def asAdmin(self, params:dict = {}):
        """ Returns params with admin params added if not a private interface """
        if self.sessionid is None:
            self.afterInstall() # make asAdmin available to other apps in afterInstall()
        params = params.copy()
        if self.interface.isPriv:
            if self.util.random.choice([True,False]):
                params['auth_sudouser'] = self.username
            else:
                params['auth_sudoacct'] = self.accountid
        else:
            params['auth_sessionid'] = self.sessionid
            params['auth_sessionkey'] = self.sessionkey
        return params
    
    def withSession(self, session:dict, params:dict = {}):
        """ Returns params with session params added """
        params = params.copy()
        params['auth_sessionid'] = session['id']
        params['auth_sessionkey'] = session['authkey']
        return params
    
    def tempAccount(self):
        """ Creates a new account/session for temporary use """
        username = self.util.randAlphanum(8)
        password = self.util.randBytes(8)
        account = self.util.assertOk(self.interface.run(app='accounts',action='createaccount',
            params=self.asAdmin({'username':username,'password':password})))
        client = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
            params={'username':username,'auth_password':password}))['client']
        session = client['session']
        return (username, password, account, client, session)
    
    def deleteAccount(self, session:dict, password:str):
        """ Deletes an account with the given session and password """
        self.util.assertOk(self.interface.run(app='accounts',action='deleteaccount',
            params=self.withSession(session, {'auth_password':password})))

    #################################################
    
    def testGetConfig(self):
        """ Tests the getconfig command """
        res = self.util.assertOk(self.interface.run(app='accounts',action='getconfig'))
        self.util.assertType(res['username_iscontact'], bool)
        self.util.assertType(res['create_account'], str)
        self.util.assertType(res['require_contact'], str)
        self.util.assertSame(res['default_auth'], None)

        if not self.interface.isPriv: # normie output
            self.util.assertNotIn('date_created',res)
            self.util.assertNotIn('default_group',res)
        else: # admin output
            self.util.assertType(res['date_created'], float)
            self.util.assertSame(res['default_group'], None)
            self.util.assertSame(res, self.util.assertOk(
                self.interface.run(app='accounts',action='setconfig'))) # setconfig gives admin output

    def testSudoUser(self):
        """ Tests auth_sudouser and auth_sudoacct overriding a session """
        # note this is different than withSession() because we are using sudouser to *override* a session
        (username, password, account, client, session) = self.tempAccount()
        adminSession = {'id':self.sessionid, 'authkey':self.sessionkey}

        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(adminSession)))
        self.util.assertSame(res['id'], self.accountid)
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(adminSession, {'auth_sudouser':username})))
        self.util.assertSame(res['id'], account['id'])
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(adminSession, {'auth_sudoacct':account['id']})))
        self.util.assertSame(res['id'], account['id'])
        self.deleteAccount(session, password)

    def testGetAccount(self):
        """ Test the getAccount command """
        if not self.interface.isPriv:
            self.util.assertError(self.interface.run(app='accounts',action='getaccount'),403,'AUTHENTICATION_FAILED')

        (username, password, account, client, session) = self.tempAccount()
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session)))
        self.util.assertSame(res['id'], account['id'])
        self.util.assertSame(res['username'], username)
        self.util.assertSame(res['dispname'], None)
        self.util.assertNotIn('date_created',res) # full
        self.util.assertNotIn('date_modified',res) # admin
        self.util.assertNotIn('comment',res) # admin
        self.util.assertNotIn('groups',res) # admin

        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session,{"full":True})))
        self.util.assertNotIn('comment',res) # admin
        self.util.assertSame(res['crypto'], False)
        self.util.assertType(res['date_created'],float)
        self.util.assertType(res['date_loggedon'],float)
        self.util.assertType(res['date_active'],float)
        self.util.assertType(res['date_passwordset'],float)
        self.util.assertCount(res['recoverykeys'],0)
        self.util.assertCount(res['twofactors'],0)
        self.util.assertCount(res['contacts'],0)

        self.util.assertCount(res['clients'],1)
        self.util.assertIn(client['id'], res['clients'])

        # TODO TESTS should double check the defaults here?
        for prop in ['session_timeout','client_timeout','max_password_age','limit_clients','limit_contacts','limit_recoverykeys','disabled','account_search','group_search']:
            self.util.assertAny2((prop in res['policy']) and (res['policy'][prop] is None or type(res['policy'][prop]) == int), res['policy'])
        for prop in ['admin','forcetf','allowcrypto','userdelete']:
            self.util.assertAny2((prop in res['policy']) and (res['policy'][prop] is None or type(res['policy'][prop]) == bool), res['policy'])

        # regular accounts can use --account to get public info only
        self.util.assertError(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(session, {'account':"badID"})),404,'UNKNOWN_ACCOUNT')
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(session, {'account':self.accountid})))
        self.util.assertSame(res['id'], self.accountid)
        self.util.assertSame(res['username'], self.username)
        self.util.assertSame(res['dispname'], None)
        self.util.assertIn('contacts',res)

        # TODO TESTS test admin output (with --account)
        # TODO TESTS test full admin output (with --account)

        self.deleteAccount(session, password)

    # TODO RAY !! crypto/changePW/recovery, create/delete acct + register allow + setfullname, group stuff + account/group search, external auth sources
    # TODO RAY !! add test for date_active setting even on client error
