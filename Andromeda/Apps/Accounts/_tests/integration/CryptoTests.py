
def testChangePassword(self):
    """ Tests basic password change """
    (username, password, account, client, session) = self.tempAccount()
    password2 = self.util.randAlphanum(8)

    # test that password change requires a session, and the correct old password
    self.assertAccountRequired(self.interface.run(app='accounts',action='changepassword'))
    self.util.assertError(self.interface.run(app='accounts',action='changepassword', 
        params=self.withSession(session)),403,'PASSWORD_REQUIRED')
    self.util.assertError(self.interface.run(app='accounts',action='changepassword',
        params=self.withSession(session,{'auth_password':'wrong','new_password':password2})),403,'AUTHENTICATION_FAILED')
    
    # change the password, check that the new one now works and the old one doesn't
    self.util.assertOk(self.interface.run(app='accounts',action='changepassword',
        params=self.withSession(session,{'auth_password':password,'new_password':password2})))
    self.util.assertError(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password}),403,'AUTHENTICATION_FAILED')
    self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password2}))
                                             
    self.deleteAccount(account)

# TODO RAY !! test changepassword as sudo user (no old PW required)
# TODO TESTS !! crypto/changePW/recovery + two factor w/ crypto
