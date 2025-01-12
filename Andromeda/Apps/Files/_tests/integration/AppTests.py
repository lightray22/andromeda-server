
from BaseTest import BaseAppTest
from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "FILES"

    def requiresInstall(self) -> bool:
        return True
    
    def getInstallParams(self) -> dict:
        return { }

    def installSelf(self):
        return self.util.assertOk(self.interface.run(app='files',action='install',install=True,params=self.getInstallParams()))

    def checkInstallRetval(self, retval):
        self.util.assertSame(None, retval)

    def asAdmin(self, params:dict = {}, withUser:bool = False):
        """ Returns params with admin params added """
        return self.appTestMap['accounts'].asAdmin(params, withUser)
    
    #################################################
    
    def testGetConfig(self):
        """ Tests the getconfig command """
        res = self.util.assertOk(self.interface.run(app='files',action='getconfig'))

        if not self.interface.isPriv: # normie output
            self.util.assertNotIn('date_created',res)
        else: # admin output
            self.util.assertType(res['date_created'], float)
            self.util.assertSame(res, self.util.assertOk(
                self.interface.run(app='files',action='setconfig'))) # setconfig gives admin output
