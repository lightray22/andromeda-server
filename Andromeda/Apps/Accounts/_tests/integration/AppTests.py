
from BaseTest import BaseAppTest
from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "ACCOUNTS"

    def requiresInstall(self) -> bool:
        return True
    
    accountid:str = None
    username:str = None
    password:str = None

    def getInstallParams(self) -> dict:
        self.username = self.util.randAlphanum(8)
        self.password = self.util.randBytes(32)
        return {'username':self.username,'password':self.password}

    def installSelf(self):
        return self.util.assertOk(self.interface.run(app='accounts',action='install',install=True,params=self.getInstallParams()))

    def checkInstallRetval(self, retval):
        self.util.assertIn('id',retval)
        self.util.assertIn('username',retval)
        self.accountid = retval['username']

    sessionid:str = None
    sessionkey:str = None

    def afterInstall(self): # init
        session = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
                                                        params={'username':self.username,'auth_password':self.password}))['client']['session']
        self.sessionid = session['id']
        self.sessionkey = session['authkey']

    def asAdmin(self, params:dict = {}, withUser:bool = False):
        """ Returns params with admin params added if not a private interface """
        if withUser and self.interface.isPriv:
            params['auth_sudouser'] = self.username
        else:
            params['auth_sessionid'] = self.sessionid
            params['auth_sessionkey'] = self.sessionkey
        return params
    
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
