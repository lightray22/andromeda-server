
from TestUtils import *

def testAuthSourceInvalid(self):
    """ Tests error cases for auth sources """
    if not self.interface.isPriv:
        self.util.assertError(self.interface.run(app='accounts',action='createauthsource'),403,'AUTHENTICATION_FAILED')
        self.util.assertError(self.interface.run(app='accounts',action='testauthsource'),403,'AUTHENTICATION_FAILED')
        self.util.assertError(self.interface.run(app='accounts',action='editauthsource'),403,'AUTHENTICATION_FAILED')
        self.util.assertError(self.interface.run(app='accounts',action='deleteauthsource'),403,'AUTHENTICATION_FAILED')

    (username, password, account, client, session) = self.tempAccount()

    # can't run create/test/edit/delete without being admin
    self.util.assertError(self.interface.run(app='accounts',action='createauthsource',params=self.withSession(session)),403,'ADMIN_REQUIRED')
    self.util.assertError(self.interface.run(app='accounts',action='testauthsource',params=self.withSession(session)),403,'ADMIN_REQUIRED')
    self.util.assertError(self.interface.run(app='accounts',action='editauthsource',params=self.withSession(session)),403,'ADMIN_REQUIRED')
    self.util.assertError(self.interface.run(app='accounts',action='deleteauthsource',params=self.withSession(session)),403,'ADMIN_REQUIRED')

    # admin needs a password for create and delete - don't use asAdmin as direct CLI bypasses it
    self.util.assertError(self.interface.run(app='accounts',action='createauthsource',params=self.withSession(self.session)),403,'PASSWORD_REQUIRED')
    self.util.assertError(self.interface.run(app='accounts',action='deleteauthsource',params=self.withSession(self.session)),403,'PASSWORD_REQUIRED')
    
    # edit, test, delete auth source with invalid ID
    badparams = {'auth_password':self.password,'authsrc':'invalid'}
    self.util.assertError(self.interface.run(app='accounts',action='editauthsource',params=self.asAdmin(badparams)),404,'UNKNOWN_AUTHSOURCE')
    self.util.assertError(self.interface.run(app='accounts',action='testauthsource',params=self.asAdmin(badparams)),404,'UNKNOWN_AUTHSOURCE')
    self.util.assertError(self.interface.run(app='accounts',action='deleteauthsource',params=self.asAdmin(badparams)),404,'UNKNOWN_AUTHSOURCE')
    
    self.deleteAccount(account)

def testAuthSourceFtp(self):
    """ Tests the FTP auth source """
    if 'authsources' in self.config and 'ftp' in self.config['authsources']:
        return doTestAuthSource(self,'ftp',checkFtpOutput)
    else:
        if self.verbose >= 1: printBlackOnYellow("no FTP authsource config, skipping")
        return False
    
def testAuthSourceImap(self):
    """ Tests the IMAP auth source """
    if 'authsources' in self.config and 'imap' in self.config['authsources']:
        return doTestAuthSource(self,'imap',checkImapOutput)
    else:
        if self.verbose >= 1: printBlackOnYellow("no IMAP authsource config, skipping")
        return False

def testAuthSourceLdap(self):
    """ Tests the LDAP auth source """
    if 'authsources' in self.config and 'ldap' in self.config['authsources']:
        return doTestAuthSource(self,'ldap',checkLdapOutput)
    else:
        if self.verbose >= 1: printBlackOnYellow("no LDAP authsource config, skipping")
        return False

def checkFtpOutput(self, params, res, admin):
    checkGeneralOutput(self, res, admin)
    if admin:
        self.util.assertSame(res['type'],'FTP')
        self.util.assertSame(res['hostname'],params['hostname'])
        self.util.assertSame(res['port'],None if not 'port' in params else params['port'])
        self.util.assertSame(res['implssl'],False if not 'implssl' in params else params['implssl'])
    else:
        for prop in ['type','hostname','port','implssl']:
            self.util.assertNotIn(prop, res)

def checkImapOutput(self, params, res, admin):
    checkGeneralOutput(self, res, admin)
    if admin:
        self.util.assertSame(res['type'],'IMAP')
        self.util.assertSame(res['hostname'],params['hostname'])
        self.util.assertSame(res['protocol'],'imap' if not 'protocol' in params else params['protocol'])
        self.util.assertSame(res['port'],None if not 'port' in params else params['port'])
        self.util.assertSame(res['implssl'],False if not 'implssl' in params else params['implssl'])
        self.util.assertSame(res['secauth'],False if not 'secauth' in params else params['secauth'])
    else:
        for prop in ['type','hostname','protocol','port','implssl','secauth']:
            self.util.assertNotIn(prop, res)

def checkLdapOutput(self, params, res, admin):
    checkGeneralOutput(self, res, admin)
    if admin:
        self.util.assertSame(res['type'],'LDAP')
        self.util.assertSame(res['hostname'],params['hostname'])
        self.util.assertSame(res['secure'],False if not 'secure' in params else params['secure'])
        self.util.assertSame(res['userprefix'],None if not 'userprefix' in params else params['userprefix'])
    else:
        for prop in ['type','hostname','secure','userprefix']:
            self.util.assertNotIn(prop, res)

def checkGeneralOutput(self, res, admin):
    self.util.assertType(res['id'],str)
    self.util.assertType(res['description'],str)
    if admin:
        self.util.assertType(res['enabled'],str)
        self.util.assertType(res['date_created'],float)
        if res['default_group'] is not None: self.util.assertType(res['default_group'],str)
    else:
        for prop in ['enabled','default_group','date_created']:
            self.util.assertNotIn(prop, res)

# TODO TESTS find a way to test createsession --old_password (needs to have the script wait while admin changes the authsource password externally)
# TODO TESTS should attempt create/edit with a bad hostname, shouldn't cause a server error
# TODO TESTS !! test create/edit with createdefgroup, test description, test --enabled
# TODO TESTS !! actually test ldap

def doTestAuthSource(self, type, checker):
    """ Tests basic create/delete/usage of an auth source type """
    params = self.config['authsources'][type]
    assert('test_username' in params) # required config

    # test that the default auth source list is empty
    (username, password, account, client, session) = self.tempAccount()
    self.util.assertEmpty(self.util.assertOk(self.interface.run(app='accounts',action='getauthsources',params=self.withSession(session))))

    # create a new authsource according to config
    createparams = params.copy()
    createparams.update({'type':type,'auth_password':self.password})
    res = self.util.assertOk(self.interface.run(app='accounts',action='createauthsource',params=self.asAdmin(createparams)))
    checker(self, params, res, True)
    authsrc = res['id']

    # check getauthsource output in admin/non-admin contexts
    res = self.util.assertOk(self.interface.run(app='accounts',action='getauthsources',params=self.withSession(session)))
    checker(self, params, res[authsrc], False)
    res = self.util.assertOk(self.interface.run(app='accounts',action='getauthsources',params=self.asAdmin()))
    checker(self, params, res[authsrc], True)
    self.deleteAccount(account)

    # test the test command with both good and bad passwords
    goodparams = params.copy()
    goodparams.update({'authsrc':authsrc})
    self.util.assertOk(self.interface.run(app='accounts',action='testauthsource',params=self.asAdmin(goodparams)))
    badparams = params.copy()
    badparams.update({'authsrc':authsrc,'test_password':self.util.randAscii(8)})
    self.util.assertError(self.interface.run(app='accounts',action='testauthsource',params=self.asAdmin(badparams)),400,'AUTHSOURCE_TEST_FAILED')

    # now try signing in via the new auth source (creates a new account!)
    res = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':params['test_username'],'auth_password':params['test_password'],'authsrc':authsrc}))
    account = res['account']
    client = res['client']
    session = client['session']
    self.util.assertSame(account['username'],params['test_username'])

    # now we can signin with or without --authsrc
    res = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':params['test_username'],'auth_password':params['test_password']}))
    self.util.assertSame(res['account']['id'],account['id'])
    res = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':params['test_username'],'auth_password':params['test_password'],'authsrc':authsrc}))
    self.util.assertSame(res['account']['id'],account['id'])
    # check again that the correct password is required, just in case
    self.util.assertError(self.interface.run(app='accounts',action='createsession',
        params={'username':params['test_username'],'auth_password':'wrong'}),403,'AUTHENTICATION_FAILED')
    
    # delete the auth source, check that it also deleted the new account
    self.util.assertOk(self.interface.run(app='accounts',action='deleteauthsource',
        params=self.asAdmin({'authsrc':authsrc,'auth_password':self.password})))
    res = self.util.assertOk(self.interface.run(app='accounts',action='getaccounts',params=self.asAdmin()))
    self.util.assertNotIn(account['id'], res)
    self.util.assertIn(self.account['id'], res) # original admin
