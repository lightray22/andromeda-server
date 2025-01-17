
def testSessions(self):
    """ Tests basic session/client create/delete/usage """
    if not self.interface.isPriv:
        self.util.assertError(self.interface.run(app='accounts',action='deletesession'),403,'AUTHENTICATION_FAILED')
        self.util.assertError(self.interface.run(app='accounts',action='deleteclient'),403,'AUTHENTICATION_FAILED')

    # test creating a session with bad username/password
    self.util.assertError(self.interface.run(app='accounts',action='createsession',
        params={'username':"bad_username",'auth_password':'wrong'}),403,'AUTHENTICATION_FAILED')
    self.util.assertError(self.interface.run(app='accounts',action='createsession',
        params={'username':self.username,'auth_password':'wrong'}),403,'AUTHENTICATION_FAILED')
    
    (username, password, account, client, session) = self.tempAccount() # creates a session
    res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session)))
    self.util.assertSame(account['id'], res['id']) # basic sanity check

    # test using the correct session ID but wrong key
    self.util.assertError(self.interface.run(app='accounts',action='getaccount',params=self.withSession({'id':session['id'],'authkey':'this_is_wrong'})),403,'INVALID_SESSION')
    
    # test deleting a session (no longer works)
    self.util.assertOk(self.interface.run(app='accounts',action='deletesession',params=self.withSession(session)))
    self.util.assertError(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session)),403,'INVALID_SESSION')

    # test creating a session from an existing client
    res = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
                                                    params={'username':username,'auth_password':password,'auth_clientid':client['id'],'auth_clientkey':client['authkey']}))
    self.util.assertSame(res['account']['id'], account['id']) # sanity check output
    self.util.assertSame(res['account']['username'], username)
    self.util.assertSame(res['client']['id'], client['id'])
    client = res['client']
    session = res['client']['session']

    # test deleting a client (no longer works)
    self.util.assertOk(self.interface.run(app='accounts',action='deleteclient',params=self.withSession(session)))
    self.util.assertError(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session)),403,'INVALID_SESSION')
    self.util.assertError(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password,'auth_clientid':client['id'],'auth_clientkey':client['authkey']}),403,'INVALID_CLIENT')
    
    # TODO TESTS --session/client specifier should be required when using auth_sudouser w/ deletesession

    # test deleting a session/client that isn't this one (allowed with password)
    name = self.util.randAlphanum(8)
    client = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password,'name':name}))['client']
    self.util.assertSame(client['name'],name) # test custom name
    session = client['session']

    client2 = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password}))['client']
    self.util.assertError(self.interface.run(app='accounts',action='deletesession',
        params=self.withSession(session, {'session':client2['session']['id']})),403,'PASSWORD_REQUIRED')
    self.util.assertError(self.interface.run(app='accounts',action='deletesession',
        params=self.withSession(session, {'session':client2['session']['id'],'auth_password':"this is wrong"})),403,'AUTHENTICATION_FAILED')
    self.util.assertOk(self.interface.run(app='accounts',action='deletesession',
        params=self.withSession(session, {'session':client2['session']['id'],'auth_password':password})))
    self.util.assertError(self.interface.run(app='accounts',action='deleteclient',
        params=self.withSession(session, {'client':client2['id']})),403,'PASSWORD_REQUIRED')
    self.util.assertOk(self.interface.run(app='accounts',action='deleteclient',
        params=self.withSession(session, {'client':client2['id'],'auth_password':password})))
    self.util.assertError(self.interface.run(app='accounts',action='getaccount',
        params=self.withSession(client2['session'])),403,'INVALID_SESSION')

    # test trying to delete a session for someone else's account (not allowed)
    (username2, password2, account2, client2, session2) = self.tempAccount() # creates a session

    self.util.assertError(self.interface.run(app='accounts',action='deletesession',
        params=self.withSession(session, {'session':session2['id'],'auth_password':password})),404,'UNKNOWN_SESSION')
    self.util.assertError(self.interface.run(app='accounts',action='deleteclient',
        params=self.withSession(session, {'client':client2['id'],'auth_password':password})),404,'UNKNOWN_CLIENT')

    # TODO RAY !! add two factor tests e.g. don't need on client login unless config set (make utility functions?)
    # TODO RAY !! test deleteAllAuth

    self.deleteAccount(session2, password2)
    self.deleteAccount(session, password)
    
# TODO RAY !! try to reorganize the above into several separate tests + better comments
