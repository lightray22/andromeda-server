
import tempfile, json, os, base64

from BaseTest import BaseTest
from TestUtils import *

class InterfaceTests(BaseTest):

    def testRun(self):
        """ Tests the full run() on the Interface with params and files """
        rval = self.util.assertOk(self.interface.run(app='testutil',action='testiface',
            params={'myparam':'aaa'},files={'myfile':('test.txt','contents')}))
        self.util.assertSame(rval['params'], {'myparam':'aaa'})
        self.util.assertSame(rval['files'], {'myfile':{'name':'test.txt','data':'contents'}})


class HTTPTests(InterfaceTests):
    
    def testBasicParams(self):
        """ Tests a basic request containing get/post/headers/files """
        b64dat = base64.b64encode(bytes.fromhex('802e0017fffe57d8')).decode('utf-8') # non-utf-8 binary
        rval = self.util.assertOk(self.interface.httpRun(
            get={'_app':'testutil','_act':'testiface','myget':'aaa'},
            post={'mytest':'xxx'},files={'myfile':('file.txt','contents')},
            headers={'X-Andromeda-mytest2':b64dat, 'X-Andromeda-mytest3':base64.b64encode(b'zzz').decode('utf-8')})[1])
        self.util.assertSame(rval['params'], {'myget':'aaa', 'mytest':'xxx', 'mytest2':b64dat, 'mytest3':'zzz'})
        self.util.assertSame(rval['files'], {'myfile':{'name':'file.txt','data':'contents'}})

    def testAppAction(self):
        """ Tests that app/action are both required in GET """
        self.util.assertError(self.interface.httpRun()[1], 400, 'APP_OR_ACTION_MISSING: missing')
        self.util.assertError(self.interface.httpRun(get={'_app':'testutil'})[1], 400, 'APP_OR_ACTION_MISSING: missing')
        self.util.assertError(self.interface.httpRun(get={'_act':'myaction'})[1], 400, 'APP_OR_ACTION_MISSING: missing')
        self.util.assertError(self.interface.httpRun(post={'_app':'testutil','_act':'myaction'})[1], 400, 'APP_OR_ACTION_MISSING: missing')

    def testInvalidBase64(self):
        """ Tests that invalid base64 is handled correctly """
        self.util.assertError(self.interface.httpRun(get={'_app':'testutil','_act':'testiface'},
            headers={'X-Andromeda-mytest':'not valid?'})[1], 400, 'BASE64_DECODE_FAIL: mytest')
        
    def testIllegalGetField(self):
        """ Tests that auth_ and password are not allowed in GET """
        self.util.assertError(self.interface.httpRun(get={'_app':'testutil','_act':'testiface','auth_test':'mytest'})[1], 400, 'ILLEGAL_GET_FIELD: auth_test')
        self.util.assertError(self.interface.httpRun(get={'_app':'testutil','_act':'testiface','password':'mytest'})[1], 400, 'ILLEGAL_GET_FIELD: password')

    def testBasicAuthentication(self):
        """ Tests that HTTP basic authentication works """
        self.util.assertSame(self.util.assertOk(self.interface.httpRun(
            get={'_app':'testutil','_act':'testiface'},auth=('myuser','mypass')
        )[1])['auth'], {'user':'myuser', 'pass':'bXlwYXNz'}) # base64

    def testHttpResponseCode(self):
        """ Tests the HTTP response code on various error types """
        pair = self.interface.httpRun(get={'_app':'testutil','_act':'testiface','outmode':'json'})
        self.util.assertSame(pair[0], 200); self.util.assertOk(pair[1])
        self.util.assertSame(self.interface.httpRun(get={'_app':'testutil','_act':'testiface','outmode':'printr'},isJson=False)[0], 200)
        self.util.assertSame(self.interface.httpRun(get={'_app':'testutil','_act':'testiface','outmode':'plain'},isJson=False)[0], 200)
        self.util.assertSame(self.interface.httpRun(get={'_app':'testutil','_act':'testiface','outmode':'none'},isJson=False)[0], 200)

        # HTTP returns real codes with the none/plain output types, 200 always with json/print
        pair = self.interface.httpRun(get={'_app':'testutil','_act':'testiface','outmode':'json','clienterror':True})
        self.util.assertSame(pair[0], 200); self.util.assertError(pair[1], 400, 'UNKNOWN_ACTION: testiface')
        self.util.assertSame(self.interface.httpRun(get={'_app':'testutil','_act':'testiface','outmode':'printr','clienterror':True},isJson=False)[0], 200)
        self.util.assertSame(self.interface.httpRun(get={'_app':'testutil','_act':'testiface','outmode':'plain','clienterror':True},isJson=False)[0], 400)
        self.util.assertSame(self.interface.httpRun(get={'_app':'testutil','_act':'testiface','outmode':'none','clienterror':True},isJson=False)[0], 400)

    def testDebugMetricsDefault(self):
        """ Tests that debug and metrics output is off by default """
        self.util.assertEmpty(self.util.assertOk(self.interface.run(app='testutil',action='geterrors'))) # check

        rval = self.interface.run(app='testutil',action='testiface',params={'clienterror':True})
        self.util.assertSame(rval['code'], 400)
        self.util.assertNotIn('debug',rval)
        self.util.assertNotIn('metrics',rval)

        rval = self.interface.run(app='testutil',action='testiface',params={'servererror':True})
        self.util.assertSame(rval['code'], 500)
        self.util.assertNotIn('debug',rval)
        self.util.assertNotIn('metrics',rval)

        self.util.assertOk(self.interface.run(app='testutil',action='clearerrors')) # reset


class CLITests(InterfaceTests):

    def advCliRun(self, app:str ='none',action:str='none', params:dict[str:str]={}, stdin:str=None, env:dict[str,str]={},
        outmode:str="json", outprop:str=None, debug:str=None, metrics:str=None, dbconf:str=None, dryrun:bool=False):
        
        args = []
        isJson = (outmode == "json")
        if outmode is not None: args += ['--outmode',outmode]
        if outprop is not None: args += ['--outprop',outprop]
        if debug is not None: args += ['--debug',debug]
        if metrics is not None: args += ['--metrics',metrics]
        if dbconf is not None: args += ['--dbconf',dbconf]
        if dryrun: args.append('--dryrun')

        args += [app, action]
        for key, value in params.items():
            args.append("--"+key)
            if value is not None:
                args.append(str(value))
        
        return self.interface.cliRun(args=args, stdin=stdin, env=env, isJson=isJson)[1]
    
    #################################################

    def testUsage(self):
        """ Tests the helptext output with invalid usage """
        rval = self.interface.cliRun()[1].decode('utf-8')
        self.util.assertStartsWith(rval, "ERROR: general usage")
        rval = self.interface.cliRun(args=["--outmode","json"],isJson=True)[1]
        self.util.assertError(rval, 400, "general usage")

        rval = self.interface.cliRun(args=["mytest"])[1].decode('utf-8')
        self.util.assertStartsWith(rval, "ERROR: general usage")
        rval = self.interface.cliRun(args=["--mytest"])[1].decode('utf-8')
        self.util.assertStartsWith(rval, "ERROR: general usage")

        rval = self.advCliRun(app="",action="")
        self.util.assertError(rval, 400, "SAFEPARAM_VALUE_NULL: app")

        rval = self.advCliRun(app="test",action="")
        self.util.assertError(rval, 400, "SAFEPARAM_VALUE_NULL: act")

    def testBasicParams(self):
        """ Tests both syntaxes for basic parameter input """
        rval = self.util.assertOk(self.advCliRun(app='testutil',action='testiface',
            params={'mytest1':'a','mytest2=5':None})) # both syntaxes
        self.util.assertSame(rval['params'], {'mytest1':'a', 'mytest2':'5'})

    def testVersionCommand(self):
        """ Tests the CLI version command """
        rval = self.interface.cliRun(args=['version'],isJson=False)[1].decode('utf-8')
        self.util.assertStartsWith(rval, "Andromeda ")

    def testDebugOption(self):
        """ Tests the CLI --debug option """
        rval = self.advCliRun(debug="") # invalid
        self.util.assertError(rval, 400, "SAFEPARAM_VALUE_NULL: debug")
        rval = self.advCliRun(debug="invalid")
        self.util.assertError(rval, 400, "SAFEPARAM_INVALID_TYPE: debug: must be")

        # ClientException debug with details only
        rval = self.advCliRun(debug=None) # default
        self.util.assertSame(rval['code'], 400)
        self.util.assertNotIn('debug', rval)
        rval = self.advCliRun(debug="none")
        self.util.assertSame(rval['code'], 400)
        self.util.assertNotIn('debug', rval)
        rval = self.advCliRun(debug="errors")
        self.util.assertSame(rval['code'], 400)
        self.util.assertNotIn('debug', rval)
        rval = self.advCliRun(debug="details")
        self.util.assertSame(rval['code'], 400)
        self.util.assertIn('debug', rval)

        self.util.assertEmpty(self.util.assertOk(self.interface.run(app='testutil',action='geterrors'))) # check

        # ServerException debug with default/errors/details
        rval = self.advCliRun(app='testutil',action='testiface',debug=None,params={'servererror':True}) # default
        self.util.assertSame(rval['code'], 500)
        self.util.assertIn('debug', rval)
        rval = self.advCliRun(app='testutil',action='testiface',debug="none",params={'servererror':True})
        self.util.assertSame(rval['code'], 500)
        self.util.assertNotIn('debug', rval)
        rval = self.advCliRun(app='testutil',action='testiface',debug="errors",params={'servererror':True})
        self.util.assertSame(rval['code'], 500)
        self.util.assertIn('debug', rval)
        rval = self.advCliRun(app='testutil',action='testiface',debug="details",params={'servererror':True})
        self.util.assertSame(rval['code'], 500)
        self.util.assertIn('debug', rval)

        self.util.assertOk(self.interface.run(app='testutil',action='clearerrors')) # reset

    def testMetricsOption(self):
        """ Tests the CLI --metrics option """
        self.util.assertNotIn('metrics', self.advCliRun(metrics=None)) # default
        self.util.assertNotIn('metrics', self.advCliRun(metrics="none"))
        self.util.assertIn('metrics', self.advCliRun(metrics="basic"))
        self.util.assertIn('metrics', self.advCliRun(metrics="extended"))

        rval = self.advCliRun(metrics="") # invalid
        self.util.assertError(rval, 400, "SAFEPARAM_VALUE_NULL: metrics")

        rval = self.advCliRun(metrics="invalid")
        self.util.assertError(rval, 400, "SAFEPARAM_INVALID_TYPE: metrics: must be")

    def testOutmodeOption(self):
        """ Tests the CLI --outmode option """
        rval = self.advCliRun(outmode=None).decode('utf-8') # default
        self.util.assertSame(rval,"ERROR: UNKNOWN_APP: none"+os.linesep)

        rval = self.advCliRun(outmode="none").decode('utf-8')
        self.util.assertEmpty(rval)

        rval = self.advCliRun(outmode="plain").decode('utf-8')
        self.util.assertSame(rval,"ERROR: UNKNOWN_APP: none"+os.linesep)

        rval = self.advCliRun(outmode="json") # json
        self.util.assertInstance(rval, object)
        self.util.assertSame(rval['ok'], False)

        rval = self.advCliRun(outmode="printr").decode('utf-8')
        self.util.assertStartsWith(rval, "Array")

        rval = self.interface.cliRun(args=["--outmode",""])[1].decode('utf-8') # invalid
        self.util.assertSame(rval, "ERROR: SAFEPARAM_VALUE_NULL: outmode"+os.linesep)

        rval = self.advCliRun(outmode="invalid").decode('utf-8')
        self.util.assertStartsWith(rval, "ERROR: SAFEPARAM_INVALID_TYPE: outmode")

    def testPlainOutputNarrowing(self):
        """ Tests the auto narrowing to appdata/message in plain outmode """
        self.util.assertStartsWith(self.advCliRun(
            app='testutil',action='testiface',outmode="plain",params={'mytest':'zzz'}).decode('utf-8'), 'Array\n(\n    [dryrun] =>')
        self.util.assertSame(self.advCliRun(
            app='testutil',action='testiface',outmode="plain",outprop='params.mytest',params={'mytest':'zzz'}).decode('utf-8'), 'zzz'+os.linesep)
        self.util.assertSame(self.advCliRun(
            app='testutil',action='zzz',outmode="plain").decode('utf-8'), 'ERROR: UNKNOWN_ACTION: zzz'+os.linesep)
        self.util.assertStartsWith(self.advCliRun(
            app='testutil',action='zzz',outmode="plain",debug="details").decode('utf-8'), 'Array')
        self.util.assertStartsWith(self.advCliRun(
            app='testutil',action='zzz',outmode="plain",metrics="extended").decode('utf-8'), 'Array')

    def testDbconfOption(self):
        """ Tests the CLI --dbconf option """
        rval = self.advCliRun(dbconf="")
        self.util.assertError(rval, 400, "SAFEPARAM_VALUE_NULL: dbconf")
        rval = self.advCliRun(dbconf="/nonexistent")
        self.util.assertError(rval, 503, "DATABASE_CONFIG_MISSING")

    def testDryrunFlag(self):
        """ Tests the CLI --dryrun flag """
        self.util.assertSame(self.util.assertOk(self.advCliRun(
            app='testutil',action='testiface'))['dryrun'], False)
        self.util.assertSame(self.util.assertOk(self.advCliRun(
            app='testutil',action='testiface',dryrun=True))['dryrun'], True)
        
        ret = self.advCliRun(app='testutil',action='testiface',dryrun=True,metrics='basic')
        self.util.assertSame(self.util.assertOk(ret)['dryrun'], True)
        self.util.assertIn('metrics', ret)
        
    def testOutpropOption(self):
        """ Tests the CLI --outprop option """
        self.util.assertError(self.advCliRun(
            app='testutil',action='testiface',outprop=''), 400, "SAFEPARAM_VALUE_NULL: outprop")
        self.util.assertError(self.advCliRun(
            app='testutil',action='testiface',outprop='<test>'), 400, "SAFEPARAM_INVALID_TYPE: outprop: must be")
        self.util.assertError(self.advCliRun(
            app='testutil',action='testiface',outprop='test'), 400, "INVALID_OUTPROP: test")
        self.util.assertOk(self.advCliRun(
            app='testutil',action='testiface',outprop='params'))
        self.util.assertSame(self.util.assertOk(self.advCliRun(
            app='testutil',action='testiface',outprop='params.mytest',params={'mytest':'myval'})), 'myval')
        self.util.assertSame(self.util.assertOk(self.advCliRun(
            app='testutil',action='testiface',outprop='params.mytest',params={'mytest':'myval'},metrics='basic')), 'myval')
        
        # ignore outprop on error
        self.util.assertSame(self.advCliRun(app='testutil',action='zzz',outprop='mytest',outmode='plain').decode('utf-8'), "ERROR: UNKNOWN_ACTION: zzz\n")
        ret = self.advCliRun(app='testutil',action='zzz',outprop='mytest',outmode='plain',debug='sensitive').decode('utf-8')
        self.util.assertStartsWith(ret, "Array\n(\n    [ok] => \n    [code] => 400\n    [message] => UNKNOWN_ACTION: zzz\n") # has debug
        
    def testParamFileInput(self):
        """ Tests the --param@ file-based param input """
        with tempfile.NamedTemporaryFile() as tmp:
            val = "myvalue!"
            with open(tmp.name,'w') as tmpfile:
                tmpfile.write(val)
            rval = self.util.assertOk(self.advCliRun(
                app='testutil',action='testiface',
                params={'myfile@': tmp.name}))
            self.util.assertSame(rval['params'], {'myfile':val})

    def testParamStdinInput(self):
        """ Tests the --param! interactive param input """
        key = "mystdin"
        val = "myvalue"
        rval = self.interface.cliRun(
            args=['--outmode','json','testutil','testiface','--'+key+'!'],stdin=val)[1].decode('utf-8')
        expect = "enter {}...".format(key)+os.linesep
        self.util.assertSame(expect, rval[0:len(expect)])
        rval = self.util.assertOk(json.loads(rval[len(expect):]))
        self.util.assertSame(rval['params'], {key:val})

    def testFileFileInput(self):
        """ Tests the --param% file-based file input """
        with tempfile.NamedTemporaryFile() as tmp:
            val = "myvalue!"
            with open(tmp.name,'w') as tmpfile:
                tmpfile.write(val)
            
            rval = self.util.assertOk(self.advCliRun(
                app='testutil',action='testiface',params={'myfile%':tmp.name}))
            self.util.assertSame(rval['files']['myfile'], {'name':os.path.basename(tmp.name), 'data':val})

            rval = self.util.assertOk(self.interface.cliRun(
                args=['--outmode','json','testutil','testiface','--myfile%',tmp.name,'newname'],isJson=True)[1])
            self.util.assertSame(rval['files']['myfile'], {'name':'newname', 'data':val})

    def testFileStdinInput(self):
        """ Tests the --param- stdin file input """
        key = "mystdin"
        val = "myvalue"
        rval = self.util.assertOk(self.advCliRun(
            app='testutil',action='testiface',params={key+'-':None}, stdin=val))
        self.util.assertSame(rval['files']['mystdin'], {'name':'data', 'data':val})

        rval = self.util.assertOk(self.advCliRun(
            app='testutil',action='testiface',params={key+'-':'newname'}, stdin=val))
        self.util.assertSame(rval['files']['mystdin'], {'name':'newname', 'data':val})

    def testEnvironment(self):
        """ Tests passing params via the environment """
        rval = self.util.assertOk(self.advCliRun(app='testutil',action='testiface',
            params={'mytest1':'aaa'},env={'mytest':'xxx','andromeda_my_test':'zzz'}))
        self.util.assertSame(rval['params'], {'mytest1':'aaa','my_test':'zzz'})
        
    def testExitCode(self):
        """ Tests the exit code on success/error """
        pair = self.interface.cliRun(args=['--outmode=json','testutil','testiface'],isJson=True)
        self.util.assertSame(pair[0], 0); self.util.assertOk(pair[1])
        self.util.assertSame(self.interface.cliRun(args=['--outmode=none','testutil','testiface'])[0], 0)
        self.util.assertSame(self.interface.cliRun(args=['--outmode=printr','testutil','testiface'])[0], 0)
        self.util.assertSame(self.interface.cliRun(args=['--outmode=plain','testutil','testiface'])[0], 0)

        pair = self.interface.cliRun(args=['--outmode=json','testutil','zzz'],isJson=True)
        self.util.assertSame(pair[0], 1); self.util.assertError(pair[1], 400, 'UNKNOWN_ACTION: zzz')
        self.util.assertSame(self.interface.cliRun(args=['--outmode=printr','testutil','zzz'])[0], 1)
        self.util.assertSame(self.interface.cliRun(args=['--outmode=plain','testutil','zzz'])[0], 1)
        self.util.assertSame(self.interface.cliRun(args=['--outmode=none','testutil','zzz'])[0], 1)
