
def testSessionsBasic(self):
    """ Tests basic session/client create/delete/usage """
    (username, password, account, client, session) = self.tempAccount() # creates a session

    self.util.assertSame(client['name'],None)
    self.util.assertType(client['lastaddr'],str)
    if self.interface.isPriv:
        self.util.assertIn('CLI',client['lastaddr'])
        self.util.assertIn('CLI',client['useragent'])
    else:
        self.util.assertIn('python-requests',client['useragent'])
    self.util.assertType(client['date_created'],float)
    self.util.assertType(client['date_loggedon'],float)
    self.util.assertSame(client['date_active'],None)

    self.util.assertSame(session['client'],client['id'])
    self.util.assertType(session['date_created'],float)
    self.util.assertSame(session['date_active'],None)

    # test a custom client name
    name = self.util.randAlphanum(8)
    res = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password,'name':name}))
    self.util.assertSame(res['client']['name'],name) # test custom name
        
    # test deleting a session (no longer can be used)
    self.util.assertOk(self.interface.run(app='accounts',action='deletesession',params=self.withSession(session)))
    self.util.assertError(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session)),403,'INVALID_SESSION')

    # test creating a session from an existing client
    res = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password,'auth_clientid':client['id'],'auth_clientkey':client['authkey']}))
    self.util.assertSame(res['account']['id'], account['id']) # sanity check output
    self.util.assertSame(res['account']['username'], username)
    self.util.assertSame(res['client']['id'], client['id'])
    client = res['client']
    session = client['session']

    # test deleting a client (no longer can be used)
    self.util.assertOk(self.interface.run(app='accounts',action='deleteclient',params=self.withSession(session)))
    self.util.assertError(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session)),403,'INVALID_SESSION')
    self.util.assertError(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password,'auth_clientid':client['id'],'auth_clientkey':client['authkey']}),403,'INVALID_CLIENT')
    
    self.deleteAccount(account)

def testSessionDateActive(self):
    """ Tests updating date_active on sessions/clients """
    (username, password, account, client, session) = self.tempAccount() # creates a session

    # test that date_active gets updated
    res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session,{'full':True})))
    date1 = res['date_active']
    date2 = res['clients'][client['id']]['date_active']
    date3 = res['clients'][client['id']]['session']['date_active']
    self.util.assertType(date1,float)
    self.util.assertType(date2,float)
    self.util.assertType(date3,float)
    self.util.assertSame(date1, date2)
    self.util.assertSame(date1, date3)

    # test that date_active gets updated, even when client errors happen (DB alwaysSave) (check with asAdmin so we don't overwrite dates again)
    self.util.assertError(self.interface.run(app='accounts',action='badAction',params=self.withSession(session)),400,'UNKNOWN_ACTION')

    res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
        params=self.withSession(self.session,{'auth_sudouser':username,'full':True})))
    self.util.assertGreater(res['date_active'], date1)
    self.util.assertGreater(res['clients'][client['id']]['date_active'], date2)
    self.util.assertGreater(res['clients'][client['id']]['session']['date_active'], date3)

    self.deleteAccount(account)

def testSessionDeleteOther(self):
    """ Tests deleting a session that isn't the current one """
    (username, password, account, client, session) = self.tempAccount()

    # TODO TESTS --session/client specifier should be required when using auth_sudouser w/ deletesession

    # test deleting a session/client that isn't this one (allowed with password)
    client2 = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password}))['client']
    self.util.assertOk(self.interface.run(app='accounts',action='deletesession',
        params=self.withSession(session, {'session':client2['session']['id'],'auth_password':password})))
    self.util.assertOk(self.interface.run(app='accounts',action='deleteclient',
        params=self.withSession(session, {'client':client2['id'],'auth_password':password})))
    self.util.assertError(self.interface.run(app='accounts',action='getaccount',
        params=self.withSession(client2['session'])),403,'INVALID_SESSION')

    self.deleteAccount(account)

def testSessionsInvalid(self):
    """ Tests error cases for sessions """
    if not self.interface.isPriv:
        self.util.assertError(self.interface.run(app='accounts',action='deletesession'),403,'AUTHENTICATION_FAILED')
        self.util.assertError(self.interface.run(app='accounts',action='deleteclient'),403,'AUTHENTICATION_FAILED')
        self.util.assertError(self.interface.run(app='accounts',action='deleteallclients'),403,'AUTHENTICATION_FAILED')

    # test creating a session with bad username/password
    self.util.assertError(self.interface.run(app='accounts',action='createsession',
        params={'username':"bad_username",'auth_password':'wrong'}),403,'AUTHENTICATION_FAILED')
    self.util.assertError(self.interface.run(app='accounts',action='createsession',
        params={'username':self.username,'auth_password':'wrong'}),403,'AUTHENTICATION_FAILED')
    
    (username, password, account, client, session) = self.tempAccount()

    # test using the correct session ID but wrong key
    self.util.assertError(self.interface.run(app='accounts',action='getaccount',params=self.withSession({'id':session['id'],'authkey':'this_is_wrong'})),403,'INVALID_SESSION')

    # test deleting a session/client that isn't this one (allowed with password)
    client2 = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password}))['client']
    self.util.assertError(self.interface.run(app='accounts',action='deletesession',
        params=self.withSession(session, {'session':client2['session']['id']})),403,'PASSWORD_REQUIRED')
    self.util.assertError(self.interface.run(app='accounts',action='deletesession',
        params=self.withSession(session, {'session':client2['session']['id'],'auth_password':"this is wrong"})),403,'AUTHENTICATION_FAILED')
    self.util.assertError(self.interface.run(app='accounts',action='deleteclient',
        params=self.withSession(session, {'client':client2['id']})),403,'PASSWORD_REQUIRED')
    self.util.assertError(self.interface.run(app='accounts',action='deleteclient',
        params=self.withSession(session, {'client':client2['id'],'auth_password':"this is wrong"})),403,'AUTHENTICATION_FAILED')
    
    # test trying to delete a session for someone else's account (not allowed)
    (username2, password2, account2, client2, session2) = self.tempAccount()

    self.util.assertError(self.interface.run(app='accounts',action='deletesession',
        params=self.withSession(session, {'session':session2['id'],'auth_password':password})),404,'UNKNOWN_SESSION')
    self.util.assertError(self.interface.run(app='accounts',action='deleteclient',
        params=self.withSession(session, {'client':client2['id'],'auth_password':password})),404,'UNKNOWN_CLIENT')

    self.deleteAccount(account2)
    self.deleteAccount(account)

def testDeleteAllClients(self):
    """ Tests the deleteallclients command """
    (username, password, account, client, session) = self.tempAccount()
    
    # deleteallclients always requires a password
    self.util.assertError(self.interface.run(app='accounts',action='deleteallclients',
        params=self.withSession(session)),403,'PASSWORD_REQUIRED')
    self.util.assertError(self.interface.run(app='accounts',action='deleteallclients',
        params=self.withSession(session, {'auth_password':"this is wrong"})),403,'AUTHENTICATION_FAILED')
    
    # deleteallclients results in all clients being deleted (including the current)
    self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password}))
    self.util.assertOk(self.interface.run(app='accounts',action='deleteallclients',
        params=self.withSession(session,{'auth_password':password})))
    
    self.util.assertError(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session)),403,'INVALID_SESSION')
    
    res = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username,'auth_password':password}))
    client = res['client']
    session = client['session']

    res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
        params=self.withSession(session,{'full':True})))
    self.util.assertCount(res['clients'], 1)

    # can't run deleteallclients --everyone unless admin
    self.util.assertError(self.interface.run(app='accounts',action='deleteallclients',
        params=self.withSession(session, {'everyone':True,'auth_password':password})),403,'ADMIN_REQUIRED')

    # deleteallclients as admin results in all existing clients being deleted
    self.util.assertOk(self.interface.run(app='accounts',action='deleteallclients',
        params=self.asAdmin({'auth_password':self.password,'everyone':True})))

    self.util.assertError(self.interface.run(app='accounts',action='getaccount',
        params=self.withSession(session)),403,'INVALID_SESSION')
    self.util.assertError(self.interface.run(app='accounts',action='getaccount',
        params=self.withSession(self.session)),403,'INVALID_SESSION')

    self.session = None
    self.afterInstall() # restore admin session
    self.deleteAccount(account)
