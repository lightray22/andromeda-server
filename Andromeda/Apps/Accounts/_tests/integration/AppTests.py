
import string

from BaseTest import BaseAppTest
from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "ACCOUNTS"

    def requiresInstall(self) -> bool:
        return True
    
    accountid:string = None
    username:string = None
    password:string = None

    def getInstallParams(self) -> dict:
        self.username = ''.join(self.util.random.choice(string.ascii_letters) for _ in range(8))
        self.password = self.util.random.randbytes(32)
        return {'username':self.username,'password':self.password}

    def installSelf(self):
        return self.util.assertOk(self.interface.run(app='accounts',action='install',install=True,params=self.getInstallParams()))

    def checkInstallRetval(self, retval):
        self.util.assertIn('id',retval)
        self.util.assertIn('username',retval)
        self.accountid = retval['username']

    sessionid:string = None
    sessionkey:string = None

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
    