
def testContactsBasic(self):
    """ Tests creating/deleting/using contacts """
    (username, password, account, client, session) = self.tempAccount()

    # create a basic contact, check output format
    email = "mytest@test.com"
    res = self.util.assertOk(self.interface.run(app='accounts',action='createcontact',
        params=self.withSession(session,{'email':email})))
    id = res['id']
    self.util.assertSame(res['type'],'email')
    self.util.assertSame(res['address'],email)
    self.util.assertSame(res['valid'],True) # verification not required by default
    self.util.assertSame(res['public'],False) # default
    self.util.assertSame(res['isfrom'],False) # default
    self.util.assertType(res['date_created'],float)

    # test editing contact properties
    res = self.util.assertOk(self.interface.run(app='accounts',action='editcontact',
        params=self.withSession(session,{'contact':id,'public':True,'isfrom':True})))
    self.util.assertSame(res['public'],True)
    self.util.assertSame(res['isfrom'],True)

    # TODO RAY !! test contact validation via email + make sure authkey isn't in the client object lol

    # create another contact
    email2 = "mytest2@test.com"
    res = self.util.assertOk(self.interface.run(app='accounts',action='createcontact',
        params=self.withSession(session,{'email':email2})))
    id2 = res['id']

    # run getaccount, check they both show up
    res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
        params=self.withSession(session,{'full':True})))
    self.util.assertSame(res['contacts'][id]['address'],email)
    self.util.assertSame(res['contacts'][id2]['address'],email2)

    # test that the contact shows up in public getaccount
    (username2, password2, account2, client2, session2) = self.tempAccount()
    res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
        params=self.withSession(session2,{'account':account['id']})))
    self.util.assertCount(res['contacts'], 1) # contact 2 is not public
    self.util.assertSame(res['contacts'][0],email) # NOT indexed by ID
    self.deleteAccount(account2)

    # test deleting contacts, shouldn't show up in getaccount anymore
    self.util.assertOk(self.interface.run(app='accounts',action='deletecontact',
        params=self.withSession(session,{'contact':id})))
    self.util.assertOk(self.interface.run(app='accounts',action='deletecontact',
        params=self.withSession(session,{'contact':id2})))
    
    res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
        params=self.withSession(session,{'full':True})))
    self.util.assertEmpty(res['contacts'])

    self.deleteAccount(account)

def testContactsInvalid(self):
    """ Tests error cases for contacts """
    if not self.interface.isPriv:
        self.util.assertError(self.interface.run(app='accounts',action='createcontact'),403,'AUTHENTICATION_FAILED')
        self.util.assertError(self.interface.run(app='accounts',action='deletecontact'),403,'AUTHENTICATION_FAILED')
        self.util.assertError(self.interface.run(app='accounts',action='editcontact'),403,'AUTHENTICATION_FAILED')

    (username, password, account, client, session) = self.tempAccount()
    (username2, password2, account2, client2, session2) = self.tempAccount()
    
    res = self.util.assertOk(self.interface.run(app='accounts',action='createcontact',
        params=self.withSession(session,{'email':'mytest@test.com'})))
    id = res['id']

    # test trying to create a contact with an already used value
    self.util.assertError(self.interface.run(app='accounts',action='createcontact',
        params=self.withSession(session,{'email':'mytest@test.com'})),400,'CONTACT_ALREADY_EXISTS')
    self.util.assertError(self.interface.run(app='accounts',action='createcontact',
        params=self.withSession(session2,{'email':'mytest@test.com'})),400,'CONTACT_ALREADY_EXISTS')

    # test contact edit/delete ID validity/ownership
    self.util.assertError(self.interface.run(app='accounts',action='editcontact',
        params=self.withSession(session,{'contact':'unknown'})),404,'UNKNOWN_CONTACT')
    self.util.assertError(self.interface.run(app='accounts',action='editcontact',
        params=self.withSession(session2,{'contact':id})),404,'UNKNOWN_CONTACT')
    
    self.util.assertError(self.interface.run(app='accounts',action='deletecontact',
        params=self.withSession(session,{'contact':'unknown'})),404,'UNKNOWN_CONTACT')
    self.util.assertError(self.interface.run(app='accounts',action='deletecontact',
        params=self.withSession(session2,{'contact':id})),404,'UNKNOWN_CONTACT')
    
    self.deleteAccount(account2)
    self.deleteAccount(account)

def testContactsSingleFrom(self):
    """ Tests that only one contact can be used as from """
    (username, password, account, client, session) = self.tempAccount()

    email1 = "mytest@test.com"
    email2 = "mytest2@test.com"
    res1 = self.util.assertOk(self.interface.run(app='accounts',action='createcontact',
        params=self.withSession(session,{'email':email1})))
    res2 = self.util.assertOk(self.interface.run(app='accounts',action='createcontact',
        params=self.withSession(session,{'email':email2})))
    id1 = res1['id']
    id2 = res2['id']

    self.util.assertOk(self.interface.run(app='accounts',action='editcontact',
        params=self.withSession(session,{'contact':id1,'isfrom':True})))
    self.util.assertOk(self.interface.run(app='accounts',action='editcontact',
        params=self.withSession(session,{'contact':id2,'isfrom':True})))

    res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
        params=self.withSession(session,{'full':True})))
    self.util.assertSame(res['contacts'][id1]['isfrom'],False) # got unset
    self.util.assertSame(res['contacts'][id2]['isfrom'],True)

    self.deleteAccount(account)
    