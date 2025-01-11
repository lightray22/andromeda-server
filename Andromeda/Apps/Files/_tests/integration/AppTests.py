
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
    