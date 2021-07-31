
from BaseAppTests import *
from TestUtils import *

class AppTests(BaseAppTests):
    def __str__(self):
        return "FILES"

    def install(self):
        assertOk(self. interface.run('files','install'))

    def runTests(self):
        admin = self.main.appMap['accounts'].admin