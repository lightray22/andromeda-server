
from BaseTest import BaseAppTest
from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "ACCOUNTS"

    def requiresInstall(self) -> bool:
        return True
    
    accountid:str = None
    username:str = None
    password:str = None

    def getInstallParams(self) -> dict:
        self.username = self.util.randAlphanum(8)
        self.password = self.util.randBytes(8)
        return {'username':self.username,'password':self.password}

    def installSelf(self):
        return self.util.assertOk(self.interface.run(app='accounts',action='install',install=True,params=self.getInstallParams()))

    def checkInstallRetval(self, retval):
        self.util.assertType(retval['id'], str)
        self.util.assertType(retval['username'], str)
        self.accountid = retval['id']

    sessionid:str = None
    sessionkey:str = None

    def afterInstall(self): # init
        if self.sessionid is not None:
            return # already done
        res = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
            params={'username':self.username,'auth_password':self.password}))
        session = res['client']['session']
        self.sessionid = session['id']
        self.sessionkey = session['authkey']

    def asAdmin(self, params:dict = {}):
        """ Returns params with admin params added if not a private interface """
        if self.sessionid is None:
            self.afterInstall() # make asAdmin available to other apps in afterInstall()
        params = params.copy()
        if self.interface.isPriv:
            if self.util.random.choice([True,False]):
                params['auth_sudouser'] = self.username
            else:
                params['auth_sudoacct'] = self.accountid
        else:
            params['auth_sessionid'] = self.sessionid
            params['auth_sessionkey'] = self.sessionkey
        return params
    
    def withSession(self, session:dict, params:dict = {}):
        """ Returns params with session params added """
        params = params.copy()
        params['auth_sessionid'] = session['id']
        params['auth_sessionkey'] = session['authkey']
        return params
    
    def tempAccount(self):
        """ Creates a new account/session for temporary use """
        username = self.util.randAlphanum(8)
        password = self.util.randBytes(8)
        account = self.util.assertOk(self.interface.run(app='accounts',action='createaccount',
            params=self.asAdmin({'username':username,'password':password})))
        client = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
            params={'username':username,'auth_password':password}))['client']
        session = client['session']
        return (username, password, account, client, session)
    
    def deleteAccount(self, session:dict, password:str):
        """ Deletes an account with the given session and password """
        self.util.assertOk(self.interface.run(app='accounts',action='deleteaccount',
            params=self.withSession(session, {'auth_password':password})))

    #################################################
    
    def testGetConfig(self):
        """ Tests the getconfig command """
        res = self.util.assertOk(self.interface.run(app='accounts',action='getconfig'))
        self.util.assertType(res['username_iscontact'], bool)
        self.util.assertType(res['create_account'], str)
        self.util.assertType(res['require_contact'], str)
        self.util.assertSame(res['default_auth'], None)

        if not self.interface.isPriv: # normie output
            self.util.assertNotIn('date_created',res)
            self.util.assertNotIn('default_group',res)
        else: # admin output
            self.util.assertType(res['date_created'], float)
            self.util.assertSame(res['default_group'], None)
            self.util.assertSame(res, self.util.assertOk(
                self.interface.run(app='accounts',action='setconfig'))) # setconfig gives admin output

    def testSudoUser(self):
        """ Tests auth_sudouser and auth_sudoacct overriding a session """
        # note this is different than withSession() because we are using sudouser to *override* a session
        (username, password, account, client, session) = self.tempAccount()
        adminSession = {'id':self.sessionid, 'authkey':self.sessionkey}

        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(adminSession)))
        self.util.assertSame(res['id'], self.accountid)
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(adminSession, {'auth_sudouser':username})))
        self.util.assertSame(res['id'], account['id'])
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(adminSession, {'auth_sudoacct':account['id']})))
        self.util.assertSame(res['id'], account['id'])
        self.deleteAccount(session, password)

    def testGetAccount(self):
        """ Test the getAccount command """
        if not self.interface.isPriv:
            self.util.assertError(self.interface.run(app='accounts',action='getaccount'),403,'AUTHENTICATION_FAILED')

        (username, password, account, client, session) = self.tempAccount()
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session)))
        self.util.assertSame(res['id'], account['id'])
        self.util.assertSame(res['username'], username)
        self.util.assertSame(res['dispname'], None)
        self.util.assertNotIn('date_created',res) # full
        self.util.assertNotIn('date_modified',res) # admin
        self.util.assertNotIn('comment',res) # admin
        self.util.assertNotIn('groups',res) # admin

        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session,{"full":True})))
        self.util.assertNotIn('comment',res) # admin
        self.util.assertSame(res['crypto'], False)
        self.util.assertType(res['date_created'],float)
        self.util.assertType(res['date_loggedon'],float)
        self.util.assertType(res['date_active'],float)
        self.util.assertType(res['date_passwordset'],float)
        self.util.assertCount(res['recoverykeys'],0)
        self.util.assertCount(res['twofactors'],0)
        self.util.assertCount(res['contacts'],0)

        self.util.assertCount(res['clients'],1)
        self.util.assertIn(client['id'], res['clients'])

        # TODO TESTS should double check the defaults here?
        for prop in ['session_timeout','client_timeout','max_password_age','limit_clients','limit_contacts','limit_recoverykeys','disabled','account_search','group_search']:
            self.util.assertAny2((prop in res['policy']) and (res['policy'][prop] is None or type(res['policy'][prop]) == int), res['policy'])
        for prop in ['admin','forcetf','allowcrypto','userdelete']:
            self.util.assertAny2((prop in res['policy']) and (res['policy'][prop] is None or type(res['policy'][prop]) == bool), res['policy'])

        # regular accounts can use --account to get public info only
        self.util.assertError(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(session, {'account':"badID"})),404,'UNKNOWN_ACCOUNT')
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(session, {'account':self.accountid})))
        self.util.assertSame(res['id'], self.accountid)
        self.util.assertSame(res['username'], self.username)
        self.util.assertSame(res['dispname'], None)
        self.util.assertIn('contacts',res)

        # TODO TESTS test admin output (with --account)
        # TODO TESTS test full admin output (with --account)

        self.deleteAccount(session, password)

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

    def testTwoFactor(self):
        """ Tests creating/deleting/using two factor """
        import pyotp
        if not self.interface.isPriv:
            self.util.assertError(self.interface.run(app='accounts',action='createtwofactor'),403,'AUTHENTICATION_FAILED')
            self.util.assertError(self.interface.run(app='accounts',action='deletetwofactor'),403,'AUTHENTICATION_FAILED')
            self.util.assertError(self.interface.run(app='accounts',action='verifytwofactor'),403,'AUTHENTICATION_FAILED')

        (username, password, account, client, session) = self.tempAccount()

        self.util.assertError(self.interface.run(app='accounts',action='createtwofactor',
            params=self.withSession(session)),403,'PASSWORD_REQUIRED')
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
        self.util.assertError(self.interface.run(app='accounts',action='deletetwofactor',
            params=self.withSession(session,{'auth_password':password,'twofactor':'unknown'})),404,'UNKNOWN_TWOFACTOR')
        self.util.assertOk(self.interface.run(app='accounts',action='deletetwofactor',
            params=self.withSession(session,{'auth_password':password,'twofactor':tf['id']})))
        self.util.assertError(self.interface.run(app='accounts',action='deletetwofactor',
            params=self.withSession(session,{'auth_password':password,'twofactor':tf['id']})),404,'UNKNOWN_TWOFACTOR')
        self.util.assertOk(self.interface.run(app='accounts',action='deletetwofactor',
            params=self.withSession(session,{'auth_password':password,'twofactor':tf2['id']})))
        self.util.assertOk(self.interface.run(app='accounts',action='createsession',
            params={'username':username,'auth_password':password}))

        # test that you can't delete a two factor for someone else's account
        (username2, password2, account2, client2, session2) = self.tempAccount()
        res = self.util.assertOk(self.interface.run(app='accounts',action='createtwofactor',
            params=self.withSession(session2,{'auth_password':password2})))
        self.util.assertError(self.interface.run(app='accounts',action='deletetwofactor',
            params=self.withSession(session,{'auth_password':password,'twofactor':res['twofactor']['id']})),404,'UNKNOWN_TWOFACTOR')
        self.util.assertOk(self.interface.run(app='accounts',action='deletetwofactor',
            params=self.withSession(session2,{'auth_password':password2,'twofactor':res['twofactor']['id']})))

        self.deleteAccount(session2, password2)
        self.deleteAccount(session, password) 

    # TODO RAY !! try to reorganize the above into several separate tests + better comments

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
        self.deleteAccount(session2, password2)

        # test deleting contacts, shouldn't show up in getaccount anymore
        self.util.assertOk(self.interface.run(app='accounts',action='deletecontact',
            params=self.withSession(session,{'contact':id})))
        self.util.assertOk(self.interface.run(app='accounts',action='deletecontact',
            params=self.withSession(session,{'contact':id2})))
        
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(session,{'full':True})))
        self.util.assertEmpty(res['contacts'])

        self.deleteAccount(session, password)

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
        
        self.deleteAccount(session2, password2)
        self.deleteAccount(session, password)

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

        self.deleteAccount(session, password)
        