
def testTwoFactorBasic(self):
    """ Tests creating/deleting/using two factor """
    import pyotp
    (username, password, account, client, session) = self.tempAccount()

    # create a couple new two factor objects, test client output
    res = self.util.assertOk(self.interface.run(app='accounts',action='createtwofactor',
        params=self.withSession(session,{'auth_password':password,'comment':'mycomment'})))
    res2 = self.util.assertOk(self.interface.run(app='accounts',action='createtwofactor',
        params=self.withSession(session,{'auth_password':password})))
    
    tf = res['twofactor']
    tf2 = res2['twofactor']
    self.util.assertIn('recoverykeys',res)
    self.util.assertNotEmpty(res['recoverykeys'])
    self.util.assertNotIn('recoverykeys',res2) # only created if not already there

    self.util.assertType(tf['id'], str)
    self.util.assertSame(tf['comment'],'mycomment')
    self.util.assertSame(tf2['comment'],None)
    self.util.assertType(tf['date_created'],float)
    self.util.assertSame(tf['date_used'],None)
    self.util.assertType(tf['secret'],str)
    self.util.assertType(tf['qrcodeurl'],str)

    # test that the two factors show up in getAccount
    res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session,{'full':True})))
    self.util.assertIn(tf['id'], res['twofactors'])
    self.util.assertIn(tf2['id'], res['twofactors'])
    self.util.assertNotIn('secret',res['twofactors'][tf['id']])
    self.util.assertNotIn('qrcodeurl',res['twofactors'][tf['id']])

    # test don't need two factor yet for sessions (not validated)
    self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password}))

    # now we validate the new two factor, it becomes required for sessions
    self.util.assertError(self.interface.run(app='accounts',action='verifytwofactor',
        params=self.withSession(session,{'auth_twofactor':'wrong'})),403,'AUTHENTICATION_FAILED')
    self.util.assertOk(self.interface.run(app='accounts',action='verifytwofactor',
        params=self.withSession(session,{'auth_twofactor':pyotp.TOTP(tf['secret']).now()})))
    self.util.assertError(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password}),403,'TWOFACTOR_REQUIRED')

    # delete the two factor again, no longer required for a session
    self.util.assertOk(self.interface.run(app='accounts',action='deletetwofactor',
        params=self.withSession(session,{'auth_password':password,'twofactor':tf['id']})))
    self.util.assertError(self.interface.run(app='accounts',action='deletetwofactor',
        params=self.withSession(session,{'auth_password':password,'twofactor':tf['id']})),404,'UNKNOWN_TWOFACTOR')
    self.util.assertOk(self.interface.run(app='accounts',action='deletetwofactor',
        params=self.withSession(session,{'auth_password':password,'twofactor':tf2['id']})))
    self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password}))

    # TODO TESTS !! add test that twofactor is not required when using an existing session (unless config is set)

    self.deleteAccount(account) 

def testTwoFactorInvalid(self):
    """ Tests error cases relating to two factor """
    if not self.interface.isPriv:
        self.util.assertError(self.interface.run(app='accounts',action='createtwofactor'),403,'AUTHENTICATION_FAILED')
        self.util.assertError(self.interface.run(app='accounts',action='deletetwofactor'),403,'AUTHENTICATION_FAILED')
        self.util.assertError(self.interface.run(app='accounts',action='verifytwofactor'),403,'AUTHENTICATION_FAILED')

    (username, password, account, client, session) = self.tempAccount()

    # creating two factor requires the password
    self.util.assertError(self.interface.run(app='accounts',action='createtwofactor',
        params=self.withSession(session)),403,'PASSWORD_REQUIRED')
    
    # test deletetwofactor with an unknown object ID
    self.util.assertError(self.interface.run(app='accounts',action='deletetwofactor',
        params=self.withSession(session,{'auth_password':password,'twofactor':'unknown'})),404,'UNKNOWN_TWOFACTOR')
    
    # test that you can't delete a two factor for someone else's account
    (username2, password2, account2, client2, session2) = self.tempAccount()
    res = self.util.assertOk(self.interface.run(app='accounts',action='createtwofactor',
        params=self.withSession(session2,{'auth_password':password2})))
    self.util.assertError(self.interface.run(app='accounts',action='deletetwofactor',
        params=self.withSession(session,{'auth_password':password,'twofactor':res['twofactor']['id']})),404,'UNKNOWN_TWOFACTOR')
    self.util.assertOk(self.interface.run(app='accounts',action='deletetwofactor',
        params=self.withSession(session2,{'auth_password':password2,'twofactor':res['twofactor']['id']})))

    self.deleteAccount(account2)
    self.deleteAccount(account) 
