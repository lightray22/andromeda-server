
from BaseTest import BaseAppTest
from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "FILES"

    def getInstallParams(self):
        assertIn('accounts', self.main.appMap)
        return { }

    def install(self):
        # TODO this is different now, we install THEN enable
        # TODO check that the server's dependnecy checking with accounts works
        assertOk(self.interface.run(app='core',action='enableapp',params={'appname':'files'}))
        assertError(self.interface.run(app='files',action='getconfig'),503,'APP_INSTALL_REQUIRED: files')
        assertOk(self.interface.run('files','install'))

    def getAdmin(self):
        return self.main.appMap['accounts'].admin
